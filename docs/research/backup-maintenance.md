Backup & Maintenance — Research module

1. Gaps (what's missing right now)
- No documented, package-scoped backup runbook covering file storage, DB, and object-store for research-specific artefacts (ingests, notebooks, replication packs).
- No automated verification steps for backups of research-specific assets (no checksum/fixity for research file uploads and no regular restore smoke test documented).
- No per-slice retention & maintenance policy mapping for long-lived research artefacts (replication packs, large datasets, video/audio recordings).
- Missing operator checklist for regular maintenance tasks (migrations, queue drains, storage quotas, search-index rebuilds tied to research imports).

2. Incomplete code (places to inspect / technical debt)
- packages/ahg-research: lacks explicit backup metadata writers. Search for where large uploads are stored (file-storage calls) and ensure a backup tag is written.
- No automated `artisan` commands under packages/ahg-research for: research:backup-manifest, research:verify-backup, research:restore-sample. (These are missing or partial.)
- Some long-running import jobs (ingest workers) do not checkpoint progress into a durable job-status table; this prevents safe resume after a partial restore.
- No documented or implemented snapshot/roll-forward strategy for the research-specific tables (e.g. research_memory, research_claim, replication_pack, analysis snapshots).

3. Enhancements and suggestions (concrete, implementable)
- Backup runbook & automated commands
  - Add CLI helpers in packages/ahg-research: `php artisan research:backup-manifest` (writes a JSON manifest listing research-related tables, file paths, checksums, and export timestamps) and `php artisan research:verify-backup` (verifies checksums and required file existence).
  - Provide `research:restore-sample <manifest>` to restore one project for operator smoke-testing.
- Fixity & checksum
  - On research file upload, write a fixity checksum to a `research_file_fixity` table and store alongside the object; include these in backup manifests and verification runs.
- Snapshot & point-in-time strategy
  - Use DB dump + file snapshot for full backups; support per-project export snapshots (export project X as a self-contained bundle with metadata, files, and provenance).
  - Add an incremental snapshot facility using binlog/WAL-aware incremental dumps (document recommended schedule for large installations).
- Job checkpointing & resumability
  - Add a `research_job_runs` table to record progress for long imports/analysis passes so restores can resume jobs without duplication.
- Retention & quotas
  - Document per-slice retention defaults (replication packs: keep full snapshots for 7 years, then thin; notebooks: retain all, but dedupe attachments > 1GB after 2 years unless linked to an output).
  - Implement a `research:prune-large-attachments` job with a dry-run mode and reporting for operator approval.
- Restore drills & CI
  - Add a scheduled monthly restore drill (automated smoke restore of a single test project) that runs on a non-prod instance and reports status via the operator inbox.
  - Add unit/integration tests for backup manifest generation and verification.
- Observability & alerts
  - Emit metrics when a backup run completes / fails, when fixity mismatches occur, and when restore drills fail. Hook into existing observability infrastructure.

4. Staged implementation plan (PR-level)
- PR 1 — Backup manifest writer + CLI
  - Add `ResearchBackupService::writeManifest(Project|null $project)` and artisan command `research:backup-manifest`.
  - Acceptance: manifest JSON produces a compact list of tables, rows count, file references, and checksums.
- PR 2 — Fixity on upload + verification job
  - On file uploads in ResearchFileService, compute SHA256 and write to `research_file_fixity` with timestamp and uploader id.
  - Add `research:verify-backup` to read a manifest and verify file existence + checksums.
- PR 3 — Restore-sample + checkpointing
  - Implement `research:restore-sample <manifest> --project=<id>` that can restore a single project to a sandbox schema and a `research_job_runs` table for resumable imports.
- PR 4 — Retention jobs + prune CLI
  - Implement `research:prune-large-attachments` with dry-run and commit flags, plus scheduled job wiring and operator notifications.
- PR 5 — Restore drill + CI
  - Add a CI job (or scheduled job) that runs a restore-sample against a nightly backup manifest and notifies on failure.

Files to add/modify (suggested)
- packages/ahg-research/src/Services/ResearchBackupService.php (new)
- packages/ahg-research/src/Console/ResearchBackupManifestCommand.php (new)
- packages/ahg-research/src/Console/ResearchVerifyBackupCommand.php (new)
- packages/ahg-research/src/Console/ResearchRestoreSampleCommand.php (new)
- packages/ahg-research/database/migrations/2026_06_XX_create_research_file_fixity.php (new)
- packages/ahg-research/database/migrations/2026_06_XX_create_research_job_runs.php (new)
- packages/ahg-research/src/Services/ResearchFileService.php (add fixity write)
- docs/research/backup-maintenance.md (this file)

Acceptance criteria (how we know it's done)
- `php artisan research:backup-manifest` produces a manifest and exits 0. The manifest includes at least one project export and file checksum entries.
- `php artisan research:verify-backup --manifest=path.json` returns 0 for a valid manifest and non-zero on checksum mismatches.
- A restore-sample run can restore a project into a sandbox schema (or temp DB) and the restored project is loadable in the UI.
- Fixity table contains SHA256 for every research file uploaded after the feature lands.
- Monthly automated restore-drill job succeeds in CI or raises a ticket in the operator inbox when failing.

Operational notes
- For very large installations, advise object-store snapshots + DB logical dumps with point-in-time strategies (WAL/GTID). Document the recommended cadence (daily DB + hourly incremental; weekly full object-store snapshot; monthly restore drill).
- Keep manifests small by supporting project-level manifests rather than always doing full-system manifests.
- Encryption: if research files are encrypted at rest, manifest verification should include a path to decrypt in the verification environment or verify on a per-file metadata basis only.

Status: very good

Next action — outstanding issue to work on
1. Implement PR 1: backup manifest writer + artisan command (small patch).  
2. Implement PR 2: fixity write on upload + verify job (patch + migration).  
3. Implement PR 3: restore-sample command + job checkpointing (larger patch).  
4. Run a repo search to find all research file upload sites so fixity can be instrumented comprehensively.
