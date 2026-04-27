# ahgFtpPlugin — User Guide

The **ahgFtpPlugin** (FTP / SFTP Upload) is the in-browser bridge between an FTP/SFTP server and Heratio's ingest pipeline. It is the recommended way to land **batches** of digital objects (PDFs, TIFFs, audio, video) when:

- the upload exceeds the browser's HTTP-POST limit (>2 GB), **or**
- the files are pre-staged on a separate machine that already has SFTP access to the institution's drop zone, **or**
- the operator wants files staged for review before binding them to archival descriptions via the CSV ingest pipeline.

---

## What it does

A logged-in user with `acl:create` permission can:

1. **Browse** the configured remote drop zone (`/sftp/ftpuser/uploads/` by default — operator-configurable).
2. **Upload** files into that drop zone directly from the browser. Files >100 MB are chunk-uploaded so unstable connections can resume.
3. **List & delete** what's already there.
4. **Hand off** the staged files to the CSV ingest wizard (`/ingest`) which links them to information objects via the `digitalObjectPath` column.

It does **not** create archival descriptions itself — its job ends at "files are now on the server". Use `/ingest` (CSV-driven) or `/informationobject/import/csv` next.

---

## Where it lives

| Surface | URL |
| --- | --- |
| Browse + upload UI | `/ftpUpload/index` |
| File list (AJAX) | `/ftpUpload/listFiles` |
| Upload (full file) | `POST /ftpUpload/upload` |
| Upload (chunked) | `POST /ftpUpload/uploadChunk` |
| Delete a file | `POST /ftpUpload/deleteFile` |
| Settings | `/admin/settings/ahg/ftp` |

The plugin is enabled by default. Disable from `/admin/ahgSettings/plugins` if your install never uses FTP/SFTP staging.

---

## Settings (`/admin/settings/ahg/ftp`)

These map directly to the `ahg_settings` keys:

| Key | Meaning | Typical value |
| --- | --- | --- |
| `ftp_protocol` | `sftp` (recommended) or `ftp` | `sftp` |
| `ftp_host` | Hostname / IP of the SFTP server | `psis.theahg.co.za` |
| `ftp_port` | Port. SFTP default 22; pick a non-standard port (`2222`) when running on the same host | `2222` |
| `ftp_username` | SSH/FTP login name | `ftpuser` |
| `ftp_password` | Login password (stored encrypted at rest) | (set) |
| `ftp_remote_path` | Subdirectory on the server where uploaded files land | `/uploads` |
| `ftp_disk_path` | Local filesystem path that mirrors the remote (when SFTP server is on the same box) | `/sftp/ftpuser/uploads` |
| `ftp_passive_mode` | Plain-FTP only — leaves data-channel negotiation to the client. Ignored for SFTP. | `1` |

Save the form, then test connectivity with the **Test connection** button on the same page. Failure modes:

- "Auth failed" → wrong username / password / key.
- "Connection refused" → host/port wrong, or firewall blocking. Confirm `nc -zv host port`.
- "Permission denied (writing)" → SFTP user does not own `ftp_remote_path`. `chown -R ftpuser:ftpuser /sftp/ftpuser/uploads`.

---

## Walk-through: upload + ingest a batch of PDFs

1. Stage your files on your workstation in a folder, plus a CSV manifest (one row per file with at minimum `legacyId`, `title`, `digitalObjectPath`).
2. Drag the entire folder into `/ftpUpload/index`. The plugin uploads each file with a per-file progress bar; at the end it lists them under the configured `ftp_remote_path`.
3. In the same browser tab, open `/ingest`. Choose **CSV upload**, select your manifest. The wizard sees the staged files at `digitalObjectPath` and links them to information-object rows on commit.
4. After the ingest run, use `/admin/jobs` to confirm derivative-generation (thumbnails / references) finished cleanly.

---

## Operational notes

- The plugin uses Laravel's `Storage` driver behind a thin wrapper. SSH keys are not yet supported via the UI — password auth only. To use keys, configure them server-side and leave `ftp_password` blank.
- Chunked uploads write to `<ftp_remote_path>/.uploads-tmp/<upload-id>/` while in-flight, then atomically rename on completion. Aborted uploads leave orphan chunk files; clean up with `find <remote>/.uploads-tmp -mtime +7 -delete` in cron.
- All upload and delete actions are recorded in `ahg_audit_log` (when `ahgAuditTrailPlugin` is enabled).
- File size limits are enforced **server-side** — set `client_max_body_size` in nginx high enough for your chunk size (default chunk = 5 MB).

---

## Related

- **CSV ingest wizard** — `/ingest` (the consumer of the staged files)
- **Watched folders** — `/admin/scan` (alternative for files dropped on the local filesystem; no browser upload)
- **Audit trail** — `/admin/audit` (review who uploaded what)
- **Storage configuration** — `/admin/ahgSettings/storage` (where the destination disk lives)
