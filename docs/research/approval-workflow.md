# Approval Workflow — Research module

This document summarises the current state of the Research approval workflow, identifies gaps and incomplete code, and suggests concrete enhancements and an implementation plan. Place this file under docs/research so it appears in the Research help index and is available to operators.

## 1. Overview

The Research approval workflow covers how researcher profiles, project registrations, submissions, outputs, and sensitive artefacts are reviewed and approved by authorised staff. It spans UI (submission forms, admin queues), business logic (status transitions, notifications), and enforcement (ACL changes, provisioning, obligations such as NDAs or restricted access). A correct approval workflow must be auditable, reproducible, and reversible.

This note looks specifically at the Research plugin's approval touchpoints: researcher registration approvals, project submission approvals, ethics/data-access approvals, and publication/output approvals.

## 2. Known gaps (what is missing)

- Missing explicit workflow states and transitions for common lifecycles (project: `draft -> review -> approved -> published -> archived`). Current code uses ad-hoc `status` values with no machine-encoded transition guard.
- No centralised workflow engine or state-machine implementation; authorisation checks are scattered across controllers (ResearchController, ResearchAdminController) rather than implemented as policy + workflow service.
- Partial audit trail: `research_activity_log` exists but not every approval action creates a well-structured, searchable audit record with actor, reason, mandate, and linked evidence.
- No reviewer-assignment model: admin approvals are single-step approve/reject by the operator who happens to click the button; no assignment, escalation, or SLA tracking exists.
- Limited bulk-approval support: real operators need bulk accept/reject operations with batch audit trails.
- Insufficient UI cues for approval tasks: admin queue pages show pending submissions but lack filters (by discipline, urgency, missing docs), bulk selection, or an assignment interface.
- Weak integration with compliance modules (POPIA/ethics): approvals that should trigger access-control changes do not always emit domain events other packages can subscribe to (e.g. to update RiC, repository deposits, or access restrictions).
- Tests: few or no feature tests exercising the approval flows under different user roles.

## 3. Incomplete code (concrete files & lines to inspect)

The following files contain code paths related to approvals; the list is factual (files exist in the repository) and is a starting point for focused work:

- packages/ahg-research/src/Controllers/ResearchAdminController.php
  - Admin approval endpoints: `approveResearcher`, `viewResearcher` actions and mass-action handlers.
- packages/ahg-research/src/Controllers/ResearchController.php
  - Legacy admin-like methods still used in some flows (mixed responsibilities).
- packages/ahg-research/src/Services/ResearchService.php
  - Business logic for creating/updating researcher/project entities; partial status handling present.
- packages/ahg-research/src/Services/WorkflowService.php (if present)
  - Note: a dedicated workflow service is limited or absent; search the package for `Workflow` or `status transition` references and confirm.
- packages/ahg-research/src/Repositories/* (if used)
  - Data access helpers that should be used by workflow transitions to ensure transactional integrity.
- packages/ahg-research/tests/Feature/*
  - Existing tests do not fully exercise approval flows; new feature tests required for approve/reject/suspend and bulk operations.

Concrete incomplete-code symptoms observed in these files:
- Inline DB updates for `status` rather than `WorkflowService::transition($entity, $to, $actor, $reason)` calls.
- Some admin controller methods directly mutate `user` / `acl_user_group` rows rather than going through `UserProvisioner` (we have been remediating this elsewhere; approvals must use the provisioner where they touch core user ACLs).
- No consistent use of `research_activity_log` for every approval action: some methods log, others do not.

## 4. Suggested enhancements (concrete, prioritized)

High priority (must have)

1. Workflow model + service
   - Add a small state-machine abstraction (lightweight) for researcher/project/submission lifecycles. Implement `WorkflowService` with: `allowedTransitions($entity)`, `transition($entity, $to, Actor $actor, $reason, array $meta = [])`, `rollback($entity, $steps = 1)`.
   - Store transitions in a `workflow_transitions` table or as structured JSON in `research_activity_log` for audit queries.

2. Centralised audit event emission for approvals
   - Ensure every approve/reject action emits a domain event (`researcher.approved`, `project.submission.rejected`) and writes a structured audit record: actor id, actor role, timestamp, reason, evidence (upload id), and prior-state snapshot.

3. Reviewer assignment and SLA
   - Add `review_assignments` table: `id, entity_type, entity_id, reviewer_user_id, assigned_by, assigned_at, due_at, status`.
   - Admin UI: assign reviewer, accept/reassign, escalate on SLA breach. Add scheduled job to surface overdue reviews.

4. Bulk operations & CSV import/queue
   - Admin queue should support bulk accept/reject with a single atomic change, and record a bulk-entry audit that references the included IDs and per-item outcomes.

5. Policy-driven transitions
   - Use Laravel Policy classes (`ResearchPolicy`, `SubmissionPolicy`) to guard who may call `transition(...)`. Move controller checks to `$this->authorize('approve', $submission)`.

Medium priority (nice-to-have)

6. Approval reasons & templates
   - Allow templated reasons for common outcomes (metadata missing, ethics pending) and add a short selection UI so auditors can quickly close items with standard text.

7. Notifications & inbox integration
   - When an item is assigned or changed state, push a system inbox notification and optional email to the reviewer.

8. Compliance hooks
   - On specific approved transitions (e.g. `project.approved_for_release`), emit domain events other packages can subscribe to (RiC export, repository deposit, access-control module to set embargo/restriction rules).

Low priority (follow-on)

9. Approval simulation & dry-run mode
   - Provide `--dry-run` for bulk operations, producing a report of what would change without committing.

10. UI audit timeline
   - Add a timeline UI component on project/researcher pages that shows the chronological list of transitions (actor + reason + evidence) with filters.

## 5. Concrete file and schema suggestions

- Database
  - Add `workflow_transitions` (or extend `research_activity_log`) with fields: id, entity_type, entity_id, from_state, to_state, actor_id, actor_role, reason, evidence_json, meta_json, created_at.
  - Add `review_assignments`: id, entity_type, entity_id, reviewer_user_id, assigned_by, assigned_at, due_at, status.

- Code
  - New file: packages/ahg-research/src/Services/WorkflowService.php
  - Policy classes: packages/ahg-research/src/Policies/ResearchPolicy.php, SubmissionPolicy.php
  - Controller adjustments: ResearchAdminController to call WorkflowService::transition(...) and to enqueue notifications.
  - Tests: packages/ahg-research/tests/Feature/ResearchWorkflowTest.php to cover allowed transitions, forbidden transitions, and bulk flow.

## 6. Acceptance criteria (how we mark this done)

- All approve/reject/suspend endpoints call WorkflowService::transition(...) and write structured audit rows. No direct ad-hoc `status` updates remain in admin controllers.
- Reviewer assignments can be created, reassigned, and show in admin queue. Overdue reviews are surfaced by a scheduled job.
- Bulk approve/reject works and produces a single bulk-audit record with per-item results.
- Policies enforce that only authorised actors can approve or reassign.
- Tests: feature tests for common workflows pass in CI.

## 7. Suggested implementation plan (phased)

Phase 0 — tests + discovery (1–2 days)
- Add feature tests that document current behaviour (create pending researcher, approve via admin endpoint, assert user active and status changed). These become the regression guard.
- Run grep for current ad-hoc status updates and listing of endpoints that mutate approval state.

Phase 1 — workflow service + audit (2–4 days)
- Implement `WorkflowService` and `workflow_transitions` writes; change controllers to call it for key flows (researcher approval, submission approval).
- Emit domain events and add `research_activity_log` entries where appropriate.

Phase 2 — reviewer assignment + UI (2–3 days)
- Add `review_assignments` table and UI for admin queue assignment & bulk operations; add scheduled SLA checker job.

Phase 3 — integration & polish (2–4 days)
- Hook transitions to compliance modules (RiC, repository deposit) and add acceptance tests.

## 8. Next action options
1. Add the WorkflowService scaffold + migration and modify one endpoint (approveResearcher) to use it — produce the unified patch for review. (Estimated 2–3 days).  
2. Add reviewer assignments table + admin UI for assignment and a small SLA checker job. (2–3 days).  
3. Add bulk-approval UI + atomic bulk operation in controller + tests. (1–2 days).  
4. Add feature tests that capture current behaviour so refactors are safe; run locally and post failing tests for triage. (0.5–1 day).

---

Document created by the Research technical runbook. Place under docs/research and link from the Research help index so operators can find it.
