# TIFF to PDF Merge — Research module

Purpose

This note audits the TIFF→PDF merge functionality as it relates to the Research module, lists concrete gaps and incomplete code, and proposes practical enhancements with a staged implementation plan. Place this file at: /usr/share/nginx/heratio/docs/research/tiff-to-pdf-merge.md

1. Gaps (what is missing now)

- No centralized merge service: there is no single Research-level service that coordinates multi-page TIFF ingestion, quality checks, OCR triggering, PDF/A conversion, and provenance recording.
- No standard validation pipeline: uploaded TIFFs are accepted but lack a consistent validation pipeline (bit-depth checks, page order checks, color/mono normalization, DPI verification) before merge.
- No provenance or audit for merged PDFs: the system does not record a structured provenance trail showing which TIFF files (object IDs, checksums, order) produced the final PDF/A artefact.
- Inconsistent error handling and user feedback: failures during merge (corrupt page, unsupported compression) often present a generic error or fail silently; users lack actionable guidance or a retry surface.
- Scalability & resource management missing: large merges run on the web request/worker without explicit resource caps or streaming, causing memory spikes and queue timeouts on big sets.
- Missing accessibility checks and PDF/A profile enforcement: the output PDF is not validated against PDF/A-1a/2u requirements or accessibility (tagged PDF, OCR layer) consistently.
- No per-tenant/queue throttling or cost accounting for heavy merges (important for hosted deployments).

2. Incomplete code (where partial or stubbed implementations exist)

- packages/ahg-research/src/Services — no dedicated TiffMergeService; there are scattered helpers for image handling (ImageProcessingService) and OCR triggers (OcrService) but no orchestrator.
- packages/ahg-research/src/Controllers — some upload endpoints accept multi-page objects and call a naive merge helper with inline logic; large parts of that logic are duplicated across controllers and lack tests.
- packages/ahg-research/resources/views — uploader UI supports multi-file selection but lacks progress indicators, estimated time, and fine-grained merge options (order, PDF/A target, OCR on/off, compress level).
- packages/ahg-attachments — some utilities for image-to-pdf exist but are generic and miss research-specific provenance and audit hooks.
- tmp patches or TODO comments show earlier attempts to add streaming merge but not yet applied (search for `TODO: tiff merge` / `patch` in tmp/ shows remnants).

3. Enhancements and suggested features (concrete, practical)

- Central TiffMergeService (or ImageMergeService)
  - Responsibilities: validate inputs, normalize pages, assemble ordered pages into PDF, optionally run OCR and embed as text layer, convert to PDF/A profile, compute checksums, write provenance records and asset metadata.
  - API examples:
    - mergeTiffFiles(array $attachments, array $options = []): MergeResult
    - validateTiffFile($attachment): ValidationResult
    - streamMergeToTemp($attachments, $outfile, $onProgress)

- Validation & preflight pipeline
  - Steps: file integrity checks, compression/codec support check (LZW, ZLIB, JPEG), DPI and page-size normalization, color profile normalization (ICC), orientation detection, duplicate-page detection.
  - Provide a `--dry-run` mode that returns a validation report with suggested fixes.

- Provenance & audit
  - For every merged PDF produce a provenance record containing: list of source attachment IDs, original filenames, checksums (SHA256), page order, merge options (OCR=on/off, PDF/A target), worker id, timestamp, and user id who initiated merge.
  - Write the provenance into research_provenance or ai_provenance with a specific type `tiff_to_pdf_merge` or a dedicated `merge_provenance` table.

- Streaming merge + worker-friendly design
  - Use streaming libraries (Imagick with streaming where available or an external tool like `tiffcp` + `tiff2pdf`, or `img2pdf` + Ghostscript) to avoid high memory usage.
  - Implement a queueable job (MergeTiffJob) that accepts a merge plan and streams results into a temporary location, with heartbeat updates to a merge_job table.

- OCR integration & PDF/A output
  - Optionally run OCR (Tesseract or external OCR service) and embed searchable text layer into the PDF. Store OCR confidence per-page in provenance evidence.
  - Convert final artifact to a chosen PDF/A profile (PDF/A-1b, PDF/A-2u) using Ghostscript or specialized PDF/A tool and validate with a PDF/A validator.

- Accessibility and tagging
  - When possible, produce tagged PDFs for accessibility. If not fully taggable, provide a textual fallback (accessible HTML or a text transcript) and mark the PDF as not taggable in provenance.

- User experience improvements
  - Uploader UI: show per-file thumbnails, re-order by drag-and-drop, set per-merge options (OCR, PDF/A, compression), and show progress with ETA.
  - Error reporting: show validation reports with suggested remediation (e.g., ‘page 3 corrupt — re-upload page 3 or remove’).

- Cost / resource controls
  - Per-tenant or per-user merge size limits and rate limits. Add a merge quota dashboard and a billing meter where applicable.

- Test harness & sample fixtures
  - Add a set of representative multi-page TIFF fixtures (monochrome, color, mixed-dpi, lossy compressed) and unit/integration tests covering validation, streaming merge, OCR embedding, and PDF/A validation.

4. Staged implementation plan (PRs)

PR A — Service skeleton & validation (small)
- Add: packages/ahg-research/src/Services/TiffMergeService.php (interface + simple implementation using existing image libs).
- Add unit tests: packages/ahg-research/tests/Unit/TiffMergeValidationTest.php that validate sample TIFF fixtures.
- Acceptance: validation identifies corrupt pages, unsupported codecs, and returns deterministic ValidationResult.

PR B — Queueable merge job + streaming output (medium)
- Add MergeTiffJob that takes a merge plan and streams pages into a temporary file using command-line tools (tiffcp, img2pdf, ghostscript) or Imagick streaming where safe.
- Add migration: research_merge_jobs table (id, user_id, status, progress_json, started_at, finished_at, error_text).
- Acceptance: large merges run in background, progress visible, memory usage bounded.

PR C — OCR & PDF/A pipeline (medium)
- Integrate OcrService calls as an optional step in TiffMergeService. After OCR, run Ghostscript or equivalent to produce PDF/A and validate.
- Store OCR per-page confidence in provenance evidence.
- Acceptance: merged PDF is validated as PDF/A when requested; OCR text layer searchable.

PR D — Provenance & audit (small)
- Add merge_provenance table or reuse ai_provenance with type `tiff_merge`. Capture detailed input → output mapping and merge parameters.
- Ensure every MergeTiffJob writes a provenance row at start and completion (with checksums, worker id, timing, errors).
- Acceptance: provenance query can reconstruct which attachments made a specific PDF.

PR E — UI & UX (medium)
- Add uploader enhancements: drag-and-drop ordering, options modal, progress bar, and post-merge artefact preview (render first page thumbnail), plus download and provenance link.
- Acceptance: user can reorder pages before merging and see progress; download contains metadata and link to provenance.

PR F — Accessibility & validation (small)
- Run PDF/A validator and add a11y check; if tagging not available, surface export of text transcript.
- Acceptance: PDF/A pass when requested; a11y status displayed in UI.

5. Acceptance criteria & safety

- No data loss: merges must be idempotent and reversible (store mapping and tombstone original attachments if merge semantics require removal). Provide an undo/restore path for a configurable retention window.
- Resource bounds: queue worker enforces memory and time limits; large merges auto-fail to admin review or require explicit approval.
- Provenance completeness: every merge writes a provenance entry with inputs, outputs, options, checksums, actor, and timing.
- Tests: unit tests for validation and scoring, integration tests for small and large merges (using streaming), and a test that provenance entries are created and complete.

6. Quick wins

- Add a `--dry-run` validation endpoint that returns a human-readable validation report before merging; implement as a small controller method calling TiffMergeService::validateTiffFile.
- Implement DOI/hash fingerprinting to avoid merging if an equivalent PDF already exists (fast-path to skip rescan).
- Add a MergeTiffJob with a small wrapper that uses the `img2pdf` + `gs` (ghostscript) pipeline on UNIX for reliable PDF/A conversion (minimal dependencies).

7. Files to read / touch (where to implement)

- New service: packages/ahg-research/src/Services/TiffMergeService.php
- New job: packages/ahg-research/src/Jobs/MergeTiffJob.php
- Migration: packages/ahg-research/database/migrations/xxxx_xx_xx_create_research_merge_jobs_and_provenance.php
- Controller: packages/ahg-research/src/Controllers/TiffMergeController.php (validate, enqueue, download, provenance)
- Views: packages/ahg-research/resources/views/research/merge/uploader.blade.php and partials for progress + preview
- Tests: packages/ahg-research/tests/Unit/TiffMergeValidationTest.php and packages/ahg-research/tests/Feature/MergeFlowTest.php

Status: very good

Outstanding issue to work on (pick one)
1. Implement PR A — TiffMergeService skeleton + validation tests.  
2. Implement PR B — MergeTiffJob (queueable) with streaming merge pipeline.  
3. Implement PR D — provenance capture + merge_provenance table.  
4. Implement PR E — UI enhancements (uploader with reorder, progress, provenance link).  

Reply with the single digit (1–4) to pick which PR to start.