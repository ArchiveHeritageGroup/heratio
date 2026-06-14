# CDPA Data Protection & Checksums / Integrity — Research module

Purpose

This document audits the Research module against the core requirements of the CDPA-style data protection regime (data minimisation, purpose limitation, subject access, erasure, auditability) and the checksums / fixity expectations for preserved digital objects. It lists gaps, incomplete code locations to inspect, pragmatic enhancements, and an implementation checklist. Save path: /usr/share/nginx/heratio/docs/research/cdpa-data-protection-checksums-integrity.md

1. First: gaps (what is missing now)

- Policy-to-implementation mapping
  - There is no short, operator-facing mapping that links each CDPA obligation (e.g. lawful basis, retention, data minimisation, DSAR handling) to the exact code paths and DB fields in the Research module that implement them. Operators need a compact crosswalk.

- PII discovery and classification
  - No single discovery job that enumerates where PII lives inside Research (researcher profiles, notes, uploads, donor fields, free-text annotations). Without a discovery index you cannot reliably answer DSAR/erasure requests.

- DSAR case workflow for research items
  - There is no end-to-end DSAR controller/exports flow scoped to research: search → bundle → redaction review → export — with audit trail and throttling.

- Encryption-at-rest enforcement & backfill tooling
  - A toggle exists in settings for encrypting fields, but there is no documented backfill command that safely encrypts existing rows when the operator enables encryption.

- Consent / lawful-basis metadata on records
  - There is no standard place on research artefacts to record the lawful basis (consent, legitimate interest, public task) or consent details (consent version, timestamp) that justify retaining certain PII.

- Retention automation tied to legal basis
  - Retention rules are modelled in RiC/mandates, but there is no guaranteed automated linkage from a legal-basis tag on a record to scheduled disposal events with audit checks.

- Fixity & provenance for preserved files
  - There is no visible, documented full fixity workflow for research file uploads (compute checksum on ingest, store checksums, scheduled fixity checks, alert on mismatch and store remediation actions).

- Fixity provenance linkage to CDPA responses
  - Fixity logs are not exposed as evidence for a DSAR or legal inquiry (e.g., "this file has not changed since X"); the research UI lacks an obvious provenance card that shows fixity events.

2. Look at incomplete code (where to inspect / what looks partial or stubbed)

Notes: these are recommended concrete paths to examine; they may already exist partially in your tree.

- Research researcher profiles & PII fields
  - packages/ahg-research/resources/views/research/profile.blade.php — ensure fields that are PII are clearly labelled and that server-side controllers persist lawful-basis metadata.
  - packages/ahg-research/src/Controllers/ResearchController.php and ResearchWorkspaceController.php — inspect write paths for free-text notes and attachments.

- DSAR tooling
  - packages/ahg-research/src/Controllers/ResearchDsarController.php — may not exist; search for DSAR or export endpoints in the research package.
  - packages/ahg-core/src/Commands/ — check for a generic dsar:export or archive-export command; if missing, add a Research-specific job.

- Encryption & backfill
  - packages/ahg-settings for the encryption toggle; packages/ahg-research/src/Services/ResearchService.php — check for encryption-aware write wrappers.
  - Missing: artisan command (e.g. research:pii:backfill) that can run a dry-run and then perform encryption updates transactionally.

- Fixity and Checksums
  - packages/ahg-research/src/Services/ChecksumService.php (may not exist); packages/ahg-research/database/migrations — check for file_checksum columns or a fixity table.
  - packages/ahg-research/src/Jobs/ — look for a FixityCheckJob or scheduled cron jobs; if absent, this is a gap.

- Provenance & Audit
  - ai_provenance table exists elsewhere for AI; search for audit logs and research_activity_log table usage (research_activity_log). Ensure every DSAR export and fixity event creates an auditable row.

- Retention automation
  - packages/ahg-research/src/Services/RetentionService.php (may not exist) — look for scheduled disposal jobs tied to mandate/retention rules. If not present, it's incomplete.

3. Enhancements and suggested concrete changes

A. Operator-facing CDPA crosswalk document
- Implement a short crosswalk document (docs/research/cdpa-crosswalk.md) mapping obligations to code paths and data fields (profile.email → lawful_basis.contact, file.upload → file_checksum + fixity_events). This should be the first deliverable (quick win).

B. PII discovery index & classifier
- Add a periodic discovery job (ResearchPiiIndexJob) that scans research_text_fields, researcher notes, attachments' OCR text, and classifies PII types (name, id number, email, health data). Store summary counts and sample hits in a secure index (not publicly expose the samples).
- Acceptance: operator command runs and writes /storage/secure/research_pii_index.json and a metrics row.

C. DSAR workflow for research
- Add ResearchDsarController + ResearchDsarExportJob that:
  - Accepts DSAR case creation (who requested, scope, date range)
  - Runs a safe export (redacts or flags sensitive items), packages provenance (audit log + fixity checks), and produces an encrypted export bundle for delivery.
- Acceptance: running the controller creates a DSAR case row and a downloadable (zip) bundle with audit manifest.

D. Encryption backfill & write-wrapper
- Implement ResearchPrivacyService::saveRecord($model,$data) that wraps writes: if encryption_enabled() then encrypt designated fields before persisting.
- Add a artisan command research:pii:backfill --dry-run that lists candidate rows and optionally replaces plaintext with encrypted values while logging before/after checks in an append-only audit table.

E. Fixity lifecycle
- Add migration for research_file_fixity (id, file_id, checksum, algo, checked_at, status, repaired_by, repaired_at, notes).
- Implement ChecksumService::computeChecksum(filePath, algo) and FixityJob that runs daily and records events; integrate alerting for repeated failures.
- Acceptance: uploaded files have a checksum recorded on ingest; scheduled check reports pass/fail and the log is queryable via UI.

F. Provenance UI & evidence bundle
- Add a small Provenance card component (Blade partial) that shows for a given file or record: create event, every edit event, every fixity check (with timestamps and results), and DSAR export occurrences.
- This card should be linkable from the DSAR export manifest and the research admin UI.

G. Retention automation (mandate-driven)
- Implement a RetentionEngine that consumes the RiC Mandate → retention rule path and enqueues DispositionEvents when records fall due; each disposition must create an auditable Event row and optionally archive or destroy the object according to retention.

H. Access controls & throttling
- Add rate-limits and provenance for export endpoints (e.g., DSAR exports, bulk downloads). Ensure export endpoints require strong operator auth and two-person approval for large exports.

4. Implementation checklist & minimal patches to add

PR 1 — CDPA Crosswalk doc + PII discovery job (small)
- Add docs/research/cdpa-crosswalk.md and a ResearchPiiIndexJob skeleton that scans selected fields and writes an index (dry-run first).

PR 2 — ResearchPrivacyService + PII backfill command (medium)
- Add ResearchPrivacyService with saveRecord wrapper, add artisan command research:pii:backfill with --dry-run and --apply flags, write unit tests for encryption behaviour.

PR 3 — Fixity migration + ChecksumService + FixityJob (medium)
- Migration for research_file_fixity table, ChecksumService implementation (sha256 default), and scheduled FixityJob; admin UI to view last fixity state.

PR 4 — DSAR controller + export job (medium-large)
- Implement ResearchDsarController, research_dsar table, and ResearchDsarExportJob that composes an encrypted ZIP of matched results and includes an audit manifest and fixity evidence.

PR 5 — RetentionEngine + mandate link (larger)
- Wire mandates to retention rules, implement RetentionEngine job, add disposition Event creation and admin review queue.

5. Operator commands to run locally (copy/paste)

# Run PII discovery (dry-run)
cd /usr/share/nginx/heratio
php artisan research:pii:discover --dry-run

# Apply PII backfill (careful: destructive unless backed up)
php artisan research:pii:backfill --apply

# Run fixity check once (ad-hoc)
php artisan research:fixity:check --once

# Create a DSAR export (example)
php artisan research:dsar:create --scope="researcher:42" --requester="user:17"

6. Acceptance criteria (how we declare done)

- CDPA crosswalk doc present and approved by the data-protection officer. (docs/research/cdpa-crosswalk.md)
- PII discovery runs and produces a report with counts and safe samples; operator can act on its output.
- Encryption backfill command runs successfully in dry-run and apply modes; new writes use ResearchPrivacyService wrapper.
- Uploaded files have checksums computed at ingest and scheduled fixity checks run with logs and alerts on failures.
- DSAR exports are produced with an audit manifest that includes fixity evidence and provenance; exports are encrypted and logged.
- Retention events are scheduled and produce disposition Events when executed, with admin approval where required.

Status: very good

Saved file path: /usr/share/nginx/heratio/docs/research/cdpa-data-protection-checksums-integrity.md

Next actions (pick one)
1. Add PR 1 (cdpa-crosswalk + PII discovery job) and post the unified patch for review.  
2. Add PR 2 (ResearchPrivacyService + pii backfill command) and post the unified patch.  
3. Add PR 3 (Fixity migration + ChecksumService + FixityJob) and post the unified patch.  
4. Add PR 4 (DSAR controller + ResearchDsarExportJob) and post the unified patch.

Reply with the single digit (1–4) to pick which PR to start.