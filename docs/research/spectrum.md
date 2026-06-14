Spectrum — Condition Assessment and Integration with Research

Overview

Spectrum is the Collections Trust standard for condition reporting. It defines how to describe the physical condition of museum objects, how to record treatments and observations, and how to structure condition assessment data so it can be reliably shared, reported and acted upon. For a research-focused platform like Heratio the key opportunity is to treat condition assessments as first-class research outputs (observations, annotations, and treatments) tied to materials, projects, claims and provenance.

This document summarises the current gaps, incomplete code areas, suggested enhancements, and a staged implementation plan for Spectrum-focused condition reporting and annotation features inside the Research module. It assumes the Research package and the wider Heratio platform with the existing object/placement models and provenance stack.

1. First — Gaps (what is missing today)

- No explicit Spectrum data model: there is no dedicated condition_assessment or treatment table that implements the Spectrum entity set (condition_statement, condition_activity, assessor, condition_detail, signed-off flag, treatment actions).

- No UI for structured condition reporting: the Research writing studio and notebook can hold free-text notes and claims but lack a guided condition assessment form that maps to Spectrum fields (e.g. area, extent, severity, materials affected, treatment recommendation).

- Poor linkage to objects and manifestations: while Research links to information_object records, there is no canonical, versioned Condition resource attached to an information_object with a stable identifier.

- No condition provenance or acceptance workflow: condition observations are research outputs but there is no approval / sign-off flow (conservator review) or clear provenance metadata (who observed, date, instrument/method, photos, measurement files).

- No import/export for Spectrum or related interchange formats (CSV/JSON mapping or IIIF annotations for photographed damage) and no mapping into RiC for activity/event relations.

- No analytics or reporting on condition trends (per-collection, material, or defect typology) — useful for prioritisation and grant evidence.

2. Incomplete code (concrete spots to inspect / extend)

These are repository locations to check and the expected missing pieces:

- packages/ahg-research/src/Services/ResearchService.php
  - Evidence: the service offers activity logging and claim capture. Missing: methods to create/find condition_assessment records and attach media.

- packages/ahg-research/database/*.sql or migrations
  - Evidence: no migration exists for condition_assessment; add a migration to create condition_assessment, condition_photo, condition_measurement, condition_treatment tables.

- packages/ahg-research/resources/views/
  - Evidence: writing-studio and notebook views exist but no condition form view or partial. Add: resources/views/research/partials/condition_form.blade.php and a View Studio widget.

- packages/ahg-ric (exporter)
  - Evidence: RiC exporter supports Activity/Agent/Event but has no mapping for Spectrum Condition entities. Add: exporter mapping for Condition -> Event/RecordResource paths and include photos as evidence nodes.

- packages/ahg-iiif or imaging stacks
  - Evidence: image handling exists but there is no IIIF annotation support for damage bounding boxes or conservation photo records. Add: a lightweight IIIF Annotation export of condition photos.

- packages/ahg-research/tests/Feature
  - Evidence: tests cover general research flows but not condition assessment. Add tests: create condition, attach photo, change status, sign-off.

3. Enhancements and suggested features (concrete)

Short-term (low friction)
- Add a ConditionModel (condition_assessment) + migration with fields:
  - id, information_object_id, project_id (nullable), assessor_id, observed_at, condition_summary (short), details (JSON or text), severity (enum), extent, location_on_object (string), accepted_by_id, accepted_at, status (draft|submitted|signed_off|rejected), created_at, updated_at.
- Add attachments: condition_photos table: id, condition_assessment_id, media_id (file ref), caption, sequence_no.
- Render a simple condition_form partial and include it in Writing Studio and the Object view (info page) as an "Add Condition" action.
- Persist every change through ResearchService so it emits an activity log and writes ai_provenance only when suggestions come from an LLM.

Medium-term (workflow & integration)
- Add a sign-off workflow for conservators: submit → review queue (admin group) → accept/reject with reasons; acceptance writes an Event and updates RiC exports.
- Add IIIF annotation support for photo-based markups: allow user to draw bounding boxes on a condition photo and save as IIIF Annotation that attaches to the Condition record.
- Link condition records to Claims and the Contradiction Engine (a condition may support or contradict a claim about a record's integrity).
- Add an import/export routine: CSV ↔ Spectrum mapping and a small JSON-LD mapping into RiC/OA-like nodes for interoperability.

Long-term (analytics, mobile, sensors)
- Add bulk condition assessment mobile UI (PWA) for field surveys; offline-capable, sync, and photo-first.
- Add analytics dashboards: condition-by-material, trending defects, urgent-treatment queue.
- Add sensor / measurement ingestion: embed spectral or moisture readings as condition_measurement rows and visualise measurement trends.

4. Implementation plan — staged (small PRs)

PR 1 — Schema + Model + Service API (small)
- Migration: create condition_assessment, condition_photo, condition_measurement tables.
- Eloquent model: ConditionAssessment, ConditionPhoto, ConditionMeasurement.
- Service: ResearchConditionService with create/update/find/list methods.
- Tests: unit tests for model + simple create flow.
- Estimate: 1–2 days.

PR 2 — UI partial + Writing Studio integration
- Blade partial: resources/views/research/partials/condition_form.blade.php (form fields mapped to the model).
- JavaScript: small client-side helper to attach images and preview bounding boxes (deferred to PR 4 for full IIIF annotation tooling).
- Controller: new endpoints: POST /research/condition (store), GET /research/condition/{id} (show) and add to routes/web.php.
- Tests: feature test for the condition creation flow.
- Estimate: 1–2 days.

PR 3 — Sign-off workflow + admin list
- Queue UI for conservators, policy enforcement, ability to accept/reject, email/inbox notifications.
- Tests: feature tests for review and sign-off.
- Estimate: 2–3 days.

PR 4 — IIIF annotation support & RiC export mapping
- Implement light IIIF annotation save + exporter mapping from condition photos to IIIF annotation JSON and RiC-O/JSON-LD mapping for the condition as an evidence collection.
- Tests: integration test that exports condition -> RiC nodes.
- Estimate: 3–5 days.

PR 5 — Mobile PWA & analytics (epic)
- Offline-first mobile app components for rapid surveying; analytics dashboards and scheduled reports.
- Estimate: longer-term multi-sprint work.

5. Acceptance criteria (how to know the work is done)
- ConditionAssessment model, tables and API exist and meet the migration schema in PR1.
- Users can create a condition report from Writing Studio / Object view and attach photos; the record persists and is visible in the project object list.
- Conservators can sign-off a condition and the action is recorded as an audit event (riC activity) with agent & timestamp.
- IIIF annotation export works for at least one condition photo and is included in the RiC JSON-LD exporter.
- Tests cover create/update/sign-off and IIIF export flows; CI passes.

Files to inspect when implementing
- packages/ahg-research/src/Services/ResearchService.php (for emitting events)
- packages/ahg-research/src/Controllers/ResearchWorkspaceController.php (integration point)
- packages/ahg-research/resources/views/research/writing-studio.blade.php (UI insertion point)
- packages/ahg-ric/src/Exporter/* (where to add mapper for Condition)

6. Small UX notes
- Use severity & extent enums with human labels (severity: minor/moderate/major/critical; extent: localized/multiple/entire).
- Provide a simple "quick condition" flow (photo + short note) and an "advanced condition" modal (detailed fields) so conservators can work quickly in situ.
- Keep provenance front-and-centre: every record shows created_by, instrument/method, associated project, and a change log.

7. Security & privacy
- Condition photos may contain donor or personal data; ensure any PII in photos is handled per the platform's privacy settings and inherits the research project's access controls.
- Enforce upload size limits and virus-scan uploads as per platform conventions.

Status: very good

Next action — outstanding issue to work on
1. Create PR1: migration + models + ResearchConditionService + unit tests.  
2. Create PR2: condition_form partial + controller endpoints + feature test for create flow.  
3. Create PR3: sign-off workflow + admin queue + tests.  
4. Create PR4: IIIF annotation export + RiC mapping + integration tests.

