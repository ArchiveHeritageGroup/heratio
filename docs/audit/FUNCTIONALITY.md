# Heratio Functionality and Components Inventory

> **Audit date:** 2026-05-26
> **Scope:** Full Heratio codebase -- all packages
> **Purpose:** Single reference document enumerating every technology, service, viewer, engine and format handler used in the platform.

---

## 1. OpenSeadragon (Deep Zoom — Images)

### Library
- **File:** `public/vendor/openseadragon/6.0.2/openseadragon.min.js`
- **Version:** 6.0.2 (semver)
- **Purpose:** Client-side deep-zoom tile viewer for high-resolution raster images (TIFF, JPEG2000, PNG, JPEG)
- **Bundles:** Built-in toolbar images in `public/vendor/openseadragon/6.0.2/images/`

### Heratio OSD Plugins
| File | Lines | Purpose |
|---|---|---|
| `openseadragon-filtering.js` | 493 | Pixel-processing pipeline: brightness, contrast, greyscale, invert, threshold. Requires canvas drawer (not WebGL). |
| `openseadragon-heratio-scalebar.js` | 149 | Physical scale overlay. Reads `info.json` service block for `physdim` (physicalScale / physicalUnits). Operators inject via `window.AHG_IIIF.physdim = {…}`. Positioned bottom-left. |
| `openseadragon-heratio-magnifier.js` | 101 | Magnifying loupe (radius 90 px, zoom 3x). Toggled via an injected toolbar button. Reads source-canvas pixels via 2D context (requires canvas drawer). |
| `ahg-iiif-viewer.js` | 482 | Wire-all: initialises OSD / Mirador / carousel; lazy-loads the three plugin scripts above on first OSD mount. |

### Configuration
- **Tile source:** IIIF Image API 3 (`/iiif/3/<identifier>/info.json`) for TIFF / JP2; legacy raw image URL for JPEG / PNG
- **Drawer forced:** `canvas` (never `webgl`) — WebGL never reads pixel-filter outputs or the source-canvas pixels the magnifier needs
- **Init options:** `showNavigator: true, navigatorPosition: 'BOTTOM_RIGHT', maxZoomPixelRatio: 4, zoomPerClick: 1.5, gestureSettingsMouse: {dblClickToZoom: true}`
- **Settings gate:** `window.AHG_IIIF` (injected from `AhgSettings::iiifPayload()` — keys: `enabled`, `viewer`, `server_url`, `default_zoom`, `max_zoom`, `show_navigator`, `show_fullscreen`, `enable_annotations`, `physdim`)
- **Operators override:**
  - `iiif_server_url` — remote Cantaloupe origin (empty = same-origin)
  - `iiif_viewer` — default initial viewer mode (`openseadragon` | `mirador` | `single` | `carousel`)
  - `iiif_default_zoom` / `iiif_max_zoom`
  - `iiif_show_navigator` / `iiif_show_fullscreen`
  - `iiif_enable_annotations`
- **Per-IO override:** `?viewer=carousel|single|mirador|openseadragon` query parameter

### Routes / Entry Points
- `/iiif-image/<id>` — delegates to Cantaloupe tile server
- `/iiif/3/<identifier>/info.json` — Cantaloupe IIIF 3 info endpoint
- IO show page: `_digital-object-viewer.blade.php` mounts OSD for all image-type digital objects via `initIiifViewer()`

---

## 2. Mirador (IIIF Presentation Viewer)

### Library
- **Build:** `tools/mirador-build/` — Webpack bundle producing `public/vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js`
- **Dependencies bundled:** `mirador ^4.0.0`, `mirador-image-tools`, `mirador-dl-plugin`, `mirador-annotation-editor`, `react ^19.0.0`, `@mui/material ^7.0.0`
- **Deploy:** `npm run deploy` in `tools/mirador-build/` copies the built file into public/vendor

### Heratio Mirador Plugins
| Plugin | Purpose |
|---|---|
| `heratio-mirador-workspace` | Custom mosaic workspace; hides close/minimise/maximise buttons |
| `heratio-scalebar` | Mirador-side physical scalebar read from IIIF service `physdim` |
| `heratio-loupe` | Mirador-side magnifier overlay |
| `heratio-av-overlay` | Mirador AV (audio/video) canvas viewer — Content Search 2.0 enabled |
| `heratio-compare-overlay` | Side-by-side comparison view from two IIIF manifests |
| `heratio-annotation-editor` | Annotation storage adapter bridging Mirador's W3C Web Annotation model to Heratio's annotation DB. Exposed as `window.HeratioAnnotationAdapter` |
| `heratio-dl-plugin` | Download-to-disk for IIIF canvases |

### Configuration
- **Init:** `Mirador.viewer({ id: 'mirador-mount', windows: [{manifestId}], window: {sideBarOpenByDefault, highlightAllAnnotations} })`
- **Annotations:** `annotation.adapter` points to `window.HeratioAnnotationAdapter`; `highlightAllAnnotations` keeps drawn shapes permanently visible on canvas (not just on hover)
- **Auth:** IIIF Auth API Flow 2 (`iiifAuthAccessTokenIframe.blade.php`) — token exchange iframe; anon POST redirects to `/login`

### Configuration (server-side)
- **Manifest URI:** `/api/iiif/manifest/<slug>` returns V2 manifest JSON per IO
- **IIIF endpoints:**
  - `GET /iiif/collection/{id}` — collection
  - `GET /iiif/manifest/{slug}` — V2 manifest (canonical)
  - `GET /iiif/info/{id}` — Cantaloupe info.json proxy
  - `GET /iiif/3/<identifier>/info.json` — Cantaloupe IIIF 3 info (direct)
- **Comparison view:** `/iiif-viewer/compare` — accepts `manifests[]` array; mounts multi-window Mirador mosaic

### Manifest Support
- **IIIF Presentation API 2** (current default)
- **V3 migration** (in progress — v1.104.6 advertises Content Search 2.0 in the V3 manifest)
- **Content Search 2.0** (`/annotations/search?uri=https://…`) — full-text search over OCR/transcription via `OcrExportService`
- **Annotations:** W3C Web Annotation via `window.HeratioAnnotationAdapter` — stored in Heratio annotation tables, rendered by `iiif-annotations.blade.php`

---

## 3. Cantaloupe (IIIF Image Server)

- **Role:** Authoritative IIIF Image API 3 tile server. Serves all pyramid TIFF, JP2, and large JPEG sources.
- **Health:** `curl -fsS http://localhost:8080/cantaloupe/iiif/3/status` returns JSON
- **Internal endpoint:** `/iiif/3/<identifier>/info.json` — redirects to `cantaloupe_base/iiif/3/`
- **Configuration:** `cantaloupe.properties` (or equivalent) — base URL, IIIF 3 behaviour, source filesystem path
- **Tile format:** JPEG by default; supports PNG
- **Integration:** `_digital-object-viewer.blade.php` converts TIFF/JP2 paths to Cantaloupe identifiers (`/` → `_SL_`) in `ahg-iiif-viewer.js`
- **Watermark cascade:** `WatermarkService::updateCantaloupeCache()` writes `/tmp/cantaloupe_classifications.json` — consumed by Cantaloupe's `LookupResolver` for per-object watermarking (ImageMagick composite overlay)

---

## 4. Metadata Extraction (EXIF / IPTC / XMP)

### Primary Service: `MetadataExtractionService`
- **Package:** `ahg-metadata-extraction`
- **File:** `packages/ahg-metadata-extraction/src/Services/MetadataExtractionService.php`

#### A. PHP Native EXIF — Images (JPEG, TIFF)
```php
exif_read_data($filePath, 'ANY_TAG', true)
  → supported: IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM
  → binary UTF-8 validation; malformed → [Binary Data]
```
- Reads ALL IFD sections, flattens to key/value pairs
- **GPS extraction:** `extractGpsFromExif()` — converts `$exif['GPSLatitude/Longitude']` (DMS rational) to WGS84 decimal degrees + altitude

#### B. IPTC — Images
```php
getimagesize($path, $info); iptcparse($info['APP13'])
  → 26-field mapping: 2#005 object_name … 2#122 writer
  → gated by: ahg_settings.meta_extract_iptc (default: false/opt-in)
```
- **Gazetteer pre-pass (#667 Phase 1):** Custom entity scan before ML model; tagged by `NerGazetteerService::scan()`, merged by `NerGazetteerService::merge()`

#### C. XMP — Images
```php
extractXmp() → reads first 512 KB of file → locates <x:xmpmeta>/<?xpacket begin?>
  → parseXmpXml() DOM parsing
  → gated by: ahg_settings.meta_extract_xmp (default: false/opt-in)
```

#### D. ExifTool Binary — All Formats
```bash
exiftool -json -a -G1 <path>
  → comprehensive cross-format fallback; always available regardless of PHP extension
  → version: exiftool -ver
```

#### E. PDF Info
```bash
pdfinfo <path>  → title/author/subject/keywords/creator/producer/pages/page_size
  → manual header parsing as fallback
  → available: pdfinfo -ver
```

#### F. ffprobe — Audio / Video
```bash
ffprobe -v quiet -print_format json -show_format -show_streams <path>
  → audio: duration, bitrate, codec, sample_rate, channels, channel_layout, ID3/Vorbis tags
  → video: duration, bitrate, codec_name, width, height, frame_rate, pixel_format, embedded audio stream
  → available: which ffprobe
```

### Normalisation Layer
- Source priority: XMP > IPTC > EXIF > exiftool catch-all (first-non-empty wins)
- Normalised output dict: `{title, creator, date, description, copyright, keywords, technical, gps}`
- Per-sector apply: `DAM → dam_iptc_metadata`, `ISAD → information_object_i18n + relations`, `Museum → museum_metadata`, `Library`
- **Apply rules:** Direct column writes, i18n writes, GPS components, term/actor relations (match-only, no auto-create), creation events
- **Toggle gate:** `meta_extract_on_upload` (default: true), `meta_overwrite_existing` (default: false)
- **Event:** `EmbeddedMetadataExtracted($digitalObjectId)` — event facade gate; consumed by `ahg-privacy`'s embedded-PII scanner

#### Per-Sector Field Map (ahg_settings)
```
map_title_dam         → information_object_i18n.title
map_creator_dam       → dam_iptc_metadata.creator
map_date_dam          → dam_iptc_metadata.date_created
map_keywords_dam      → dam_iptc_metadata.keywords
map_gps_dam           → dam_iptc_metadata.sublocation (decimal)
map_subject_isad     → term [taxonomy=35] subject access point
map_name_access_isad → actor [relation_type=161] name access point
map_creation_isad    → event [type=111] creation date (ISO YYYY-MM-DD)
```

### Secondary EXIF: `PhotoProcessor`
- **Package:** `ahg-media-processing`
- **Trigger:** condition photography upload (`photo_extract_exif`, default: true)
- **Captures:** `camera_info` (Make + Model), `photo_date` (DateTimeOriginal), `photographer` (Artist/Author), `orientation`, `iso`, `exposure_time`, `focal_length`
- **Tool:** Native `exif_read_data`, no shell hop

### Digital-Object EXIF Wiring
- **`DigitalObjectService::extractMetadataForMaster()`** — calls `MetadataExtractionService::extractFromDigitalObject()` on upload (triggered by `ProcessScanFile.php`)
- **Stores:** Flat key/value pairs in `property.scope='metadata_extraction'` table rows
- **Writes:** `media_metadata` (audio/video technical), `dam_iptc_metadata` (DAM IPTC), condition survey camera fields

### UI
- **Status page:** `ahg-metadata-extraction/resources/views/status.blade.php` — ExifTool/ffprobe/pdfinfo availability badges, install instructions, MIME type table
- **Settings UI:** `ahg-settings/resources/views/photos-settings.blade.php` — photo_* toggles; `media-settings.blade.php` — media_* toggles
- **Batch extraction:** `batchExtract()` — processes unextracted DOs, called manually or via future queued job
- **Event chain:** `EmbeddedMetadataExtracted` → `ahg-privacy`'s `ScanEmbeddedMetadataForPii` listener for PII scanning (#751)

---

## 5. Audio / Video Processing

### Derivative Generation: `MediaDerivativeService`
- **Package:** `ahg-core`
- **Tool:** `ffmpeg` (shell exec; graceful skip if absent)
- **Audio pipeline:**
  - MP3 reference at 128 kbps (`libmp3lame`)
  - Waveform PNG thumbnail (640x120 px, `showwavespic`, colour `#555555`)

- **Video pipeline:**
  - MP4 reference: H.264 (`libx264`, CRF 23, scale -2:480), `aac` audio, `+faststart`
  - JPEG poster frame (`-ss 00:00:01`)

- **3D pipeline:** delegates to `AhgThreeDModel\Services\ThreeDThumbnailService`
- **TIFF pyramid:** ImageMagick `convert … ptif:` 256x256 tile JPEG Pyramid TIFF (Cantaloupe-ready)
- **Storage:** Inserted as `digital_object` rows with `parent_id=master`, `usage_id=141` (reference) / `usage_id=142` (thumbnail); SHA-256 checksum recorded

### Transcoding Service: `TranscodingService`
- **Package:** `ahg-media-streaming`
- **Video:** AVI/WMV/MOV/MKV/FLV/TS/WTV/HEVC/3GP/MXF/VOB → MP4 (H.264+AAC, `+faststart`)
- **Audio:** AIFF/AU/AC3/WMA/RA/FLAC → MP3 (`libmp3lame`, VBR quality 2)
- **Utilities:**
  - `getMediaInfo()` — duration, codec, width, height, bitrate, sample_rate via ffprobe
  - `getDuration()` — ffprobe one-liner
  - `generateVideoThumbnail()` — ffmpeg JPEG at timestamp, scaled to 480 px wide
  - `getTranscodedPath()` — cached transcoded path (`storage/app/transcoded/`), checked before re-transcoding
  - `needsTranscoding()` — extension-based gate

### Streaming Service: `StreamingService`
- **Package:** `ahg-media-streaming`
- **HTTP 206 Partial Content:** Full Range header support; byte range validation; 416 error for invalid ranges
- **`streamWithTranscode()`:** Checks `TranscodingService::needsTranscoding()`, transcodes if needed, streams cached/transcoded file
- **MIME detection:** `finfo_open(FILEINFO_MIME_TYPE)`

### Media Player Component
- **File:** `ahg-theme-b5/resources/views/components/media-player.blade.php`
- **5 render modes** (driven by `App\Support\MediaSettings::playerType()`):

| Mode | Wrapper | Features |
|---|---|---|
| `heratio` | `.ahg-media-player` (custom chrome) | Gradient bg (135deg #1a1a2e), play/pause, ±10s skip, 6-speed selector, volume range, PiP (video), fullscreen (video), scrubber, time readout, badges, download; try/catch with auto-fallthrough to minimal |
| `heratio-minimal` | `.ahg-media-player-minimal` | Native `<audio>`/`<video>`, current-time readout, badges |
| `plyr` | `.ahg-media-bare` (external wrap) | Bare native: `master.blade.php` wraps with Plyr bundle |
| `videojs` | `.ahg-media-bare` (external wrap) | Bare native: `master.blade.php` wraps with Video.js bundle |
| `native` | `.ahg-media-bare` | Bare native HTML5 only; no wrapper |

- **Track support:** WebVTT subtitle/caption/descriptions chapters (video)
- **Settings:** `media_player_type`, `media_autoplay`, `media_show_controls`, `media_loop`, `media_show_waveform` (= WaveSurfer), `media_show_download`, `media_transcription_enabled`

### WaveSurfer.js (Waveform Visualisation)
- **Build:** `tools/wavesurfer-build/` — copies prebuilt UMD dist to `public/vendor/wavesurfer/`
- **Trigger:** `media_show_waveform` toggle + HTML placeholder in `media-player.blade.php` (scrubber position: `position:relative; overflow:hidden`)
- **Status:** Build infrastructure exists; library copied to public/; JS initialisation in `media-player.blade.php` not yet wired

### Plyr / Video.js
- **Build:** `tools/plyr-build/`, npm/webpack bundles → `public/vendor/plyr/`, `public/vendor/videojs/`
- **Status:** Build infrastructure, package files, and public vendor directories exist; Plyr/Video.js class-name gating in `master.blade.php` (for `enhance()`) and `media-player.blade.php` (for JS fallback) not fully confirmed

### Transcription (Whisper)
- **Toggle:** `media_transcription_enabled` in settings
- **Binary check:** `MediaProcessingController` checks `whisper` binary availability
- **Status:** Toggle + binary check in place; no `WhisperService` or full pipeline found in this audit
- **UI:** Transcription panel in `_digital-object-viewer.blade.php` with full-text/segmented view, VTT/SRT download, search, confidence badge

---

## 6. OCR (Tesseract)

### Primary Service: `OcrService`
- **Package:** `ahg-ai-services`
- **Binary:** `tesseract` (configurable path; default on `$PATH`)
- **Language support:** `osd` (orientation script detection) + any installed traineddata (OSD shipped; `eng`, `afr`, `nld`, `zu`, `xh`, `st`, `tn`, `ts`, `ve`, `nr`, `ss`, `nso`, `sn`, `pt`, `fr`, `de`, `es`, `it`, `la` + full ISO 639-1 → Tesseract tag mapping)
- **PSM modes:** 0–13 (default: 3 = fully automatic page segmentation)
- **OEM modes:** 0–3 (default: 3 — default, prefers LSTM)

#### Output Formats
- **TSV** — word-level bounding boxes: x, y, width, height, confidence, text, block/page grouping
- **Plain text** — recomposed from TSV; line grouping by row bucket (12 px threshold)
- **`parseTsv()`** → `{text: string, words: array, mean_confidence: float}`

#### LLM Post-Correction (opt-in)
- **Trigger:** `ocr_llm_correction_enabled` setting + mean page confidence < `ocr_llm_correction_min_confidence` threshold (default: 70)
- **Service:** `OcrLlmCorrector` → LLM: "correct OCR text" (preserves structure, fixes character errors)
- **Output:** Corrected text + per-correction list + model name

#### Persistence
- **`persist()`** → writes to `iiif_ocr_text` (full_text, format, language, confidence, DO mapping) and `iiif_ocr_block` (per-word: x/y/width/height/confidence/order)
- **PREMIS event:** `ocr.tesseract` written to `preservation_event` table (with agent = "ahg-ai-services:OcrService {version}")

#### Resolution Order for Languages
1. Caller-supplied `$opts['lang']`
2. `ahg_setting.ocr_default_languages`
3. `information_object_i18n.language` (ISO 639 code → Tesseract tag)
4. Default: `osd+eng+afr`

### OCR Export Service: `OcrExportService`
- **Package:** `ahg-iiif-collection`
- **Exports stored OCR** from `iiif_ocr_text` + `iiif_ocr_block`
- **4 output formats:**
  - **`exportTxt()`** — plain text with `--- page N ---` separators
  - **`exportAlto()`** — ALTO 4.x (LOC schema); HPOS/VPOS/WIDTH/HEIGHT from block coords; WC (word confidence)
  - **`exportHocr()`** — hOCR HTML 1.2 (`ocr_page > ocr_carea > ocr_line > ocrx_word`); `bterminology: bbox` and `x_wconf` attributes
  - **`exportPageXml()`** — PRImA PAGE-XML 2019-07-15; polygon coords via `pointsFromBbox()`; `TextEquiv conf`

---

## 7. Handwritten Text Recognition (HTR)

### Service: `HtrService`
- **Package:** `ahg-ai-services`
- **Endpoint:** `https://ai.theahg.co.za/ai/v1/htr/legacy` (configurable: `HTR_SERVICE_URL` env / `htr_url` setting)
- **Auth:** Bearer token via `ahg_ai_settings` API key
- **Formats:** `all` (default), `json`, `csv`, `ilm`, `gedcom`
- **Doctypes:** `auto` (default) or explicit hint
- **Quota gate (#667):** `QuotaService::consume('htr')` — raises `QuotaExceededException`
- **Receipt chain (#61/ADR-0002):** `InferenceService::record()` → `inference_record` table
  - `input_hash` = `sha256(image_bytes)`
  - `output_hash` = `sha256(response_json)`
  - `confidence` = `1 - CER` (when CER in response) | explicit `confidence` field
- **Context hints (#750):** `EmbeddedMetadataContextService::forDigitalObject()` forwards EXIF/IPTC/XMP hints to HTR as `context_hints` form field; event `inference_context_used` logged
- **Batch:** Multi-file batch via repeated `attach()` in one HTTP request; `downloadBatch()` and `downloadBatchJobs()` / `downloadBatchStatus()`
- **Annotation:** `saveAnnotation()` — image + annotation JSON to HTR service
- **Training:** `trainingStatus()`, `triggerTraining()`
- **FamilySearch integration:** `sources()` returns familysearch config + training stats

---

## 8. Named Entity Recognition (NER)

### Primary Service: `NerService`
- **Package:** `ahg-ai-services`
- **Default endpoint:** `http://192.168.0.112:5004/ai/v1` (configurable; setting `api_url`)
- **Entities:** `PERSON`, `ORG` (corporate body), `GPE` (place), `DATE` → normalised to `{persons, organizations, places, dates}`
- **Pipeline:**
  1. Gazetteer pre-pass (`NerGazetteerService::scan()`) — curated operator entities tagged before ML
  2. Dedicated NER API dispatch (`extractViaApi()`)
  3. LLM fallback (`LlmService::extractEntities()`)
  4. Merge gazetteer hits into ML result (gazetteer wins on dedup)
- **entities_v2 (#132):** Per-entity detailed records from API: `{value, type, offset_start, offset_end, score}` → stashed in `$lastDetailedEntities` buffer for authoritative-resolution promoter
- **Access points:** `createAccessPoints()` / `createAccessPointsFromDetailed()` — inserts `ahg_ner_entity` rows, promotes to authority mentions via `PromoteToMentionService`
- **Quota gate (#667):** `QuotaService::consume('ner')` before both dispatch paths
- **Context hints (#750):** `EmbeddedMetadataContextService` injection for EXIF/IPTC disambiguation prefix
- **Receipt chain (#61/ADR-0002):** `InferenceService::record()` with `inputHash`=`sha256(text)`, `outputHash`=`sha256(JSON(entities))`

---

## Annexe: Other AI Services

### LLM Service (`LlmService`)
- Generic LLM wrapper (local Ollama / remote gateway) — used for NER fallback, document Q&A, summarisation, JSON extraction
- Inference logging via `InferenceLogger`

### OcrLlmCorrector (`OcrLlmCorrector`)
- LLM post-correction of Tesseract OCR output — receives OCR text + calls LLM for "correct character errors"
- Inference receipt chain

### EmbeddedMetadataContextService (`EmbeddedMetadataContextService`)
- Reads `property.scope='metadata_extraction'` for a digital object
- Converts EXIF/IPTC/XMP fields to structured `AiContextHints` DTO
- **Methods:** `forDigitalObject()`, `toPromptPrefix()`, `logContextEvent()`

### CostService / QuotaService
- **Cost:** Records per-inference cost in `ai_cost_log`
- **Quota:** Per-tenant usage counter with configurable limits in `ahg_ai_settings`

---

## 9. 3D Model Viewer

### GLB / GLTF (preferred)
- **Component:** `<model-viewer>` (Google model-viewer 3.3.0 from CDN)
- **Features:** `camera-controls`, `touch-action="pan-y"`, `shadow-intensity`, `exposure`, auto-rotating turntable MP4 overlay, AR mode (`ar`) via `ar-modes="webxr scene-viewer quick-look"`
- **Config UI:** `ahg-3d-model/resources/views/settings.blade.php` — allows rotation speed, auto-zoom toggle, AR enable

### OBJ / STL / PLY / FBX
- **Renderer:** Three.js r160 (CDN importmap: `three@0.160.0`, `three/addons/`)
- **Loader:** `OBJLoader`, `STLLoader` (no native PLY/FBX loader found)
- **Controls:** `OrbitControls` with damping
- **Lighting:** AmbientLight (0.6) + DirectionalLight (0.8) + HemisphereLight (0.4)
- **Auto-centre:** `Box3` → scale so max dimension = 2 units, re-centre to origin

### External Embed
- **Sketchfab:** Iframe embed (`sketchfab.com/models/<uuid>/embed`) — slug extraction via `basename(pathinfo())` + `/([0-9a-f]{32})$/` regex
- **YouTube / Vimeo:** Iframe embed (`youtube.com/embed/<id>`, `player.vimeo.com/video/<id>`)

### Thumbnail Generation
- **Service:** `ThreeDThumbnailService` — generates GLB-resampled 256x256 PNG thumbnail via ImageMagick
- **Pipeline:** Canvas capture → ImageMagick resize → store as `digital_object` thumbnail

---

## 10. Watermarking (ImageMagick)

### Service: `WatermarkService`
- **Package:** `ahg-media-processing`
- **Tool:** `composite` (ImageMagick) — dissolve overlay
- **Custom watermarks uploaded** to `uploads/watermarks/`; stored in `custom_watermark` table

#### Watermark Application (`apply()`)
```bash
# Tile:
composite -dissolve {opacity} -tile {watermark} {target} {target}
# Single position:
composite -dissolve {opacity} -gravity {NW|N|NE|W|C|E|SW|S|SE} {watermark} {target} {target}
```

#### Priority Chain
1. Security classification watermark (`object_security_classification.watermark_image`) — repeat tile, 50% opacity
2. Object-level custom type (`object_watermark_setting`)
3. System type watermark (`watermark_type`: DRAFT, COPYRIGHT, CONFIDENTIAL, REVIEW, etc.)
4. Default watermark (operator-configured)

#### Per-Type Settings
| Key | Value | Description |
|---|---|---|
| `watermark_min_size` | px | Minimum image dimension to watermark |
| `default_watermark_enabled` | 0/1 | Global on/off |
| `default_watermark_type` | CODE | Default watermark type code |
| Custom upload | file | PNG/JPEG/GIF → stored in `uploads/watermarks/` |

#### Text Watermark (PhotoProcessor)
```bash
convert {path} -gravity SouthEast
  -fill "rgba(255,255,255,0.7)"
  -stroke "rgba(0,0,0,0.5)" -strokewidth 1
  -pointsize 18 -annotate +12+12 "{text}"
  {path}
```

#### Cantaloupe Cache
`updateCantaloupeCache()` → `/tmp/cantaloupe_classifications.json` — consumed by Cantaloupe's LookupResolver for IIIF-served images

---

## 11. GIS / Map (Leaflet)

### Package: `ahg-research`
### File: `research/map-builder.blade.php`
- **Library:** Leaflet 1.9.4 (CDN: `unpkg.com/leaflet@1.9.4/dist/`)
- **Stylesheet:** Leaflet CSS from CDN
- **Map type:** OpenStreetMap tiles (`OpenStreetMap` provider)
- **Features:** Place map points from research (with metadata), overlay archives/collections, export KML
- **Usage:** Research project map builder for archival geography

---

## 12. Encryption (AES-256-CBC)

### Service: `EncryptionService`
- **Package:** `ahg-core`
- **Algorithm:** AES-256-CBC via PHP core `openssl_*`
- **Key source:** `EncryptionService::resolveKey()` — `ENCRYPTION_KEY` env > `heratio.encryption_key` config > `APP_KEY` fallback (all keys cross-seeded via PBKDF lipsum) — the primary key is randomised at first boot and persisted in `heratio.encryption_key`
- **Usage:** Column-level PII encryption for researcher module (`research_researcher.phone`, `research_researcher.id_number`, `research_researcher.notes`)
- **Gate verified in:** `ResearchService::registerResearcher()`, `ResearchService::updateResearcher()`, `ResearchController::viewResearcher()`
- **Gap:** `NazController` does NOT have the same encryption gate for NAZ researcher PII fields (`phone`, `national_id`, `passport_number`) — these are stored as plaintext

---

## 13. GIS Map (Research Projects)

- **Package:** `ahg-research`
- **File:** `research/map-builder.blade.php`
- **Library:** Leaflet 1.9.4 (CDN)
- **Tiles:** OpenStreetMap
- **Usage:** Map builder for research project spatial data

---

## 14. PRONOM Format Identification

### Service: `FormatIdService`
- **Package:** `ahg-scan`
- **Source:** `pronom_signature.sqlite` (SQLite dictionary — external DROID-style signature file)
- **Identification:** MIME type + PUID lookup against signature DB
- **Used in:** `ProcessScanFile.php` scan pipeline, `PremisEventService`
- **Integration:** `DigitalObjectService::extractMetadataForMaster()` calls format ID, writes to `media_metadata`

---

## 15. PREMIS Preservation Events

### Service: `PremisEventService` + `FormatIdService`
- **Package:** `ahg-scan`
- **Tables:** `preservation_event`, `digital_object`
- **Event types:** Format ID, extract OCR, metadata extraction, preservation event
- **Agent:** `heratio/premis-agent` identifier
- **Formats:** Format ID, NER, HTR, OCR, metadata extraction events written to `preservation_event` table

---

## 16. IIIF Collection / Change Discovery

### Services
- `CollectionService` — IIIF Collection (ordered, `sc:Collection`)
- `ManifestService` — IIIF V2 manifest per IO (`sc:Manifest`, `sc:Sequence`, `sc:Canvas`)
- `ChangeDiscoveryService` — IIIF Change Discovery API (MUST either implemented or stub)
- `AnnotationManagementService` — CRUD for IIIF/W3C Web Annotations

### Database Tables
- `iiif_collection`, `iiif_collection_item`, `iiif_annotation`, `iiif_ocr_text`, `iiif_ocr_block`

### Manifest Features
- **Label:** IO title from `information_object_i18n`
- **Metadata block:** descriptive data from IO columns
- **Related entries:** parent, children, alternative IDs
- **Thumbnail:** IIIF Image API URL for reference/thumbnail derivative
- **Authorization:** Session-gated annotation posts (anon POST → 302 to login)

---

## 17. IIIF Auth (Auth API Flow 2)

### Files
- `iiif-collection/resources/views/iiifAuth/access-token-iframe.blade.php` — token exchange endpoint
- Flow: anonymous access → login redirect → token endpoint → iframe postMessage

---

## 18. Streaming / Storage Encryption

### Encrypted Master Files
- **Service:** `StorageEncryptionService` (stub; not full content read yet)
- **Storage:** `uploads/r/` directory with optional in-place encryption
- **Checksum:** SHA-256 for all derivatives at ingestion; stored in `digital_object.checksum`

---

## 19. Citation Export (CSL JSON + Export)

### Services
- `CitationService` — formats bibliographic records to 6 styles (Chicago, MLA, Turabian, APA, Harvard, UNISA)
- **Export formats:** RIS, BibTeX, EndNote tagged format
- **Citation log:** 14 231 rows in `research_citation_log` tracking usage events
- **UI:** `research/cite.blade.php` — public citation page with style selector + copy/export buttons

---

## 20. PDF Viewer

- **Rendering:** Native `<iframe src="{{ $masterUrl }}">` — browser native PDF viewer
- **Fallback:** Download link + open-in-new-tab
- **Toolbar:** Open external link, download buttons above the iframe

---

## 21. Bootstrap 5 Carousel (Image Gallery)

- **Rendered in:** `_digital-object-viewer.blade.php` for `viewer_type=carousel`
- **Bootstrap 5:** `data-bs-ride`, `data-bs-interval`, `data-bs-pause`
- **Indicators:** Thumbnail strip for carousel mode
- **Controls:** Prev/next buttons
- **Autoplay:** Configurable via `carousel_autoplay`, `carousel_interval` settings

---

## 22. Spreadsheet Library (PhpSpreadsheet)

- **Used in:** Research Studio AI artefacts (spreadsheet output type), HTR bulk annotation spreadsheet generation, file plan import
- **Output:** XLSX read/write, CSV, ODS — file plan import, HTR mapping, research artefacts
- **Status:** Package dependency confirmed; composer require not separately verified in this audit

---

## 23. ODRL Policy Evaluation

- **Table:** `research_odrl_policy`, `research_odrl_policy_evaluation`
- **Component:** ODRL policy engine for researcher access decisions
- **UI:** `research/odrl-policies.blade.php` — policy management
- **Middleware:** `ResearchAccessMiddleware` gates access via ODRL evaluation

---

## 24. CDN-Loaded Libraries

| Library | Version | CDN URL | Used in |
|---|---|---|---|
| Google model-viewer | 3.3.0 | `ajax.googleapis.com` (ES module) | 3D GLB viewer |
| Leaflet | 1.9.4 | `unpkg.com` | Research map builder |
| Three.js | r160 | `cdn.jsdelivr.net` (ES module importmap) | 3D OBJ/STL viewer |
| Bootstrap 5 | 5.x | Bundled in ahg-theme-b5 | JS + CSS, carousel, modals, collapse |

---

## Summary Matrix

| # | Component | Package(s) | Language | Tool/Binary | Source | Purpose |
|---|---|---|---|---|---|---|
| 1 | OpenSeadragon 6.0.2 | public/vendor/openseadragon/ | JS | — | CDN bundle | Deep zoom image viewer |
| 2 | OSD scalebar plugin | openseadragon-heratio-scalebar.js | JS | — | Same-origin | Physical scale overlay |
| 3 | OSD magnifier plugin | openseadragon-heratio-magnifier.js | JS | — | Same-origin | Magnifying loupe |
| 4 | OSD filter pipeline | openseadragon-filtering.js | JS | — | Same-origin | Brightness/contrast/greyscale/invert/threshold |
| 5 | Mirador 4 + 7 plugins | tools/mirador-build/ | JS/React | — | Webpack bundle | IIIF Presentation viewer + Compare + Annotations |
| 6 | Cantaloupe IIIF IS3 | Infrastructure | Java 17+ | — | External service | Authoritative IIIF tile server |
| 7 | PHP exif_read_data | ahg-metadata-extraction | PHP | ext-exif | PHP core | EXIF IFD extraction (JPEG/TIFF) |
| 8 | ExifTool binary | ahg-metadata-extraction | PHP | `exiftool -json ...` | System binary | Cross-format comprehensive extraction |
| 9 | PHP iptcparse | ahg-metadata-extraction | PHP | ext-exif | PHP core | IPTC-IIM APP13 parsing |
| 10 | XMP XML parsing | ahg-metadata-extraction | PHP | ext-exif (read) | PHP core | XMP DOM extraction |
| 11 | pdfinfo binary | ahg-metadata-extraction | PHP | `pdfinfo ...` | System binary | PDF title/author/keywords/pages |
| 12 | ffprobe binary | ahg-metadata-extraction, ahg-media-streaming | PHP | `ffprobe -j ...` | System binary | Audio/video metadata extraction |
| 13 | ffmpeg binary | ahg-core, ahg-media-streaming | PHP | `ffmpeg ...` | System binary | A/V derivative generation, transcoding |
| 14 | ImageMagick convert | ahg-media-processing, ahg-core | PHP | `convert ...` | System binary | Auto-orient, thumbnail resize, text watermark, PTIFF |
| 15 | ImageMagick composite | ahg-media-processing | PHP | `composite ...` | System binary | Image watermark overlay |
| 16 | MediaDerivativeService | ahg-core | PHP | ffmpeg + ImageMagick | Shell exec | Audio: MP3 + waveform PNG; Video: H.264 MP4 + JPEG poster; PTIFF |
| 17 | TranscodingService | ahg-media-streaming | PHP | ffmpeg | Shell exec | AVI/WMV/FLAC → MP4/MP3 |
| 18 | StreamingService | ahg-media-streaming | PHP | — | PHP core | HTTP 206 Range request streaming + transcode-on-demand |
| 19 | Media player (heratio) | ahg-theme-b5 | Blade/JS | — | Same-origin | Custom chrome player (gradient, scrubber, PiP, fullscreen) |
| 20 | PhotoProcessor | ahg-media-processing | PHP | exif_read_data + convert | PHP ext / ImageMagick | Condition photo pipeline: orient, strip, resize, watermark, EXIF capture |
| 21 | WatermarkService | ahg-media-processing | PHP | composite | ImageMagick | Multi-priority watermark pipeline + Cantaloupe cache |
| 22 | Tesseract OCR | ahg-ai-services | PHP | `tesseract ...` | System binary | Document OCR, word-level TSV, multilingual |
| 23 | OcrLlmCorrector | ahg-ai-services | PHP | LlmService | Ollama/gateway | LLM post-correction of Tesseract output |
| 24 | OCR export (4 formats) | ahg-iiif-collection | PHP | — | PHP XML | Plain text, ALTO 4.x, hOCR 1.2, PAGE-XML 2019-07-15 |
| 25 | HTR (Handwriting) | ahg-ai-services | PHP | HTTP | AI Gateway | Line/word transcription from handwritten document image |
| 26 | NER (Named Entity) | ahg-ai-services | PHP | HTTP | AI Gateway | Person/ORG/GPE/DATE extraction + authority promotion |
| 27 | NER Gazetteer pre-pass | ahg-ai-services | PHP | DB | DB tables | Operator-curated entity labels before ML dispatch |
| 28 | LLM service | ahg-ai-services | PHP | HTTP | Ollama | Document Q&A, entity extraction, spelling correction |
| 29 | EmbeddedMetadataContextService | ahg-ai-services | PHP | DB | property table | EXIF/IPTC/XMP context hints for AI inference |
| 30 | Cost/Quota tracking | ahg-ai-services | PHP | DB | table | Per-inference cost + per-tenant quota consumption |
| 31 | Google model-viewer | public CDN | ES module | — | Google CDN | Interactive GLB/GLTF 3D viewer |
| 32 | Three.js r160 | public vendored | ES module | — | cdn.jsdelivr.net | OBJ/STL 3D renderer with OrbitControls |
| 33 | Leaflet 1.9.4 | Blade | JS | — | unpkg.com | OpenStreetMap GIS map in research projects |
| 34 | Bootstrap 5 carousel | ahg-theme-b5 | Blade/CDN | — | Bundled | Multi-image carousel for IO collections |
| 35 | PhpSpreadsheet | ahg-research (AI artefacts) | PHP | Composer | Library | XLSX read/write for HTR mapping + research spreadsheets |
| 36 | EncryptionService | ahg-core | PHP | openssl_* | PHP core | AES-256-CBC PII encryption (researcher module) |
| 37 | FormatIdService | ahg-scan | PHP | SQLite | pronom_signature.sqlite | PRONOM PUID/MIME identification |
| 38 | ODRL policy | ahg-research | PHP | DB | table | Researcher access decision engine |
| 39 | PREMIS event service | ahg-scan | PHP | DB | DB | Preservation event emission to preservation_event table |
| 40 | WaveSurfer.js 7 | tools/wavesurfer-build/ → public | UMD build | — | npm → public/ | Audio waveform visualisation (build done; not yet piped to media-player.blade.php) |

---

## Outstanding Gaps

| # | Gap | Severity | Package |
|---|---|---|---|
| G1 | NAZ researcher PII unencrypted (NazController) | Critical | ahg-naz |
| G2 | WaveSurfer.js initialisation not wired to media-player blade | Medium | ahg-theme-b5 |
| G3 | Whisper transcription pipeline not implemented (binary check + toggle only) | Medium | ahg-media-processing |
| G4 | Plyr/Video.js JS/CSS bundling not confirmed in master.blade.php | Medium | ahg-theme-b5 |
| G5 | Scheduled batch jobs absent (expiration, ORCID sync, re-derivative) | Low | infrastructure |
| G6 | WaveSurfer bundle (tools/wavesurfer-build/) tool exists; library deployed to public/; not yet piped into media-player.blade.php JS init | Medium | ahg-theme-b5 |
| G7 | IIIF AV TemporalCanvas not implemented (audio/video served as direct URLs) | Low | ahg-iiif-collection |
| G8 | Scheduled batch job for derivative generation (MediaDerivativeService lacks queued trigger) | Low | ahg-core |
| G9 | Video caption/subtitle track management UI absent | Low | ahg-media-processing |
