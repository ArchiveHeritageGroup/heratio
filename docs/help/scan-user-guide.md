> Heratio Help Center article. Category: Digitisation & Capture.

# Scanner & Capture Pipeline

The Scanner & Capture pipeline turns loose scan files into fully described, indexed archival records without manual data entry. Point it at a folder your scanner writes to (or send files over its HTTP API) and Heratio runs each file through virus scanning, format identification, metadata extraction, record creation, derivative generation, optional OCR/HTR, search indexing and preservation packaging - automatically. The admin dashboard lives at **Admin -> Scan** (`/admin/scan`).

---

## Overview

Most digitisation work produces a stream of files: a flatbed or overhead scanner writing TIFFs to a network share, a bulk camera rig, or a third-party capture application. The Scanner & Capture pipeline (the `ahg-scan` package) connects that stream to Heratio's ingest engine so that every file becomes a digital object attached to an information object, with full audit and preservation metadata.

There are two ways to feed it:

- **Watched folders** - Heratio polls one or more directories on disk. New files that have settled (stopped changing) are picked up and processed.
- **Scan API** - a scanner application or script uploads files directly over HTTP to `/api/v2/scan/*`, opening a session, posting files, and committing.

Both routes share the same processing pipeline and the same dashboard, so you can mix them freely.

---

## Key features

- **Hands-off ingestion** - drop a file in a watched folder and a described record appears in the catalogue.
- **Two layouts** - `path` layout derives the destination collection and identifier from the folder structure; `flat-sidecar` layout pairs each file with an XML sidecar that carries its metadata.
- **Rich sidecars** - native heratioScan XML plus automatic transformation from EAD, MARC21, MODS and LIDO descriptive standards.
- **Multi-sector** - route into the archive, library, gallery or museum sector, each with its own descriptive standard (ISAD(G) and others).
- **Preservation built in** - virus scan (ClamAV), format identification, checksums and PREMIS events for ingestion, fixity, format ID and derivation.
- **Derivatives + OCR/HTR** - thumbnails and reference copies are generated automatically; handwriting/text recognition can be switched on per folder.
- **BagIt support** - a BagIt zip dropped in a watched folder is unpacked and each payload file ingested.
- **Rights holds** - files can be held in an `awaiting_rights` state for a person to review before they go live.
- **Safe retries** - failures are quarantined and retried on an exponential backoff ladder; the inbox lets you retry, restore or discard files in bulk.
- **Full audit** - scanner-created records are written to the audit log just like human-created ones.

---

## How to use

### Set up a watched folder

1. Go to **Admin -> Scan -> Watched folders** (`/admin/scan/folders`) and click **Create**.
2. Fill in the form:
   - **Code** - a short machine name (lowercase letters, digits, `-` and `_`), e.g. `arc-intake`.
   - **Label** - a human-friendly name.
   - **Path** - the absolute directory Heratio should watch.
   - **Layout** - `path` (derive metadata from folder structure) or `flat-sidecar` (read a paired `.xml` per file).
   - **Sector** - archive, library, gallery or museum.
   - **Standard** - the descriptive standard, e.g. `isadg`.
   - **Parent** and **Repository** - the default destination collection and repository.
   - **Quiet period** - seconds a file must sit unchanged before it is picked up (stops half-written files being grabbed).
   - **On success / On failure** - move, delete or leave files after a successful run; quarantine or leave them after a failure.
   - **Auto-commit**, **derivatives**, **virus scan** and **OCR** toggles.
3. Save. The folder is created together with a backing ingest session that stores the processing configuration.
4. To process the folder immediately without waiting for the scheduler, click **Run now** on the folders list (this runs a single watcher pass for that folder).

### Watch the dashboard

The dashboard at `/admin/scan` shows live status tiles - **pending, processing, done, failed, duplicate, quarantined, awaiting rights** - plus 24-hour throughput, a per-folder summary (pending / failed / done counts and last completion) and the 20 most recent files.

### How files advance through stages

Each file is tracked as an ingest-file row that moves through the pipeline in order:

`virus -> format -> resolve destination -> create IO + DO -> metadata -> sector routing -> rights -> deriving -> OCR -> indexing -> packaging -> done`

- **Virus** - ClamAV scan (skipped if disabled or ClamAV is not installed). An infection fails the file.
- **Format** - format identification records a PUID and emits a PREMIS event.
- **Resolve destination** - works out the parent collection and identifier, in priority order: XML sidecar, then path layout, then session defaults. Files whose checksum already exists are marked **duplicate**.
- **Create IO + DO** - hands off to the ingest engine (`IngestService::ingestFile()`) to create the information object and attach the digital object, with an audit-log entry.
- **Metadata** - extracts embedded EXIF / IPTC / XMP / document properties.
- **Sector routing** - writes library/gallery/museum-specific metadata when present.
- **Rights** - applies rights from the sidecar; if the destination is classified but no rights were supplied, the file is held as **awaiting rights**.
- **Deriving / OCR / indexing / packaging** - generates thumbnails and reference copies, optionally runs OCR/HTR, indexes the record for search, and builds SIP/AIP/DIP packages if configured.

On success the source file is moved, deleted or left per the folder's disposition; on failure it is quarantined for retry.

### Use the inbox

The inbox at **Admin -> Scan -> Inbox** (`/admin/scan/inbox`) lists every file with filters by status, folder and free-text search. Open any file to see its full history and the resolved record. Per-file actions:

- **Retry** - reset to pending and reprocess (used for failed files).
- **Restore** - bring a quarantined file back to pending.
- **Discard** - quarantine a file you do not want.
- **Release rights** - clear an awaiting-rights hold and resume from the deriving stage.
- **Bulk** - select many files and retry or discard them in one action.

### Use the Scan API

A scanner application or script can ingest over HTTP. All endpoints require an API key with the `scan:write` scope (or an admin session):

1. `GET /api/v2/scan/destinations?q=...` - search for a parent collection.
2. `POST /api/v2/scan/sessions` - open a session (body sets parent, sector, standard, repository, auto-commit). Returns a token and upload/commit URLs.
3. `POST /api/v2/scan/sessions/{token}/files` - upload a file (multipart), optionally with a `sidecar` XML or inline `metadata` JSON. With auto-commit on, processing starts at once.
4. `POST /api/v2/scan/sessions/{token}/commit` - kick processing for every pending file in the session.
5. `GET /api/v2/scan/sessions/{token}` - check status and per-file results.
6. `DELETE /api/v2/scan/sessions/{token}` - abandon the session and clean up un-ingested files.

Helper scripts for desktop scanners ship under the package's `tools/scanner/` directory.

---

## Configuration

### The watcher command

A watched folder only moves files when the watcher runs. Run it continuously under a process supervisor, or one pass at a time from a scheduler:

```
php artisan ahg:scan-watch --interval=15     # continuous, poll every 15s
php artisan ahg:scan-watch --once            # single pass (ideal from cron)
php artisan ahg:scan-watch --once --folder=arc-intake   # one folder only
```

Companion commands:

- `php artisan ahg:scan-process` - process pending files synchronously.
- `php artisan ahg:scan-retry-failed` - re-dispatch failed files whose backoff window has elapsed.
- `php artisan ahg:scan-install` - apply the schema and dropdown seeds (runs automatically on first boot).

### Watched-folder fields (`scan_folder`)

Each folder stores: `code`, `label`, `path`, `layout` (`path` or `flat-sidecar`), `disposition_success` (move / delete / leave), `disposition_failure` (quarantine / leave), `min_quiet_seconds`, `enabled`, `notify_emails`, `notify_on_failure`, and a link to its ingest session (which holds sector, standard, parent, repository, auto-commit, derivative, virus-scan and OCR settings).

### System paths and limits (`config/heratio.php`, `scan` block)

| Setting | Env variable | Default |
|---|---|---|
| Staging path (Scan API uploads) | `HERATIO_SCAN_STAGING` | `<storage>/.scan_staging` |
| Quarantine path | `HERATIO_SCAN_QUARANTINE` | `<storage>/.scan_quarantine` |
| Archive path (moved originals) | `HERATIO_SCAN_ARCHIVE` | `<storage>/.scan_archived` |
| Default quiet period | `HERATIO_SCAN_MIN_QUIET` | `10` |
| Max retry attempts | `HERATIO_SCAN_MAX_ATTEMPTS` | `5` |
| Retry backoff (minutes) | `HERATIO_SCAN_RETRY_BACKOFF` | `15,60,240,1440,4320` |

The Scan API upload size limit is set by `ahg_settings.scan_max_upload_mb` (default 2048 MB). All dropdown values (sector, layout, dispositions) come from the Dropdown Manager - none are hardcoded.

---

## References

- Source package: `packages/ahg-scan/`
- Companion guide: `docs/scanner-capture-user-guide.md` (and `docs/scanner-integration-plan.md` for the integration design)
- GitHub issue: https://github.com/ArchiveHeritageGroup/heratio/issues/622
