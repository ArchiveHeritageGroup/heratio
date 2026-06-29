# Data Ingest is wired to the Digital Preservation pipeline (Archivematica-style)

> Audience: archivist, digital preservation officer, developer, sysadmin
> Status: shipped. Applies to both codebases (Laravel Heratio + AtoM-AHG).

Every digital object created by a Data Ingest commit now automatically flows
through a preservation baseline, the way an Archivematica transfer runs its
micro-services. No operator toggle is required - the baseline runs on every
ingested digital object so it appears in the Digital Preservation dashboard
with full provenance.

## What the baseline does, per digital object

1. **Fixity checksum** (SHA-256) - the baseline used by later fixity checks.
2. **Format identification** (PRONOM) - written into the preservation format
   registry so at-risk formats surface in reports.
3. **Virus scan** (ClamAV) - on the AtoM side when ClamAV is present; on the
   Laravel side virus scanning stays on the ahg-ai-services path.
4. **PREMIS `ingestion` event** - one event per object linking the digital
   object and its information object, so the ingest is auditable.

All steps are fail-soft and idempotent: a preservation hiccup (down GPU,
missing tool, unreadable file) is logged and skipped - it never breaks the
already-saved ingest row.

## Where it is wired

### Laravel Heratio
- `packages/ahg-ingest/src/Services/IngestCommitRunner.php`
  - `commitOneRow()` calls new `runPreservationBaseline(int $ioId, int $doId)`
    right after IO+DO creation and the AI steps.
  - The baseline resolves `AhgPreservation\Services\PreservationService`
    (`generateChecksum`, `logEvent`) and
    `AhgPreservation\Services\PronomIdentificationService`
    (`identifyDigitalObject`) via `class_exists()` + `app()`, so ahg-ingest
    still installs cleanly without ahg-preservation.

### AtoM-AHG (psis / WDB / archaeology)
- `ahgIngestPlugin/lib/Services/IngestCommitService.php`
  - `executeJob()` calls new `runPreservationBaseline(int $jobId, object $session)`
    after the row loop. It uses the global `\PreservationService`
    (`generateChecksums`, `identifyFormat`, `scanForVirus`, `logEvent`).
  - Format-id / virus-scan inside the baseline are skipped when the session
    already requested them as explicit processing steps (no double work);
    checksum + PREMIS event always run.
  - Fixed a latent bug in `buildSipPackage()`: it referenced the non-existent
    `\AhgPreservationPlugin\PreservationService` namespace (the class is global
    `\PreservationService`), passed the information-object id to a
    digital-object checksum call, and called `logEvent()` with the wrong
    signature - so the SIP checksum + PREMIS block silently never ran. Now
    corrected.

## Central Dashboard surfacing

The Central Dashboard's **Data Ingest** card now shows live counts (jobs
completed, records, objects, last-ingest time) and a green
"Ingested objects are auto-preserved" line linking to PREMIS Events and the
Fixity log.

- Laravel: `packages/ahg-reports/src/Services/ReportService.php`
  (`getReportStats()` adds `ingest_*` and `preserved_objects` / `premis_events`,
  each guarded by try/catch) + `resources/views/dashboard.blade.php`.
- AtoM-AHG: `ahgReportsPlugin/modules/reports/actions/actions.class.php`
  (`getReportStats()`) + `modules/reports/templates/indexSuccess.php`.

All dashboard stat queries are guarded so a missing ingest/preservation table
cannot 500 the dashboard.

## Operator notes

- The preservation tables (`preservation_checksum`, `preservation_event`,
  `preservation_object_format`, ...) must exist - i.e. the preservation
  plugin/package installed - for the baseline to record anything. If it is not
  installed the baseline is a silent no-op.
- On AtoM-AHG, the `ahgPreservationPlugin` must be enabled + symlinked (the
  standard plugin-enable sequence) for `\PreservationService` to load.
