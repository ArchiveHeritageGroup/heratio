# TIFF/image -> PDF/A combine pipeline (both codebases)

The "Combine a folder into PDF/A" feature on the FTP-upload page turns a folder of
page images (TIFF/JPG/PNG) into a single memory-safe PDF/A and either attaches it
to a record or leaves it ready to link by name. It exists in both codebases:

- **Heratio (Laravel):** `ahg:pdf-combine` console command (`ahg-pdf-tools`) driven
  by `FtpUploadController::combineFolder` (`ahg-ftp-upload`). Runs as a background
  `exec` - no queue worker needed. Attach via `DigitalObjectService::upload` +
  `generateDerivativesForMaster`, web derivative via `ahg:optimize-pdfs`.
- **AtoM-AHG (Symfony 1.4):** queued `tiff_pdf_merge_job` rows processed by the
  `ahg:tiff-pdf-process` cron worker (every minute). Orchestrated by
  `TiffPdfMergeJob` -> `TiffPdfMergeService`. The combine button posts to
  `tiffpdfmerge/importFolder`.

## Design rules (each combine is one self-contained batch)

1. **A job per batch.** Each combine submission is its own job / command run.
2. **Unique output name.** The PDF is named from the folder/slug, truncated to 80
   chars (long record slugs would otherwise produce unusable filenames). AtoM
   appends the job id (`<name>_<jobid>.pdf`); Heratio appends a short random token.
   This guarantees a fresh PDF every convert - never a reused name that serves a
   stale file.
3. **Slug given -> auto-linked.** If a record slug is supplied, the PDF is attached
   as a master digital object and the record is reindexed.
4. **No slug -> link by name later.** The finished PDF is held (AtoM: kept as a
   completed unlinked job; Heratio: in a `_combined/` folder under the FTP base)
   and listed in a "Combined PDFs - link to a record" panel with a slug box +
   Attach button. Endpoints: AtoM `tiffpdfmerge/readyToLink` +
   `tiffpdfmerge/attachExisting`; Heratio `ftpUpload.readyToLink` +
   `ftpUpload.attachExisting`.
5. **Fresh start.** After a successful combine the source page files are deleted
   (guarded to the FTP upload folder only) and the now-empty subfolder removed, so
   the next convert can never re-combine stale leftovers.

## AtoM gotcha: CLI worker must commit the attach transaction

AtoM (Propel) turns autocommit off and opens a transaction on the shared DB
connection during digital-object creation. A **web request** commits it via a
symfony filter at end of request; a **CLI task / cron worker does not**, so the
`object` / `digital_object` rows were inserted (the autoincrement advanced) and
then **rolled back at process exit** - visible mid-run (the web-derivative step
could read them) but gone afterwards. Symptom: the worker logs "Attached as digital
object ID: N" and "completed", yet the record shows no (or a stale) digital object,
and `MAX(object.id)` is ahead of the highest surviving row.

Fix: force-commit any open PDO transaction before the worker process ends
(`$pdo->inTransaction() && $pdo->commit()`), via raw PDO because the transaction is
opened by Propel, not Laravel's query builder. Applies to any AtoM-AHG CLI/worker
task that writes Qubit entities (object, digital_object, slug, ...).

## Reindex after a CLI attach

The cron worker cannot update Elasticsearch (`"default" context does not exist` in
a CLI context), so a newly attached PDF shows on the record page immediately but
not in search/browse until reindexed. Reindex one record with:
`php symfony search:populate --slug="<slug>" --update --ignore-descendants`.
