# Compliance Autopilot - Retention Schedule Slice (heratio#1199)

Auto-draft a defensible retention schedule from the catalogue PII scan, in `packages/ahg-privacy`. Second slice of the compliance autopilot (the first slice scans for PII and auto-drafts a ROPA / Article 30 entry).

## What it does

On the autopilot page (`/admin/privacy/autopilot`) a "Draft retention schedule" button runs the same catalogue PII scan, then asks the AHG AI gateway to suggest, per data category found, a defensible retention period, a generic legal/policy basis, and a disposal action. Each suggestion is persisted as a proposal for a data-protection officer to accept. Accepting flags the row (sign-off); a re-scan refreshes still-proposed rows but never overwrites an accepted one.

The schedule is grounded ONLY in the category names the scan surfaced (email, phone, national_id, etc.) - the model is explicitly told not to invent record contents.

## Jurisdiction-neutral by design

Heratio is international. The LLM prompt forbids naming any single country's law (no POPIA / GDPR / IPSAS by name); the basis text stays generic ("per the applicable data-protection retention regime and the institution's appraisal/retention policy"). The concrete statute is the job of the enabled per-market module. The deterministic fallback (used when the gateway is unavailable) is generic for the same reason.

## Pieces

- Service: `ComplianceAutopilotService::draftRetentionSchedule($scan)`, `listRetentionProposals()`, `acceptRetentionProposal($id, $userId)`. AI suggestions via `AhgAiServices\Services\LlmService::complete()` (gateway only, never a direct node). On any LLM failure it falls back to a deterministic per-category placeholder, so the feature degrades gracefully.
- Controller: `ComplianceAutopilotController::draftRetention()` (POST `ahgprivacy.autopilot.retention`), `acceptRetention()` (POST `ahgprivacy.autopilot.retention.accept`). `index()` now passes existing proposals to the view.
- Model: `AhgPrivacy\Models\RetentionProposal` on `ahg_retention_proposal`.
- Table: `database/install-phase5.sql`, installed by `AhgPrivacyServiceProvider::boot()` via the single-try `Schema::hasTable` probe + `installSqlFile` pattern (reference_ci_schema_hastable). Unique key on `category` makes the draft an upsert.
- View: retention-schedule card appended to `resources/views/autopilot.blade.php` with a table + per-row Accept buttons.

## Table: ahg_retention_proposal

One row per data category. Columns: `category` (unique), `category_label`, `records_affected`, `retention_period`, `legal_basis`, `disposal_action`, `rationale`, `source` (`autopilot`/`llm`/`heuristic`), `status` (`proposed`/`accepted`), `accepted_at`, `accepted_by`.

## Routes

Loaded with the `web` middleware group via `Route::middleware('web')->group(...)` in the provider (NOT bare `loadRoutesFrom` - that was a real bug: without `web` the `auth` middleware can't see the session and every `/admin/privacy/*` page 302-redirects a logged-in user back to login).

## Notes

- Verified the gateway path live: a tinker smoke test returned `source=llm` with sensible neutral periods ("7 years after last email interaction", disposal "Secure deletion").
- Known unrelated breakage at the time of build: `packages/ahg-c2pa` had a parallel agent's uncommitted provider edit referencing a non-existent `routes/web.php`, which crashes all artisan/route boot. That is outside ahg-privacy and was not modified.
