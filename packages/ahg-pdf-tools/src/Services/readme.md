# AhgPdfTools - Services

Services directory for the AhgPdfTools package.

Contains PDF processing service classes for the AHG PDF Tools plugin package.

## Rebuilding a text layer from vision OCR (heratio#1523)

`php artisan ahg:pdf-rebuild-text-layer <pdf> [--text-dir=<dir>] [--out=<dir>] [--dpi=300] [--lang=eng] [--python=<python with ocrmypdf>]`

Replaces a PDF's invisible text layer with better text that has no word positions (a vision-model transcript). Each page is rendered and read by Tesseract for word boxes. Its text comes from `--text-dir/NNNN.txt`, or from the vision engine (heratio#1522) when no folder is given. `bin/rebuild-text-layer.py` aligns the words to Tesseract's boxes. The text pages are overlaid on the original, and the command writes `PDF/`, `PDFA/` (PDF/A-2b) and `PDF Lossy/` copies.

Needs `qpdf`, `tesseract`, `pdftoppm`, Ghostscript and a Python environment with `ocrmypdf` (pass it as `--python`). Page counts are checked at every step; structure is checked with `qpdf --show-xref`, because `--check` decodes every image. After a build, check that the title pages and spines are searchable: that is where a layer is most often thin.
