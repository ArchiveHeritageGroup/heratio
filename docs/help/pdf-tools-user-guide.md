> Heratio Help Center article. Category: Plugin Reference.

# PDF Tools - User Guide

## Extract Searchable Text and Merge Pages into Archival PDFs

The PDF Tools module does two jobs. It pulls the text out of PDF files so they
become searchable, and it merges a set of page images (or PDFs) into a single,
archival-grade PDF/A document. Both are available through a simple admin screen,
and large merging jobs can also be run from the command line.

---

## Overview

**Text extraction** reads a PDF and extracts its text, storing it against the
digital object so it can be indexed and searched. You can extract from a file you
upload, from an existing digital object, or in a batch across many PDFs that have
no extracted text yet.

**Merging** takes a list of images - TIFF, JPG, PNG, BMP, GIF, WebP - or PDFs and
combines them into one PDF, optionally as PDF/A for long-term preservation. The
merge is memory-safe: pages are processed one at a time, so even very large
documents combine without exhausting memory.

---

## Key features

- Extract text from an uploaded PDF, from an existing digital object, or in
  batches.
- Stored extracted text becomes searchable and is shown with a character and word
  count.
- Merge images and PDFs into a single document, in page order.
- Optional PDF/A output (versions 1b, 2b, 3b) for preservation.
- Page-size, orientation, DPI, and quality controls for merges.
- Memory-safe merging that handles large, multi-page documents.
- A dashboard that reports which tools are installed and your extraction
  progress.

---

## How to use

The module lives under the **PDF Tools** admin area (**`/admin/pdf-tools`**) and
requires an administrator login.

### Dashboard

Open **`/admin/pdf-tools`**. The dashboard shows whether each required tool is
installed (with versions), your text-extraction statistics (total PDFs, extracted,
remaining), the formats supported for merging, and quick-action buttons.

### Extract text

- **From an upload or existing object:** use the extract-text action. Upload a
  PDF, or point at an existing digital object by its ID. The extracted text is
  stored and shown on screen with character and word counts, and a copy-to-
  clipboard button.
- **In batches:** use the batch-extract action. It processes up to 50 PDFs that
  have no extracted text yet and reports how many remain, so you can run it again
  until the backlog is clear.

### Merge files into a PDF

Go to **`/admin/pdf-tools/merge`**. Select the files in the order you want them
combined, set the options, and merge:

- **Quality** - JPEG compression (0 to 100).
- **DPI** - output resolution (72 to 600).
- **Page size** - letter, A4, legal, A3, or A5.
- **Orientation** - portrait or landscape.
- **PDF/A** - tick to produce a PDF/A, and choose the version (1b, 2b, or 3b).

The merged document downloads when it is ready.

### Merge a server folder (command line)

For high-volume jobs, combine a whole folder of page images into one PDF/A from
the command line:

```
php artisan ahg:pdf-combine /path/to/folder --dpi=200 --quality=85
```

Files in the folder are sorted by name so page order follows the filenames.
Useful options:

- `--out=` - output path (defaults to the folder name plus `.pdf`).
- `--id=` - attach the resulting PDF/A to an information object as a master
  digital object (derivatives are generated automatically).
- `--no-web` - skip creating the fast web-optimized derivative.
- `--clear-source` - move the source page files into a dated quarantine folder
  after a successful combine, rather than deleting them outright.

Quarantined source files are kept for a retention window and then removed by a
scheduled purge. You can run the purge manually, with a dry-run option to preview:

```
php artisan ahg:purge-combine-trash --dry-run
```

---

## Configuration

- **Quarantine retention:** how many days combined source files are kept in
  quarantine before purge is controlled by a setting (default 7 days). The purge
  runs automatically each day, and can be overridden per run with `--days=`.
- **Storage paths:** temporary and merged files use the configured uploads path;
  quarantine uses the configured storage path.

### Tools required

PDF Tools relies on external utilities on the host:

- **pdftotext** (from poppler-utils) for text extraction.
- **ImageMagick** for converting images to PDF.
- **Ghostscript** for producing PDF/A.
- **qpdf** for joining pages efficiently.

The dashboard's tool check shows which of these are present. Text extraction
needs pdftotext; merging needs ImageMagick; PDF/A output needs Ghostscript.

---

## References

- Source package: `packages/ahg-pdf-tools/`
- GitHub issue: [GH #607](https://github.com/ArchiveHeritageGroup/heratio/issues/607)
