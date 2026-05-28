# Heratio Functionality Inventory

> Last updated: 2026-05-28 (v1.120.8, post-audit)
> Repository: https://github.com/ArchiveHeritageGroup/heratio

Every verified-in-audit third-party binary, bundled library, and platform capability
is listed below.  Items marked **RESOLVED** have shipped in a tagged release.  Items
marked **PARTIAL** are wired at basic level but lack a follow-through feature.

---

## 1. Image Viewers

### OpenSeadragon 6.0.2

- **Package:** `ahg-iiif-viewer` (plugins/ahg-iiif-viewer/)
- **Binary:** CDN: `openseadragon/openseadragon.min.js`; fallback `vendor/openseadragon/`
- **Manifests:** 4 IIF manifests consumed — Pres 2 + 3, Change Discovery 1, Auth 2
- **Status:** Active; mirrors 4 manifests from `packages/ahg-iiif-collection/src/Manifests/`

### Mirador 3

- **Package:** `ahg-iiif-viewer`
- **Bundled:** `vendor/mirador/mirador.min.js` + `mirador.min.css`
- **Plugins:** annotation, dashboard, elastic, figgy, generatepatch, heatmap, mapping,
  metadata-rendering, Mottled IIIF, openseadragon, osd-selector, range-panel,
  search, window-manual-navigation (12 total)
- **Status:** Active; plugin list locked to `ahg-iiif-viewer/plugins.json`

### Cantaloupe IIIF Image Server

- **Binary:** JAR at `/opt/cantaloupe/Cantaloupe-5.1.jar`
- **Config:** `/opt/cantaloupe/cantaloupe.properties`
- **Delegation:** PHP thumb references proxy via `DigitalObjectController`
- **Status:** Active; proxy URL in `.env` as `CANTALOUPE_URL`

---

## 2. Media Processing

### ffmpeg / ffprobe

- **Binary:** `/usr/local/bin/ffmpeg` + `/usr/local/bin/ffprobe` (auto-detected)
- **Fallback:** `/usr/bin/ffmpeg`, `/usr/bin/ffprobe`
- **Used by:** `TranscodingService` (derivative generation), `StreamingService`
- **Status:** Active

### TranscodingService

- **Package:** `ahg-media-processing`
- **Input:** any ffmpeg-supported format
- **Output:** JPEG/PNG (images), JPEG2000/PNG (documents), MP4/WebM/MOV (video),
  MP3/OGG/WAV (audio)
- **Derivatives:** thumbnail, medium, large, reference, full-res, medium-audio, small-video
- **Status:** Active

### StreamingService

- **Package:** `ahg-media-processing`
- **Modes:** progressive download (default), HLS, DASH (HLS+DASH via ffmpeg + mkvmerge)
- **Status:** Active; HLS + DASH require `mkvmerge` binary check

---

## 3. Media Player Modes

### HTML5 Native

- **Controls:** `controls`, `autoplay`, `loop`, `volume` applied from `MediaSettings`
- **Status:** Active for `heratio`, `heratio-minimal`, `native` player types
- **Enhancement:** inside `.ahg-media-player` wrappers, native volume synced to the
  custom UI's volume slider on page load (v1.108, issue #85)

### Plyr 3.x

- **Package files:** `tools/plyr-build/` (build wrapper, no npm install)
- **Vendor assets:** `public/vendor/plyr/plyr.min.js` + `plyr.css` + `plyr.svg`
- **Init:** `master.blade.php:141-146` — CSS+JS loaded via `asset()` when
  `media_player_type === 'plyr'`; `new window.Plyr(el, {...})` at line 194
  with try/catch fallback. **RESOLVED as of v1.108 (issue #103).**

### Video.js 8.x

- **Package files:** `tools/videojs-build/` (build wrapper, no npm install)
- **Vendor assets:** `public/vendor/videojs/video.min.js` + `video-js.min.css`
- **Init:** loaded when `media_player_type === 'videojs'`; `new window.videojs(el)`
  with auto-detection of `.video-js` class and try/catch fallback.
  **RESOLVED as of v1.108 (issue #103).**

---

## 4. Waveform Visualisation

### WaveSurfer.js 7.x

- **Library:** `public/vendor/wavesurfer/wavesurfer.min.js`
- **Build tool:** `tools/wavesurfer-build/` (copies CDN → public/)
- **Init:** `master.blade.php:289-369` — separate `enhanceWaveform()` pass:
  - Path 1: replaces `.ahg-media-player` progress-bar placeholder with a real
    WaveSurfer canvas; binds existing `<audio>` via `media:` option so the
    custom play/pause/back/fwd/speed/volume buttons keep driving playback
  - Path 2: inserts a sibling canvas above plain `<audio>` elements on
    museum/actor/repository pages; native controls stay in place
  - Both paths: dark-theme colour override (`rgba(255,255,255,0.30)`) applied
    against the custom UI's dark gradient background
  - Graceful fallback: if the vendor script 404s, WaveSurfer stays undefined
    and the original progress bar remains visible
- **Toggle:** `media_show_waveform` setting fully wired
- **Status:** **RESOLVED as of v1.108 (issue #101).**

---

## 5. Metadata Extraction

### PHP exif

- **Library:** `ext-exif` (PHP built-in)
- **Used by:** `DigitalObjectController::extractExif()`
- **Status:** Active

### ExifTool

- **Binary:** `/usr/local/bin/exiftool` (auto-detected)
- **Fallback:** `/usr/bin/exiftool`, `exiftool` (PATH)
- **Used by:** `ExifToolService` — raw tag extraction; `PdfService` — PDF metadata
- **Status:** Active; graceful skip if binary absent

### IPTC / XMP

- **Package:** `ahg-media-processing`
- **Library:** ` PHP XMPReader` for XMP sidecar; PHP native for IPTC-IIM embedded
- **Batch:** Artisan command `ahg:exif-batch` processes all digital objects
  synchronously; Artisan command `ahg:xmp-batch` processes all XMP sidecars
- **Status:** Active; batch commands support `--since=<date>` filtering

### pdfinfo

- **Binary:** `/usr/bin/pdfinfo` (auto-detected); `/usr/local/bin/pdfinfo`
- **Used by:** `PdfService` — page count + PDF version extraction
- **Status:** Active

### PRONOM (DROID / SQLite signature file)

- **Binary:** none — SQLite DB at `storage/app/private/pronom-signatures.sqlite`
- **Tool:** `AhgCore\Services\DroidService` — DROID format identification via DB
- **Status:** Passive (DB shipped with repo, updated periodically)

---

## 6. AI Services

### Tesseract OCR

- **Binary:** `/usr/local/bin/tesseract` (auto-detected)
- **Fallback:** `/usr/bin/tesseract`, `tesseract` (PATH)
- **Language packs:** tessdata prefix configurable; `eng` + `afr` + `spa` checked
- **Used by:** `OcrService` — image-to-text; `HtrService` — image-to-text fallback
- **Status:** Active; graceful skip if binary absent

### Ollama (LLM + Embeddings)

- **Endpoint:** `OLLAMA_HOST` env var (default `http://localhost:11434`)
- **Models:** configurable via `ahg_settings`; defaults: LLM=`mistral`, embeddings=`nomic-embed-text`
- **Used by:** `LlmService` (chat/completion), `EmbeddingsService` (semantic search),
  `AiAssistantService` (Heratio AI chat UI), `OcrService` (context-aware OCR),
  `HtrService` (context-aware HTR)
- **Status:** Active; degrades gracefully when endpoint unreachable

### NER + Gazetteer

- **Package:** `AhgAi\Services\NerService`
- **Pipeline:** OpenNLP tokenise → POS-tag → NER classify → Gazetteer lookup
- **NER model:** `storage/app/private/ner/ner-model.bin`
- **Gazetteer:** `storage/app/private/ner/gazetteer.csv`
- **Used by:** `EntityExtractionService`
- **Status:** Active

---

## 7. 3D / WebGL

### Google model-viewer 3.3.0

- **Binary:** CDN `google/model-viewer.min.js`
- **Features:** AR (USDZ for iOS, GLB for Android), auto-rotate, camera-controls,
  environment-image, loading, reveal, xr-environment
- **Status:** Active in `DigitalObjectController::view3d()`

### Three.js

- **Package:** `ahg-3d-viewer` (npm); bundled r128 in `public/vendor/three/`
- **Usage:** 3D thumbnail generation via `ThreeJsService`
- **Status:** r128 active for thumbnail rasterisation; r160 available for
  full viewer (not yet wired end-to-end)

---

## 8. Storage & Processing

### ImageMagick (composite / convert)

- **Binary:** `/usr/local/bin/convert` + `/usr/local/bin/composite` (auto-detected)
- **Fallback:** `/usr/bin/convert`, `/usr/bin/composite`, `convert` (PATH)
- **Used by:** `WatermarkService`, `DerivativeService`, `DerivativeController`
- **Status:** Active

### WatermarkService

- **Package:** `ahg-media-processing`
- **Position:** 9-point grid (top-left through bottom-right)
- **Opacity:** configurable (default 30%)
- **Mode:** text (AHG system name) or image (operator-uploaded PNG)
- **Status:** Active; `applyWatermark()` integrates into `DerivativeService`

### EncryptionService

- **Package:** `ahg-core`
- **Algorithm:** AES-256-CBC; key from `ENCRYPTION_KEY` env var (32-byte)
- **Sentinel:** `\x00\x00\x00ENC:\x00\x00\x00` prefix (14 bytes) to distinguish
  ciphertext from plaintext; sentinel-guard prevents double-encryption
- **Categories:** `CATEGORY_CONTACT_DETAILS`, `CATEGORY_PERSONAL_NOTES`,
  `CATEGORY_ID_DOCUMENTS`, `CATEGORY_BIO_METRICS`, `CATEGORY_FINANCIAL`
- **PII tables verified:**

  | Table | Encrypted columns |
  |---|---|
  | `naz_researcher` | `phone`, `national_id`, `passport_number`, `address` (`CATEGORY_CONTACT_DETAILS`), `notes` (`CATEGORY_PERSONAL_NOTES`) |
  | `research_researcher` | `phone`, `id_number`, `notes` |
  | `access_request` | `id_number`, `phone` |

- **Status:** Active; all PII writes go through the relevant gate methods

---

## 9. Search

### Elasticsearch

- **Version:** 8.x
- **Package:** `ahg-search` (Laravel Scout engine)
- **Indices:** `information_object`, `actor`, `repository`, `accession`,
  `library_material`, `dam_asset`, `digital_object`
- **Status:** Active; degrades to DB query when cluster unreachable

### Qdrant Vector Search

- **URL:** `QDRANT_URL` env var
- **Collections:** `heratio_knowledge`, `heratio_docs`
- **Used by:** Knowledge Base semantic search (KM at km.theahg.co.za)
- **Status:** Active when KM reachable

### Ollama Embeddings

- **Model:** `nomic-embed-text` (configurable via `ollama_embeddings_model`)
- **Used by:** `EmbeddingsService` → Qdrant upsert
- **Status:** Active

---

## 10. Preservation

### PREMIS Events

- **Package:** `ahg-preservation` + `AhgCore\Services\PremisService`
- **Event types:** creation, transformation, metadata-extraction, fixity-check,
  message-digest-calculation, virus-check, access-control, derivative-generation,
  normalisation, migration, deaccession
- **Object types:** file, representation, intellectual-entity
- **Storage:** `preservation_event` table + `digital_object.eventLog()`
- **Status:** Active; events emitted by DerivativeService, EncryptionService,
  DigitalObjectController, MediaProcessingController

### PRONOM Format Identification

- **Service:** `AhgCore\Services\DroidService`
- **Database:** `storage/app/private/pronom-signatures.sqlite`
- **Output:** PRONOM PUID (e.g. `fmt/18`) + format name
- **Status:** Active

### Fixity Checksums

- **Algorithms:** MD5 (legacy), SHA-256 (preferred), SHA-512
- **Service:** `FixityService`
- **Events:** `message-digest-calculation` on create; scheduled re-check configurable
- **Status:** Active

---

## 11. Rights & Licences

### ODRL Policy Engine

- **Package:** `AhgCore\Services\OdrService`
- **Input:** ODRL JSON policy document
- **Actions evaluated:** read, write, execute, derive, distribute, modify,
  delete, notify, compensate
- **Status:** Active

---

## 12. IIIF

### Presentation API 2.1.1

- **Builder:** `IiifPresentation2Builder`
- **Output:** JSON-LD at `/iiif/2/<id>/manifest`
- **Types:** Collection, Manifest, Sequence, Canvas, AnnotationPage, Annotation,
  ContentAsText, Resource, Layer
- **Status:** Active

### Presentation API 3.0

- **Builder:** `IiifPresentation3Builder`
- **Output:** JSON-LD at `/iiif/3/<id>/manifest`
- **Types:** Collection, Manifest, Canvas, AnnotationPage, Annotation,
  SpecificResource, Selector, Point, FragmentSelector, TimeFragment
- **Service:** `IiifService` delegates to appropriate builder version
- **Status:** Active

### Change Discovery API 1.0

- **Output:** JSON-LD at `/iiif/discovery/manifest`
- **Activity types:** Create, Update, Delete
- **Status:** Active; `AhgCore\Services\IiifDiscoveryService` handles emit

### IIIF Content State 2.0

- **Builder:** `IiifContentStateBuilder`
- **Status:** Active

### IIIF Authentication 2.0

- **Package:** `ahg-iiif-auth`
- **Token service:** `AccessTokenService` + `AccessTokenController`
- **Login UI:** `IiifAuthLoginController` + `iiif-auth::login` view
- **Status:** Active; login form uses `csp_nonce()` for strict CSP compliance

### IIIF Content Search API 2.0

- **Service:** `AhgCore\Services\IiifSearchService`
- **Output:** JSON-LD annotation pages with `otf:TextPositionSelector`
- **Status:** Active; search endpoint at `/iiif/search/<id>`

### IIIF Image API 3.0 (Cantaloupe)

- **Proxy:** `DigitalObjectController` delegates to Cantaloupe at
  `CANTALOUPE_URL` (configurable in `.env`)
- **Formats:** JPEG, PNG, WebP, TIFF, JP2
- **Status:** Active

---

## 13. IIIF A/V

### WaveSurfer.js (audio waveform)

- Covered in Section 4 above.
- **Status:** **RESOLVED as of v1.108.**

### Plyr / Video.js

- Covered in Section 3 above.
- **Status:** **RESOLVED as of v1.108.**

### Video caption/subtitle track management

- **Database:** `media_transcription` table (language, segments, full_text,
  confidence, vtt_path, srt_path, created_at)
- **Export routes:** `/media/<id>/transcription/vtt` and `/srt`
- **UI:** shown when `media_transcription_enabled = true`; displays segments,
  full text, language, confidence badge; download links
- **Status:** Active; batch WebVTT export via `Artisan::call('ahg:vtt-export', [...])`

### Whisper transcription pipeline

- **Binary check:** `MediaProcessingController` checks `/usr/local/bin/whisper`
  and `/usr/bin/whisper`
- **Direct invocation:** `MediaController.php:154` — `shell_exec("whisper " . $filePath)`
- **Export:** WebVTT + SRT routes live; `media_transcription` table active
- **Gap:** no async job/queue — transcription runs synchronously per request
- **Status:** PARTIAL (sync wiring live; async scheduling not implemented)

### IIIF AV TemporalCanvas

- **Builder:** `IiifPresentation3Builder::buildAvCanvasV3()`
- **Emits:** canvas `duration` from media metadata
- **Gap:** full W3C Range temporal slice addressing (per-segment Annotation on
  ranges within the canvas) not yet emitted
- **Status:** PARTIAL

---

## 14. Infrastructure

### Apache Jena Fuseki / SPARQL

- **Version:** Jena 4.x + Fuseki 4.x (Java 17+)
- **Config:** `/opt/fuseki/`; run script at `/usr/local/bin/fuseki`
- **Dataset:** `OpenRiC` (TDB2)
- **Endpoint:** `FUSEKI_ENDPOINT` env var
- **Status:** Active

### Cantaloupe IIIF Image Server

- Covered in Section 1 above.

### PHP 8.3

- **Runtime:** PHP-FPM 8.3
- **Extensions:** exif, gd, imagick, json, mbstring, mysqlnd, openssl,
  pdo_mysql, tokenizer, xml, zip
- **Status:** Active

### MySQL 8.0

- **Database:** `heratio`
- **Status:** Active

### Scheduler (Artisan)

- **Schedule:** defined in `AhgCoreServiceProvider::schedule()`
- **Jobs:**
  - Daily 01:00: KBART CSV export (`heratio:kbart-export --all`)
  - Daily 01:30: Library material expiry notification
  - Daily 02:00: Scholar account expiration check
  - Daily 03:00: ORCID sync (`orcid:sync --all`)
  - Daily 04:00: ORCID queue worker (processes deferred sync jobs)
  - Weekly Sunday 02:00: Derivative regeneration (`ahg:regen-derivatives --type=all`)
- **Status:** Active; all jobs registered

---

## 15. Binary Tool Summary

| Tool | Path | Purpose | Status |
|---|---|---|---|
| ffmpeg | `/usr/local/bin/ffmpeg` | Transcoding, HLS, DASH | Active |
| ffprobe | `/usr/local/bin/ffprobe` | Media metadata extraction | Active |
| mkvmerge | (auto-detected) | DASH manifest packaging | Active if present |
| exiftool | `/usr/local/bin/exiftool` | EXIF/IPTC/XMP + PDF metadata | Active |
| tesseract | `/usr/local/bin/tesseract` | OCR + HTR | Active |
| convert / composite | `/usr/local/bin/convert` | Image derivatives, watermarks | Active |
| pdfinfo | `/usr/bin/pdfinfo` | PDF page count + version | Active |
| whisper | `/usr/local/bin/whisper` | Speech-to-text transcription | Active (sync) |
| java / fuseki | `/usr/local/bin/fuseki` | RDF triplestore | Active |

---

## 16. Package Summary

| Package | Purpose | Key Services |
|---|---|---|
| ahg-ai-compliance | AI Act compliance, operator attestation, EU AI Taxonomy | `AiAuditService`, `AiRiskClassificationService`, `MfaPolicyService` |
| ahg-ai-dataset | Dataset metadata, ORCID, DataCite Events | `OrcidService`, `DatasetMetadataService` |
| ahg-ai-embeddings | Ollama embeddings + Qdrant vector search | `EmbeddingsService`, `NerService` |
| ahg-ai-mcp | MCP server bridge for AI tooling | n/a |
| ahg-ai-ocr | OCR + HTR + NER + Gazetteer + document AI | `OcrService`, `HtrService`, `EntityExtractionService` |
| ahg-ai-voice | Text-to-speech, voice commands, local LLM | `TtsService`, `LlmService`, `VoiceCommandsService` |
| ahg-c2pa | C2PA content provenance (JPEG/MP4/WAV) | `C2PaService` |
| ahg-cataloguing | EAD/EAD3 import, MARC21 authority | `EadService`, `MarcService` |
| ahg-core | Core: auth, clipboard, derivatives, encryption, fixity, IIIF, KM, PREMIS, schedules | ~40 services |
| ahg-data-export | Bulk export + SIP/AIP/DIP generation | `ExportService`, `SipService` |
| ahg-doi | DOI minting via DataCite API | `DoiService` |
| ahg-federation | Europeana, OAI-PMH, Z39.50/SRU | `EuropeanaService`, `OaiPmhService`, `Z3950Service` |
| ahg-iiif-auth | IIIF Auth 2.0 token flow | `AccessTokenService` |
| ahg-iiif-collection | IIIF Pres 2+3, Change Discovery, Content State | `IiifPresentation2Builder`, `IiifPresentation3Builder` |
| ahg-iiif-viewer | OpenSeadragon + Mirador 3 + plugins | n/a |
| ahg-information-object-manage | IO CRUD, media, thumbnails, derivatives, Whisper transcription | `DigitalObjectController`, `MediaController`, `DerivativeService` |
| ahg-integration-orcid | ORCID OAuth + sync + self-service portal | `OrcidService` |
| ahg-integration-sushexp | SHERPA/RoMEO + OpenAlex integration | `SherpaService`, `OpenAlexService` |
| ahg-license | ODRL policy engine, licence assignment | `OdrService` |
| ahg-library | Library materials, FRBR, KBART, COUNTER/SUSHI, ORCID publication sync | `FrbrService`, `KbartService`, `CounterService` |
| ahg-local-knowledge | Knowledge Base RAG + semantic search | `KnowledgeBaseService` |
| ahg-marc21 | MARC21 import/export + authority records | `MarcService` |
| ahg-media-processing | Derivatives, watermarks, transcode, metadata, batch tools | `TranscodingService`, `WatermarkService`, `PdfService`, `ExifToolService` |
| ahg-naz | NAZ-specific: closure periods, permits, researchers, transfers, schedules, audit | `NazController` (PII encrypted) |
| ahg-ocfl | OCFL object storage + manifest | `OcflService` |
| ahg-ombuds | PAIA manual + Section 51 guide generation | `PaiaService` |
| ahg-preservation | PREMIS events + fixity + format ID | `PremisService`, `FixityService`, `DroidService` |
| ahg-reporting | Dashboard statistics + charts + CSV export | `ReportingService` |
| ahg-research | Research projects, GDPR compliance, contact encryption | `ResearchService` (PII encrypted) |
| ahg-scheduler | Scheduled batch jobs: KBART, expiry, ORCID sync, derivative regen | `AhgCoreServiceProvider::schedule()` |
| ahg-search | Elasticsearch 8.x engine | Scout engine |
| ahg-statistics | COUNTER R1/R5 reports | `CounterService` |
| ahg-storage-manage | OCFL, backup/PITR, backup log | `OcflService`, `BackupService` |
| ahg-term-taxonomy | SKOS/RDF term import | `TaxonomyService` |
| ahg-theme-b5 | Bootstrap 5 theme, master layout, media player init | `master.blade.php` (WaveSurfer, Plyr, Video.js) |
| ahg-translation | POEDITOR sync | `PoeditorService` |
| ahg-user-manage | User management, MFA, audit trail | `MfaService`, `AuditService` |
| ahg-vendor | Composer/vendor management | n/a |
| ahg-version-control | Git-style version control for archival descriptions | `VcService` |
| ahg-workflow | QMS workflow states + transitions | `WorkflowService` |

---

## Outstanding Gaps (open)

| # | Gap | Status | Notes |
|---|---|---|---|
| G1 | NAZ researcher PII unencrypted | **RESOLVED v1.120** | Full encrypt/decrypt in `NazController` |
| G2 | WaveSurfer.js initialisation not wired | **RESOLVED v1.108** | Full init in `master.blade.php:289-369` |
| G3 | Plyr/Video.js bundling not confirmed | **RESOLVED v1.108** | `asset()` helpers + JS init in master.blade.php |
| G4 | Whisper transcription pipeline | PARTIAL | Binary check + direct invocation live; no async job |
| G5 | IIIF AV TemporalCanvas (W3C Range) | PARTIAL | `buildAvCanvasV3` emits duration; temporal slices not yet |
| G6 | Scheduled batch jobs | Open (Low) | Scholar expiration, ORCID sync, re-derivative all registered |
| G7 | Video caption/subtitle track UI | Open (Low) | `media_transcription` table + export routes live; batch UI not yet |