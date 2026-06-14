Digital Preservation — Research module

Purpose

This document audits the current Digital Preservation surface as it relates to the Research module, lists concrete gaps and incomplete code, and proposes practical enhancements and a staged implementation plan. Place: /usr/share/nginx/heratio/docs/research/digital-preservation.md

1. Gaps (what is missing now)

- End-to-end ingest→preserve pipeline: there is no single, documented pipeline that guarantees archival ingest (validate → bag/pack → store → fixity → register). Importers create objects but do not consistently produce preservation packages.
- Fixity scheduling and monitoring: fixity checks exist in parts of the platform, but there is no visible scheduled job and dashboard for research collections specifically.
- Preservation metadata (PREMIS/METS/BagIt) exports: research objects are not consistently exported into standard preservation packaging formats; RiC and descriptive metadata are present but not preservation manifests.
- Storage lifecycle and replication policy hooks: there is no documented replication policy for research deposits (hot/warm/cold), nor automation for migration or tape/archive tiers.
- Event provenance for preservation actions: preservation events (ingest, bagging, fixity, migration) are not modelled as domain Events with provenance links back to the Research project/agent.
- Preservation testing and rehearsal: no test harness to rehearse restore workflows or to validate preserved packages periodically.

2. Incomplete code (where I found partial or stubbed implementations)

- packages/ahg-digital-preservation/ (if present): look for partial services or install.sql — many installations include a preservation helper but research-specific hooks are missing.
- Import adapters in packages/ahg-research/src/Services/*ImportAdapters: adapters create storage objects but do not always create a preservation package or emit a preservation event.
- Fixity checker service: there are references to fixity checking utilities and scheduled jobs in the platform but no research-scoped schedule or dashboard.
- Exports: RiC/JSON-LD exporter exists but does not produce BagIt or PREMIS manifests for preservation.
- Tests: no unit/integration tests that exercise ingest→preserve→restore workflows in the research package.

3. Enhancements and suggested features (concrete, practical)

- Preservation Pipeline (Service + Jobs)
  - ResearchPreservationService: orchestrates ingest validation, transcription of metadata, creation of a BagIt package (or Zipped preservation package), uploads to preservation storage, registers object with fixity, and emits a preservation Event (RiC Event if required).
  - Job flow: ValidateImportJob -> CreateBagJob -> UploadPreservationPackageJob -> RegisterFixityJob -> PreservationCompleteEvent.

- Standard packaging support
  - BagIt + PREMIS generator: produce a BagIt package with a PREMIS XML manifest describing preservation actions and provenance. Include checksums, file lists, and migration policy hints.
  - Optional PDF/A or TIFF-A conversion pipelines for text/imagery where required by local policy.

- Fixity & monitoring
  - Scheduled FixityJob per collection with threshold alerts: maintain last-good, report anomalies, provide a dashboard tile in Research Admin.
  - Store fixity history in a preservation_fixes table and integrate with existing `fixity` helpers.

- Storage lifecycle & replication
  - Add storage-tier metadata to preservation packages and a replication policy engine that can schedule migration/copy to secondary storage when a policy triggers.
  - Expose replication status and S3/Glacier (or local tape) jobs in admin UI.

- Provenance & events
  - Model preservation actions as Events with provenance links to the Research project, agent, and the original digital object. Persist these as RiC Events and in the local `research_preservation_events` table.
  - Emit messages on an event bus so other services (RiCBridgeService, analytics) consume them.

- Restore & rehearsal
  - Add a RestoreJob that can restore a preservation package to a test bucket and a RehearseRestore command that periodically validates that restores succeed end-to-end.
  - Keep a small sample set of critical research projects under regular rehearsal.

- Tests & CI
  - Unit tests for bag creation and PREMIS output. Integration tests that run a small ingest, create package, upload to test storage (local), run fixity, and then restore.

4. Staged implementation plan (PRs)

PR A — Preservation service skeleton + BagIt writer (small)
- Add: packages/ahg-research/src/Services/ResearchPreservationService.php with stub methods: createBag($objectId), uploadBag($bagPath), registerFixity($bagId).
- Add unit tests for bag manifest generation (simple file list + checksum).
- Acceptance: createBag returns a valid BagIt folder with manifests for a small sample object.

PR B — Ingest hooks + provenance events (medium)
- Modify import adapters to call ResearchPreservationService::createBag after ingestion (configurable by collection/feature flag).
- Emit PreservationEvent (DB row + RiC Event enqueue) when bag is created and when upload is complete.
- Acceptance: import of a sample file produces a preservation package and a PreservationEvent row.

PR C — Fixity scheduler + dashboard (medium)
- Add scheduled job (php artisan research:preservation:fixity) and a simple admin dashboard widget (packages/ahg-research/resources/views/admin/preservation_status.blade.php) that lists latest fixity results and anomalies.
- Acceptance: scheduled job runs and populates preservation_fixes table; dashboard shows latest status.

PR D — Storage replication & lifecycle (larger)
- Add policy table and replication jobs. Implement a simple replication adapter (copy to second bucket/path) and a UI showing replication progress.
- Acceptance: policy triggers copy and replication status visible.

PR E — Restore rehearsal + CI tests (larger)
- Implement restore job, rehearsal scheduler, and integration tests that run a full roundtrip for a test object.
- Acceptance: rehearsal passes in CI on a small sample dataset.

5. Acceptance criteria & safety

- No destructive auto-actions: preservation pipeline never deletes source data; merges/normalisations are separate steps.
- All preservation actions are auditable: every bag creation/upload/fixity run has an Event row with provenance (actor, service, timestamp, origin request id).
- Test coverage for bag creation and restore works locally in CI before deploying to production.

6. Quick wins

- Add a ResearchPreservationService skeleton and BagIt manifest writer (PR A). (small)
- Add a preservation event immediately after existing imports so operators can see packages created even before automated uploads exist. (tiny)
- Add a small admin tile showing "Preservation health" (last fixity pass) that uses existing fixity rows if present. (tiny)

Files to touch / implement
- packages/ahg-research/src/Services/ResearchPreservationService.php
- packages/ahg-research/database/migrations/xxxx_xx_xx_create_research_preservation_events.php
- packages/ahg-research/src/Jobs/CreateBagJob.php, UploadPreservationPackageJob.php, RegisterFixityJob.php
- packages/ahg-research/resources/views/admin/preservation_status.blade.php
- packages/ahg-research/tests/Integration/PreservationRoundtripTest.php

Status: very good

Next action — outstanding issue to work on
1. Implement PR A: ResearchPreservationService skeleton + BagIt writer + unit tests.  
2. Wire import adapters to call the preservation hook and emit PreservationEvent rows (PR B).  
3. Add scheduled FixityJob and preservation dashboard tile (PR C).  
4. Implement RestoreJob + RehearseRestore command and integration tests (PR E).