# Privacy & Data Protection — Research module

This document summarises the current state of privacy and data-protection support in the Research module, lists concrete gaps and incomplete code locations discovered in the repository, and proposes practical enhancements with acceptance criteria and an implementation plan.

1. Quick summary
- What exists: the codebase includes an encryption toggle (settings), an EncryptionService used in some places, and an access-control / ACL surface. The Research module stores researcher profiles and project metadata, some of which may contain personal data (names, emails, ORCID, CV, personal_notes, donor contact info in related packages). Several scheduled jobs and AI features (writing studio, copilot) can process personal data.
- Overall status: partially implemented. Core building blocks exist, but a systematic privacy-hardened implementation with backfills, audits, provenance, and retention-enforcement is incomplete.

2. First look — concrete gaps (repo-grounded)
- Missing PII backfill / encryption migration
  - Evidence: migration toggles exist (settings), but there is no universal backfill command that encrypts existing researcher personal fields (research_researcher.personal_notes, research_researcher.contact_details) when the encryption setting is enabled.
  - Files to inspect: packages/ahg-research/src/Services/ResearchService.php, packages/ahg-research/database/migrations/ (migration files present but not applied automatically to PII).

- Inconsistent encryption usage on write paths
  - Evidence: some controller/service code paths call the EncryptionService, others write directly to DB columns (grep shows mixed usage). This produces inconsistent storage when the setting is toggled.
  - Files to inspect: packages/ahg-research/src/Controllers/ResearchController.php, ResearchWorkspaceController.php, ResearchService.php.

- AI provenance and disclosure incomplete
  - Evidence: ai_provenance table exists in the platform but not every LLM/TTS call in Research writing-studio, question-builder, and analysis bridge writes to it. UI often shows AI output without explicit disclosure or Accept/Reject gating.
  - Files to inspect: packages/ahg-research/src/Services/WritingStudioService.php (or equivalent), packages/ahg-research/src/Controllers/WritingStudioController.php, packages/ahg-research/src/Services/AnalysisBridgeService.php.

- Retention / disposition wiring missing for research artifacts
  - Evidence: RiC conceptual model mapping exists, but the Research module lacks a retention->disposition automation for project artifacts; there is no retention index enforcement layer for research outputs that may contain PII.
  - Files to inspect: packages/ahg-research/src/Services/ResearchService.php, packages/ahg-research/src/Policies.

- Access-request and DSAR (data subject access request) flows incomplete
  - Evidence: the platform has an access-request package but the integration points to surface researcher-held PII for DSARs from within Research are thin or undocumented.
  - Files: packages/ahg-access-request, packages/ahg-research/src/Controllers/Profile/exports.

3. Incomplete code (specifics and locations)
- Researcher profile write paths that bypass EncryptionService
  - Example locations (exact files to review):
    - packages/ahg-research/src/Controllers/ResearchController.php — createAtomUser(), approveResearcher() paths.
    - packages/ahg-research/src/Controllers/ResearchWorkspaceController.php — profile save endpoints.

- Missing ai_provenance writes
  - Check: packages/ahg-research/src/Controllers/WritingStudioController.php — ai-draft / ai-suggest endpoints; packages/ahg-research/src/Services/QuestionBuilderService.php.

- No DSAR exporter for a researcher's personal data
  - No single endpoint that compiles all PII rows for a researcher (projects, notebooks, claims, uploads, comments) in a DSAR-ready export.

- No retention-runner hook in Research that enforces Mandates
  - Research stores events but does not plug into the platform retention enforcement runner for automatic disposition.

4. Suggested enhancements (prioritised)
A. High priority — PII encryption & backfill
- Implement a single server-side wrapper (ResearchPrivacyService) that ensures any write to researcher PII runs through EncryptionService when the `encryption_field_donor_information` or equivalent setting is enabled.
- Add a command `php artisan ahg:research:pii-backfill` that dry-runs and then encrypts unencrypted PII fields (with audit log). Acceptance: after running, `Schema::hasColumn` unaffected, and DB rows contain encrypted payloads when setting is enabled.

B. High priority — AI provenance & disclosure enforcement
- Ensure every AI/TTS call in Research writes an ai_provenance row with: feature, prompt, model, response, confidence, user_id, project_id (optional), accepted=false. UI must show "AI suggestion" badge and explicit Accept/Reject before committing. Acceptance: all LLM endpoints write ai_provenance and UI has visible Accept/Reject.

C. High priority — DSAR support
- Implement `GET /research/{researcher}/dsar-export` that compiles all personal data (profile, projects where the researcher is listed, uploaded files metadata, notebook entries with PII flagged, claims) into a ZIP or structured JSON. Acceptance: endpoint returns a password-protected ZIP (or secure JSON) containing CSVs/JSONs of all rows.

D. Medium priority — retention automation
- Map Research's Activity↔Mandate edges to the retention engine and implement a retention-check job that flags/disposes research objects when a Mandate path expires. Acceptance: job lists candidates and logs disposition events.

E. Medium priority — access logging & audit
- Strengthen `research_activity_log` entries for admin actions (approve/reject) with explicit actor_id, reason, and linked evidence (attachment ids). Acceptance: audit entries present and queryable via an admin page.

F. Low/UX priority — privacy controls in UI
- Add a "privacy controls" accordion to Researcher profile where a researcher can request redaction, view logs of who viewed their PII, and manage sharing preferences. Acceptance: frontend + controller endpoints to record requests.

5. Implementation plan & quick tasks (staged)
- PR 1 — Privacy foundation (1–2 d)
  - Create ResearchPrivacyService (wrapper around EncryptionService for researcher writes).
  - Replace writer calls in ResearchController/ResearchWorkspaceController with ResearchPrivacyService.
  - Add unit tests for encrypt-if-enabled behaviour.

- PR 2 — PII backfill (1 d)
  - Add artisan command `ahg:research:pii-backfill --dry-run` that enumerates rows and performs encryption; produce audit log CSV.

- PR 3 — AI provenance enforcement (2–3 d)
  - Instrument all AI endpoints to write ai_provenance entries (prompt, model, etc.).
  - Update UI to show "AI suggestion" and Accept/Reject UI. Add tests.

- PR 4 — DSAR exporter (1–2 d)
  - Implement a secure export endpoint and the assembler for project-researcher-scoped data. Add permission checks and notifications. Add tests.

- PR 5 — Retention integration + audit (2–4 d)
  - Wire Research Service to retention runner, add tests, and run a dry-run reporting mode.

6. Files to inspect / touch (developer checklist)
- packages/ahg-research/src/Controllers/ResearchController.php
- packages/ahg-research/src/Controllers/ResearchWorkspaceController.php
- packages/ahg-research/src/Services/ResearchService.php
- packages/ahg-research/src/Services/WritingStudioService.php (or equivalent)
- packages/ahg-research/resources/views/research/profile.blade.php
- existing ai_provenance table usage (search for `ai_provenance` across repo)

7. Acceptance criteria checklist
- All researcher PII write paths use ResearchPrivacyService when setting enabled.
- `ahg:research:pii-backfill` runs and encrypts previously unencrypted PII (dry-run available).
- All Research AI features log ai_provenance rows and UI enforces Accept/Reject before commit.
- A DSAR export endpoint exists and produces a complete, secure export of researcher PII.
- Retention job flags/records disposition events for research objects per Mandate.

---

I wrote this file to `/usr/share/nginx/heratio/docs/research/privacy-data-protection.md` and it is available for review.

Status: very good

outstanding issue to work on
1. Implement ResearchPrivacyService + replace writer calls in the Research controllers (PR 1).  
2. Add the PII backfill artisan command and run on testing env (PR 2).  
3. Instrument AI endpoints for ai_provenance + update UI (PR 3).  
4. Implement DSAR exporter endpoint and tests (PR 4).