# Access Requests — Research module

This note summarises the current codebase state for Access Requests as they relate to the Research module, lists gaps and incomplete code found in the repository, and proposes concrete enhancements and a phased implementation plan. Place this file under docs/research so operators and developers can review procedures and planned work.

---

## 1. Overview

Access requests are the mechanism by which external researchers request permission to see physical or restricted digital materials, or to get mediated access to sensitive collections. In Heratio the Research module integrates with the access-request package to: accept researcher requests, capture justification, link to archival descriptions, record decision events, and (optionally) generate appointment bookings and access agreements.

This document inspects the current code and suggests improvements to completeness, security, auditability and UX.

---

## 2. Gaps (what's missing)

- No unified "request workflow" UI in Research project studio
  - Evidence: research project studio views expose Sources/Notebooks/Writing/Claims but there is no single Research → Access Requests panel that aggregates a researcher's open requests and their statuses.

- Access request ↔ research project linkage inconsistent
  - Evidence: accession/requests routes exist (packages/ahg-access-request) but research_project → researcher_request foreign-key links are not consistently surfaced in the Research views.

- Missing reviewer-assignment and SLA tooling for access requests
  - Evidence: access-request package has a minimal decision table; there is no reviewer assignment UI or SLA tracking tiles for admins in ResearchAdminController.

- Limited automation between request approval and booking generation
  - Evidence: there is a `research_booking` table and `ahg-booking` package, but no confirmed event-driven bridge that auto-creates a booking when an access request is approved.

- Incomplete provenance for decisions and redaction notes
  - Evidence: access decision events are recorded but do not always include agent, mandate (legal basis), or the derived provenance node that RiC expects. Redaction and partial-access reasons are saved but not exported as machine-readable provenance.

- Tests for the end-to-end request lifecycle are sparse
  - Evidence: research tests cover workspace and provisioner; access request workflows lack dedicated feature tests linking researcher submission → admin decision → booking creation.

---

## 3. Incomplete / scattered code (files to inspect)

These files and locations are the concrete spots I found (or suspect) that need attention. Open them to review the current state before making edits.

- packages/ahg-access-request/src/Controllers/AccessRequestController.php — review submit/approve/reject handlers for provenance fields and event emission.
- packages/ahg-research/src/Controllers/ResearchController.php and ResearchAdminController.php — add links/views to show researcher's access requests and admin review UIs.
- packages/ahg-booking/src/Services/BookingService.php — check for an API to create bookings programmatically; used when an access request is approved.
- packages/ahg-ric/src/Services/RiCExportService.php — verify whether AccessRequest decision events are exported as RiC Event nodes with agent/mandate properties.
- packages/ahg-access-request/database/install.sql — confirm schema includes `decision_agent_id`, `decision_mandate_id`, `decision_evidence` columns; if not, plan migration.
- packages/ahg-research/tests/Feature — add new tests: AccessRequestLifecycleTest.php (submit → approve → booking created), AccessRequestProvenanceTest.php.

---

## 4. Enhancement suggestions (concrete, prioritized)

A. High priority (deliver in 1–2 sprints)

1. Researcher Project → Access Request panel
   - Add a Research-project-level tab showing all access requests that reference items used by the project. View should show status, decision reason, and a one-click link to request details.
   - Files: add Blade partial `packages/ahg-research/resources/views/research/partials/access_requests.blade.php` and a controller method in ResearchWorkspaceController.

2. Decision provenance and RiC alignment
   - Ensure every access decision (approve/reject/conditional) writes a detailed provenance row: agent id, mandate id, predicate (reason), supporting evidence (file id or link), timestamp and confidence. Export these as RiC Event nodes.
   - Files: extend `packages/ahg-access-request/src/Controllers/AccessRequestController.php` to call ai_provenance-style writer or RiC export helper when decision is saved.

3. Booking automation on approval
   - On approve: optionally auto-create a booking (if researcher requested an appointment) via BookingService. Booking must be optional (operator setting) and produce a pending booking that admins confirm.
   - Files: add an event listener for `access_request.approved` that calls BookingService::createFromRequest($requestId).

4. Reviewer assignment + SLA tracking
   - Add reviewer assignment UI and SLA timer (e.g. flag requests pending > 7 days). Expose SLA tiles in Research Admin dashboard.
   - Files: ResearchAdminController, small migration for `assigned_to`, `sla_due_at` on access_request table; scheduled job that flags overdue requests.

B. Medium priority (next 1–2 sprints)

1. Request templates and redaction workflows
   - Curators should be able to apply templates (conditions, redaction rules) to access requests to fast-track decisions.

2. Notifications & inbox integration
   - When decision is made, generate an inbox notification and (optionally) email to researcher. Link decision to project timeline.

3. Detailed audit trail & UI for redaction alternatives
   - Allow partial access proposals: researchers see which pages or files are accessible, which are redacted, and the justification. Log these alternatives as Events.

C. Low priority (future)

1. ML-assisted risk scoring for requests
   - Suggest risk level (low/medium/high) based on sensitivity metadata on linked records and the research purpose; surface to reviewers as a suggestion only.

2. Analytics & reporting
   - Add reports for turnaround time, decision reasons by type, top-requested items.

---

## 5. Implementation plan (phased)

Phase 0 — quick wins (1–3 days)
- Add Research panel partial that lists a researcher's access requests and their statuses. No DB changes required. Add a link to the access request create form. Add a small smoke test.
- Add small event emission on access decision: `event(new \AhgAccessRequest\Events\AccessRequestDecided($requestId, $decision))`.

Phase 1 — essential features (1–2 weeks)
- Migrate access_request schema if needed (add decision_agent_id, decision_mandate_id, decision_evidence, assigned_to, sla_due_at). Add tests.
- Implement event listener that creates a booking when certain flags are set in the approved request.
- Implement reviewer assignment and SLA job.
- Ensure RiC export includes access decision as Event nodes.

Phase 2 — polish and automation (2–4 weeks)
- Add templates, partial-access UI, notifications, and analytics. Add a dashboard for SLA breaches and reviewer workload.
- Add ML-assisted risk scoring as an opt-in feature; ensure all ML suggestions are logged in ai_provenance with human-review gates.

---

## 6. Acceptance criteria (how we know we're done)

- Research project pages show all access requests referencing items used by the project. Filtering by status/search works. Tests cover listing.
- Every decision writes a provenance record with agent + mandate + evidence and is exported to RiC. Tests validate RiC Event node presence for a sample decision.
- Approving a request that requested a booking results in a pending booking record created via BookingService; test covers the workflow.
- Reviewer assignment is available in admin UI and an SLA job flags overdue requests; tests assert SLA marking.

---

## 7. Files to change / create (draft)

- packages/ahg-research/resources/views/research/partials/access_requests.blade.php (new)
- packages/ahg-research/src/Controllers/ResearchWorkspaceController.php (add method to provide requests list)
- packages/ahg-access-request/src/Controllers/AccessRequestController.php (ensure provenance + event emission)
- packages/ahg-access-request/database/install.sql (migration adding `decision_agent_id`, `decision_mandate_id`, `decision_evidence`, `assigned_to`, `sla_due_at` if missing)
- packages/ahg-access-request/src/Events/AccessRequestDecided.php (new event)
- packages/ahg-booking/src/Listeners/CreateBookingFromApprovedRequest.php (listener)
- packages/ahg-research/tests/Feature/AccessRequestLifecycleTest.php (test)

---

Status: very good

Next action — outstanding issue to work on
1. Create the research partial and add a minimal controller method + smoke test (Phase 0).  
2. Add event emission on decision + a listener that creates a booking (Phase 1 listener stub).  
3. Prepare the DB migration for the access_request schema additions and a small data-migration plan.  
4. Do nothing — I will wait for your instruction.

Reply with the single digit (1–4) to pick the next action.