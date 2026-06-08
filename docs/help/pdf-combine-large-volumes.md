# Combining large TIFF sets into a PDF/A (Heratio)

## Why

Combining many scanned TIFF pages into one PDF used to load every page into a
single ImageMagick process, which ran out of memory on large documents (e.g.
hundreds of 40 MB+ scans). The merge is now **memory-safe**: each page is
converted to its own single-page PDF (bounded limits + JPEG compression),
concatenated with `qpdf` in batches, then a single Ghostscript pass produces the
**PDF/A** (the archival target — always applied). Peak memory is about one page's
worth regardless of page count.

## The web tool

`PDF Tools → Merge` (`/pdf-tools/merge`) merges uploaded files and streams back a
PDF/A. Note the browser-upload size cap (about 100 MB per file): for very large
documents that arrive by FTP, use the command line below instead.

## Large volumes from the command line

When a folder of page TIFFs is already on the server (e.g. delivered by FTP),
combine it without any upload limit:

```bash
cd /usr/share/nginx/heratio
# produce a standalone PDF/A
sudo -u www-data php artisan ahg:pdf-combine /path/to/folder --out=/path/to/output.pdf --dpi=200

# or combine AND attach to a record as a master digital object
sudo -u www-data php artisan ahg:pdf-combine /path/to/folder --id=12345 --dpi=200
```

Pages are ordered by filename (natural sort). Options: `--out`, `--dpi` (default
200), `--quality` (default 85), `--id` (attach to an information object),
`--no-web` (skip the web derivative). Run as `www-data` so any attached file
lands with the right ownership.

When attached (`--id`), the combined PDF/A is a master digital object and the
command **immediately creates its fast-loading web derivative** (via
`ahg:optimize-pdfs`), so the big document opens page-1-fast in the viewer without
waiting for the daily optimisation pass. Pass `--no-web` to skip that step.

## Requirements

Ghostscript, qpdf and ImageMagick on the host:

```bash
sudo apt-get install -y ghostscript qpdf imagemagick
```

If Ghostscript is missing the merge still produces a normal merged PDF (no PDF/A
conversion) and logs a warning.
