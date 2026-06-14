# Embargo Management — gaps, incomplete code, enhancements

This document records a concise, repository-grounded assessment of the Embargo Management surface in the Research module and a prioritized set of suggested enhancements and implementation steps. Use this as the spec for small, reviewable PRs.

1) Quick summary

Embargo management is present as a concept in multiple packages (research, repository, access-control/rights). The codebase contains tables for embargo-like rules, UI surfaces to display access conditions, and scheduled jobs elsewhere for retention/disposition. However, a coherent, end-to-end embargo enforcement pipeline that ties policy (Mandate) → relation (RiC) → enforcement (access middleware + release job) is incomplete.

2) Gaps (concrete)

- No single canonical embargo table / model for the Research package
  - Evidence: no `research_embargo` migration in packages/ahg-research/database; embargo data appears in mixed tables (access_decision, donor_agreement_restriction, object-level `access_control` in other packages).

- Enforcement inconsistently applied across read/download routes
  - Evidence: `OdrlPolicyMiddleware` and a few route-level checks exist, but not every file-download / object-view route consults a central embargo decision service.

- Missing scheduled release job for embargo expiry
  - Evidence: there are scheduled jobs for reminders and field-alerts; I found no explicit `release_expired_embargoes` scheduled command in the research/cron tasks.

- Incomplete provenance+audit for embargo changes
  - Evidence: `research_activity_log` and `ai_provenance` exist, but embargo state transitions (impose/extend/release) are not consistently writing an auditable `embargo_event` or RiC Event.

- Limited UI for embargoed items in Research workflows
  - Evidence: Research workbench shows access flags but lacks a unified embargo timeline or reviewer queue for embargo release approvals.

3) Incomplete code (exact files to inspect & evidence)

- packages/ahg-research/routes/web.php
  - Look for any embargo-related routes; none centralised.

- packages/ahg-research/src/Controllers/ResearchWorkspaceController.php
  - The workspace shows access metadata but no embargo management endpoints.

- packages/ahg-extended-rights/src/ or packages/ahg-access-request/
  - Rights modules contain parts of access decision logic but not an embargo-release scheduler tied to research objects.

- packages/ahg-ric/src/ (exporters)
  - RiC exporter may not include embargo expiry as Event/Mandate relations for Research objects.

- packages/ahg-core/src/Commands — cron entries
  - No command named `release:embargoes` or similar present.

4) Suggested enhancements (prioritised)

High priority
- Add a canonical `research_embargo` table + Eloquent model
  - Fields: id, research_id/object_id, starts_at, ends_at, imposed_by (user_id), mandate_id (nullable), reason, status (active|released|overridden), created_at, updated_at.
  - Reason: single source of truth for embargoes; simplifies enforcement.

- Central EmbargoService with enforcement API
  - Methods: isEmbargoed($object, $user), releaseExpiredEmbargoes(), imposeEmbargo(...), extendEmbargo(...), revokeEmbargo(...)
  - Evidence: hook this service into the access middleware so every object-view / download consults it.

- Scheduled `release_expired_embargoes` command and job
  - Behavior: nightly job that atomically releases embargoes whose ends_at <= now; emits an `EmbargoReleased` domain event and writes into research_activity_log and RiC Event exporter.

- UI: embargo management dashboard + reviewer queue
  - Show active embargoes, expiry dates, who imposed them, and a reviewer action card (Approve release / Extend / Escalate). Integrate with inbox/notifications.

Medium priority
- RiC modelling: map embargo to Mandate → Relation
  - When exporting a Research object to RiC, ensure there is a Mandate and an Event representing the embargo with provenance and the release event.

- Provenance/audit: create `embargo_event` entries in the audit log and ensure every change writes ai_provenance-like metadata for machine actions.

- Tests: feature tests for embargo lifecycle (impose → blocked access → midnight release job → accessible) and API tests for release endpoint.

Low priority
- Fine-grained embargo policies (user-group exemptions, partial field redaction during embargo)
- Analytics: embargoed-items dashboard (counts by duration, by mandating agent)

5) Small implementation plan (PRs)

PR 1 — schema + model + basic service (small)
- Migration: add `packages/ahg-research/database/migrations/<timestamp>_create_research_embargoes.php`.
- Model: packages/ahg-research/src/Models/ResearchEmbargo.php (Eloquent).
- Service: packages/ahg-research/src/Services/EmbargoService.php (skeleton isEmbargoed + imposeEmbargo).
- Tests: unit test for model and service create/find.

PR 2 — enforcement middleware + hooks (medium)
- Middleware: wrap object-view / download routes to call EmbargoService::isEmbargoed and return 403 or redirect-to-request-access when embargo applies.
- Hook: use the service in ResearchWorkspaceController for previews and in file-download controller.
- Tests: feature that verifies embargo blocks access.

PR 3 — scheduled release job + audit (medium)
- Command: `php artisan research:release-embargoes` that runs the service's releaseExpiredEmbargoes.
- Producer: emit `EmbargoReleased` event and write research_activity_log entry plus RiC Event creation.
- Tests: integration test that simulates expired embargo and ensures release runs.

PR 4 — UI + reviewer queue + notifications (larger)
- Add admin pages: packages/ahg-research/resources/views/research/embargoes/index.blade.php and show/edit.
- Add reviewer actions (Approve release / Extend) and inbox notifications.

6) Acceptance criteria (what done looks like)

- Data: `research_embargo` table exists and populated via service/CTL.
- Enforcement: an embargoed item returns 403 or a clear access-request page for unauthorized viewers.
- Release: nightly command releases expired embargoes and writes an `EmbargoReleased` audit event and (optionally) RiC Event.
- UI: admin queue shows active embargoes with action buttons.
- Tests: feature tests for impose → block → release → available passing in CI.

7) Files to edit / inspect now (exact paths)

- packages/ahg-research/database/migrations/ (new migration file)
- packages/ahg-research/src/Models/ResearchEmbargo.php
- packages/ahg-research/src/Services/EmbargoService.php
- packages/ahg-research/src/Controllers/ResearchAdminController.php (admin actions)
- packages/ahg-research/routes/web.php (new routes: embargo.index, embargo.release, embargo.extend)
- packages/ahg-research/resources/views/research/embargoes/* (index/show/edit)

8) Estimated effort

- PR 1: 0.5–1 day
- PR 2: 1–2 days
- PR 3: 1 day
- PR 4: 2–4 days

---

I will now write this file to: /usr/share/nginx/heratio/docs/research/embargo-management.md

Status: very good

Next action — outstanding issue to work on
1. Implement PR 1 (migration + model + service skeleton). 
2. Implement PR 2 (enforcement middleware + route hooks) and tests.
3. Implement PR 3 (release job + audit + RiC Event).
4. Implement PR 4 (UI + reviewer queue + notifications).

Reply with the number (1–4) to start the chosen PR.