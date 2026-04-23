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

| You do | Heratio does |
|---|---|
| Configure a watched folder under `Admin → Scan → Watched folders` | Creates a long-lived ingest session with your chosen sector, standard, and processing options |
| Point your scanner application at that folder, using path `<parent-slug>/<identifier>/` | Watches the folder, detects the new file once it's been idle for the quiet period |
| Drop (or let the scanner drop) a file such as `<folder>/fonds-smith/SMITH-001/page_001.tiff` | Creates/finds the information object with identifier `SMITH-001` under the `fonds-smith` parent, then attaches the TIFF as a master digital object with SHA-256 checksum |
| Check `Admin → Scan` dashboard | See throughput, failures, and per-folder stats; retry or discard any failed file |

## Key concepts

### Watched folders
A watched folder is a directory on disk that Heratio polls for new files. Each
folder is backed by a **persistent ingest session** — so all the processing
options you already know from the Ingest wizard (sector, archival standard,
derivative generation, OCR, virus scan, SIP/AIP/DIP packaging) apply to
every file that arrives.

### Destination routing (path layout)
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
  `museum_metadata`, plus optional Spectrum-workflow entry for museums.
  Archive sector already works through path-layout. **Status: Committed —
  plan P3.**
- **Sidecar XML** (`<heratioScan>` envelope) — rich per-file metadata
  without path encoding. All four sector profiles inside the envelope:
  archive (ISAD(G) / EAD / DACS / RAD / RiC), library (MARC21 / MODS /
  RDA), gallery (CDWA / VRA Core / LIDO), museum (Spectrum / LIDO /
  Darwin Core). DAM-augmentation block applies to all sectors.
  **Status: Committed — envelope + parser in plan P5; sector-specific
  field mapping in P3.**
- **Scan API** (`/api/v2/scan/*`) — direct integration with scanner
  applications (VueScan, NAPS2, ScanDirect, custom).
  **Status: Committed — plan P5.**
- **Wrapper scripts** for PowerShell, bash, Python — plug into scanner
  apps' "post-scan" hooks without needing a desktop helper.
  **Status: Committed — plan P5.**
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
