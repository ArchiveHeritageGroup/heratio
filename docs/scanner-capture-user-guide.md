# Scanner / Capture — User Guide

Heratio can ingest scanned and captured material (TIFF, JPEG, JP2, PDF and
other digital-object formats) directly from a **watched folder** — no manual
upload, no per-file clicking in the web UI. Drop a file into the right
directory on your scanning workstation, and Heratio creates the information
object, digital object, and derivatives automatically.

This guide covers the first-release feature set (Mode A drop-folder, Style 1
"path-as-destination"). The sidecar-XML layout, Scan API, Capture desktop
helper, PREMIS emission, format identification, and library/gallery/museum
sector profiles are on the roadmap — see §"What's coming next" below.

## At a glance

Three ways to get scans into Heratio:

| Mode | You do | Best for |
|---|---|---|
| **Watched folder — path layout** | Drop files into `<folder>/<parent-slug>/<identifier>/page_001.tiff` | Simple; scanner station saves straight to a shared folder |
| **Watched folder — flat sidecar** | Drop `ARC-001.tiff` + `ARC-001.xml` in the same folder | Rich metadata (ISAD(G), MARC, LIDO, Spectrum, etc.) without encoding everything in paths |
| **Scan API** | POST to `/api/v2/scan/*` from the scanner application or a wrapper script | Direct integration (VueScan, NAPS2, custom software) |

| You configure | Heratio does |
|---|---|
| Watched folder under `Admin → Scan → Watched folders`, or create an API session via `POST /api/v2/scan/sessions` | Binds a long-lived ingest session with your chosen sector, standard, and processing options |
| Drop the file (or POST it) | Detects arrival, hashes for dedupe, virus-scans, creates IO + DO, extracts IPTC/EXIF/XMP into the DAM metadata tables, generates thumbnail + reference derivatives, indexes to Elasticsearch |
| Open `Admin → Scan` | Live dashboard with per-folder stats, inbox, retry/discard controls |

## Key concepts

### Watched folders
A watched folder is a directory on disk that Heratio polls for new files. Each
folder is backed by a **persistent ingest session** — so all the processing
options you already know from the Ingest wizard (sector, archival standard,
derivative generation, OCR, virus scan, SIP/AIP/DIP packaging) apply to
every file that arrives.

### Destination routing: path layout

The **path** of a file inside the watched folder decides where the record
lands in Heratio's hierarchy:

```
<watched-folder-path>/
└── <parent-slug>/               ← existing information object's URL slug
    └── <identifier>/            ← new IO's identifier (directory name)
        ├── page_001.tiff
        ├── page_002.tiff
        └── page_003.tiff
```

- `<parent-slug>` must match the URL slug of an existing archival description,
  collection, fonds, series, file, or any other information object already in
  Heratio.
- `<identifier>` becomes the new IO's identifier. If an IO with that identifier
  already exists under the same parent, additional pages are attached to the
  same record as extra digital objects (configurable via the `merge` rule).
- Files directly inside the parent slug directory (without an inner
  identifier sub-directory) are treated as single-file items with the
  filename stem as their identifier.

### Destination routing: flat-sidecar layout

For richer per-file metadata, pair each scan with an XML sidecar:

```
<watched-folder-path>/
├── ARC-2026-0001.tiff        ← scan
├── ARC-2026-0001.xml         ← heratioScan sidecar describing the scan
├── ARC-2026-0001_p2.tiff     ← additional page, same base stem, same sidecar
└── ARC-2026-0001_p3.tiff
```

The sidecar uses the `heratioScan` schema:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<heratioScan xmlns="https://heratio.io/scan/v1">
  <sector>archive</sector>
  <standard>isadg</standard>
  <parentSlug>fonds-johan-smith-papers</parentSlug>
  <identifier>ARC-2026-0001</identifier>
  <title>Letter from Smith to editor, 1923</title>
  <levelOfDescription>item</levelOfDescription>
  <dates>
    <date type="creation" start="1923-07-14" end="1923-07-14"/>
  </dates>
  <publicationStatus>draft</publicationStatus>
  <rightsStatement uri="http://rightsstatements.org/vocab/InC/1.0/"/>
  <ccLicense>cc-by-nc-4.0</ccLicense>
  <digitalObject>
    <usage>master</usage>
    <makeDerivatives>true</makeDerivatives>
    <ocr>auto</ocr>
  </digitalObject>
  <archiveProfile>
    <scopeAndContent>One-page handwritten letter...</scopeAndContent>
    <extentAndMedium>1 p. : ink on paper ; 21 x 28 cm</extentAndMedium>
    <creators>
      <creator vocab="ulan">Smith, Johan</creator>
    </creators>
  </archiveProfile>
  <merge>add-sequence</merge>
</heratioScan>
```

One `<heratioScan>` per IO. Per sector you include exactly one profile
(archiveProfile / libraryProfile / galleryProfile / museumProfile).

### Sector-aware routing

When the sidecar sets `<sector>` (or the folder's session sector kicks in),
the pipeline writes sector-specific rows **in addition to** the core IO:

| Sector | Also writes to | Notes |
|---|---|---|
| `archive` | `information_object`, `information_object_i18n`, `slug`, `status` | Core only — nothing extra |
| `library` | `library_item`, `library_item_creator`, `library_item_subject`, `library_copy`, `library_creator`, `library_subject` | ISBN/ISSN/edition/publisher/pagination; creators + subjects linked; `holdings/copy` entries populate `library_copy` |
| `gallery` | `gallery_artwork`, `gallery_artist`, `gallery_valuation`, `museum_metadata`, `event` (type=Creation) | Artists auto-created as actors when `output_create_authorities=1`; descriptive fields (medium, techniques, dimensions, movement, provenance) land in `museum_metadata` (shared with museum sector) |
| `museum` | `museum_object`, `museum_metadata`, `event`, plus optionally `spectrum_object_entry` + `spectrum_acquisition` when `spectrum_auto_enter=1` | Object number, accession number, classification, materials, cultural affiliation, measurements, current location, Spectrum workflow entry |

### Authority auto-creation

When a sidecar names a creator/artist that doesn't already exist, Heratio
creates a **draft actor** record (`description_status_id = 232 — Draft`)
so curators can later enrich and promote it to Final. Control this per
watched folder (or per API session):

- `output_create_authorities=1` (default): auto-create missing creators
- `output_create_authorities=0`: skip the creator link and record a
  warning in the Inbox — safer for sites where authority quality matters

The draft actor has the sidecar's `uri` attribute stored in
`actor.description_identifier`, so you can later reconcile against Getty
ULAN / LCNAF / ORCID.

### Spectrum auto-entry (museum sector)

Off by default — museum scans create `museum_object` + `museum_metadata`
only. Turn on `spectrum_auto_enter=1` at folder-config time to
automatically create a `spectrum_object_entry` (workflow state:
*received*) and a `spectrum_acquisition` row when the sidecar includes a
`<spectrum>` block. Suitable for institutions that run the full Spectrum
5.1 workflow.

### Controlled vocabularies

Sidecar values carrying `vocab=` / `uri=` attributes (AAT, ULAN, TGN,
LCSH, LCNAF, Iconclass, Nomenclature 4, ITIS, GBIF) are preserved on the
matching columns (`library_item_subject.uri`, `library_item_creator.authority_uri`)
but **term resolution is lookup-only** in this release — if the term
doesn't exist in Heratio's taxonomy, the raw label is written with a
warning surfaced in the Inbox detail view. Full auto-creation of
controlled-vocab terms is planned for P7.

### Quiet period
Scanner software often writes a file in chunks. Heratio waits until a file
has been idle for the **quiet period** (default 10 seconds, configurable per
folder) before ingesting it, so half-written files don't get processed.

### Deduplication
Every ingested file is hashed with SHA-256. If the exact same bytes are
dropped again — accidentally, or by a re-run — Heratio detects the duplicate
and doesn't create a second digital object.

### Auto-commit
Watched folders default to `auto_commit=1`. Files move through the pipeline
without anyone having to click through the Ingest wizard's validate/preview
steps. If you want a review step, turn `auto_commit` off; files park in the
Scan Inbox awaiting a human approval.

## Setting up a watched folder

1. **Create the directory on disk** and make sure it's writable by your
   scanning workstation and readable by the Heratio server:
   ```
   mkdir -p /mnt/nas/heratio/scan_inbox/<your-code>
   chown -R <heratio-user>:<heratio-group> /mnt/nas/heratio/scan_inbox/<your-code>
   ```
2. **Admin → Scan → Watched folders → New watched folder.**
3. Fill in:
   - **Code** — short lowercase slug used in logs and the API
     (e.g. `archive-main`, `museum-accessions-2026`).
   - **Label** — human-readable name.
   - **Absolute path** — e.g. `/mnt/nas/heratio/scan_inbox/archive-main`.
   - **Layout** — keep as *Path as destination* for now.
   - **Sector** — archive / library / gallery / museum.
   - **Standard** — descriptive standard (ISAD(G), RAD, DACS, MARC21,
     MODS, LIDO, Spectrum, Darwin Core, etc.).
   - **Default parent** — optional fallback if a dropped file's path does
     not resolve to a known slug.
   - **Repository** — optional; inherited by every IO created from this
     folder.
   - **Processing** — auto-commit, thumbnails, reference image, virus scan,
     OCR.
   - **Disposition** — what to do with the original file on success (move
     to archive folder, leave in place, delete) and on failure (quarantine
     or leave in place).
4. **Save.**

## Running the watcher

The watcher polls enabled folders on an interval. You can run it in three ways:

### Foreground (one-shot, for testing)
```
php artisan ahg:scan-watch --once
# or just one folder
php artisan ahg:scan-watch --once --folder=archive-main
```

### Background (production)
Under supervisord or systemd:
```
php artisan ahg:scan-watch --interval=30
```
Keep one worker per host. `--interval` is seconds between passes.

### From the UI
The **"Run now"** button on each folder in `Admin → Scan → Watched folders`
kicks a single pass without needing shell access.

## What happens when a file arrives

```
pending → virus → format → io → do → meta → sector-route → rights
   → deriving → indexing → done
                         ↘ failed / quarantined / awaiting_rights
```

1. **Virus** — ClamAV scan (`clamscan`, honours the session's
   `process_virus_scan` flag). Aborts on infection.
2. **Format** — siegfried PUID identification, falls back to `file`.
   Obsolete formats flagged for migration planning.
3. **IO** — parent + identifier resolved from sidecar XML (highest
   priority), inline JSON metadata, path layout, or session fallback.
4. **DO** — the file is moved to Heratio's canonical uploads location
   under `/uploads/<io-id>/master_<name>.<ext>`, hashed (SHA-256), and
   linked as a master digital object. `preservation_checksum` row
   written.
5. **Meta** — ExifTool runs, writing IPTC / EXIF / XMP to
   `digital_object_metadata` + `media_metadata` + `dam_iptc_metadata`.
6. **Sector-route** — based on the sidecar sector, writes to
   `library_item` / `gallery_artwork` + `museum_metadata` /
   `museum_object`; auto-creates draft actors for new creators; enters
   Spectrum workflow when opted in.
7. **Rights** — applies rights block (statements, CC, embargo, ODRL,
   TK). Holds the file in `awaiting_rights` when a security
   classification is set but no rights were supplied.
8. **Deriving** — thumbnail + reference derivatives honour the session's
   flags. One PREMIS `creation (derivation)` event emitted per
   derivative.
9. **Indexing** — Elasticsearch upsert so the new IO is searchable.

Every observable stage emits a PREMIS event to `preservation_event`, so
the preservation record is complete at ingest time.

On **failure**, the file moves to the quarantine folder (by default
`<storage_path>/.scan_quarantine/<yyyy>/<mm>/<reason>/`), and the ingest_file
row in the Inbox retains the error message for review.

On **success**, the original is moved to the archive folder
(`<storage_path>/.scan_archived/<yyyy>/<mm>/`) — so the scanning workstation's
inbox stays clean — or left in place if you preferred that disposition.

## The Scan dashboard

`Admin → Scan` shows:

- **Status tiles** — pending / processing / done / failed / duplicate /
  quarantined counts, clickable straight into the Inbox.
- **24-hour activity** — new arrivals plus completed ingests.
- **Per-folder throughput** — each watched folder's pending/failed/done
  counts and last successful ingest timestamp.
- **Recent activity** — the 20 most-recent files with links to the resulting
  information objects.

## The Scan Inbox

`Admin → Scan → Inbox` is a filterable list of every file that has passed
through (or is currently in) the pipeline:

- Filter by status, folder, or filename.
- Click a row for the file detail page: stored path, hash, folder, session,
  resulting IO/DO, stage log, and last error.
- **Retry** a failed file after fixing whatever caused the error.
- **Discard** a file you don't want to ingest.

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| File sits in the watched folder, never ingests | Quiet period hasn't elapsed; file still being written | Increase quiet period, or wait; check file mtime |
| File fails with "Path does not match layout" | Dropped at the wrong depth | Must be `<folder>/<parent-slug>/<identifier>/file.ext` |
| File fails with "No slug found" | `<parent-slug>` doesn't match any existing IO | Check the parent IO's URL slug in Heratio; use that exactly |
| File shown as "duplicate" | Same bytes already ingested | Expected — dedupe working |
| Watcher runs but enqueues nothing | Folder disabled, or no write-permission for the Heratio user | Toggle Enabled, check filesystem permissions |
| All new files fail with DB error | Schema not installed | `php artisan ahg:scan-install` (idempotent) |

## Scan API (Mode B — scanner-application integration)

For scanner applications that can call HTTP (VueScan "After save", NAPS2
external tool, custom software), Heratio exposes a REST API under
`/api/v2/scan/*`. All endpoints require an API key with the `scan:write`
scope (create at `Admin → API Keys`).

### Endpoints

| Method | Path | Purpose |
|---|---|---|
| `GET`    | `/api/v2/scan/destinations?q=<search>&parent=<id>` | Autocomplete; returns matching IOs with id / parent_id / identifier / title / slug |
| `POST`   | `/api/v2/scan/sessions` | Create a scan session (backed by a long-lived `ingest_session`); returns `{token, upload_url, commit_url}` |
| `POST`   | `/api/v2/scan/sessions/{token}/files` | Upload one file (multipart). Optional `sidecar` part (XML) or `metadata` part (JSON) to drive the IO |
| `POST`   | `/api/v2/scan/sessions/{token}/commit` | Kick processing for pending files (only needed when `auto_commit=false`) |
| `GET`    | `/api/v2/scan/sessions/{token}` | Status: per-file ingest state, resulting IO/DO ids |
| `DELETE` | `/api/v2/scan/sessions/{token}` | Abandon — removes staged files not yet ingested; created IOs stay |

### Session defaults

`POST /sessions` body (JSON):

```json
{
  "parent_id": 631,
  "sector": "archive",
  "standard": "isadg",
  "auto_commit": true,
  "title": "Monday scanning session"
}
```

With `auto_commit: true`, each uploaded file is processed immediately. Use
`false` + an explicit `/commit` call if you want to batch-review before
anything is created.

### Uploading a file

Multipart form fields:

| Field | Purpose |
|---|---|
| `file` | The scan (required) |
| `sidecar` | Optional `heratioScan` XML — same schema as flat-sidecar layout. Takes precedence over `metadata`. |
| `metadata` | Optional flat JSON: `{"identifier":"ARC-001","title":"...","scope_and_content":"..."}`. Used when a full sidecar isn't available. |

Response: `{success: true, data: {ingest_file_id, auto_dispatched, status_url}}`.

### Wrapper scripts

Heratio ships three ready-made wrappers in
`packages/ahg-scan/tools/scanner/` that do the 3-call dance for you:

- `heratio-scan.sh` — Linux / macOS (needs `curl` + `jq`)
- `heratio-scan.ps1` — Windows PowerShell 7+
- `heratio-scan.py` — cross-platform (needs Python + `requests`)

All three read configuration from environment variables or a config file
(`~/.heratio-scan.conf` or `%USERPROFILE%\.heratio-scan.conf`):

```
HERATIO_URL=https://heratio.example.org
HERATIO_API_KEY=<your-scan-key>
HERATIO_PARENT_SLUG=fonds-johan-smith-papers
HERATIO_SECTOR=archive
HERATIO_STANDARD=isadg
```

Bash usage (VueScan "Command" field on Linux, SANE `scanadf` script):

```bash
heratio-scan.sh /path/to/scan.tiff "ARC-2026-0001" "Letter from Smith, 1923"
```

PowerShell (VueScan "After save" on Windows, NAPS2 external tool):

```powershell
.\heratio-scan.ps1 -File "C:\scans\page001.tiff" `
                   -Identifier "ARC-2026-0001" `
                   -Title "Letter from Smith, 1923" `
                   -Sidecar "C:\scans\page001.xml"
```

Python (any platform, script-friendly):

```bash
heratio-scan.py /path/to/scan.tiff --identifier ARC-2026-0001 --title "Letter from Smith, 1923"
```

## BagIt container ingest

Drop a `.zip` that contains a BagIt structure
(`bagit.txt` + `manifest-<alg>.txt` + `data/` subdir) into a watched
folder. Heratio will:

1. Detect the zip as a bag (looks for `bagit.txt` inside).
2. Extract to a temporary directory, verify every `data/` file against
   the manifest checksums (prefers `sha512` > `sha256` > `sha1` > `md5`).
3. Parse `bag-info.txt` — `External-Identifier` becomes the IO's
   identifier; `Source-Organization` / `Contact-Email` /
   `Bagging-Date` are preserved as provenance metadata.
4. Ingest every `data/` file against one IO (same identifier, multiple
   digital objects per `<merge>add-sequence</merge>`). Files retain
   their original names.
5. Move the original bag zip to `.scan_archived/<yyyy>/<mm>/` on success
   (or leave it in place, per folder disposition).

Checksum mismatches or missing manifest entries surface as warnings in
the Inbox detail view without failing other files in the same bag.

## OAIS packaging (SIP / AIP / DIP)

When a watched folder's session has any of `output_generate_sip`,
`output_generate_aip`, or `output_generate_dip` turned on, the scanner
pipeline runs a **packaging** stage after indexing that builds a BagIt
zip for each requested type.

| Type | Contents | Intended use |
|---|---|---|
| **SIP** (Submission) | Master file(s) + Dublin Core descriptive XML | What was submitted to the archive — the raw form |
| **AIP** (Archival) | Master + all derivatives + Dublin Core + PREMIS 3.0 events + fixity manifest | Long-term preservation bundle; what's stored |
| **DIP** (Dissemination) | Access derivatives only (reference + thumbnail) + Dublin Core | What's delivered to users; no master, no preservation record |

All three use BagIt 1.0 (RFC 8493): `bagit.txt` + `bag-info.txt` +
`manifest-sha256.txt` + `tagmanifest-sha256.txt` + `data/` tree.

**Where packages land.** By default under
`{heratio.storage_path}/packages/exports/<uuid>.zip`. Override per
session via the ingest wizard's configure step (`output_sip_path`,
`output_aip_path`, `output_dip_path`) or per folder by editing the
backing ingest session through the "Configure processing" deep-link.

**What gets recorded.** Each package inserts a row in
`preservation_package` (with uuid, type, status, export path, checksum,
object count, total size) plus one `preservation_package_object` per
included file (master + each derivative, with role, PUID, checksum).
A `preservation_package_event` of type `packageBuilt` marks the build.
A PREMIS `accession (SIP)` / `preservation (AIP)` / `dissemination
(DIP)` event is also written to `preservation_event` so the IO's
event chain reflects the packaging.

**Viewing packages.** `Admin → Preservation → Packages` lists them and
lets operators download the zip. Packages also appear on each IO's
preservation tab.

**Both paths now real.** The wizard's "Commit" step got its own runner
on 2026-04-24 — `IngestCommitRunner` walks `ingest_row` rows, creates
IOs, attaches files, and invokes the OAIS packager per IO when the
session has SIP/AIP/DIP flags set. See the
[Data ingestion manager guide](data-ingest-user-guide.md) for the
wizard side.

## Retry, backoff, and notifications

**Automatic retries**: the `ahg:scan-retry-failed` command runs every 5
minutes (registered in `cron_schedule`) and re-dispatches failed
files whose backoff window has elapsed. The exponential ladder is
configurable via `HERATIO_SCAN_RETRY_BACKOFF` env
(default `15,60,240,1440,4320` — minutes between retry #1 through #5).
After `HERATIO_SCAN_MAX_ATTEMPTS` (default 5), the file stays in
`failed` state and no further automatic retries happen — but an admin
can click "Retry now" manually from the Inbox.

**Quarantine UX**: files that hit `quarantined` (either on failure with
`disposition_failure=quarantine` or via bulk discard) can be restored
from the Inbox detail view. Use the **Bulk ops** toolbar on the Inbox
list for multi-file retry or discard.

**Email on final failure**: configure per watched folder in
`Admin → Scan → Watched folders → (folder) → Notifications`. When
`notify_on_failure` is on and `notify_emails` has recipients, the
pipeline sends a terse email containing the file path, error message,
and Inbox URL *only after the last retry has been exhausted* — early
failures don't spam the inbox during the backoff period.

## Capture TUI (Mode C — ad-hoc archivist capture)

`packages/ahg-scan/tools/scanner/heratio-capture.py` is an interactive
terminal UI that wraps the Scan API. Archivists can:

1. Search for a parent IO by title, identifier, or slug.
2. Pick it with a number, then specify sector + standard (inherited
   from config if preset).
3. Drop file paths one at a time (supports shell-style quoting), each
   with optional identifier / title / sidecar XML.
4. See live status with `status` inside the session.

Configure once via `~/.heratio-scan.conf`:

```
HERATIO_URL=https://heratio.example.org
HERATIO_API_KEY=<scan:write key>
HERATIO_PARENT_SLUG=fonds-smith
HERATIO_SECTOR=archive
HERATIO_STANDARD=isadg
```

Run `heratio-capture.py` with no arguments for the interactive menu, or
pass a file path + flags for one-shot use from automation scripts:

```bash
heratio-capture.py /path/to/scan.tiff \
    --parent-slug fonds-smith \
    --identifier ARC-2026-0001 \
    --title "Letter from Smith, 1923"
```

A full Tauri/Electron desktop app remains on the backlog — it would
wrap the same API surface and add drag-and-drop from Finder/Explorer.
The TUI covers the same workflow for sites that don't need
binary distribution.

## Relationship to the Ingest wizard

The scanner is not a parallel system — it's a different **entry point** into
the same ingest engine the wizard uses. A watched folder creates a
long-lived `ingest_session` with `session_kind = watched_folder`; the ingest
wizard creates a one-shot session with `session_kind = wizard`. Both feed
the same information-object and digital-object creation path, the same
derivatives, the same virus scan, the same SIP/AIP/DIP packaging — so any
improvement to Ingest automatically benefits scanning.

Use the **wizard** for:
- One-off CSV batches with column mapping
- Uploading through the web UI
- Migrating a set of legacy files once

Use a **watched folder** for:
- Continuous scanning stations
- Unattended bulk capture (photography rigs, book scanners, film scanners)
- Any workflow where the scanner application can save to a shared location

Use both together if you want — a site can have many watched folders and
still use the wizard ad-hoc.

## What's coming next

Every item below is cross-referenced against the [scanner integration
plan](scanner-integration-plan.md). **Committed** items are assigned to a
specific phase (§11 of the plan). **Open** items appear in §12 "Open
questions" — the approach is drafted but the work has not been scheduled
into a phase yet.

Status legend: **P3 / P4 / P5 / P6 / P7** = plan phase. **Open** = not
yet scheduled.

- **Sector-aware destination routing (library / gallery / museum)** —
  parses sector-specific fields from the sidecar and writes to
  `library_item` / `gallery_artwork` / `museum_object` /
  `museum_metadata`, plus optional Spectrum-workflow entry for museums,
  plus authority auto-creation (creators/artists) with configurable
  opt-out. ✅ **Delivered (P3).**
- **Sidecar XML** (`<heratioScan>` envelope) — rich per-file metadata
  without path encoding. ✅ **All four profiles delivered: archive (P5),
  library / gallery / museum (P3).**
- **Scan API** (`/api/v2/scan/*`) — direct integration with scanner
  applications (VueScan, NAPS2, ScanDirect, custom). ✅ **Delivered (P5).**
- **Wrapper scripts** for PowerShell, bash, Python — plug into scanner
  apps' "post-scan" hooks without needing a desktop helper.
  ✅ **Delivered (P5).** Shipped in `packages/ahg-scan/tools/scanner/`.
- **Capture TUI helper** — `heratio-capture.py` in
  `packages/ahg-scan/tools/scanner/` provides an interactive terminal UI
  for browsing destinations and uploading files via the Scan API.
  Cross-platform (Python + `requests`). ✅ **Delivered (P6).** A full
  Tauri/Electron desktop app remains on the backlog as an
  engineering-heavy follow-up — the TUI covers the same workflow.
- **PREMIS events** (`virusCheck`, `formatIdentification`,
  `messageDigestCalculation`, `ingestion`, `creation (derivation)` per
  derivative) written to `preservation_event` at each pipeline stage.
  ✅ **Delivered (P4).**
- **PRONOM format identification** via `siegfried` (DROID-compatible),
  falling back to `file --mime-type`. Results populate
  `preservation_format` and flag obsolete formats via
  `preservation_format_obsolescence`. ✅ **Delivered (P4).**
- **Rights enforcement** at ingest — embargo (`rights_embargo`),
  Creative Commons (`rights_cc_license`), rights statements
  (`rights_statement` + `object_rights_statement`), Traditional
  Knowledge labels (`rights_tk_label`), rights holders
  (`object_rights_holder`), ODRL policy binding
  (`research_rights_policy`). **"Awaiting rights" hold** when the
  session has a `security_classification_id` set and the sidecar
  supplied no rights — admin "Release rights + resume" action on the
  Inbox detail view lifts the hold. ✅ **Delivered (P4).**
- **BagIt container ingest** — drop a `.zip` with `bagit.txt` +
  `manifest-<alg>.txt` + `data/` into a watched folder; Heratio
  verifies checksums, parses `bag-info.txt`, and ingests each `data/`
  file under one IO identified by `External-Identifier`. ✅ **Delivered
  (P6).**
- **EAD / MARC21 / MODS / LIDO native ingress** — drop an EAD, MARC21-XML,
  MODS, or LIDO sidecar into a watched folder; `AlternateFormatTransformer`
  detects the format and applies the right XSLT to produce a canonical
  `heratioScan` envelope before parsing. ✅ **Delivered (P7)** — EAD
  stylesheet ships in `packages/ahg-scan/resources/transforms/`; MARC21 /
  MODS / LIDO are detected but flagged "transform pending" (add the XSLT
  file next to EAD's to enable).
- **Audio / video derivatives** — `MediaDerivativeService` generates
  waveform PNG + MP3 128 kbps preview for audio; MP4 480p preview + poster
  frame for video. Runs via `ffmpeg`. ✅ **Delivered (P7).**
- **3D preview thumbnails** — delegates to the existing
  `ThreeDThumbnailService::createDerivatives()` when master is GLB / OBJ /
  USDZ / PLY / STL / FBX. ✅ **Delivered (P7).**
- **IIIF pyramid pre-generation** — for TIFF masters, ImageMagick's
  `convert ... ptif:` target builds a pyramidal TIFF that Cantaloupe can
  stream tiles from at any zoom. JP2 is already pyramidal natively.
  ✅ **Delivered (P7)** — needs ImageMagick on PATH (present on the
  reference server).
- **HTR** (handwritten / printed text extraction) — when a session's
  `process_ocr` flag is on and the master is image/PDF, `stageOcr` hands
  the file to `AhgAiServices\HtrService::extract()` (routed to the
  Ollama server per CLAUDE.md). Non-fatal on failure — emits a PREMIS
  `HTR extraction` event either way. ✅ **Delivered (P7).**
- **Audit trail** — scanner creates of IO + DO now insert `audit_log`
  rows (action=`create`, module=`ahg-scan`, username=`ahg-scan
  (<folder_code>)`) matching the footprint of human-initiated creation.
  ✅ **Delivered (P7).**
- **Retry/backoff + quarantine UX + email notifications**:
  `ahg:scan-retry-failed` runs every 5 minutes via cron and re-dispatches
  failed files using an exponential backoff ladder (15min → 1h → 4h →
  24h → 72h, configurable). "Restore from quarantine" on the Inbox
  detail view. Bulk retry / discard on the Inbox list. Per-folder email
  recipients notified after the final failure. ✅ **Delivered (P6).**
- **RAW → DNG preservation derivatives** — keep proprietary RAW
  (CR2 / NEF / ARW / RAF) as preservation master and auto-generate
  open-standard DNG for delivery to Cantaloupe. **Status: Open —
  plan §12 question #9.** A proposal exists (keep-RAW + auto-DNG) but
  has not been scheduled into a phase. Raise on the tracker if you
  need it before P7 hardening.
- **Alternate sidecar formats** (METS accepted alongside
  `heratioScan`). **Status: Open — plan §12 question #1.** Proposed
  as a transform plugin rather than core.
- **Spectrum workflow auto-activation** on museum-sector scans.
  **Status: Open — plan §12 question #7.** Proposed as an opt-in flag
  per folder, default off.

### Explicitly out of scope

These are not planned for any release — see §13 of the plan:

- Driving scanner hardware directly (no TWAIN / WIA / SANE) — use
  your scanner application.
- Auto-cropping, deskewing, colour correction — belongs in the
  scanner application, not Heratio.
- Form-recognition / structured data extraction from scanned forms —
  separate AI pipeline.

## Related

- [Data ingestion manager](data-ingest-user-guide.md) — the wizard used for
  one-off batch imports.
- [Scanner integration plan](scanner-integration-plan.md) — the full
  technical plan behind this feature.
- [DAM user guide](dam-module-user-guide.md) — how scanned assets appear in
  the Digital Asset Manager.
