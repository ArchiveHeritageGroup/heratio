# ahgIiifPlugin - Technical Documentation

## Overview

The ahgIiifPlugin provides comprehensive IIIF (International Image Interoperability Framework) capabilities for AtoM, including manifest generation, deep zoom viewing, media streaming with on-the-fly transcoding, annotation support, collection management, and authentication (IIIF Auth API 1.0). The plugin supports images, PDFs, multi-page TIFFs, 3D models, and audio/video content.

**Version:** 1.3.0
**Category:** Media/Viewing
**Dependencies:** atom >= 2.8.0, PHP >= 8.1, atom-framework

**Optional Dependencies:**
- Cantaloupe (IIIF Image Server for deep zoom tiling)
- FFmpeg (media transcoding and metadata extraction)
- Whisper (audio/video transcription)
- ImageMagick (PSD/RAW conversion)
- LibreOffice (Office document → PDF conversion)

---

## Architecture

### Component Diagram

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           ahgIiifPlugin                                          │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌────────────────────────────────────────────────────────────────────────────┐ │
│  │                       PRESENTATION LAYER                                   │ │
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────────┐ │ │
│  │  │ IiifViewer   │ │ Collection   │ │ Auth Admin   │ │ Viewer Settings  │ │ │
│  │  │ Manager (JS) │ │ Templates    │ │ Templates    │ │ Templates        │ │ │
│  │  │ (ES6 module) │ │              │ │              │ │                  │ │ │
│  │  └──────────────┘ └──────────────┘ └──────────────┘ └──────────────────┘ │ │
│  └────────────────────────────────────────────────────────────────────────────┘ │
│                                      │                                           │
│                                      ▼                                           │
│  ┌────────────────────────────────────────────────────────────────────────────┐ │
│  │                      CONTROLLER LAYER                                      │ │
│  │  ┌───────────────┐ ┌───────────────┐ ┌───────────────┐ ┌───────────────┐ │ │
│  │  │ iiif/actions   │ │ iiifCollection│ │ iiifAuth      │ │ media/actions  │ │ │
│  │  │               │ │ /actions      │ │ /actions      │ │               │ │ │
│  │  │ manifest()    │ │ index()       │ │ login()       │ │ stream()      │ │ │
│  │  │ manifestById()│ │ create()      │ │ token()       │ │ download()    │ │ │
│  │  │ annotations() │ │ edit()        │ │ logout()      │ │ transcribe()  │ │ │
│  │  │ annotationsModify()│ │ manifest()│ │ confirm()     │ │ convert()     │ │ │
│  │  │ compare()     │ │ addItems()    │ │ check()       │ │ extract()     │ │ │
│  │  │ settings()    │ │ reorder()     │ │ protect()     │ │ snippets()    │ │ │
│  │  └───────────────┘ └───────────────┘ └───────────────┘ └───────────────┘ │ │
│  └────────────────────────────────────────────────────────────────────────────┘ │
│                                      │                                           │
│                                      ▼                                           │
│  ┌────────────────────────────────────────────────────────────────────────────┐ │
│  │                       SERVICE LAYER                                        │ │
│  │  ┌──────────────────────┐ ┌──────────────────────┐ ┌────────────────────┐ │ │
│  │  │ IiifAnnotationService│ │ IiifCollectionService │ │ IiifAuthService    │ │ │
│  │  │ (CRUD + W3C format)  │ │ (CRUD + hierarchy)   │ │ (tokens + access)  │ │ │
│  │  └──────────────────────┘ └──────────────────────┘ └────────────────────┘ │ │
│  │  ┌──────────────────────┐ ┌──────────────────────┐ ┌────────────────────┐ │ │
│  │  │ TranscriptionService │ │ MediaConversionService│ │IiifManifestV3Svc  │ │ │
│  │  │ (Whisper integration)│ │ (FFmpeg/IM/LO)       │ │(v3 manifest gen)  │ │ │
│  │  └──────────────────────┘ └──────────────────────┘ └────────────────────┘ │ │
│  └────────────────────────────────────────────────────────────────────────────┘ │
│                                      │                                           │
│                                      ▼                                           │
│  ┌────────────────────────────────────────────────────────────────────────────┐ │
│  │                       HELPER LAYER                                         │ │
│  │                    IiifViewerHelper.php                                     │ │
│  │  ┌───────────────────────────────────────────────────────────────────────┐ │ │
│  │  │ render_iiif_viewer()  │ get_iiif_base_url() │ get_preferred_viewer() │ │ │
│  │  │ render_3d_viewer()    │ get_cantaloupe_url() │ has_3d_models()       │ │ │
│  │  │ render_av_viewer()    │ get_digital_obj_url() │ render_controls()    │ │ │
│  │  │ render_pdf_viewer()   │ render_thumbnail_strip() │ render_toggle()  │ │ │
│  │  └───────────────────────────────────────────────────────────────────────┘ │ │
│  └────────────────────────────────────────────────────────────────────────────┘ │
│                                      │                                           │
│                                      ▼                                           │
│  ┌────────────────────────────────────────────────────────────────────────────┐ │
│  │                       DATA LAYER                                           │ │
│  │         Illuminate\Database\Capsule\Manager (Laravel Query Builder)        │ │
│  └────────────────────────────────────────────────────────────────────────────┘ │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_9213b39a.png)
```

---

## Database Schema

### Tables

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `iiif_collection` | Collection hierarchy | name, slug, parent_id, attribution, viewing_hint |
| `iiif_collection_i18n` | Collection translations | collection_id, culture, name, description |
| `iiif_collection_item` | Items in collections | collection_id, object_id, manifest_uri, item_type, sort_order |
| `iiif_annotation` | W3C annotations | object_id, canvas_id, target_selector (JSON), motivation |
| `iiif_annotation_body` | Annotation content | annotation_id, body_type, body_value, body_format |
| `iiif_ocr_text` | OCR full text | digital_object_id, full_text (FULLTEXT), format, confidence |
| `iiif_ocr_block` | OCR block coordinates | ocr_id, page_number, text, x, y, width, height |
| `iiif_viewer_settings` | Viewer config (key-value) | setting_key, setting_value |
| `iiif_auth_service` | Auth service profiles | name, profile, label, token_ttl, is_active |
| `iiif_auth_token` | Access tokens | token_hash (SHA-256), user_id, expires_at, is_revoked |
| `iiif_auth_resource` | Per-object access control | object_id, service_id, apply_to_children, degraded_access |
| `iiif_auth_access_log` | Audit trail | object_id, user_id, action, details (JSON) |
| `iiif_manifest_cache` | Manifest JSON cache | object_id, culture, manifest_json, page_count, expires_at |

### Entity Relationships

```
information_object (AtoM core)
    │
    ├──► iiif_collection_item.object_id
    ├──► iiif_annotation.object_id
    ├──► iiif_auth_resource.object_id
    └──► iiif_auth_access_log.object_id

iiif_collection ──► iiif_collection_i18n (1:N)
                ──► iiif_collection_item (1:N, CASCADE)
                ──► iiif_collection (self-ref parent_id)

iiif_annotation ──► iiif_annotation_body (1:N, CASCADE)

iiif_auth_service ──► iiif_auth_token (1:N)
                  ──► iiif_auth_resource (1:N)

iiif_ocr_text ──► iiif_ocr_block (1:N)
```

---

## Routing

Routes are registered in `ahgIiifPluginConfiguration.class.php` via the `routing.load_configuration` event.

### Manifest Routes

| Method | Route | Action | Description |
|--------|-------|--------|-------------|
| GET | `/iiif/manifest/:slug` | manifest | IIIF manifest by slug (v3 default, ?format=2 for v2.1) |
| GET | `/iiif/manifest/id/:id` | manifestById | IIIF manifest by ID (v3 default, ?format=2 for v2.1) |
| GET | `/iiif/v3/manifest/:slug` | manifestV3 | Explicit v3 manifest endpoint |
| GET | `/iiif/compare` | compare | Side-by-side comparison viewer |

### Annotation Routes

| Method | Route | Action | Description |
|--------|-------|--------|-------------|
| GET | `/iiif/annotations/object/:id` | listAnnotations | Annotations for object |
| POST | `/iiif/annotations` | createAnnotation | Create annotation |
| GET/PUT/DELETE | `/iiif/annotations/:id` | annotationsModify | Get/update/delete annotation (dispatches by HTTP method) |

### Collection Routes

| Method | Route | Action | Description |
|--------|-------|--------|-------------|
| GET | `/manifest-collections` | index | List all collections |
| GET | `/manifest-collection/new` | new | Create form |
| POST | `/manifest-collection/create` | create | Create collection |
| GET | `/manifest-collection/:id/view` | view | View collection |
| GET | `/manifest-collection/:id/edit` | edit | Edit form |
| PUT | `/manifest-collection/:id/update` | update | Update collection |
| DELETE | `/manifest-collection/:id/delete` | delete | Delete collection |
| POST | `/manifest-collection/:id/items/add` | addItems | Add items |
| GET | `/manifest-collection/:slug/manifest.json` | manifest | IIIF Collection manifest |

### Media Routes

| Method | Route | Action | Description |
|--------|-------|--------|-------------|
| GET | `/media/stream/:id` | stream | Stream with transcoding |
| GET | `/media/download/:id` | download | Download original |
| GET | `/media/snippets/:id` | snippets | Get/manage snippets |
| GET | `/media/extract/:id` | extract | Extract metadata (FFprobe) |
| POST | `/media/transcribe/:id` | transcribe | Start transcription |
| GET | `/media/transcription/:id/:format` | transcription | Get transcription (json/vtt/srt/txt) |
| GET | `/media/convert/:id` | convert | On-demand format conversion |
| GET | `/media/metadata/:id` | metadata | Get extracted metadata |

### Auth Routes (IIIF Auth API 1.0)

| Method | Route | Action | Description |
|--------|-------|--------|-------------|
| GET | `/iiif/auth/login/:service` | login | Interactive login |
| GET | `/iiif/auth/token/:service` | token | Request access token |
| GET | `/iiif/auth/logout/:service` | logout | Revoke token |
| POST | `/iiif/auth/confirm/:service` | confirm | Clickthrough confirmation |
| GET | `/iiif/auth/check/:id` | check | Check access for object |

---

## Manifest Generation

### Process

1. Query `information_object` by slug or ID (Laravel Query Builder)
2. Get all `digital_object` records for the object
3. Build IIIF identifier: `str_replace('/', '_SL_', $path . $name)`
4. Check for multi-page TIFF (probe Cantaloupe for pages 2-100)
5. Generate canvases (one per digital object, or one per page for multi-page TIFFs)
6. Return IIIF Presentation API 3.0 manifest (default), or 2.1 with ?format=2

### Multi-Page TIFF Detection

```php
// Probe Cantaloupe for page 2
$page2InfoUrl = "{$cantaloupeBaseUrl}/iiif/2/{$cantaloupeId};2/info.json";
$page2Info = @file_get_contents($page2InfoUrl);
if ($page2Info !== false) {
    $isMultiPageTiff = true;
    // Continue probing up to page 100
}
```

### Manifest Structure (IIIF 2.1)

```json
{
  "@context": "http://iiif.io/api/presentation/2/context.json",
  "@type": "sc:Manifest",
  "@id": "https://host/iiif/manifest/slug",
  "label": "Object Title",
  "sequences": [{
    "@type": "sc:Sequence",
    "canvases": [{
      "@type": "sc:Canvas",
      "label": "Page 1",
      "width": 4000,
      "height": 3000,
      "images": [{
        "@type": "oa:Annotation",
        "resource": {
          "@type": "dctypes:Image",
          "service": {
            "@context": "http://iiif.io/api/image/2/context.json",
            "@id": "https://host/iiif/2/identifier",
            "profile": "http://iiif.io/api/image/2/level2.json"
          }
        }
      }]
    }]
  }]
}
```

### Manifest Structure (IIIF 3.0 - Default)

```json
{
  "@context": "http://iiif.io/api/presentation/3/context.json",
  "id": "https://host/iiif/v3/manifest/slug",
  "type": "Manifest",
  "label": {"en": ["Object Title"]},
  "summary": {"en": ["Scope and content text"]},
  "metadata": [...],
  "rights": "https://creativecommons.org/licenses/by/4.0/",
  "requiredStatement": {
    "label": {"none": ["Attribution"]},
    "value": {"en": ["Institution Name"]}
  },
  "provider": [{
    "id": "https://host/repository-slug",
    "type": "Agent",
    "label": {"en": ["Institution Name"]}
  }],
  "seeAlso": [{
    "id": "https://host/record-slug",
    "type": "Dataset",
    "format": "text/html"
  }],
  "items": [{
    "id": "https://host/.../canvas/1",
    "type": "Canvas",
    "width": 4000,
    "height": 3000,
    "items": [{
      "type": "AnnotationPage",
      "items": [{
        "type": "Annotation",
        "motivation": "painting",
        "body": {
          "type": "Image",
          "service": [
            {"type": "ImageService2", "profile": "level2"},
            {"type": "ImageService3", "profile": "level2"}
          ]
        }
      }]
    }]
  }]
}
```

Multi-language labels are automatically populated from all available `information_object_i18n` cultures.

---

## Viewer Manager (JavaScript)

### File: `web/js/iiif-viewer-manager.js`

ES6 module that manages all viewer types with lazy-loading and preference persistence.

### Supported Viewers

| Viewer | Content | Source | Library |
|--------|---------|--------|---------|
| OpenSeadragon | Images (deep zoom) | Bundled | openseadragon@3.1.0 |
| Mirador 3 | Rich IIIF workspace | Bundled | mirador.min.js |
| PDF.js | PDF documents | Bundled | pdf.js@3.11.174 |
| model-viewer | 3D models | Bundled | model-viewer@3.3.0 |
| Annotorious | Annotations on OSD | Bundled | annotorious-openseadragon |
| Three.js | OBJ/STL 3D models | Bundled | three@0.128.0 |

### Viewer Selection Logic

```
Content type detected
    │
    ├─ PDF → PDF.js (override user preference)
    ├─ Audio/Video → HTML5 player (override user preference)
    ├─ 3D model → model-viewer (override user preference)
    └─ Image → User preference (OpenSeadragon or Mirador)
                Saved in localStorage
```

### Key Methods

```javascript
class IiifViewerManager {
    constructor(containerId, options)
    initOpenSeadragon(manifestUrl)
    initMirador(manifestUrl)
    initPdfViewer(pdfUrl)
    init3DViewer(modelUrl)
    switchViewer(viewerType)
    toggleFullscreen()
    toggleAnnotations()
    showError(message)
    destroy()
}
```

---

## Media Streaming

### Transcoding (FFmpeg)

When a file's MIME type is not browser-native, FFmpeg transcodes in real-time:

**Video → MP4:**
```bash
ffmpeg -y -i INPUT \
  -c:v libx264 -preset ultrafast -tune zerolatency -crf 23 \
  -c:a aac -b:a 128k \
  -movflags frag_keyframe+empty_moov+faststart \
  -f mp4 pipe:1
```

**Audio → MP3:**
```bash
ffmpeg -y -i INPUT \
  -c:a libmp3lame -b:a 192k \
  -f mp3 pipe:1
```

### Formats Requiring Transcoding

| Type | Formats |
|------|---------|
| Video | ASF, AVI, MOV, WMV, FLV, MKV, TS, WTV, HEVC, 3GP, VOB, MXF, MPEG |
| Audio | AIFF, AU, AC3, 8SVX, WMA, RA, FLAC |

### Range Request Support

For native formats, HTTP Range requests enable seeking:

```php
if (isset($_SERVER['HTTP_RANGE'])) {
    preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches);
    http_response_code(206);
    header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
}
```

### Format Conversion (non-streaming)

| Source | Output | Tool |
|--------|--------|------|
| PSD, CR2 (RAW) | JPEG | ImageMagick |
| DOCX, XLSX, PPTX | PDF | LibreOffice headless |
| ZIP, RAR, TGZ | JSON file listing | Built-in |
| TXT, CSV, XML | Plain text (500KB limit) | Built-in |
| SVG | Served with correct MIME | Pass-through |

Conversions cached in `/uploads/conversions/`.

---

## Transcription (Whisper)

### Background Job Pattern

1. Create lock file to prevent duplicate jobs
2. Generate temporary PHP script in `/tmp`
3. Launch as background process: `php script.php >> log 2>&1 &`
4. Client polls `/media/transcription/:id` every 5 seconds
5. Status file written on completion

### TranscriptionService

- Python/Whisper integration
- Models: tiny, base, small, medium, large
- Language auto-detection or manually specified
- Output: segments (timestamped), full_text, confidence

### Export Formats

| Format | Route | Use |
|--------|-------|-----|
| JSON | `/media/transcription/:id/json` | Full data with timestamps |
| WebVTT | `/media/transcription/:id/vtt` | Browser captions |
| SRT | `/media/transcription/:id/srt` | Subtitle editors |
| TXT | `/media/transcription/:id/txt` | Plain text |

---

## Annotation Service

### IiifAnnotationService Methods

```php
getAnnotationsForObject(int $objectId): array
getAnnotationsForCanvas(string $canvasId): array
createAnnotation(array $data): int
updateAnnotation(int $annotationId, array $data): bool
deleteAnnotation(int $annotationId): bool
formatAsAnnotationPage(array $annotations, int $objectId): array
formatAnnotationAsIiif(object $annotation): array
parseAnnotoriousAnnotation(array $annoData, int $objectId): array
syncResearchAnnotation(int $researchAnnotationId, ?int $iiifAnnotationId = null): int
```

### Motivations

| Motivation | Purpose |
|------------|---------|
| commenting | General comments on a region |
| tagging | Tag with a keyword |
| describing | Detailed description |
| linking | Link to external resource |
| transcribing | Transcribe text in image |
| identifying | Identify a person/object |

### Selectors (JSON stored in `target_selector`)

- **FragmentSelector:** `xywh=x,y,w,h` (rectangle)
- **SvgSelector:** `<svg>...</svg>` (polygon, freehand)

---

## IIIF Auth API 1.0

### Auth Profiles

| Profile | Description | Use Case |
|---------|-------------|----------|
| `login` | Requires AtoM authentication | Registered users only |
| `clickthrough` | User agrees to terms | Public with acknowledgment |
| `kiosk` | Location-based cookie | On-premises terminals |
| `external` | External auth provider | SSO integration |

### Authentication Flow

```
Client                                              Server
  │                                                    │
  │  1. Request manifest                               │
  │───────────────────────────────────────────────────►│
  │                                                    │
  │  2. Manifest with service block                    │
  │◄───────────────────────────────────────────────────│
  │                                                    │
  │  3. Open popup: /iiif/auth/login/:service          │
  │───────────────────────────────────────────────────►│
  │                                                    │
  │  4. Login page / Clickthrough terms                │
  │◄───────────────────────────────────────────────────│
  │                                                    │
  │  5. User authenticates/agrees                      │
  │───────────────────────────────────────────────────►│
  │                                                    │
  │  6. Set cookie, close popup                        │
  │◄───────────────────────────────────────────────────│
  │                                                    │
  │  7. Token request: /iiif/auth/token/:service       │
  │───────────────────────────────────────────────────►│
  │                                                    │
  │  8. Access token response                          │
  │◄───────────────────────────────────────────────────│
  │                                                    │
  │  9. Request image with token                       │
  │───────────────────────────────────────────────────►│
  │                                                    │
  │  10. Full resolution image                         │
  │◄───────────────────────────────────────────────────│
```

### IiifAuthService Methods

```php
checkAccess(int $objectId, ?int $userId = null): array
// Returns: ['allowed' => bool, 'degraded' => bool, 'service' => ?array]

requestToken(string $serviceName, ?int $userId, ?string $messageId): array
validateCurrentToken(?int $serviceId = null): ?object
logout(): bool
cleanupExpiredTokens(): int

setObjectAuth(int $objectId, string $serviceName, array $options = []): bool
// Options: apply_to_children, degraded_access, degraded_width, notes

removeObjectAuth(int $objectId, ?string $serviceName = null): bool
```

### Token Security

- Tokens hashed with SHA-256 before storage
- Cookies: `HttpOnly`, `Secure`, `SameSite=None`
- Configurable TTL per service (default: 1 hour)
- Automatic cleanup of expired tokens

### Access Inheritance

```
Repository Level
    └── Fonds (apply_to_children=true)
        └── Series (inherits)
            └── File (inherits)
                └── Item (inherits)
```

---

## Nginx Integration

### extensions.conf

```nginx
# Media streaming - buffering off for real-time transcoding
location ~ ^/media/stream/ {
    fastcgi_buffering off;
    fastcgi_read_timeout 600;
    # → PHP-FPM
}

# Format conversion - 120s timeout for LibreOffice/ImageMagick
location ~ ^/media/convert/ {
    fastcgi_read_timeout 120;
    # → PHP-FPM
}

# Transcription - 600s timeout for Whisper
location ~ ^/media/transcribe/ {
    fastcgi_read_timeout 600;
    # → PHP-FPM
}

# Static viewer assets - 7 day cache
location /atom-framework/src/Extensions/IiifViewer/public/ {
    expires 7d;
}

# Optional: Cantaloupe IIIF Image API proxy
# location /iiif/ {
#     proxy_pass http://127.0.0.1:8182/iiif/;
# }
```

---

## Helper Functions (IiifViewerHelper.php)

### Main Entry Point

```php
render_iiif_viewer($resource, $options = [])
```

Detects content type, selects viewer, builds HTML with container + controls + JavaScript initialization. Returns complete HTML string.

### Content-Specific Renderers

| Function | Purpose |
|----------|---------|
| `ahg_iiif_render_pdf_viewer_html()` | iframe with browser PDF viewer |
| `ahg_iiif_render_3d_viewer_html()` | `<model-viewer>` custom element |
| `ahg_iiif_render_av_viewer_html()` | `<video>` or `<audio>` element with streaming URL |

### UI Components

| Function | Purpose |
|----------|---------|
| `ahg_iiif_render_viewer_toggle()` | Viewer type switch buttons |
| `ahg_iiif_render_viewer_controls()` | IIIF badge, fullscreen, download, annotations |
| `ahg_iiif_render_thumbnail_strip()` | Multi-image thumbnail navigation |
| `ahg_iiif_render_viewer_javascript()` | ES6 module initialization script |

### URL Helpers

| Function | Purpose |
|----------|---------|
| `get_iiif_base_url()` | Auto-detects from request or sfConfig |
| `get_iiif_cantaloupe_url()` | Handles relative URLs to Cantaloupe |
| `is_iiif_available()` | Checks if IIIF is enabled |
| `get_digital_object_url()` | Checks for PDF redactions (ahgPrivacyPlugin) |
| `has_3d_models()` | Uses ahg3DModelPlugin or extension check |

---

## Plugin Integration Points

| Plugin | Integration | Pattern |
|--------|-------------|---------|
| ahgCorePlugin | Database bootstrap | `DatabaseBootstrap::initializeFromAtom()` |
| ahgUiOverridesPlugin | Viewer dispatch | Determines which component to render |
| ahgThemeB5Plugin | Bootstrap 5 UI | Digital object display templates |
| ahg3DModelPlugin | 3D model detection | `has_3d_model()`, try/catch if absent |
| ahgPrivacyPlugin | PDF redactions | `get_digital_object_url()`, try/catch if absent |

All integration is guarded with try/catch or `class_exists()` - the plugin degrades gracefully when optional plugins are missing.

---

## CSP Compliance

All inline `<script>` and `<style>` tags use the CSP nonce:

```php
$n = sfConfig::get('csp_nonce', '');
$nonceAttr = $n ? preg_replace('/^nonce=/', ' nonce="', $n) . '"' : '';
echo '<script type="module"' . $nonceAttr . '>';
```

All viewer libraries are bundled locally - no CDN whitelisting required for IIIF functionality.
External CDN domains are only needed if other plugins load external scripts.

---

## Configuration (sfConfig)

| Setting | Default | Description |
|---------|---------|-------------|
| `app_iiif_enabled` | true | Master on/off |
| `app_iiif_base_url` | (auto) | Public base URL |
| `app_iiif_cantaloupe_url` | `/iiif/2` | Public Cantaloupe URL |
| `app_iiif_cantaloupe_internal_url` | `http://127.0.0.1:8182` | Internal Cantaloupe URL |
| `app_iiif_plugin_path` | `/plugins/ahgIiifPlugin/web` | Plugin web path |
| `app_iiif_default_viewer` | openseadragon | Default viewer |
| `app_iiif_viewer_height` | 600px | Viewer CSS height |
| `app_iiif_enable_annotations` | true | Enable annotation UI |

### Viewer Settings (iiif_viewer_settings table)

| Key | Default | Description |
|-----|---------|-------------|
| `viewer_type` | mirador | openseadragon, mirador, carousel, single |
| `carousel_autoplay` | 1 | Auto-rotate carousel |
| `carousel_interval` | 5000 | Rotation interval (ms) |
| `background_color` | #b1aaaa | Viewer background |
| `homepage_collection_enabled` | 1 | Show collection on homepage |
| `homepage_collection_id` | null | Featured collection ID |

---

## Static Assets

### Bundled Libraries

| Path | Library |
|------|---------|
| `web/public/mirador/` | Mirador 3 (CSS + JS) |
| `web/public/openseadragon/` | OpenSeadragon |
| `web/public/viewers/annotorious/` | Annotorious for OSD |
| `web/images/` | OSD button icons (PNG) |
| `web/js/iiif-viewer-manager.js` | Main viewer manager (ES6) |
| `web/js/atom-media-player.js` | Enhanced media player |

### Bundled Libraries

All viewer libraries are served locally from `web/js/vendor/` - no CDN dependencies at runtime:

| Library | Path |
|---------|------|
| OpenSeadragon 3.1.0 | `web/js/vendor/openseadragon.min.js` |
| PDF.js 3.11.174 | `web/js/vendor/pdf.min.js` + `pdf.worker.min.js` |
| model-viewer 3.3.0 | `web/js/vendor/model-viewer.min.js` |
| Three.js 0.128.0 | `web/js/vendor/three.min.js` + loaders |
| Mirador 3 | `web/public/mirador/mirador.min.js` |
| Annotorious | `web/public/viewers/annotorious/` |

---

## Error Handling

| Layer | Strategy |
|-------|----------|
| Manifest generation | Returns 404 if object not found or no digital objects |
| Viewer JS | Catches errors, falls back to OpenSeadragon, shows error in container |
| Media streaming | Falls back to transcoding if direct playback fails; 500 on FFmpeg failure |
| Transcription | Background job with status file; polls for completion |
| Auth | Logs all access attempts; degraded access on failure |
| All services | Wrapped in try/catch; failures logged to PHP error log |

---

## Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| Token not accepted | Cookie blocked by browser | Check SameSite settings |
| Auth popup blocked | Browser popup blocker | Whitelist domain |
| 403 on images | Missing Cantaloupe config | Check nginx proxy |
| Manifest returns 404 | No digital objects | Verify object has media |
| Video won't play | FFmpeg not installed | `apt install ffmpeg` |
| Transcription stuck | Whisper process failed | Check lock file in /tmp |
| 3D model not rendering | WebGL not available | Check browser support |
| Page frozen | CSP blocking scripts | Add nonce to all script tags |

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.3.0 | 2026-03 | M2-IIIF-Complete: v3 default manifest, annotation route fix, CDN→local bundling, compare viewer, multi-language labels, research annotation bridge, manifest caching for v3 |
| 1.2.0 | 2026-02 | Added media streaming, transcription, format conversion, snippets, annotations REST API, 3D viewer, viewer manager JS |
| 1.1.0 | 2025-01-24 | Added IIIF Auth API 1.0 support |
| 1.0.0 | 2025-01-15 | Initial release with manifests and collections |

---

*Part of the AtoM AHG Framework*
