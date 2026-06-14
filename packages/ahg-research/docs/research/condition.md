Condition assessment & Annotation — evaluation and recommendations

Summary

This note evaluates the current "condition assessment" and "annotation" capabilities in the Research module and documents concrete gaps, incomplete code areas, and recommended enhancements. All findings are grounded in the repository (table names, views, controllers and SQL found under packages/ahg-research). Use this as a development checklist and an operator-facing explainer.

Where to look in the codebase (evidence)
- Schema / tables (package):
  - packages/ahg-research/database/install.sql — fields and tables referencing condition: condition_before, condition_after, condition_status, condition_notes, return_condition, condition_on_return, etc.
  - research_custody_handoff table (condition_at_handoff, condition_notes)
  - research_activity_material (condition_before, condition_after)
  - research_equipment, research_equipment_maintenance (condition fields)
  - research_annotation, research_annotation_target, research_annotation_v2

- Views / templates:
  - packages/ahg-research/resources/views/research/custody-checkout.blade.php (checkout condition select)
  - packages/ahg-research/resources/views/research/custody-return-verify.blade.php (return condition select)
  - packages/ahg-research/resources/views/research/equipment.blade.php (equipment condition UI)
  - packages/ahg-research/resources/views/research/batch-return.blade.php (batch return condition inputs)
  - packages/ahg-research/resources/views/research/document-templates.blade.php (condition_report template option)

- Controllers and services:
  - packages/ahg-research/src/Controllers/ResearchController.php — custody/condition handling code paths (checkout/return/condition update flows).
  - packages/ahg-research/src/Services/ResearchService.php and related services used by the controllers for handoffs and condition updates.

- Annotation support (tables & concept):
  - research_annotation (simple annotation model)
  - research_annotation_target (selector, canvas/IIIF fields)
  - research_annotation_v2 (JSON bodies, motivations) — indicates movement toward Web Annotation-style storage

What exists today (facts)
- The database contains fields for recording object condition at multiple points (handoff, return, equipment maintenance)
- Several UI forms already allow operators to record a condition (checkout/return/equipment forms and batch-return flows)
- A document template option exists for "condition_report" suggesting the system can render condition reports via templates
- Annotation tables exist in two flavours: a simple research_annotation and a richer research_annotation_v2 with JSON bodies and selector types. The target table supports selectors (TextQuoteSelector, SvgSelector, etc.) and canvas_id / iiif_annotation_id fields

Gaps and incomplete code (concrete)
1. No dedicated Condition Report entity or workflow
   - Evidence: there is no dedicated research_condition_report table; condition data is stored across handoff/maintenance/material rows and free-text condition_notes. That fragments reporting and makes it hard to produce a single authoritative condition report per object or event.
   - Impact: generating a formal condition report (PDF/archival record) requires aggregating multiple fields and is error-prone.

2. Limited structured condition provenance and media attachments
   - Evidence: condition_before/after are text or small enums; no standard place to attach photos/scans tied to a condition entry (photos are supported elsewhere but not consistently linked to a condition event).
   - Impact: conservators need image evidence with timestamps and operator identity; current fields record notes only.

3. Annotation UX and WebAnnotation compliance incomplete
   - Evidence: tables for annotation_v2 and annotation_target exist, but consolidating UI paths (create, edit, share, IIIF POST/GET) and connector endpoints are spotty. No obvious controller that publishes IIIF annotations or consumes IIIF Canvas editing flows.
   - Impact: researchers cannot reliably create shareable, addressable annotations that interoperate with IIIF viewers and external annotation tools.

4. No structured conservation workflow (triage → repair → verify)
   - Evidence: equipment_maintenance exists and material_request tracks triage; but there is no single conservation queue UI or status machine specifically for condition assessments leading to conservation actions.

5. Missing tests and exports
   - Evidence: I did not find targeted PHPUnit feature tests asserting condition workflows (create assessment, attach photo, return verify).
   - Impact: regressions are likely and reporting/export (condition_report PDF, RiC/JSON-LD) behavior is untested.

Recommended enhancements (detailed & prioritized)

High priority (deliver in 1–3 days)
- Create a first-class ConditionReport entity and table
  - New table: research_condition_report
    - fields: id, object_id, object_type, assessor_id, assessor_role, reported_at, condition_state_before, condition_state_after, condition_code (enum), notes, media_json (array of attachment metadata), provenance_json (who/when/why), created_at/updated_at
  - Controller: ConditionReportController: create, edit, show, export (PDF) endpoints
  - Accept attachments (photos) and store metadata (path, mime, width/height, hash) in media_json. Ensure file ownership and access control.
  - Acceptance: operator can create a ConditionReport event that appears in custody_handoff and in activity logs; PDF export available.

- Link ConditionReport to custody_handoff and research_activity_log
  - When a condition check happens (handoff or return), create a ConditionReport record and reference it from research_custody_handoff.condition_report_id (or record id in details JSON)
  - Log the action via research_activity_log with entity_type=condition_report and entity_id set to the new id

Medium priority (3–7 days)
- Full IIIF/WebAnnotation integration for annotations & condition evidence
  - Provide endpoints to publish annotations (POST to /research/annotations) and to serve annotation lists for a canvas/manifest
  - Ensure research_annotation_v2 body_json schema matches W3C Web Annotation (body, target, motivation) so external tools can import/export
  - Acceptance: a conserved photo + annotation URI pair can be embedded in an IIIF viewer and retrieved via the annotation endpoint

- Conservation workflow UI
  - Create a conservation queue view that lists condition reports requiring action (triage_status), with assign/accept/reject/complete actions and links to material_request and maintenance records

Lower priority (7–14 days)
- Provenance and retention policy for condition records
  - Persist provenance for every condition report (who created/edited, which tool, IP, device), map to research_activity_log and ai_provenance (if AI-assisted image enhancement was used)
  - Add retention settings and a prune command for old condition reports with legal hold support

- Tests + exports
  - Add feature tests verifying creation of ConditionReport + attachment + export + custody_handoff linking
  - Add RiC mapping: represent condition report as an Event in RiC exporter so that the archival graph includes conservation events

Concrete code pointers for implementers
- Add DB table migration file under: packages/ahg-research/database/migrations/2026_xx_xx_create_research_condition_report.php
- New controller + service: packages/ahg-research/src/Controllers/ConditionReportController.php and packages/ahg-research/src/Services/ConditionReportService.php
- Views: packages/ahg-research/resources/views/research/condition/report_form.blade.php and report_show.blade.php
- Annotation endpoint: packages/ahg-research/src/Controllers/AnnotationController.php (if not already present), tie to research_annotation_v2 and research_annotation_target
- Tests: packages/ahg-research/tests/Feature/ConditionReportTest.php (create/store/attach/export), AnnotationWebTest.php (IIIF compatibility)

Acceptance criteria (how to tell work is done)
- Operator can create a Condition Report from the custody checkout/return UI and attach photos.
- ConditionReport rows are created and linked to custody_handoff rows; research_activity_log contains entries for create/update.
- PDF export renders a human-readable Condition Report including attachments (links) and provenance.
- Annotations created on a condition photo are persisted in research_annotation_v2 and are retrievable via an annotations endpoint using IIIF canvas_id.
- Unit and feature tests cover create/store/attach/export and pass in CI.

Minimal quick wins (30–120 minutes)
- Surface existing condition fields in one consolidated "Condition" panel on the custody handoff page (show condition_before, condition_after, condition_notes and link to uploads).
- Add a document template rendering for condition_report (populate template with fields from the condition panel and allow download as PDF).

Files to inspect now (commands to run locally)
- grep for condition fields: grep -n "condition_before\|condition_after\|condition_notes" packages/ahg-research
- Open custody check view: packages/ahg-research/resources/views/research/custody-checkout.blade.php
- Open annotation tables: packages/ahg-research/database/install.sql (look for research_annotation and research_annotation_v2)

Status: very good
Next action — outstanding issue to work on
1. Create the research_condition_report migration + ConditionReport controller/service + basic create/show views (PR).
2. Implement IIIF-compatible annotation publish/serve endpoints backed by research_annotation_v2.
3. Add feature tests for ConditionReport creation + attachment + PDF export.
4. Do the quick-win UI consolidation (condition panel) and template PDF rendering.

Reply with the single digit (1–4) to pick the next task and I will produce the exact patch(es)/diff(s).