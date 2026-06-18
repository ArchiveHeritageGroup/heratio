# TIFF/PDF Merge Tool - how it works

The TIFF/PDF Merge tool (`/admin/preservation/tiffpdfmerge`, package `ahg-preservation`)
merges multiple TIFF / PDF / image files into a single PDF or multi-page TIFF.

## What was broken

The tool was a non-functional skeleton. The "Start Merge" form posted to
`TiffPdfMergeController::store()`, which only validated the format and flashed
"Merge job queued successfully" without creating a job, saving any files, or
running a merge. The AJAX helpers (`upload()`, `process()`) were also stubs that
returned fake success. As a result a submitted merge produced no
`tiff_pdf_merge_job` row, so the job never appeared in the Browse Jobs list and
nothing was merged. `view()` also queried non-existent columns (`job_id`,
`sort_order`) instead of the real `merge_job_id` / `page_order`.

## How it works now (synchronous)

1. The index form (`tiffpdfmerge/index.blade.php`) posts `output_format`
   (`pdf`|`tiff`), optional `output_filename`, `files[]`, and a hidden `io`
   field when the tool was opened from an archival record ("Open Merge Tool").
   Submit is a normal POST carrying the Laravel `@csrf` token (no CSRF
   exemption needed).
2. `store()` creates the `tiff_pdf_merge_job` row first (status `processing`,
   `information_object_id` from `io`, `user_id`, `total_files`, sanitised
   `output_filename` with forced extension), so the job is visible in Browse
   even if the merge later fails.
3. Each uploaded file is staged under `uploads_path/merges/<jobId>/` and a
   `tiff_pdf_merge_file` row is written (`merge_job_id`, `original_filename`,
   `stored_filename`, `file_path`, `file_size`, `mime_type`, `page_order`,
   `checksum_md5`).
4. The merge runs synchronously via ImageMagick `convert`
   (`runImagickMerge()`): inputs in `page_order` are concatenated to a single
   PDF (`-density 200 -quality 90`) or multi-page TIFF (`-compress lzw`).
   Multi-page PDF/TIFF inputs contribute all their pages.
5. On success the job is marked `completed` with `output_path`,
   `processed_files`, `completed_at`. On failure it is marked `failed` with
   `error_message`. Either way the user is redirected to the job view page.
6. The completed output is served by `download()` at
   `preservation.tiffpdfmerge.download` (admin-gated, streams the file with the
   stored filename) - not a static path.

## Requirements

- ImageMagick `convert` + Ghostscript (`gs`) on the host. The PDF coder must be
  allowed in `policy.xml` (`<policy domain="coder" rights="read|write"
  pattern="PDF"/>`); the `{PS,PDF,XPS}` module restriction must stay commented
  out.
- `www-data` must be able to create `uploads_path/merges/`.
- No queue worker is required - the merge is synchronous (consistent with the
  derivative pipeline). `/jobs/browse` (Laravel queue) is unrelated and stays
  empty.

## Tables

- `tiff_pdf_merge_job` - one row per merge. Key columns: `information_object_id`,
  `status`, `total_files`, `processed_files`, `output_filename`, `output_format`,
  `output_path`, `completed_at`, `error_message`.
- `tiff_pdf_merge_file` - one row per source file. FK is `merge_job_id`; order
  column is `page_order`.
