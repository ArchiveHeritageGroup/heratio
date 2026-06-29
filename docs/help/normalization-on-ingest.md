# Normalization on ingest (preservation masters)

> Audience: archivist, digital preservation officer, sysadmin

Normalization creates an **open, long-lived "preservation master"** copy of an
ingested file - the way Archivematica does. For example a JPEG becomes a TIFF,
a Word document or PDF becomes PDF/A, an MP3 becomes WAV, and a proprietary
video becomes a Matroska/FFV1 file. The original is always kept; the master is
an additional, format-stable copy held against the day the original format
becomes hard to open.

## Turning it on

Normalization is **opt-in** (it is heavier and more opinionated than the
always-on baseline of checksum + format-ID + virus + PREMIS):

- On the Data Ingest **Configure** step, tick **"Normalize (preservation
  master)"**.
- When the ingest commits, each created digital object is queued for
  normalization (it runs in the background so large ingests are not held up).

## What you get per object

- A **preservation master** file in the target open format, attached to the
  record as a linked digital object with usage **"Preservation Master"**.
- A **fixity checksum** on the master.
- A **PREMIS `normalization` event** recording the source, the target format
  and the tool used.
- A row in the **format-conversion log** (source/target, sizes, checksums,
  duration).

## Preservation masters and access copies

Two kinds of copy are produced, each driven by its own rules:

- **Preservation master** - an archival, lossless/open format (image -> TIFF,
  office/PDF -> PDF/A, audio -> WAV, video -> Matroska/FFV1). Attached with
  usage **"Preservation Master"**.
- **Access copy** - a web-friendly format for delivery (TIFF/PNG -> JPEG,
  office -> PDF, WAV -> MP3, Matroska -> MP4). Attached with usage
  **"Reference"**.

When you tick **Normalize** on ingest, both are produced where a rule matches.

## What controls the target format

A rule registry - the **format policy registry** - maps each source format to
its target and tool. Manage it at **Preservation -> Normalization Rules**
(add/edit/toggle/delete rules; separate rules for the `preservation` and
`access` purposes). Defaults cover common image, office/PDF, audio and video
formats. A file with no matching rule (or already in the target format, e.g. a
TIFF for the preservation purpose) is simply left as-is.

## Normalizing files that are already in the catalogue

To backfill masters/access copies for objects ingested before normalization was
enabled, run:

```
php artisan ahg:normalize-existing --purpose=preservation   # or --purpose=access
```

Options: `--limit=N`, `--mime=image/jpeg`, `--sync` (run inline instead of
queueing).

## Where to see it

- **Central Dashboard → Data Ingest card**: a "normalized" count appears next
  to the preservation figures once masters exist.
- **Preservation dashboard → conversions / events**: the normalization events
  and conversion records.
- **The record's digital-object panel**: the Preservation Master derivative.

## Requirements

- The conversion tools must be installed on the server: ImageMagick (images),
  Ghostscript (PDF/A), FFmpeg (audio/video), LibreOffice (office documents).
- The Digital Preservation module must be installed. If a tool or the module
  is missing, normalization is skipped and the ingest still completes - the
  failure is logged, never fatal.

## Related

- Automatic digital preservation on ingest (the always-on baseline)
- PREMIS XML export
- Preservation packages (BagIt / AIP)
