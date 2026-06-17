# Scan Run History & Folder Path Routing - User Guide

Two related additions to the watched-folder scanner: a **per-pass run history** and **per-folder path routing**.

## Run history (`scan_event`)

Every time the watcher scans a folder it now records one row in `scan_event` capturing what happened that pass:

| Column | Meaning |
|---|---|
| detected | genuine ingest candidates seen on disk this pass |
| enqueued | new files staged for ingest |
| skipped_duplicate | files already staged (deduped by hash) |
| skipped_quiet | files still being written (inside the quiet window) |
| failed | files that errored this pass (e.g. a bad BagIt) |
| job_id | the ingest job opened for the pass, if any |
| status | `completed`, `idle` (nothing on disk), or `failed` |
| message | failure detail when status is `failed` |

The last 20 passes are shown on the **Scan dashboard** (`/admin/scan`) under **Watched-folder run history**, so an operator can see at a glance whether a hot folder is being picked up, how much it ingested, and why a pass failed - without reading logs.

## Path routing (`processed_path` / `failed_path`)

Previously, successful files were always moved to the global archive dir (`heratio.scan.archive_path`) and failures to the global quarantine dir (`heratio.scan.quarantine_path`). You can now override these **per folder** on the folder edit form:

- **Processed path** - where successful files are archived. Blank = use the global archive path.
- **Failed path** - where failed files are quarantined. Blank = use the global quarantine path.

Both honour the existing `On success` / `On failure` disposition modes (move / leave / delete / quarantine); the path fields only change *where* "move"/"quarantine" sends files. A dated subfolder (`/YYYY/MM`) is still appended automatically.

Folders also now record `created_by` (the operator who registered them) for provenance.

## Notes

- `auto_commit` is unchanged - it continues to live on the folder's bound `ingest_session`, not duplicated onto the folder.
- The `scan_event` table and the new columns install automatically on first boot after upgrade (idempotent).

Source: PSIS `ahgScanPlugin` parity (issue #1281).
