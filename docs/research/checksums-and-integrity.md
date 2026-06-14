# Checksums & Integrity — Research module

Purpose

This document audits the current checksums & fixity posture for the Research module, lists concrete gaps and incomplete code, and proposes practical enhancements with a staged implementation plan. Place this file at: /usr/share/nginx/heratio/docs/research/checksums-and-integrity.md

1. Gaps (what is missing now)

- No standardised checksum column on research object records. Some import paths compute a hash transiently but there is no canonical `checksum_algorithm` + `checksum` persisted field available across research_* tables.
- No fixity-monitoring table or historical log that records verification runs, results, and remediation actions.
- Upload paths do not consistently compute checksums at ingest and store them atomically with the file record (race conditions possible).
- No scheduled fixity check job exposed to operators (a queue worker exists for other packages but not a research-specific fixity sweep).
- No alerting or dashboard for failed fixity checks (only low-level storage errors appear in logs).
- Verification is not tied to provenance: when a checksum fails we do not create an auditable Event that records context, operator, or automated remediation attempt.

2. Incomplete code (where I found partial or stubbed implementations)

- Import adapters and upload controllers include comments or TODOs mentioning `sha256`/`md5` checks but do not write a canonical checksum field into research-related tables.
- There is no dedicated `FixityJob` or `FixityService` under packages/ahg-research/src/Services — only generic storage helpers in other packages that compute hashes on demand.
- No migration exists to add the typical columns (`checksum_algorithm`, `checksum`, `checksum_created_at`, `fixity_status`) to research object tables (e.g. research_sources, research_files, research_binaries) — this is required for consistent storage.
- UI views do not show fixity status or historical verification results; tooltip hints exist in a few blades but are not wired to real data.

3. Enhancements and suggested features (concrete, practical)

- Canonical checksum fields
  - Add `checksum_algorithm` (varchar, e.g. 'sha256'), `checksum` (char(64) for sha256), `checksum_created_at` (timestamp), and `checksum_source` (enum: 'upload','import','external') to every file/object table that stores binary or research-source content.

- Fixity provenance table
  - Create `research_file_fixity` (id, file_id, algorithm, checksum_expected, checksum_actual, status enum (ok, failed, unavailable), checked_at, checked_by, job_id, evidence json, created_at). This table stores every verification run and is the audit trail for remediation.

- Compute checksum on ingest atomically
  - When uploading a file (direct upload, import adapter), compute the checksum while streaming and persist it in the same DB transaction that creates the file record. Use temporary multipart storage if needed to avoid double-copy.

- Scheduled fixity sweeper (queue job)
  - Add an artisan command `php artisan research:fixity:check [--batch=...]` that enqueues `ResearchFileFixityJob` jobs grouped by storage backend/volume. Jobs compute current checksum and write a `research_file_fixity` row.

- Dashboard & alerting
  - Add an admin tile that shows recent fixity failures and an export. Send operator email / webhook on repeated failures (configurable threshold: e.g. 3 consecutive failures within 7 days).

- Automatic remediation workflow
  - For supported backends (S3/OSS), attempt a re-ingest from a known good replica, or mark the file as corrupted and create a `repair_request` record for manual recovery. All remediation actions must create provenance Events.

- Test harness & monitoring
  - Add unit tests for checksum calculation, integration tests that write a file then mutate it on disk to ensure the fixity failure path is exercised, and an e2e test that runs the fixity job and checks `research_file_fixity` entries.

- Standards & algorithms
  - Use SHA-256 as canonical algorithm (no MD5 for integrity checks). Store algorithm name with the checksum so future algorithm migration is possible.

4. Staged implementation plan (PRs)

PR A — Migrations + model changes (small)
- Add migration(s) to add `checksum_algorithm`, `checksum`, `checksum_created_at` to the file/object tables used by Research (research_files, research_sources). Add `research_file_fixity` table.
- Add Eloquent attributes/casts to the relevant models.
- Acceptance: migrations run; new columns exist; models map fields.

PR B — Ingest-time checksum (medium)
- Implement a `ChecksumService` in packages/ahg-research/src/Services/ChecksumService.php with a `computeAndStoreChecksum(FileUploadContext $ctx)` method.
- Wire the service into upload and import adapters so checksum is computed while streaming and stored atomically.
- Acceptance: uploading a file sets checksum_algorithm='sha256' and checksum populated.

PR C — Fixity job + worker + API (medium)
- Implement `ResearchFileFixityJob` (queueable) that computes checksum and writes `research_file_fixity` row; implement artisan command `research:fixity:check` that schedules jobs in batches.
- Add a light API endpoint for operators to request immediate verification for a file or a set (POST /research/fixity/check).
- Acceptance: running the command enqueues jobs, jobs populate fixity table.

PR D — Dashboard & alerting (medium)
- Add admin UI tile and a detailed fixity list page (searchable, filterable). Implement alerting config and a notification routine on repeated failures.
- Acceptance: fail a file hash in tests, ensure the admin tile shows the failure and an email/webhook is triggered if configured.

PR E — Remediation & provenance (larger)
- Implement remediation strategies (replica re-ingest, repair request management) and tie every remediation attempt to a provenance/audit Event (RiC Event if appropriate). Ensure a revert/undo path for merges that may occur as part of remediation.
- Acceptance: automated remediation attempt creates an Event and updates fixity rows; operators can view remediation history and abort.

5. Tests and acceptance criteria (must-have)
- Unit tests for ChecksumService compute function (sha256 correctness).
- Integration test that uploads a file, then the job runs and writes fixity ok. Then mutate the stored file and re-run job to assert a failed fixity row is created and alert triggered (mocking mail/webhook in test).
- Grep test to ensure no hard-coded MD5 remains where fixity uses sha256.

6. Files to create or modify (suggested)
- packages/ahg-research/database/migrations/2026_xx_xx_add_checksums_to_research_files.php
- packages/ahg-research/database/migrations/2026_xx_xx_create_research_file_fixity.php
- packages/ahg-research/src/Services/ChecksumService.php
- packages/ahg-research/src/Jobs/ResearchFileFixityJob.php
- packages/ahg-research/src/Http/Controllers/ResearchFixityController.php (admin endpoints)
- packages/ahg-research/resources/views/research/admin/fixity.blade.php
- packages/ahg-research/tests/Feature/FixityIntegrationTest.php

7. Quick commands & operator notes
- Run migrations (testing first):
  cd /usr/share/nginx/heratio
  export APP_ENV=testing
  php artisan migrate --env=testing --force

- Enqueue a one-off fixity sweep:
  php artisan research:fixity:check --batch=100

- Inspect recent fixity rows:
  php artisan tinker -q <<'PHP'
  print_r(DB::table('research_file_fixity')->orderBy('checked_at','desc')->limit(20)->get()->toArray());
  PHP

Status: very good

Next action — outstanding issue to work on
1. Implement PR A (migrations + model fields) and post the unified patch for review.  
2. Implement PR B (ChecksumService + ingest-time compute + tests).  
3. Implement PR C (Fixity job + artisan command + API).  
4. Implement PR D (Dashboard, alerting and remediation UI/workflow).

Reply with the single digit (1–4) to pick which PR to start.