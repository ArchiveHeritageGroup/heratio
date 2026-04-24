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
pending → virus → meta → io → do → indexing → done
                                             ↘ failed / quarantined
```

1. **Virus** — ClamAV scan (logged to `preservation_virus_scan` when
   available; full blocking scan in a later release).
2. **Meta** — basic filename / MIME / size captured at detection.
3. **IO** — parent slug + identifier resolved from the file's path; the
   information object is created (or re-used) with the folder's sector and
   standard.
4. **DO** — the file is moved to Heratio's canonical uploads location under
   `/uploads/<io-id>/master_<name>.<ext>`, hashed, and linked as a master
   digital object.
5. **Indexing** — Elasticsearch upsert so the new IO is searchable.

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
- **Capture desktop helper** — a small cross-platform app (Tauri
  preferred) for ad-hoc archivist capture, browsing the hierarchy and
  uploading live. **Status: Committed — plan P6.**
- **PREMIS events** (virusCheck, format identification,
  messageDigestCalculation, creation/derivation, replication) written to
  `oais_premis_event` at each pipeline stage. **Status: Committed —
  plan P4.**
- **DROID / PRONOM format identification** against `oais_pronom_format`
  + `preservation_format`, flagging obsolete formats for migration
  planning. **Status: Committed — plan P4.**
- **Rights enforcement** at ingest — embargo (`rights_embargo`),
  Creative Commons (`rights_cc_license`), ODRL policy
  (`research_rights_policy`), Traditional Knowledge labels
  (`rights_tk_label`), with "awaiting rights" hold for classified
  material. **Status: Committed — plan P4.**
- **BagIt container ingest** — drop a `.zip` or directory with
  `bag-info.txt` + `manifest-*.txt`; manifest rows become sibling IOs,
  `bag-info.txt` fields map to session metadata.
  **Status: Committed — plan P6.**
- **EAD / MARC21 / MODS / LIDO native ingress XSLTs** — drop the
  institution's existing XML standard directly into the watched folder;
  Heratio transforms to the canonical `heratioScan` envelope on ingest.
  **Status: Committed — plan P7.**
- **Audio / video derivatives** (MP3 preview + waveform PNG; MP4 480p
  + poster frame) and **3D preview thumbnails** (rendered from GLB /
  OBJ / USDZ). **Status: Committed — plan P7.**
- **IIIF pyramid pre-generation** for TIFF / JP2 masters, fed to
  Cantaloupe. **Status: Committed — plan P7.**
- **HTR** (handwritten text recognition) on opt-in, routed to the
  on-premise Ollama server. **Status: Committed — plan P7.**
- **Retry/backoff, quarantine UI, email notifications** for pipeline
  failures. **Status: Committed — plan P6.**
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
