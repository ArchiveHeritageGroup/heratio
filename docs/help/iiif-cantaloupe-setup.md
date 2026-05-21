> Heratio Help Center article. Category: System Administration / Infrastructure.

# IIIF Image Server (Cantaloupe)

Heratio uses Cantaloupe as its IIIF image server for deep-zoom viewing of high-resolution images (TIFF, JP2) that browsers cannot display natively.

## How It Works

When viewing a TIFF or JP2 image, the viewer automatically detects the format and requests tiles from Cantaloupe instead of loading the raw file. This enables smooth pan and zoom on images of any size.

## Architecture

- **Cantaloupe** runs on port 8182, serving IIIF Image API 3.0
- **Nginx** proxies /iiif/ requests to Cantaloupe
- **OpenSeadragon** and **Mirador** viewers consume IIIF tiles
- Path resolution uses hostname-based mapping via delegates.rb

## Supported Formats

- TIFF (.tif, .tiff)
- JPEG 2000 (.jp2, .jpx)
- JPEG, PNG, GIF, BMP, WebP (also supported but usually loaded directly)

## Viewer Modes

- **Deep Zoom** (OpenSeadragon): Tile-based pan/zoom with navigator
- **Mirador**: IIIF-compliant scholarly viewer with sidebar
- **Image**: Simple browser-native display

## URL Format

IIIF identifiers use _SL_ as path separator:

uploads/r/repo/hash/image.tiff becomes uploads_SL_r_SL_repo_SL_hash_SL_image.tiff

## Administration

- Config: /opt/cantaloupe-5.0.6/cantaloupe.properties
- Delegates: /opt/cantaloupe-5.0.6/delegates.rb
- Service: systemctl status cantaloupe
- Logs: /opt/cantaloupe-5.0.6/nohup.out

For full setup instructions see docs/cantaloupe-iiif-setup.md
