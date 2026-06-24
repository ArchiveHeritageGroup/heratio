# Resumable (Chunked) Uploads for Large Files

Single large digital objects - high-resolution TIFF/JP2, video, 3D models -
can exceed the normal web-upload limit and are fragile over slow or unstable
connections. Heratio's resumable upload sends the file in small chunks and can
pick up where it left off after an interruption.

## Where to find it

On the ingest **Upload** step there is a **Large file (resumable upload)** card
below the normal upload form. Use the normal form for CSV/ZIP/EAD metadata
files; use the resumable card for a single large media file.

## How to use it

1. Choose the large file in the resumable card.
2. Click **Start resumable upload**.
3. A progress bar shows each chunk as it is sent. If the connection drops, or
   you reload the page, select the same file and start again - the upload
   **resumes** from the last chunk that reached the server rather than
   restarting.
4. When all chunks have arrived, the server reassembles the file, verifies its
   integrity, and ingests it into the session's target record. A success
   message shows the record number.

## What happens behind the scenes

- The file is split into 8 MB chunks, so the transfer is never blocked by the
  server's whole-file upload size limit.
- The reassembled file is checksum-verified and ingested through the same
  pipeline as any other digital object, so digital-object metadata, fixity, and
  repository quotas all apply.
- A preservation (PREMIS) ingestion event is recorded for the upload.

## Tips

- Keep the browser tab open while uploading; resume works on the same device
  and browser.
- If an upload repeatedly fails near the end, retry - reassembly only runs once
  every chunk is present.
