# PRONOM format identification and the format risk registry in Heratio

**Summary.** Knowing exactly what file formats you hold is the bedrock of digital
preservation - you cannot plan migration or assess obsolescence risk for formats
you have not identified. PRONOM is the technical format registry maintained by The
National Archives (UK); each format has a PRONOM Unique Identifier (PUID) such as
`fmt/353` (TIFF) or `fmt/276` (PDF 1.7). Proper identification reads the file's
internal byte signature rather than trusting its extension, which catches
misnamed and disguised files. Heratio performs PRONOM-aware identification via
Siegfried, stores the resulting PUID per object, and maintains a format registry
with preservation risk levels that feed migration planning and the maturity
self-assessment.

## The concept

- **PUID.** A stable, registry-backed identifier for a format and version. Two
  files can both be "a TIFF" but a PUID pins the exact variant, which matters for
  obsolescence and migration decisions.
- **Signature-based identification.** Tools such as Siegfried and DROID read a
  file's magic bytes / internal structure and match them against PRONOM
  signatures, returning a confidence (certain / high / medium / low). Extension-
  only identification is unreliable; signature matching is authoritative.
- **Format risk registry.** Once you know your formats, you classify them by
  preservation risk (open standard and well-supported = low; proprietary or
  obsolete = high / critical) so you can prioritise migration. This is OAIS
  Preservation Planning in practice.

## How Heratio addresses this

- **Siegfried (PRONOM) identification.** `AhgPreservation\Tools\SiegfriedTool`
  shells out to Richard Lehane's Siegfried, parses its JSON, and extracts the
  PRONOM PUID, format name, and version per file. The scan command is
  `php artisan preservation:scan {ioId}` (or a stale sweep with
  `--stale-days` / `--limit`, and tool selection via `--tools=siegfried,clamav`).
  The orchestration service is `AhgPreservation\Services\FixityScanService`.
- **Ingest-time format identification.** The ingest pipeline can run format
  identification as a per-file step, gated by the `ingest_format_id` setting
  (`AhgIngest\Services\IngestService` reads `process_format_id`). This means
  formats can be identified at the moment of ingest, not only on a later sweep.
- **Admin surfaces.** Format identification status and PUID coverage are at
  `GET /admin/preservation/identification` (route `preservation.identification`);
  the format registry / at-risk formats view is
  `GET /admin/preservation/formats` (route `preservation.formats`).
- **Risk feeds maturity.** The preservation-maturity assessment reads
  `digital_object.mime_type` (basic identification), `preservation_object_format.puid`
  (PRONOM identification), and `preservation_format` joined with
  `preservation_action` (a monitored risk registry) to score the Content / file
  formats functional area (see `dp-08-ndsa-levels`).
- **Normalisation / migration** of at-risk formats is covered in
  `dp-10-significant-properties`.

## Gaps / not yet

- Identification depends on the Siegfried binary being installed on the host; if
  it is absent, the scanner falls back to a null / MIME-only path and PUID
  coverage stays low (which the maturity assessment correctly reports as a gap).
- The PRONOM signature set is only as current as the installed Siegfried build;
  Heratio does not itself sync the live PRONOM registry on a schedule, so very
  new formats may identify with lower confidence until the binary is updated.
