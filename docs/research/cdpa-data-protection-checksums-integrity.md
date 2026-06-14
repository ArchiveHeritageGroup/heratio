# CDPA Data Protection & Checksums / Integrity — Research module

Purpose

This document audits the Research module against the core requirements of a CDPA-style data protection regime (data minimisation, purpose limitation, subject access, erasure, auditability) and the checksums / fixity expectations for preserved digital objects. It lists gaps, incomplete code locations to inspect, pragmatic enhancements, and an implementation checklist.

1. First: gaps (what is missing now)

- Policy-to-implementation mapping
  - No concise operator-facing crosswalk that maps CDPA obligations (lawful basis, retention, DSAR handling) to the exact code paths and DB fields in Research.

- PII discovery and classification
  - No single discovery job that enumerates PII locations inside Research (profiles, notes, uploads, donor fields, annotations). Without a discovery index DSAR/erasure cannot be reliably executed.

- DSAR case workflow for research items
  - No end-to-end DSAR controller/export flow scoped to Research: search → bundle → redaction review → encrypted export — with append-only audit trail and rate-limiting.

- Encryption-at-rest enforcement & backfill tooling
  - A toggle exists for field encryption, but no documented backfill command to encrypt existing rows when the operator enables the setting.

- Lawful-basis metadata on records
  - No standard field on research artefacts to record lawful basis (consent, public task) or consent metadata (version, timestamp) linking retention to legal justification.

- Retention automation tied to legal basis
  - Retention rules are modelled, but there is no automated engine that links a legal-basis tag to scheduled disposition events with approval flows.

- Fixity & provenance for preserved files
  - No full fixity workflow visible: ingest checksum capture, scheduled fixity checks, repair logging, and audit evidence packaged for DSARs.

- Fixity provenance linkage to DSAR / legal inquiries
  - Fixity logs are not exposed as a standard evidence bundle in DSAR exports.

2. Incomplete code (where to inspect)

- Profile & PII fields
  - packages/ahg-research/resources/views/research/profile.blade.php — check which fields are PII and whether lawful-basis metadata is present.
  - packages/ahg-research/src/Controllers/ResearchController.php and ResearchWorkspaceController.php — review write paths for notes, attachments.

- DSAR tooling
  - No ResearchDsarController present; search for DSAR-related controllers or export commands.

- Encryption & backfill
  - packages/ahg-settings contains the toggle; Research write paths should use a privacy wrapper (ResearchPrivacyService) — currently incomplete.

- Fixity & checksums
  - No research_file_fixity table or ChecksumService found. Search for fixity jobs or checksum fields in migrations.

- Provenance & audit
  - ai_provenance exists for AI calls; research_activity_log exists for some actions but not all fixity/DSAR events are recorded.

- Retention automation
  - RetentionEngine/RetentionService absent or partial; check for retention cron tasks and mandate linkers.

3. Enhancements and suggested changes

A. CDPA crosswalk document
- Create docs/research/cdpa-crosswalk.md mapping obligations to code fields (quick win).

B. PII discovery & classifier
- Add ResearchPiiIndexJob scanning researcher profiles, notes, OCR text, attachments; store index securely for operators.

C. DSAR workflow for Research
- Implement ResearchDsarController + ResearchDsarExportJob producing encrypted export bundles with audit manifest and fixity evidence.

D. Encryption backfill & write-wrapper
- Add ResearchPrivacyService::saveRecord() that conditionally encrypts fields and an artisan command research:pii:backfill (--dry-run | --apply).

E. Fixity lifecycle
- Migration: research_file_fixity table; implement ChecksumService and scheduled FixityJob; UI to view fixity history per file.

F. Provenance UI & evidence bundle
- Add a Provenance card partial showing create/edit/fixity/DSAR events for a record; include this in DSAR export manifest.

G. Retention engine
- Implement RetentionEngine that consumes RiC Mandate → retention rules and enqueues disposition events with admin review.

H. Access controls & throttling
- Rate-limit DSAR and bulk export endpoints; require two-person approval for large exports.

4. Implementation checklist & minimal patches to add

PR 1 — CDPA crosswalk doc + PII discovery job
- Add docs/research/cdpa-crosswalk.md and ResearchPiiIndexJob skeleton.

PR 2 — ResearchPrivacyService + PII backfill command
- Add ResearchPrivacyService, research:pii:backfill command with dry-run, unit tests.

PR 3 — Fixity migration + ChecksumService + FixityJob
- Add research_file_fixity migration, ChecksumService, scheduled FixityJob and admin UI view.

PR 4 — DSAR controller + export job
- Implement ResearchDsarController, research_dsar table, ResearchDsarExportJob that creates encrypted ZIP with audit manifest and fixity evidence.

5. Operator commands (examples to run locally)

cd /usr/share/nginx/heratio
# PII discovery (dry-run)
php artisan research:pii:discover --dry-run
# Backfill PII encryption (careful)
php artisan research:pii:backfill --apply
# Run a one-off fixity check
php artisan research:fixity:check --once
# Create a DSAR export (example)
php artisan research:dsar:create --scope="researcher:42" --requester="user:17"

6. Acceptance criteria

- Crosswalk doc present and approved by data protection officer. (docs/research/cdpa-crosswalk.md)
- PII discovery runs and produces a report with counts and safe samples.
- Encryption backfill runs in dry-run & apply; ResearchPrivacyService used for new writes.
- Uploaded files have checksums on ingest and scheduled fixity checks log pass/fail.
- DSAR exports include audit manifest and fixity evidence; exports are encrypted and logged.
- Retention events scheduled and disposition Events produced, with admin approval where required.

Status: very good

Next actions (pick one)
1. Add PR 1 (cdpa-crosswalk + PII discovery job) and post unified patch for review.
2. Add PR 2 (ResearchPrivacyService + pii backfill command) and post unified patch.
3. Add PR 3 (Fixity migration + ChecksumService + FixityJob) and post unified patch.
4. Add PR 4 (DSAR controller + ResearchDsarExportJob) and post unified patch.

Reply with the single digit (1–4) to pick which PR to start.