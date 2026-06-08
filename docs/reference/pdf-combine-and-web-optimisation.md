# PDF tooling: large-volume TIFF→PDF/A combine + web-optimised viewing

**Summary.** Two related capabilities span both the Laravel **Heratio** codebase and the
**AtoM-AHG** codebase (the AtoM fork with AHG plugins). (1) Large PDFs now load
page-1-fast in the document viewer via a downsampled + linearized web derivative.
(2) Combining many scanned TIFF pages into one PDF/A is now memory-safe and runs in
the background, so it no longer fails on large volumes (e.g. hundreds of 40 MB+ scans).
Masters/originals are never modified. Both features need `ghostscript`, `qpdf` and
`imagemagick` on the host.

## 1. PDF web optimisation (fast viewer loading)

Large/scanned PDFs open slowly because the master is not linearized ("fast web view"
off) and each page is a full-resolution image. The fix generates a **web-optimised
derivative**: Ghostscript downsamples embedded images (default 200 dpi) and qpdf
linearizes the result (a 200 MB scan becomes a few MB). The viewer prefers the
derivative for display; the master stays the download/preservation copy. Redaction-safe
(non-admins with redactions keep the redacted stream).

- **Heratio:** command `php artisan ahg:optimize-pdfs` (options `--commit --min-mb=20
  --dpi=200 --max-ratio=0.8 --limit --id`). Stores the derivative as a usage-141
  digital object with mime `application/pdf`; viewer (`_digital-object-viewer`) prefers
  it. Runs daily via the core scheduler. Service: `PdfWebOptimizationService`.
- **AtoM-AHG:** task `php symfony ahg:optimize-pdfs` (ahgCorePlugin). Stores the
  derivative as an on-disk `<base>.web.pdf` sibling next to the master (no DB row);
  the digital-object viewer's PDF click-through points at the sibling. Helper:
  `ahgWebPdf` (lib/ahgWebPdf.class.php). Plugin-only, no AtoM base changes.

## 2. Large-volume TIFF→PDF/A combine

The old combine ran a single ImageMagick `convert` over all pages at once and executed
in the web request, so big documents ran out of memory and timed out. Now:

- **Memory-safe pipeline:** each page → its own single-page PDF (`convert` with bounded
  `-limit` and `-compress JPEG`) → concatenated with `qpdf` in batches → one Ghostscript
  PDF/A pass. Peak memory ≈ one page regardless of page count. Output is always **PDF/A**
  (pdfa-2b default).
- **Background + intake:**
  - AtoM-AHG: the web "Process" action only queues; the `ahg:tiff-pdf-process` worker
    (cron, every minute) runs the merge and emails the user when done. A `recreate`
    action re-queues failed/completed jobs. Intake is either a manual server-folder
    import (`tiffpdfmerge/importFolder`, files referenced in place) or the
    `ahg:tiff-combine-watch` drop-folder watcher (`<watch-base>/<record-ref>/`): maps the
    folder to a record and auto-links the PDF/A, or — if no record matches — still creates
    it to link later.
  - Heratio: command `php artisan ahg:pdf-combine <folder> [--out] [--dpi=200] [--id]
    [--no-web]` combines a server folder (no browser upload cap); `--id` attaches the
    PDF/A to a record as a master. The web merge tool (`/pdf-tools/merge`) remains for
    small browser uploads.
- **Immediate web derivative:** on both codebases, as soon as a combined PDF/A is
  attached to a record, its fast `.web.pdf` / web derivative is created immediately
  (not only on the daily optimise pass).

## Host requirements & per-instance setup

```
sudo apt-get install -y ghostscript qpdf imagemagick
```

- Use a temp dir on real disk (not tmpfs) with ample free space for intermediates.
- AtoM-AHG cron: `ahg:tiff-pdf-process` every minute; `ahg:tiff-combine-watch` every
  5 minutes. Optional `ahg:optimize-pdfs` daily.
- Heratio: `ahg:optimize-pdfs` is scheduled by the core provider; run `ahg:pdf-combine`
  manually or from cron for large folders.

## Reference

- Heratio docs: `docs/pdf-web-optimisation-setup.md`, `docs/help/pdf-combine-large-volumes.md`.
- AtoM-AHG docs: `atom-extensions-catalog/docs/pdf-web-optimisation-setup.md`,
  `atom-extensions-catalog/docs/tiff-pdfa-combine-large-volumes.md`.
- Both behaviours are plugin/package-local; masters are never modified; the combine and
  optimisation no-op cleanly when the host tools are absent.
