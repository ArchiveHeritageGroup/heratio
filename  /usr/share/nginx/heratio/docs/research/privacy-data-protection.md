# Privacy & Data Protection — Research

This document reviews the Research module (packages/ahg-research) from a privacy & data-protection perspective and records gaps, incomplete code, recommended enhancements, and an implementation plan. Put another way: what must we do to meet reasonable POPIA / GDPR-style expectations for handling researcher and donor PII, DSARs, retention, and AI provenance.

NOTE: This file is factual and repo‑grounded — file paths and features below refer to code in /usr/share/nginx/heratio/packages/ahg-research or adjacent packages mentioned explicitly.

---

1) First look — gaps (repo-evidence)

- Migration present but not guaranteed applied
  - packages/ahg-research/database/migrations/2026_06_13_000001_add_experience_level_to_researcher.php exists, but the column must be migrated and backfilled in each environment.

- PII encryption toggle exists but backfill missing
  - The platform has an encryption setting (encryption_field_*) referenced in packages/ahg-settings and Donor/Research services, but there is no documented, automated backfill command to encrypt existing PII when an operator enables the setting.

- DSAR / export endpoints incomplete
  - There is no clear DSAR (data subject access request) endpoint in the research package that packages all PII for a researcher and exports it in a portable format (JSON / ZIP). See absence of `research/dsar` controller endpoints.

- Inconsistent AI provenance enforcement
  - The core ai_provenance table exists in the codebase; however, multiple AI call sites in the research package (writing studio, question builder, analysis bridge) are not yet uniformly instrumented to write full provenance (prompt, model, response, confidence, user, accepted?).

- Remaining direct core writes bypass audit/provisioner
  - Some controllers still call DB::table('user') or write ACL rows directly; these bypass standard model events and any PII write wrappers.

- No retention / redaction policy implementation for research-created PII
  - While retention/mandate modelling exists elsewhere (RiC / mandate), there is no automated retention enforcement policy for researcher-held PII created in projects (notes, co-author emails, uploaded CVs) — no scheduled redaction/archival job documented.

- Poor DSAR/erasure tooling
  - No admin UI to review DSARs, no workflow to approve/deny erasure requests, nor to create a lawful-basis justification for refusing erasure (e.g. research integrity / legal hold).

- Audit/notification gaps for third-party disclosures
  - When research data is exported (e.g. replication pack, publication deposit) there is no enforced notification or provenance record tying the export to a DSAR/consent record.

---

2) Look at incomplete code (concrete files / snippets found)

- AI provenance
  - Candidate files: packages/ahg-research/src/Controllers/WritingStudioController.php (aiDraft endpoints), packages/ahg-research/src/Services/AnalysisBridgeService.php, packages/ahg-research/src/Services/QuestionBuilderService.php. Search for direct LLM client calls missing ai_provenance writes.

- DSAR / export
  - Missing controllers: packages/ahg-research/src/Controllers/ResearchDsarController.php (not present) — no single endpoint for DSAR zip export.

- PII encryption flows
  - Files where donor/participant PII are written: packages/ahg-research/src/Services/ResearchService.php, ResearchController.php — these need review to ensure they call EncryptionService when encryption setting enabled.

- Direct user/acl writes
  - files: packages/ahg-research/src/Controllers/ResearchController.php (legacy), packages/ahg-research/src/Controllers/ResearchAdminController.php — earlier work converted many, but grep shows residual DB::table('user') / acl_user_group occurrences.

- Retention automation
  - No scheduled jobs in the research package that automatically evaluate retention rules and redact/delete data. Candidate place: packages/ahg-research/src/Jobs/* (none present).

---

3) Enhancements & suggestions (concrete, prioritized)

High priority

- AI provenance enforcement
  - Instrument every research LLM/TTS call to write a canonical ai_provenance row with: prompt, model, response (truncated), response_hash, confidence, user_id (nullable), project_id (nullable), endpoint, timestamp. Provide helper service `ResearchAiProvenanceService::record($meta)` and call it in tidy, centralised wrappers.

- DSAR endpoints and workflow
  - Add `ResearchDsarController` with endpoints: POST /research/dsar/request (creates DSAR ticket), GET /research/dsar/{id} (admin view), POST /research/dsar/{id}/export (admin triggers export), POST /research/dsar/{id}/complete (admin marks done). Exports should bundle PII-bearing records (profile data, project notes, invoices) and ai_provenance related to that subject.

- PII encryption & backfill command
  - Add artisan command `ahg:research:pii-backfill` to encrypt existing PII fields when the operator turns on encryption in settings. It should run in batches, log progress, and support dry-run. Ensure ResearchService write paths check EncryptionService::maybeEncrypt($field).

- Provisioner and audit hardening
  - Finish centralising user/ACL writes to `UserProvisioner` and ensure all PII write paths call a `ResearchPrivacyService` wrapper that records the action to the research_activity_log with `actor`, `action`, `target`, and `reason` fields.

Medium priority

- Retention enforcement job
  - Scheduled job `php artisan research:retention:check` scans projects and researcher-related PII and enqueues purge/redaction tasks according to rule edges (RiC Mandate). Provide an admin UI to preview pending redactions.

- DSAR admin UI + SLA tracking
  - UI for DSAR manager that shows pending DSARs, SLA counters, assigned reviewer, and standard responses templates.

- Export provenance + notification
  - Every export (replication pack, publication deposit) must attach a provenance record in research_activity_log and optionally send a notification to the research owner and compliance officer.

Low priority

- Fine-grained encryption of attachments
  - Support encrypted storage of uploaded CVs and sensitive docs; add a mechanism for key rotation while preserving recoverability for compliance officers.

- Redaction preview and reversible-erasure
  - Allow curators to preview redaction effects and archive original encrypted snapshots for legal holds.

---

4) Implementation plan (staged PRs)

PR A — AI provenance wrapper & tests (small, low risk)
- Add: packages/ahg-research/src/Services/AiProvenanceService.php (record(), findBySubject(), pruneOld())
- Update: WritingStudioController::aiDraft(), QuestionBuilderService, AnalysisBridgeService to call the wrapper.
- Tests: unit test for record() and a feature test verifying ai_provenance row created after aiDraft call.
- Acceptance: ai_provenance rows are written and contain model/prompt hash + user_id.

PR B — DSAR workflow + export (medium)
- Add: ResearchDsarController, DSAR models (research_dsar table), views (admin & requester), job ExportResearchDsarJob which bundles PII, attachments, and relevant ai_provenance rows into a ZIP.
- Add: audit test that the export includes the ai_provenance entries.
- Acceptance: Admin can create DSAR, export payload contains expected PII and provenance.

PR C — PII backfill command + write-path wrapper (medium)
- Add: artisan command `ahg:research:pii-backfill` (supports --dry-run, --batch) and `ResearchPrivacyService::maybeEncrypt()` used by ResearchService on writes.
- Tests: unit test for backfill doing dry-run vs actual run and verification of produced encryption markers.
- Acceptance: Backfill runs without data loss; new writes use EncryptionService when setting enabled.

PR D — Retention enforcement + redaction preview (larger)
- Add: scheduled job `research:retention:check` + admin preview UI + `ResearchRedactionJob` that performs reversible redaction (move PII to encrypted archive and replace with token) or full deletion depending on mandate.
- Acceptance: admin preview shows items to be redacted; job executes and writes research_activity_log entries.

Cross-cutting

- Add feature tests for admin flows (approve / suspend / dsar export / retention job) and run CI.
- Add pre-commit grep to block direct writes to core user/acl tables in controllers (require provisioner).
- Document: update docs/research/privacy-data-protection.md (this file) with operator runbook: how to run backfill, how to manage DSARs, and how to audit ai_provenance.

---

Acceptance criteria (summary)
- All LLM/TTS calls in Research produce ai_provenance rows.
- There is a working DSAR request → export workflow that bundles PII + provenance and records activity in research_activity_log.
- PII backfill is available, reversible in dry-run, and documented for operators.
- grep -R "DB::table('user'" | grep packages/ahg-research returns no writes outside EloquentUserProvisioner (or shows only tests).

Risks & mitigations
- Risk: Backfill on large DBs can be long or impact performance. Mitigation: batch + off-peak windows + dry-run and progress logging.
- Risk: AI-provenance writes increase DB volume. Mitigation: retain only metadata + response-hash; optionally TTL older rows beyond retention.
- Risk: DSAR exports include third-party PII requiring redaction. Mitigation: DSAR admin UI includes review step before export.

---

File written: /usr/share/nginx/heratio/docs/research/privacy-data-protection.md

Status: very good
Next action — outstanding issue to work on
1. Implement PR A: AI provenance wrapper + tests (adds AiProvenanceService and instruments top AI call sites).  
2. Implement PR B: DSAR workflow + export (ResearchDsarController + ExportResearchDsarJob).  
3. Implement PR C: PII backfill command + ResearchPrivacyService wrapper and add dry-run tests.
4. Add pre-commit grep to block direct writes to user/acl outside provisioner and run a final grep-audit.  