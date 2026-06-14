# Extended Rights — Condition Assessment and Annotations

This document summarises the current state of the Extended Rights functionality as it relates to Research workflows, lists gaps and incomplete code, and proposes concrete enhancements and an implementation plan. Place this file under docs/research so it is visible from the Research help index.

## Short summary
Extended Rights support exists in the codebase (policy models, restriction records, and some UI surfaces) but it is not fully integrated with Research workflows. Key missing pieces are (a) consistent annotation and condition-assessment capture in the Research UI, (b) provenance and export of restriction metadata, and (c) tests and operational guidance for administrators.

---

## 1. First look — gaps (what is missing today)
- No unified Condition Assessment form or model surfaced in the Research project studio. Research currently stores notes and restrictions in scattered places, but there is no first-class ConditionAssessment entity linked to research projects or sources.
- Annotations (structured, targetable comments with severity / remediation fields) are not available in the Writing Studio or Source viewer; only free-text notes exist.
- Lack of a cross-slice view showing which items in a Project carry rights/restrictions and what actions are required (redaction, access request, embargo lift date).
- Incomplete provenance tying a restriction/annotation to the agent who recorded it, the evidence (donor agreement / policy clause), and the decision path (who authorised remediation).
- Export gap: RIc and JSON‑LD exports do not include extended-rights assertions for Research objects; auditor and compliance exports cannot be generated.
- No feature flags or per-client enablement for condition-assessment workflows (some deployments may not require them).

---

## 2. Incomplete code (concrete places in the repo to inspect)
(These paths are from the current tree and represent starting points for the work.)
- packages/ahg-extended-rights/: core models and services live here; many utilities exist but research-specific glue is missing.
  - packages/ahg-extended-rights/src/Models/* (restriction, clause, policy)
  - packages/ahg-extended-rights/src/Services/ExtendedRightsService.php
- Research UI surfaces where integration is incomplete:
  - packages/ahg-research/resources/views/ (no condition-assessment partial / no per-source annotation panel)
  - packages/ahg-research/src/Controllers/ResearchWorkspaceController.php (no endpoints to create ConditionAssessment)
  - packages/ahg-research/src/Services/ResearchService.php (no linkage to ExtendedRightsService for automatic scans)
- Exporters:
  - packages/ahg-ric/src/Exporters/* — missing codepaths that include restriction nodes referencing research objects
- Tests:
  - packages/ahg-extended-rights/tests/ — limited coverage for the research integration scenarios; research package tests do not assert restriction export or annotation capture.

Concrete evidence: a code search for "condition"/"assessment"/"annotation" in the Research package returns few/no hits — indicating the feature is not yet wired into research flows.

---

## 3. Enhancements and suggested implementation (practical, incremental)
Priority order with short descriptions and acceptance criteria.

A. Add ConditionAssessment entity and UI (high)
- Create DB migration + model `research_condition_assessment` (id, research_project_id, source_id nullable, assessor_user_id, severity ENUM, recommended_action VARCHAR, notes TEXT, created_at, updated_at).
- Add API endpoints and a small Blade partial to capture an assessment in the Source viewer and the Project workspace. Wire to ResearchService and ExtendedRightsService.
- Acceptance: user can add an assessment to a source or project; a record is visible in the workspace and in a new "Assessments" tab; created_by and timestamps present.

B. Structured Annotations in Writing Studio and Source viewer (medium)
- Reuse existing annotation table (if present) or add `research_annotations` with fields: target_type/target_id, author_id, annotation_type, text, tags, remediation_required BOOLEAN, created_at.
- UI: inline annotation widget in Writing Studio (comment → tag → create assessment link). Allow converting an annotation into a ConditionAssessment.
- Acceptance: researcher can create an annotation and link it to an assessment; annotations appear in the object detail panel.

C. Rights scan & automated suggestions (medium)
- Implement a background job `ScanProjectForRestrictions` that queries placed sources and runs a set of deterministic checks (license flags, metadata fields, donor_agreement.restriction) and records suggested ConditionAssessments (as proposals). Each proposal is stored with provenance (source: "automated-scan", confidence, job_id).
- Acceptance: job runs per-project on demand and creates proposal assessments which a human can accept/reject.

D. Provenance + audit (high)
- Ensure every assessment/annotation creation writes a provenance row in `ai_provenance` (for AI-assisted suggestions) or `research_activity_log` (for human actions). Capture: agent, method (ui/manual/auto), evidence_id, evidence_text, confidence.
- Acceptance: audit for an assessment shows who proposed it, who accepted it, and when.

E. Export + Interop (RiC / JSON‑LD) (medium)
- Extend RiC exporter to include ConditionAssessment as an entity related to RecordResource/Activity with properties: assessor, recommended_action, severity, evidence pointer.
- Acceptance: exporter output contains <ConditionAssessment> nodes linked to the corresponding RecordResource.

F. Admin UI & policy enforcement (medium)
- Add settings to enable/disable condition assessments per-deployment and to configure default severity thresholds that require escalation to an administrator.
- Acceptance: settings toggle shown in admin, and when disabled the assessment UI is hidden.

G. Tests & docs (low-medium)
- Add feature tests for assessment creation, annotation linking, automated scan proposals, and exporter output.
- Update docs: add this new MD (Condition Assessment & Annotations) to docs/research and link from Research help.

---

## 4. Suggested file to add
I will add this document to:

`/usr/share/nginx/heratio/docs/research/condition-assessment-and-annotations.md`

(If you prefer a different filename please tell me.)

---

## 5. Implementation plan (staged PRs)
- PR 1 (small): DB migration + model + simple API + Blade partial inserted in Source viewer — smoke tests. (1–2 days)
- PR 2: Annotations widget in Writing Studio & linking UI (1–2 days)
- PR 3: Background scan job + proposal flow + accept/reject endpoints + provenance logging (2–3 days)
- PR 4: RiC export changes + exporter tests (1–2 days)
- PR 5: Admin settings + docs + full test sweep (1–2 days)

---

If you want I can scaffold PR 1 now (migration + model + API stub + partial) and prepare the unified patch for your review. Reply with `scaffold` to start or say which PR number above to implement first.