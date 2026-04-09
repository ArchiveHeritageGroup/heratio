# Cantaloupe IIIF Image Server — Setup Guide

## Overview

Heratio uses [Cantaloupe](https://cantaloupe-project.github.io/) as its IIIF image server for deep-zoom viewing of high-resolution images (TIFF, JP2, etc.) that browsers cannot display natively. Cantaloupe serves tiled JPEG derivatives on-the-fly from master files, enabling OpenSeadragon and Mirador viewers to provide smooth pan/zoom at any resolution.

## Architecture

```
Browser (OpenSeadragon/Mirador)
    ↓ IIIF Image API 3.0 requests
Nginx (reverse proxy, /iiif/ → localhost:8182)
    ↓
Cantaloupe 5.0.6 (Java, port 8182)
    ↓ reads master files from disk
/usr/share/nginx/heratio/uploads/   (or configured path)
```

## Requirements

- **Java 17+** (`openjdk-17-jdk-headless`)
- **ImageMagick** (fallback processor)
- **Cantaloupe 5.0.6+** (standalone JAR)

## Installation

### 1. Download Cantaloupe

```bash
cd /opt
wget https://github.com/cantaloupe-project/cantaloupe/releases/download/v5.0.6/cantaloupe-5.0.6.zip
unzip cantaloupe-5.0.6.zip
```

### 2. Configure Cantaloupe

Edit `/opt/cantaloupe-5.0.6/cantaloupe.properties`:

```properties
# HTTP
http.enabled = true
http.host = 0.0.0.0
http.port = 8182

# Source
source.static = FilesystemSource
FilesystemSource.BasicLookupStrategy.path_prefix = /usr/share/nginx/heratio/uploads/

# Use delegates for multi-instance path resolution
delegate_script.enabled = true
delegate_script.pathname = /opt/cantaloupe-5.0.6/delegates.rb

# Processors
processor.ManualSelectionStrategy.tif = Java2dProcessor
processor.ManualSelectionStrategy.jp2 = OpenJpegProcessor
processor.fallback = Java2dProcessor

# Cache (recommended for production)
cache.server.derivative.enabled = true
cache.server.derivative = FilesystemCache
FilesystemCache.pathname = /var/cache/cantaloupe
cache.server.derivative.ttl_seconds = 86400
```

### 3. Configure Delegates (Multi-Instance)

Edit `/opt/cantaloupe-5.0.6/delegates.rb` to resolve paths per hostname:

```ruby
INSTANCE_PATHS = {
  'heratio.theahg.co.za' => '/usr/share/nginx/heratio/',
  'psis.theahg.co.za'    => '/usr/share/nginx/archive/',
}.freeze

DEFAULT_PATH = '/usr/share/nginx/heratio/'.freeze

def filesystemsource_pathname
  identifier = context['identifier'].to_s
  decoded_identifier = identifier.gsub('_SL_', '/')

  headers = context['request_headers'] || {}
  host = (headers['X-Forwarded-Host'] || headers['Host'] || '').to_s.split(':').first.to_s.downcase
  base = INSTANCE_PATHS[host] || DEFAULT_PATH

  path = base + decoded_identifier

  # Dynamic fallback: try absolute path if file not found
  unless File.exist?(path)
    abs = '/' + decoded_identifier
    path = abs if File.exist?(abs)
  end

  path
end
```

### 4. Configure Nginx

Add to the Heratio nginx server block. **Must use `^~` prefix** to take priority over the static file regex location that matches `.jpg`:

```nginx
location ^~ /iiif/ {
    proxy_pass http://127.0.0.1:8182/iiif/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    add_header Access-Control-Allow-Origin "*" always;

    # Rewrite http:// to https:// in JSON-LD responses
    sub_filter_types application/json application/ld+json;
    sub_filter_once off;
    sub_filter 'http://127.0.0.1:8182' 'https://$host';
}
```

Reload nginx:

```bash
nginx -t && systemctl reload nginx
```

### 5. Start Cantaloupe

```bash
# Foreground (for testing)
java -Dcantaloupe.config=/opt/cantaloupe-5.0.6/cantaloupe.properties -Xmx2g -jar /opt/cantaloupe-5.0.6/cantaloupe-5.0.6.jar

# Background (production)
nohup java -Dcantaloupe.config=/opt/cantaloupe-5.0.6/cantaloupe.properties -Xmx2g -jar /opt/cantaloupe-5.0.6/cantaloupe-5.0.6.jar &

# Or create a systemd service (recommended)
```

### 6. Systemd Service (Recommended)

Create `/etc/systemd/system/cantaloupe.service`:

```ini
[Unit]
Description=Cantaloupe IIIF Image Server
After=network.target

[Service]
Type=simple
User=www-data
ExecStart=/usr/bin/java -Dcantaloupe.config=/opt/cantaloupe-5.0.6/cantaloupe.properties -Xmx2g -jar /opt/cantaloupe-5.0.6/cantaloupe-5.0.6.jar
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable cantaloupe
systemctl start cantaloupe
```

## How It Works

### IIIF Identifier Format

File paths are encoded using `_SL_` as a path separator:

```
uploads/r/my-repo/a/b/c/hash/image.tiff
→ uploads_SL_r_SL_my-repo_SL_a_SL_b_SL_c_SL_hash_SL_image.tiff
```

### IIIF URLs

```
# Image info (JSON-LD)
/iiif/3/{identifier}/info.json

# Full image as JPEG
/iiif/3/{identifier}/full/max/0/default.jpg

# Tile (512x512 region)
/iiif/3/{identifier}/0,0,512,512/512,/0/default.jpg

# Thumbnail (200px wide)
/iiif/3/{identifier}/full/200,/0/default.jpg
```

### Viewer Integration

The `ahg-iiif-viewer.js` automatically detects TIFF/JP2 files and routes through Cantaloupe:

```javascript
// Detects TIFF/JP2 → builds Cantaloupe IIIF URL
var needsIiif = /\.(tiff?|jp2|jpx)$/i.test(imageUrl);
if (needsIiif) {
    var relPath = new URL(imageUrl, location.origin).pathname.replace(/^\//, '');
    var iiifId = relPath.replace(/\//g, '_SL_');
    iiifTileSource = location.origin + '/iiif/3/' + iiifId + '/info.json';
}
```

OpenSeadragon uses the IIIF tile source for deep zoom. Mirador uses the IIIF image service in its manifest.

## Supported Formats

| Format | Extension | Processor |
|--------|-----------|-----------|
| TIFF | .tif, .tiff | Java2dProcessor |
| JPEG 2000 | .jp2, .jpx | OpenJpegProcessor |
| JPEG | .jpg, .jpeg | Java2dProcessor |
| PNG | .png | Java2dProcessor |
| GIF | .gif | Java2dProcessor |
| BMP | .bmp | Java2dProcessor |
| WebP | .webp | Java2dProcessor |

## Verification

```bash
# Check Cantaloupe is running
curl -s http://localhost:8182/iiif/3 | head -5

# Test info.json for a TIFF
curl -s -H "Host: heratio.theahg.co.za" \
  "http://localhost:8182/iiif/3/uploads_SL_path_SL_to_SL_image.tiff/info.json"

# Test tile delivery through nginx
curl -s -o /dev/null -w "%{http_code}" \
  "https://heratio.theahg.co.za/iiif/3/{identifier}/0,0,512,512/512,/0/default.jpg"
```

## Troubleshooting

| Problem | Cause | Fix |
|---------|-------|-----|
| Black screen in viewer | Tiles returning 404 | Ensure nginx `location ^~ /iiif/` (with `^~`) takes priority over static file regex |
| info.json returns 404 | Wrong identifier encoding | Check `_SL_` encoding matches file path relative to base directory |
| Mixed content error | info.json `id` field uses `http://` | Ensure nginx `sub_filter` rewrites `http://` to `https://` |
| Wrong file resolved | Multi-instance path conflict | Check `delegates.rb` `INSTANCE_PATHS` has correct hostname → path mapping |
| Cantaloupe 500 error | Unsupported format or corrupt file | Check `/opt/cantaloupe-5.0.6/nohup.out` for Java stack traces |
