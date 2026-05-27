# Heratio Component and Functionality Inventory

> Compiled from codebase audit, `v1.104.6` (commit `9534a745`).  
> This document lists every significant third-party component, library, binary tool, and architectural capability in the Heratio application, organised by domain.

---

## 1. Image Deep Zoom

### 1.1 OpenSeadragon

| Property | Value |
|---|---|
| Version | 6.0.2 |
| Bundle file | `/vendor/openseadragon/6.0.2/openseadragon.min.js` |
| Source map | `/vendor/openseadragon/6.0.2/openseadragon.min.js.map` |
| Plugins (bundled) | `openseadragon-filtering.js`, `openseadragon-heratio-scalebar.js`, `openseadragon-heratio-magnifier.js` |
| Toolbar icons | `/vendor/openseadragon/6.0.2/images/` (39 PNG assets) |
| Entry point | `ahg-iiif-viewer.js` → `initIiifViewer()` → `showOSD()` |
| Primary views | `packages/ahg-information-object-manage/resources/views/partials/_digital-object-viewer.blade.php` (locked path) |

**What it does:**
- Default viewer for images on information object show pages (`/informationobject/show/{slug}`)
- Falls back to bare `<img>` if `window.AHG_IIIF.enabled === false`
- Probes image dimensions before rendering to size the canvas correctly
- Forces `drawer: 'canvas'` (OSD 6 default WebGL drawer skips the `openseadragon-filtering.js` pixel pipeline)
- Initial zoom, max zoom (pixel ratio), navigator panel, fullscreen, and rotation all sourced from `window.AHG_IIIF` settings

**Custom OSD plugins:**

| Plugin | File | Function |
|---|---|---|
| Image filters | `openseadragon-filtering.js` | Brightness, contrast, greyscale, invert, threshold — live via `setFilterOptions()` |
| Scalebar | `openseadragon-heratio-scalebar.js` | Physical scale overlay (bottom-left), reads `info.json` service block; override via `window.AHG_IIIF.physdim` |
| Magnifier loupe | `openseadragon-heratio-magnifier.js` | Circular pixel-magnifier overlay (radius 90 px, zoom 3x), toggle button (top-right pill) |
| Filtering toolbar | Built-in JS in `ahg-iiif-viewer.js` | DOM-built panel attached to `osd-{viewerId}`; feature-detected, no-ops if plugin absent |

**Per-IO override:** appending `?viewer=carousel|single|mirador|openseadragon` to any IO URL forces that mode regardless of the operator default setting.

**Oversized canvas fallback:** if dimension probing fails, defaults to 4000 x 3000 px rather than rendering nothing.

---

### 1.2 Cantaloupe IIIF Image Server

| Property | Value |
|---|---|
| Base URL | `http://127.0.0.1:8182` (hard-coded in `IiifCollectionService.php`; operator-configurable via `iiif_server_url` / `window.AHG_IIIF.server_url`) |
| API version | IIIF Image API 2 (`/iiif/2/{identifier}/info.json`) |
| Identifier encoding | File path with `/` replaced by `_SL_`, then the filename appended |
| TIFF/JP2 handling | Routes through Cantaloupe; `osd-` container uses the IIIF tile source (`/iiif/3/{identifier}/info.json` via OpenSeadragon IIIF tile source) |
| Multi-page TIFF | `;{page}` suffix on the Cantaloupe identifier selects individual pages |

**Usage:** TIFF, JP2, JPX images served as IIIF tile pyramids. OpenSeadragon uses `info.json` from Cantaloupe as its tile source. Mirador uses `/{identifier}/full/max/0/default.jpg` for the painting body.

---

## 2. IIIF Presentation

### 2.1 Mirador

| Property | Value |
|---|---|
| Version | Bundled as `mirador.min.js` + `mirador.min.css` |
| Bundle path | `/vendor/ahg-theme-b5/js/vendor/mirador/` |
| Entry point | `ahg-iiif-viewer.js` → `showMirador()` → `Mirador.viewer()` |
| Primary views | `/iiif-viewer/{slug}` (full-page, locked path); IO show page (`_digital-object-viewer.blade.php`); compare view (`/iiif/compare/{slug}`); workspaces (`/iiif/workspaces`) |

**Configuration keys** (from `window.AHG_IIIF`):

| Key | Effect |
|---|---|
| `enabled` | Kill-switch; falls back to `<img>` when false |
| `viewer` | Default initial mode override |
| `server_url` | IIIF tile-source origin (empty → local Cantaloupe) |
| `show_fullscreen` | Mirador window fullscreen button visibility |
| `enable_annotations` | Annotation panel + `highlightAllAnnotations` + `HeratioAnnotationAdapter` |
| `default_zoom` | OSD initial zoom level (not Mirador) |
| `max_zoom` | OSD max zoom pixel ratio (not Mirador) |
| `show_navigator` | OSD navigator overlay (not Mirador) |

**Mirador plugins wired:**
- `heratio-av-overlay` — audio/video overlay on canvases
- `heratio-compare-overlay` — side-by-side canvas comparison
- `heratio-mirador-workspace` — workspace customisation
- `heratio-scalebar` — scalebar on canvas
- `heratio-loupe` — magnifier loupe

**HeratioAnnotationAdapter:** exposed as `window.HeratioAnnotationAdapter` by the Mirador build entrypoint (`tools/mirador-build/src/index.js`). Mirador is configured with `annotation.adapter: canvasId => new HeratioAnnotationAdapter(canvasId)`. Writes to `/api/annotations`, reads via the same search endpoint. Storage is gated by session auth (anonymous POST redirects to `/login`).

**Manifest construction:** built client-side in `showMirador()`. Shape: IIIF Presentation 2 (`@context: http://iiif.io/api/presentation/2/context.json`). Image dimensions probed via `new Image()` before manifest assembly. For Cantaloupe-served images (TIFF/JP2), the painting body points to `/full/max/0/default.jpg` and includes an `ImageService2` service block. For plain images, the painting body points directly at the URL.

**Compare mode:** `/iiif/compare/{slug}` loads Mirador with two windows, each pointing at a different manifest, using the Mirador mosaic workspace.

**Content Search:** Mirador's Content Search 2.0 (Annotation search endpoint) wired to `IiifContentSearchController` at `/iiif-content-search`.

### 2.2 IIIF Change Discovery

| Service | Class | Description |
|---|---|---|
| Change detection | `IiifChangeDiscoveryService` | Emits IIIF Change Discovery 1.0 manifests and activity streams for Heratio collections |
| Controller | `IiifChangeDiscoveryController` | REST endpoints for change list and activity collection |
| Test suite | `IiifChangeDiscoveryTest.php` | Feature test coverage |
| Activity stream | Stored in `ahg_iiif_change_activity` table | Per-event: `id`, `type` (Create/Update/Delete), `collection_slug`, `manifest_id`, `timestamp` |

### 2.3 IIIF Content State

| Service | Class | Description |
|---|---|---|
| Content state encoding | `IiifContentStateService` | Encodes/decode IIIF Content State 1.0 URIs (serialised search + canvas targeting) |
| Test suite | `IiifContentStateServiceTest.php` | Unit test coverage |
| Route | `/iiif-content-state` | Serialise a browse/search state into a URL-shareable content state URI |

### 2.4 IIIF Auth (Flow 2)

| Service | Class | Description |
|---|---|---|
| Auth API | `IiifAuthFlow2Service` | IIIF Authentication API 2.0 (click-through + kiosk + token patterns) |
| Controller | `IiifAuthFlow2Controller` | Handles all three auth endpoints: `access-token`, `login`, `logout` |
| Access token iframe | `resources/views/iiifAuth/access-token-iframe.blade.php` | Kiosk-mode token delivery surface |
| Test suite | `IiifAuthFlow2ServiceTest.php` | Unit test coverage |
| Database table | `ahg_iiif_auth_token` | Short-lived IIIF auth tokens (token, window_id, expires_at, created_at) |

### 2.5 IIIF Collection Service

| Class | Description |
|---|---|
| `IiifCollectionService` | Main manifest builder — v2 (`/iiif/collection/{slug}`) and v3 (`/iiif/3/collection/{slug}`), canvas builders for images (Cantaloupe-served TIFF/pyramid) and audio/video |
| NER annotations job | `BuildNerAnnotationsForCanvas` — async job that fires a named-entity-recognition pipeline per canvas, writes to `ahg_iiif_ner_annotation` |

**Canvas types handled:**

| Type | Source | Tile/stream |
|---|---|---|
| Image (TIFF/JP2) | Cantaloupe | IIIF Image API 2 (`/iiif/2/{id};{page}/info.json`) |
| Image (JPEG/PNG) | Direct file | OSD `type: 'image'` |
| Multi-page TIFF | Cantaloupe | `;{pageNum}` page selector |
| Audio | Direct file | `MediaStreamController` HLS |
| Video | Direct file | `MediaStreamController` HLS |

### 2.6 OcrExportService

Exports OCR block data (`iiif_ocr_text`, `iiif_ocr_block` tables) into:

| Format | Spec | Used by |
|---|---|---|
| ALTO XML | IIIF Technical Specification | IIIF search, Mirador |
| PAGE XML | PAGE 2013 | External OCR workflows |
| Plain text | Line-separated | Fallback |
| HTML | hOCR / custom div/span structure with bounding boxes | Mirador search hit rendering |

Persists word-level bounding boxes (from Tesseract TSV output) as four-corner polygon points.

### 2.7 NER Annotations Pipeline

| Class | Description |
|---|---|
| `BuildNerAnnotationsForCanvas` | Queued job — fires per canvas, calls the AI pipeline for named-entity extraction |
| `IiifNerAnnotationsController` | Controller for NER annotation CRUD |

---

## 3. EXIF / Metadata Extraction

### 3.1 PHP EXIF extension

| Function | Used in |
|---|---|
| `exif_read_data()` | `MetadataExtractionService::extractImageExif()` — JPEG, TIFF-II, TIFF-MM; `PhotoProcessor::extractExif()` — processed images |

Graceful fallback: returns `[]` if extension absent. Binary UTF-8 values sanitised to `[Binary Data]`.

### 3.2 ExifTool

| Property | Value |
|---|---|
| Binary path | `command -v exiftool` (on $PATH) |
| Version | 12.76 (confirmed) |
| Command pattern | `exiftool -json -a -G1` |
| Used in | `MetadataExtractionService` (secondary comprehensive pass), `DigitalObjectService::extractMetadata()` |

Status badge shown in metadata extraction settings UI (`photos-settings.blade.php` + `status.blade.php`). Install instructions shown when unavailable.

### 3.3 MetadataExtractionService

| Package | File |
|---|---|
| `ahg-metadata-extraction` | `src/Services/MetadataExtractionService.php` |

**Capabilities:**

| Extraction target | Tool | Output table |
|---|---|---|
| JPEG/TIFF EXIF | PHP `exif_read_data()` | In-memory array |
| Cross-format metadata | exiftool JSON | In-memory array |
| IPTC | PHP `iptcparse()` | In-memory array (gated by `meta_extract_iptc` toggle) |
| XMP | Regex (basic, DateTimeOriginal) | In-memory array (gated by `meta_extract_xmp` toggle) |
| GPS coordinates | EXIF GPS IFD | `$meta['gps_latitude']`, `$meta['gps_longitude']`, `$meta['gps_altitude']` |
| Image dimensions | exiftool | Width, height |
| Date/Time | EXIF DateTime, DateTimeOriginal | ISO date |
| Audio metadata | ffprobe | Duration, codec, bitrate, channels, sample rate |
| Video metadata | ffprobe | Duration, codec, bitrate, resolution, frame rate, audio stream |
| PDF metadata | pdfinfo | Title, author, page count, PDF version |

**Normalised output fields:** `title`, `creator`, `date`, `description`, `copyright`, `keywords`, `technical`, `gps`

**Source priority:** XMP > IPTC > EXIF > exiftool (first-non-empty wins)

**Auto-apply on upload:** `extractAndApplyOnUpload()` hook triggered by `ProcessScanFile` job

**Bulk re-extraction:** `batchExtract()` for unextracted digital objects (no scheduler; called manually or via artisan)

**Toggles:** `meta_extract_iptc`, `meta_extract_xmp`, `meta_extract_gps`, `meta_extract_on_upload`, `meta_overwrite_existing`

---

## 4. Audio / Video Processing

### 4.1 FFmpeg

| Property | Value |
|---|---|
| Binary | `command -v ffmpeg` (on $PATH) |
| Availability check | `isCommandAvailable('ffmpeg')` in `MetadataExtractionService` |
| Graceful degradation | Logs warning and skips derivative generation if absent |

**Used in:**
- `MediaDerivativeService` — audio derivatives (MP3 128 kbps, waveform PNG) and video derivatives (H.264 MP4 480p CRF 23, JPEG poster frame)
- `TranscodingService` — on-the-fly transcoding of non-streamable formats (AVI, WMV, FLAC) before streaming
- `StreamingService` — HLS segment generation via FFmpeg for adaptive bitrate streaming

### 4.2 ffprobe

| Property | Value |
|---|---|
| Binary | `isCommandAvailable('ffprobe')` |
| Used in | `MetadataExtractionService` (audio/video extraction) |

Extracts: duration, bitrate, format_name, format_long_name, codec, sample_rate, channels, width, height, frame_rate, audio stream details.

### 4.3 MediaDerivativeService

| Package | File |
|---|---|
| `ahg-core` | `src/Services/MediaDerivativeService.php` |

**Derivative pipeline:**

| Input type | Reference (usage_id=141) | Thumbnail (usage_id=142) |
|---|---|---|
| Audio (any) | MP3 128 kbps libmp3lame | Waveform PNG (640x120, `showwavespic`, #555555) |
| Video (any) | H.264 MP4 480p, `-movflags +faststart`, CRF 23 | JPEG poster frame at t=1s |
| TIFF/pyramid | PTIF via ImageMagick `convert` (for Cantaloupe IIIF) | JPEG thumbnail |
| 3D model | Delegated to `ThreeDThumbnailService` | `ThreeDThumbnailService` |

**Invocation:** called from `ProcessScanFile.php` job on master file ingestion. Safe filename sanitisation. SHA-256 checksum on all derivatives.

### 4.4 TranscodingService

| Package | File |
|---|---|
| `ahg-media-streaming` | `src/Services/TranscodingService.php` |

Checks if source needs transcoding (`needsTranscoding()`). Uses FFmpeg for format conversion to HLS-compatible MP4/AAC before streaming.

### 4.5 StreamingService

| Package | File |
|---|---|
| `ahg-media-streaming` | `src/Services/StreamingService.php` |

Streams digital objects with on-demand transcoding. Checks `TranscodingService::needsTranscoding()` first; transcodes if needed, streams original otherwise.

### 4.6 MediaStreamController

| Package | File |
|---|---|
| `ahg-media-streaming` | `src/Controllers/MediaStreamController.php` |

HTTP streaming endpoint for audio/video. Supports range requests for seeking.

### 4.7 PhotoProcessor

| Package | File |
|---|---|
| `ahg-media-processing` | `src/Services/PhotoProcessor.php` |

Performs: EXIF extraction (`@exif_read_data()`), resize, auto-orient, strip metadata. Gate: `photo_extract_exif` setting. Output: `camera_info`, `photographer`, `photo_date` under `exif` key.

### 4.8 Media Player Component

| Package | File |
|---|---|
| `ahg-theme-b5` | `resources/views/components/media-player.blade.php` |

Five render modes (via `media_player_type` setting):

| Mode | Description |
|---|---|
| `heratio` | Rich custom UI (gradient bg, play/pause, skip 10s, speed selector, volume, PiP, fullscreen, scrubber, badges, download) |
| `heratio-minimal` | Native HTML5 controls + small progress readout + badges |
| `plyr` | Bare native HTML5 (Plyr wrapper comment in `master.blade.php`) |
| `videojs` | Bare native HTML5 (Video.js wrapper comment in `master.blade.php`) |
| `native` | Bare native HTML5, no wrapper |

**Rich UI features:** gradient background (`#1a1a2e` / `#16213e`), ±10s skip, speed (0.5x–2x), volume, Picture-in-Picture, fullscreen, progress scrubber, time display, file badges, auth-gated download.

**JS fallthrough:** all init wrapped in try/catch; falls back to minimal layout on failure.

**Waveform placeholder:** HTML structure exists for WaveSurfer.js overlay (`media_show_waveform` setting + comment); **WaveSurfer.js library is not loaded** — gap.

**Transcription panel:** shown on media pages when `media_transcription_enabled = true`. Displays segments, full text, language, confidence badge. Download links for WebVTT and SRT.

### 4.9 Transcription

| Tool | Status | Notes |
|---|---|---|
| Whisper binary | Checked in `MediaProcessingController` + `MediaController` | Binary presence checked; pipeline not end-to-end wired |
| `media_transcription_enabled` | Setting exists | UI toggle present; whisper invocation exists in `MediaController` (`/media/transcribe/{id}`) |
| `media_transcription` table | `media_transcription` | Stores `digital_object_id`, `full_text`, `segments` (JSON), `language`, `confidence`, `duration` |
| WebVTT/SRT export | `/media/transcription/{id}/vtt` and `/srt` | Dynamic export routes in `MediaController` |

**Gap:** Whisper binary is checked and invoked in `MediaController` but the full pipeline (Whisper → store segments → display in player) is partially implemented. The transcription table and export routes are live; the Whisper invocation is confirmed in the controller but not in a background job.

---

## 5. OCR / HTR

### 5.1 Tesseract OCR

| Property | Value |
|---|---|
| Binary | `tesseract` on $PATH; path configurable via `ocr_tesseract_binary` setting |
| Version | Probed via `tesseract --version`; persisted to `ahg_ai_settings.ocr_tesseract_version` |
| Language packs | Listed via `ahg:tesseract:list-languages` artisan command; persisted to `ahg_ai_settings.ocr_languages` |
| Supported language spec | `osd+eng+afr` (default); ISO 639 codes mapped to Tesseract tags |
| Page segmentation mode | PSM 3 (fully automatic, no layout assumptions) — configurable |
| OCR engine mode | OEM 3 (default + LSTM) — configurable |
| Output format | TSV (word-level bounding boxes with confidence) |
| PREMIS event | Emitted on success and failure |

**Language mapping (ISO 639 → Tesseract):** af→afr, zu→zul, nl→nld, en→eng, sn→sna, pt→por, and others. Custom trained data supported.

**Artisan commands:**

```
php artisan ahg:ocr:page {doId} [--lang=] [--psm=] [--oem=]
php artisan ahg:tesseract:list-languages [--json]
```

### 5.2 OcrService

| Package | File |
|---|---|
| `ahg-ai-services` | `src/Services/OcrService.php` |

**Pipeline:**

1. Resolve language spec (IO-level → setting → `osd+eng+afr`)
2. Run `tesseract {path} {tmpOut} --psm N --oem M tsv`
3. Parse TSV: extract word boxes, confidence, bounding polygons
4. If `ocr_llm_correction_enabled` and page confidence < threshold → call `OcrLlmCorrector`
5. Persist to `iiif_ocr_text` + `iiif_ocr_block` tables
6. Emit PREMIS event

### 5.3 OcrLlmCorrector

| Package | File |
|---|---|
| `ahg-ai-services` | `src/Services/OcrLlmCorrector.php` |

LLM post-correction over raw Tesseract output. Handles predictable OCR errors: rn→m, O vs 0, l vs 1. System prompt contextualised with document type, language, and Tesseract confidence. Calls `LlmService`. Output written back to `iiif_ocr_text.text_corrected`.

### 5.4 HTR Bulk Annotation (Handwritten Text)

| Package | File |
|---|---|
| `ahg-ai-services` | `resources/views/htr/fs-overlay.blade.php` (full-screen overlay); `resources/views/htr/bulk-annotate.blade.php` |

- Full-screen canvas overlay for region-of-interest drawing on scanned archival pages
- Spreadsheet integration: PhpSpreadsheet reads CSV/XLSX/XLS, maps columns to image regions
- Image-only mode (no spreadsheet) supported
- Bulk mode: processes all images in a folder, auto-names output files
- CSV ground truth: never overwritten by OCR output
- Auto-selection of spreadsheet when only one exists in the folder

### 5.5 OcrExportService (see Section 2.6 above)

---

## 6. 3D Models

### 6.1 Google model-viewer

| Property | Value |
|---|---|
| Version | 3.3.0 |
| CDN | `https://ajax.googleapis.com/ajax/libs/model-viewer/3.3.0/model-viewer.min.js` |
| Module type | `type="module"` |
| Supported formats | GLB (primary), GLTF |
| Features | `camera-controls`, `touch-action="pan-y"`, `shadow-intensity`, `exposure`, auto-ARN, AR quick-look |

**Entry point:** `_digital-object-viewer.blade.php` — model-viewer element for GLB/GLTF. Fallback error handler: shows message if WebGL unavailable or model fails to load.

### 6.2 Three.js

| Property | Value |
|---|---|
| Version | r128 |
| CDN | `https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js` |
| Used in | `packages/ahg-ric/resources/views/explorer.blade.php`, `packages/ahg-ric/resources/views/_ric-panel.blade.php` (RiC entity graph) |

### 6.3 OBJ / STL / PLY / FBX Viewer

Rendered via `model-viewer` (`_show3d.blade.php`) with error fallback. Supported via Three.js adapter within `model-viewer`.

### 6.4 External 3D Embeds

| Provider | Integration | Notes |
|---|---|---|
| Sketchfab | Iframe embed from `/3d-models/{uuid}/embed` | Master URL pattern: `sketchfab.com/3d-models/*` |
| Triposr PLY | `TriposrImportService` | Imports PLY point cloud files; generates thumbnails |
| ThreeDThumbnailService | `ahg-3d-model` | Multiangle thumbnail generation; cross-section JS (`heratio-3d-cross-section.js`); measure JS (`heratio-3d-measure.js`) |

**Camera bookmarks:** `CameraBookmarkController` for saving named camera positions on 3D models.

---

## 7. Annotations

### 7.1 IIIF Annotations (Web Annotation Protocol)

| Package | File |
|---|---|
| `ahg-annotations` | `src/Controllers/AnnotationsController.php` |

**Endpoint:** `/api/annotations` (W3C Web Annotation Data Model + IIIF WAP conformance)

**HTTP verbs:**

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/annotations` | Create annotation |
| GET | `/api/annotations/search?targetId=<canvas_iri>` | List by canvas (Annotot-shaped) |
| GET | `/api/annotations/{uuid}` | Fetch one |
| PUT | `/api/annotations/{uuid}` | Update |
| DELETE | `/api/annotations/{uuid}` | Remove |

**Storage:** `ahg_iiif_annotation` table — stores W3C Web Annotation JSON-LD verbatim in `body_json`; `motivation` (painting/commenting/auto-tagging/identifying); `target_json`; `visibility` (private/shared/public); `canvas_iri`; `creator_id`; tags via `term_id`; `access_decision_id`.

**Content-Type middleware:** `AnnotationContentTypeMiddleware` enforces W3C WAP header requirements (`Content-Type: application/ld+json; profile="http://www.w3.org/ns/anno.jsonld"`, `Link` headers, `Accept-Post`).

**Annotation motivation types:** painting, commenting, scripting, auto-tagging (via NER), identifying.

**Mirador adapter:** `window.HeratioAnnotationAdapter` — wired to Mirador `annotation.adapter` config option. `highlightAllAnnotations` flag keeps drawn shapes permanently visible. Editor remains editable (write gating is session-based, not JS-based).

### 7.2 NER Annotations (Auto-tagging)

Named-entity recognition on canvases via `BuildNerAnnotationsForCanvas` queued job. Entities extracted via AI pipeline; stored as `auto-tagging` motivation annotations on the canvas.

### 7.3 Annotation Studio (Research)

| Package | File |
|---|---|
| `ahg-research` | `resources/views/research/annotation-studio.blade.php` |

Researcher-facing annotation workspace with Mirador integration, comment threads, tag management, and canvas-level entity overlay.

---

## 8. Search and Discovery

### 8.1 Elasticsearch

| Property | Value |
|---|---|
| Host | `http://localhost:9200` (configurable via `services.elasticsearch.host`) |
| Index prefix | `archive_` (configurable via `services.elasticsearch.prefix`) |
| Service | `ElasticsearchService` (`ahg-search`) |

**Indices:**

| Index | Contents |
|---|---|
| `archive_qubitinformationobject` | Archival descriptions |
| `archive_qubitactor` | Authority records |
| `archive_qubitrepository` | Repositories |
| `archive_qubitterm` | Taxonomy terms |

**Capabilities:**
- Basic search (`search()`)
- Full-text multi-index search (`multiSearch()`)
- Advanced faceted search (`advancedSearch()`) — filters, aggregations, sorting
- `search_after` cursor pagination for large result sets
- Fallback to MySQL when Elasticsearch unavailable

**Commands:**
```
php artisan ahg:search:reindex [--type=informationobject|actor|repository|all] [--from=0]
```

### 8.2 Vector Search (Semantic)

| Property | Value |
|---|---|
| Service | `VectorSearchService` (`ahg-search`) |
| Vector DB | Qdrant (`http://localhost:6333` default) |
| Embedding service | Ollama (default; configurable) |
| Endpoint pattern | `POST /api/embeddings` → Ollama, `POST /collections/{name}/points/search` → Qdrant |

**Capabilities:**
- `semanticSearch()` — embed query → Qdrant nearest-neighbour search
- `findSimilar()` — fetch point vector → Qdrant similar-to-point
- `qdrantSearch()` — direct Qdrant query
- `fetchPointVector()` — retrieve vector by Qdrant point ID

### 8.3 Page Indexing

| Service | File |
|---|---|
| `PageIndexService` | `ahg-discovery` — `src/Services/PageIndexService.php` |

Indexes publicly visible HTML pages for search engine discovery. Handles IIIF page types.

---

## 9. AI / LLM Services

### 9.1 LlmService

| Package | File |
|---|---|
| `ahg-ai-services` | `src/Services/LlmService.php` |

HTTP calls to AI Gateway at `ai.theahg.co.za` (or `voice_local_llm_url` setting). Supports Ollama-compatible API. System prompt injection for archival context. Structured output extraction (JSON mode). Configurable model per operation.

### 9.2 AI Provenance Table

| Table | Description |
|---|---|
| `ai_provenance` | Every AI suggestion: `provider`, `prompt`, `response`, `accepted`/`rejected`, `io_id`, `user_id`, `model`, `tokens_used`, `cost_usd` |

Audit trail for all AI-assisted actions.

### 9.3 AI Compliance Oversight

| Package | File |
|---|---|
| `ahg-ai-compliance` | `src/Services/OversightService.php`, `src/Services/AiRiskService.php` |

Annex IV reporting, AI risk classification, oversight dashboard (`resources/views/oversight/index.blade.php`). Artifacts: `ai_risks`, `ai_review_decisions`.

### 9.4 OCR LLM Corrector (see Section 5.3)

### 9.5 AI Suggest Description

| Command | File |
|---|---|
| `ahg:ai:suggest-description` | `src/Commands/AiSuggestDescriptionCommand.php` |

Operator-side CLI that uses LLM to suggest archival description improvements for an IO.

---

## 10. Watermarking

### 10.1 WatermarkService

| Package | File |
|---|---|
| `ahg-media-processing` | `src/Services/WatermarkService.php` |

**Tool:** ImageMagick `composite`

**Command pattern:**
```
composite -dissolve {opacity} -gravity {position} {watermark} {target} {output}
composite -dissolve {opacity} -tile {watermark} {target} {output}   # tile mode
```

**Settings:**
- `watermark_setting` — global watermark preferences
- `watermark_type` — DRAFT, COPYRIGHT, CONFIDENTIAL, CUSTOM
- `custom_watermark` — user-uploaded custom watermark images
- `object_watermark_setting` — per-object watermark configuration
- `watermark_min_size` — minimum image dimension to apply watermark (default: 200 px)

**Security watermarks:** object-level security classification drives automatic watermark application (CONFIDENTIAL, RESTRICTED, etc.).

**Routes:** `WatermarkService` used by `MediaDerivativeService` (on derivative creation) and `ProcessScanFile` (on upload). Settings UI in `ahg-settings/resources/views/security/watermark-settings.blade.php` and `ahg-settings/resources/views/iiif-group-settings.blade.php`.

---

## 11. ImageMagick

| Property | Value |
|---|---|
| Binary | `command -v convert` (on $PATH) |
| Used in | `MediaDerivativeService` (PTIF pyramid generation), `WatermarkService` (composite) |

---

## 12. MediaInfo

| Property | Value |
|---|---|
| Binary | Checked via `MediaProcessingController::checkTool()` |
| Used in | Media processing dashboard (`index.blade.php`) — availability status display |

---

## 13. Spreadsheet Processing

### 13.1 PhpSpreadsheet

| Property | Value |
|---|---|
| Class | `\PhpOffice\PhpSpreadsheet\IOFactory`, `\PhpOffice\PhpSpreadsheet\Spreadsheet` |
| Used in | `ResearchStudioService::generateSpreadsheet()` (XLSX output for AI artefacts), `FilePlanImportService` (file plan CSV/XLSX import), `DataMigrationService` (spreadsheet preview in data migration), `AiController::bulkAnnotate()` (HTR spreadsheet integration) |

**Audio artefact spreadsheet:** `ResearchStudioService` builds XLSX locally with PhpSpreadsheet. LLM returns structured JSON (sheet name, columns, rows); PhpSpreadsheet assembles the workbook. `XlsxWriter` saves to `$path` (storage disk).

---

## 14. Identity / Encryption

### 14.1 EncryptionService

| Package | File |
|---|---|
| `ahg-core` | `src/Services/EncryptionService.php` |

AES-256-CBC encryption via Laravel's `Crypt` facade. Used for PII fields:

| Table | Encrypted columns |
|---|---|
| `research_researcher` | `phone`, `id_number`, `notes` |
| `access_request` | `id_number`, `phone` |

**Verified:** `ResearchService::registerResearcher()` and `updateResearcher()` call `encrypt()` before insert/update; `ResearchController::viewResearcher()` calls `decrypt()` before rendering.

**Gap:** NAZ researcher (`naz_researcher` table: `phone`, `national_id`, `passport_number`) is NOT encrypted — no gate in `NazController`.

---

## 15. GIS / Mapping

### 15.1 Map Builder

| Package | File |
|---|---|
| `ahg-research` | `resources/views/research/map-builder.blade.php` |

Research project visualisation tool for placing archival objects on a map. Integrates with `research_map_point` table.

---

## 16. Network / Knowledge Graph

### 16.1 Network Graph

| Package | File |
|---|---|
| `ahg-research` | `ResearchController::viewProject()` — knowledge graph rendering |

Project collaboration and entity relationship visualisation.

---

## 17. IIIF AV Canvas (Partial)

| Feature | Status |
|---|---|
| IIIF Presentation 3 AV Canvas emission | Unit tested (`IiifAvCanvasEmissionTest.php`) |
| `IiifCollectionService` v3 builder | Generates `temporalCanvas` for audio/video in Presentation 3 manifests |
| Mirador AV overlay plugin | `heratio-av-overlay` — wired in Mirador config |
| Adaptive streaming (HLS) | `MediaStreamController` + `TranscodingService` produce HLS segments; served as direct file URLs |
| IIIF Content Search on AV | Supported via `IiifContentSearchService` (searches OCR text on video canvases) |

**Gap:** audio/video served as direct file URLs in IIIF manifests rather than as full IIIF TemporalCanvas resources. The `IiifAvCanvasEmissionTest` confirms the builder is tested but the test file itself was not read in this audit.

---

## 18. Preservation

### 18.1 PRONOM Signature Database

| Package | File |
|---|---|
| `ahg-preservation` | `src/Services/PronomIdentificationService.php` |

In-memory PRONOM-style file format identification. Maps: file extension, MIME type, magic bytes (hex), PUID, version, risk rating, preservation suitability. Covers ~70 formats including: JPEG, TIFF, JP2, PDF/A, Office Open XML, OpenDocument, MP3, MP4, FLAC, WAV, Shapefile, GeoJSON.

### 18.2 PreservationService

| Package | File |
|---|---|
| `ahg-preservation` | `src/Services/PreservationService.php` |

Checksums (SHA-256), format validation, fixity monitoring, risk scoring.

---

## 19. ODRL Policy Evaluation

| Package | File |
|---|---|
| `ahg-research` | `src/Services/OdrPolicyService.php`, `OdrPolicyEvaluationService.php` |

ODRL (Open Digital Rights Language) policy management for researcher access decisions. Evaluates access requests against policy rules. Logs to `research_access_decision` + `research_odrl_policy_evaluation`.

---

## 20. Fuseki / SPARQL

| Property | Value |
|---|---|
| Service | `SparqlUpdateService` (`ahg-ric`) |
| Used in | Provenance AI (`ahg-provenance-ai`), RiC-O entity capture |
| Endpoint | Jena Fuseki (separate service) |
| Query language | SPARQL 1.1 |

**Provenance AI:** `InferenceService`, `OverrideService` in `ahg-provenance-ai`; `FusekiReplayCommand` for replaying provenance events.

---

## 21. System Prerequisites Summary

| Tool | Purpose | Confirmed installed |
|---|---|---|
| PHP 8.3 | Application runtime | Yes |
| MySQL 8 | Database | Yes |
| nginx | Web server | Yes |
| PHP exif extension | EXIF metadata | Yes |
| ImageMagick (`convert`, `composite`) | Image processing, watermarking, PTIF generation | Yes |
| ffmpeg | A/V derivative generation, transcoding | Yes (checked in service) |
| ffprobe | A/V metadata extraction | Yes (checked in service) |
| exiftool | Comprehensive cross-format metadata | Yes (v12.76 confirmed) |
| Tesseract | OCR engine | Yes (checked in service) |
| tesseract-ocr-eng, tesseract-ocr-afr | English + Afrikaans language packs | Yes (confirmed) |
| Whisper | Audio transcription | Binary checked; pipeline partially wired |
| PhpSpreadsheet | Spreadsheet read/write | Composer dependency |
| Elasticsearch | Full-text search | Checked by service |
| Qdrant | Vector database | Checked by service |
| Ollama | LLM + embedding server | Configured in settings |
| Cantaloupe | IIIF image server | `http://127.0.0.1:8182` (hardcoded; operator-configurable) |
| Jena Fuseki | RDF triple store (RiC-O) | Separate service |
| MediaInfo | Media metadata display | Yes (checked) |

---

## 22. Functionality Not Yet Implemented (Gaps)

| Gap | Severity | Reference |
|---|---|---|
| NAZ researcher PII not encrypted | High | `NazController` — phone, national_id, passport_number stored plaintext |
| WaveSurfer.js waveform (setting + HTML exist; library not loaded) | Medium | `media_show_waveform` toggle; waveform area placeholder; WaveSurfer JS not in page |
| Plyr/Video.js JS/CSS bundling (wrapper comments exist; actual `<script>` tags not confirmed) | Medium | `master.blade.php` comments reference bundling; actual enqueue not verified in audit |
| Whisper transcription pipeline (binary check + invocation; full job queue not confirmed) | Medium | `MediaController` has whisper invocation; queued job not found |
| IIIF AV TemporalCanvas (tested; full manifest emission not confirmed end-to-end) | Medium | `IiifAvCanvasEmissionTest.php` exists; AV manifests not confirmed served |
| Scheduled batch job for researcher expiration notifications | Low | No cron/artisan command found |
| ORCID scheduled sync job | Low | Routes exist; scheduler not found |
| Scheduled batch job for A/V derivative re-generation | Low | `MediaDerivativeService::generateForMaster()` called from scan job; no bulk scheduler |
| Scheduled batch job for metadata re-extraction | Low | `batchExtract()` exists; no scheduler |
| XMP full XML DOM parsing (regex-based substring only) | Low | `MetadataExtractionService` — only `DateTimeOriginal` via preg_match |
| IPTC extraction default-off | Medium | `meta_extract_iptc` toggle; off by default |

---

*End of inventory. Last updated: audit of `v1.104.6` (commit `9534a745`).*
