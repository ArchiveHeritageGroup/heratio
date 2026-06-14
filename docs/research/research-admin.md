# Research Admin — Gaps, Incomplete Code, and Suggested Enhancements

This document audits the Research Admin surface (what administrators and operators need to manage researchers, submissions, approvals, and governance) and records concrete, repo-grounded gaps, incomplete pieces of code, and suggested enhancements. Add this file to /usr/share/nginx/heratio/docs/research/ as requested.

1. First — quick scope
- Surface audited: admin researcher approval flows, researcher profile management, bulk actions, reviewer assignment, SLA/queueing, audit logs, export, and ACL provisioning.
- Key code loci: packages/ahg-research/src/Controllers/ResearchAdminController.php, packages/ahg-research/src/Controllers/ResearchController.php (legacy), packages/ahg-research/src/Services/UserProvisioner/EloquentUserProvisioner.php, packages/ahg-research/src/Services/ResearchService.php, packages/ahg-research/routes/web.php, packages/ahg-research/tests.

2. Gaps (concrete)
- Missing/unstable test coverage for admin flows
  - Evidence: feature tests exist but were not run in CI during the refactors. No targeted tests for approve/suspend/verify bulk operations.
- Incomplete centralisation of core user/ACL writes
  - Evidence: several DB::table('user') and DB::table('acl_user_group') occurrences remained in ResearchController and other admin hotspots during the audit. EloquentUserProvisioner is in place but not yet the sole writer everywhere.
- No bulk-review / assignment workflow
  - Evidence: ResearchAdminController exposes approve/reject per-submission. There is no documented reviewer assignment queue, SLA, or bulk-approve UI endpoint.
- Limited audit & activity logging consistency
  - Evidence: research_researcher_status_log exists, but admin actions are not consistently logged to a central audit table with actor/time/reason in all code paths.
- No admin dashboard KPIs or workload view
  - Evidence: no route/view summarising pending approvals, average time-to-approve, or reminder backlogs.
- Weak feature-flagging and slice enable/disable
  - Evidence: service provider installs slices on boot; there is no per-slice enablement flag for admins to switch off features or sections safely.

3. Incomplete code (files & exact loci)
- packages/ahg-research/src/Controllers/ResearchController.php
  - Direct DB writes to user/acl still present in some approval and registration paths (createAtomUser, approveResearcher pre-patches). These should call the provisioner API.
- packages/ahg-research/src/Controllers/ResearchAdminController.php
  - Good progress — uses provisioner in some handlers — but a full sweep and unit tests are still needed to ensure parity across all endpoints (approve, suspend, verify, delete, bulk actions).
- packages/ahg-research/src/Services/UserProvisioner/EloquentUserProvisioner.php
  - Implementation present; ensure it is the canonical writer and add tests to prove idempotence and event emission.
- packages/ahg-research/tests/Feature/
  - ResearchUserProvisionerTest, ResearchWorkspaceSmokeTest exist — need ResearchAdminFlowsTest, bulk-actions tests, reviewer-assignment tests.
- packages/ahg-research/routes/web.php
  - Confirm all admin routes point to ResearchAdminController and none remain bound to the legacy methods in ResearchController.

4. Suggested enhancements (concrete, implementable)
- A. Finish centralisation: ensure only the provisioner writes core user/ACL rows
  - Action: grep-and-replace remaining DB::table('user'/'acl_user_group') writes to provisioner calls, add tests asserting the provisioner is invoked, and add a pre-commit grep check to prevent regressions.
- B. Bulk-review + reviewer assignment workflow
  - Add: endpoints & UI for assign-to-reviewer, batch-approve (with confirmation modal), and CSV import for bulk decisions. Add small audit reason field for each batch action.
- C. SLA and backlog KPIs
  - Add: admin dashboard tiles (pending submissions, avg time pending, overdue approvals), and scheduled reminders for aged submissions. Emit metrics to observability.
- D. Consistent audit log & undo
  - Ensure every admin action writes a structured audit (actor_id, action, target_id, reason, old_value, new_value, timestamp) and provide a limited ‘undo’ for simple reversals (revoke approval within 24h).
- E. Reviewer/role management & delegation
  - Add reviewer groups, delegation (assign proxy for vacations), and per-project reviewer rules. Integrate with ACL groups and provisioner membership.
- F. Feature flags for Research slices
  - Add research_slice table or settings flags for enabling/disabling slices (DMP, replication, writing studio) per deployment; service provider checks the flag before registering routes.
- G. Admin audit UX
  - Add per-researcher activity timeline on ResearchAdminController::viewResearcher: show recent events, provenance rows (ai_provenance), and status changes with filters.
- H. Tests & CI gating
  - Add targeted feature tests for admin flows and add them to CI; add a low-cost smoke test that runs after deploy.

5. Implementation plan (staged, low-risk)
- Stage 1 — (safety) Add pre-commit grep rule + core tests (1–2 days)
  - Add a CI check and pre-commit hook to fail on new direct writes to core tables outside the provisioner.
  - Add ResearchAdminFlowsTest for the canonical admin actions.
- Stage 2 — (remediation) Sweep & convert remaining direct writes (1–3 days)
  - Replace site-wide occurrences, run php -l, and run tests. Small PRs per controller to ease review.
- Stage 3 — (function) Bulk-review + reviewer assignment (2–4 days)
  - API: POST /research/admin/assign, POST /research/admin/bulk-approve, GET /research/admin/queue
  - UI: admin queue + per-reviewer filter + bulk actions
- Stage 4 — (ops) SLA dashboard & reminders (1–2 days)
  - Add scheduled job to flag overdue submissions and admin tiles for KPIs.
- Stage 5 — (polish) audit UX, undo, delegation, feature flags, CI hardening (2–4 days)

6. Acceptance criteria (how we know it’s done)
- Grep shows no direct DB writes to core user/acl tables outside EloquentUserProvisioner. (Automated check must pass.)
- Feature tests for admin flows pass in CI. Bulk-approve + assign endpoints exist and have tests. Admin dashboard tiles render and show realistic numbers from seeded data.
- Every admin action is recorded in the audit log with actor/timestamp/reason. Undo works for recent actions and is logged.
- Slice enable/disable settings present and respected by the service provider during boot.

---

Files modified/created during audit: check the staged patches under /usr/share/nginx/heratio/tmp/ for the remediation snippets created during this session. Review and apply in small PRs.

Status: very good

outstanding issue to work on
1. Add pre-commit & CI grep check to block direct core user/acl writes and add ResearchAdminFlowsTest.  
2. Sweep and convert remaining direct writes to UserProvisioner (small PRs per controller).  
3. Implement bulk-review + reviewer assignment endpoints + UI.  
4. Add SLA dashboard tiles and scheduled reminder generator.

Reply with the single digit (1–4) to pick which I should start.