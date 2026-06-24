# Chunked / resumable upload for large files (#1328)

Adds a resumable web-upload path for single large digital objects (TIFF/JP2/
video/3D > 1 GB) that the normal one-shot form cannot handle within
`post_max_size` / `upload_max_filesize`. Custom chunk protocol - no tus/external
dependency.

## Server (`AhgIngest\Controllers\ChunkedUploadController`)

Routes under the existing admin-gated `ingest` prefix:
- `POST ingest/{id}/chunk` - store one part (`upload_id`, `chunk_index`,
  `total_chunks`, `chunk` file) under `{storage_path}/.chunks/{session}/{uploadId}/`.
- `GET  ingest/{id}/chunk/status?upload_id=` - list received chunk indices (resume).
- `POST ingest/{id}/chunk/complete` - verify all parts present, reassemble in
  order via a streamed copy (constant memory), verify the whole-file sha256 when
  the client supplied one, then hand the staged file to
  `IngestService::ingestFile()` - the same entry point ahg-scan uses, so normal
  digital-object creation, byte_size/checksum, and repository-quota checks apply.
  Emits a PREMIS `ingestion (resumable upload)` event and cleans up the parts.
- `POST ingest/{id}/chunk/abort` - discard a partial upload.

Because each part is small (8 MB), the whole-file size is never bounded by
`post_max_size`; no php.ini change is required for the chunked path.

## Client (in the ingest upload step view)

A dependency-free uploader: slices the file into 8 MB chunks, asks `status` to
resume after an interruption, uploads sequentially with up to 3 retries per
chunk and a progress bar, then calls `complete`. The `upload_id` is persisted in
`localStorage` keyed by session+name+size, so a page reload resumes the same
upload rather than restarting.

## Scope / follow-up

- Wired into the **ingest** upload step (primary path). The same server endpoints
  reassemble into the configured storage path and can be reused by ahg-scan by
  targeting the scan staging dir; wiring a chunked client into the Scan API /
  capture UI is a clean follow-up.
- Client-side whole-file checksum is optional (omitted for very large files to
  avoid loading them into browser memory); the server verifies when provided and
  always reassembles deterministically in index order.
