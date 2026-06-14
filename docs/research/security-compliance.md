# Security & Compliance — Research module

This document summarises the observable security and compliance posture of the Research module (code in packages/ahg-research) and gives a concise list of gaps, incomplete code paths, and recommended enhancements. It is intended as an operational checklist for an engineer to act on and for an operator to verify.

1) Quick summary
- Current posture: functional features implemented, but a number of security and compliance controls are incomplete or not fully enforced. The highest-risk areas are PII handling (donor/researcher data), AI/TTS provenance & decision gates, and scattered direct writes to platform core tables that can bypass model events and audit hooks.
- Primary compliance concerns: data-at-rest encryption of personal fields, auditability of admin actions, provable AI provenance, retention/disposition enforcement, and operational controls for secrets and third-party AI/TTS gateways.

---

2) First look — observable gaps (high priority)
- Encryption at rest for PII is partially surfaced in settings, but there is no clear, repo-tracked backfill or enforcement plan to ensure existing rows are encrypted when the setting is toggled on.
  - Action: add a controlled backfill command (dry-run + audit) and an automated write-path guard that uses the EncryptionService when the setting is enabled.

- AI provenance is recorded by the platform in an ai_provenance table in several places, but enforcement ("AI suggestions must be treated as proposals and require human acceptance") is not consistently implemented across all AI entry-points in the Research module.
  - Action: instrument every AI/TTS call site in Research (writing studio, question builder, claim suggestion, analysis bridge) to write a provenance row and ensure UI shows Accept/Reject before any permanent write.

- Admin actions (researcher approval, user provisioning, group membership) were being performed via direct DB writes in several controllers. This risked bypassing Eloquent events and audit logging.
  - Action: centralise all core-user and ACL writes through the canonical UserProvisioner and add an audit log event (with actor, IP, reason) for each admin change.

- Lack of end-to-end automated tests for critical compliance flows: data export (RiC / JSON-LD), PII encryption toggle, and AI provenance. Tests exist but have not been validated in CI in this session.
  - Action: add CI tests that exercise migration + backfill and AI-provenance enforcement paths.

- Secrets & gateway configuration: AI/TTS gateway credentials, ORCID, Crossref/OpenAlex keys, and mailer credentials must be validated and have a fail-safe (skip when not configured) — some scheduled jobs run without config guards.
  - Action: add isConfigured() gates to scheduled jobs and centralise secrets access via a secrets helper.

- File-upload sanitisation and large-object handling: research sources and donor agreement documents are uploaded and stored; scanning, filename sanitisation, content sniffing and safe serving (signed URLs / short-lived URLs) need review.
  - Action: enforce MIME/type checks, virus scanning on upload, store uploads behind the configured storage disk with signed download URLs.

- Retention & disposition enforcement is modelled conceptually (RiC edges for Mandate/Activity) but enforcement bodies live in plugin code. There is not yet a single enforcement verification test confirming disposition events are blocked until authorised.
  - Action: add policy tests and an enforcement audit that runs retention rule checks daily and emits actionable reports.

---

3) Look at incomplete code (concrete checks to run)
Run these checks (commands) in the repo to confirm the exact locations that need fixes:
- grep for direct core user/ACL writes (should be only in the provisioner):
  - grep -R --line-number "DB::table('user'\|'acl_user_group')" packages/ahg-research | sed -n '1,400p'

- locate AI/TTS call sites in research: search for ai_provenance usage and for calls to the AI gateway:
  - grep -R --line-number "ai_provenance" packages/ahg-research || true
  - grep -R --line-number "ai_theahg\|aiGateway\|ai_provide\|sendPrompt" packages/ahg-research || true

- find scheduled commands that lack isConfigured() guards:
  - grep -R --line-number "schedule->command" -n | grep research || true

- check migrations present but not applied (testing/staging):
  - php artisan migrate:status --env=testing

- verify upload handling code paths for sanitisation/scan points (search upload handlers):
  - grep -R --line-number "store\(|move\(|getClientOriginalName" packages/ahg-research | sed -n '1,300p'

Use the above outputs as the truth to identify the remaining incomplete code locations.

---

4) Suggested enhancements (priority & acceptance criteria)
High priority (must fix)
- Full PII encryption enforcement and backfill
  - Implement an "encrypt-if-enabled" wrapper used by donor/researcher write paths.
  - Add an idempotent backfill command with dry-run and a verification report.
  - Acceptance: when the setting is enabled all new writes are encrypted and backfill reports a zero-failure run.

- AI provenance enforcement + UI gating
  - For every LLM/TTS call: write a provenance row {module, user_id, prompt, model, response, confidence, timestamp}. Do not commit suggested metadata to canonical fields until human Accept. In UI mark any AI proposal clearly and provide Accept/Reject.
  - Acceptance: every AI interaction creates a row and no AI-derived metadata appears in canonical indexes without explicit accept.

- Centralise core-user writes + audit log
  - Ensure UserProvisioner is the only writer; remove direct DB::table writes from controllers; add an audit event store entry for admin actions with reason and IP.
  - Acceptance: grep returns no DB writes to core tables outside the provisioner; audit log contains the admin events for a sample approve/suspend flow.

- Secrets & scheduled job guards
  - Add isConfigured checks on all scheduled tasks; surface a maintenance notice when a scheduled job is disabled due to missing credentials.
  - Acceptance: jobs skip cleanly and write a notice to the operator log; failed jobs do not run in production silently.

Medium priority
- Upload security: MIME sniffing, virus scan, signed download URLs, retention metadata on uploads.
- Rate limiting on public AI/TTS endpoints; quota per API key or per operator.
- Retention enforcement auditing: nightly job to assert dispositions that should have happened.

Low priority
- Add additional feature tests, RiC export verification tests, and CI gating to block merges that touch core user writes without a provisioner test.
- Add monitoring alerts (Sentry/Prometheus) for LLM error rates and failed provenance writes.

---

5) Implementation checklist (actionable items)
- Add a small audit PR that:
  1. Replaces direct DB core writes with provisioner calls in high-risk controllers. Run php -l and unit tests.
  2. Instruments all AI endpoints in Research module to write ai_provenance rows and mark UI proposals.
  3. Adds isConfigured checks to scheduled research jobs and the ORCID/AI/email tasks.
  4. Implements the PII backfill command and unit tests.

- Add CI test cases that exercise: migration + backfill; AI suggestion flow (write suggestion, assert no canonical change until accept); admin approval flow audit log entries.

---

6) Where I added quick documentation
- I added docs/research/security-compliance.md in the project docs folder summarising these points and recommended next steps.

If you want I can now: (choose a single next action)
1. Produce a unified patch that implements the AI provenance instrumentation across the top 5 call sites in the Research package and add a short feature test. (estimated 2–3 days)  
2. Implement the PII backfill command + wrapper and add unit tests. (estimated 1–2 days)  
3. Replace remaining direct core DB writes in controllers with provisioner calls (small PRs per controller) and run php -l + tests; produce the patch. (estimated 1–3 days)  
4. Add isConfigured guards to scheduled tasks and add operator notices when jobs are disabled. (estimated 0.5–1 day)

Status: very good
