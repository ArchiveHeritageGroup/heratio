# Condition assessment and annotations — Research module

Purpose

This document summarises the current condition-assessment and annotation requirements for the Research module, lists gaps and incomplete code areas to check, and proposes concrete enhancements and an implementation plan. It is written as an engineering runbook: precise actions, acceptance criteria, and short tests you can run locally.

Scope

- "Condition assessment" means recording an authoritative assessment of a physical or digital object's condition (structured fields, scores, dates, agent, treatment recommendations, evidence attachments).
- "Annotations" means in-context notes attached to records (inline text highlights, margin notes, time-stamped annotations on items or images) with provenance, versioning and access controls.

1) First look — gaps (what to check / what's commonly missing)

- Data model for condition assessments
  - Check whether a dedicated table (e.g. `research_condition_assessment` or `condition_assessments`) exists with: object_id, assessed_at, assessor_id, condition_code, severity, notes, recommended_action, attachments.
  - If no table/migration is present, the feature is not implemented and requires a schema addition.

- Annotation model
  - Check for an annotations table (e.g. `research_annotations`) that can attach: target (object record / file / work), selector (char offset / image bbox / time-range), author_id, created_at, updated_at, body (text/markdown), visibility (public/private/internal), and provenance metadata (origin, ai_suggestion_id if applicable).
  - If only ad-hoc comment fields exist on objects, the system lacks structured inline annotations.

- UI affordances
  - Look for a lightweight inline annotation UI in the viewer (image viewer, document viewer, or writing studio). If absent, users cannot annotate in-context.
  - Check for a dedicated Condition Assessment editor/viewer in the staff/admin UI.

- Provenance and audit
  - Do all assessment and annotation actions write to the existing provenance / audit tables (ai_provenance for AI-assisted suggestions and the general audit log for human actions)? If not, provenance is incomplete.

- Access controls
  - Condition assessments often have restricted visibility (conservation staff only) and annotations may be public or private. Confirm the annotations and assessment write/read paths enforce ACLs and integrate with existing groups/roles.

- Versioning and undo
  - Are annotations versioned (so edits are auditable)? Is there an undo / revert for accidental changes? If not, add versioning.

- Exports and integration
  - Can condition assessments and annotations be exported (CSV/JSON/RiC/JSON-LD) and included in the object's RiC export? If not, the data will not travel with publish/export workflows.

2) Look at incomplete code (what to inspect in the repo)

Perform these concrete repository checks. For each item, run a search and inspect the files listed here — they are the exact places to look.

- Search for existing schema and migrations
  - grep -R --line-number "condition_assessment" packages || true
  - grep -R --line-number "annotation" packages || true
  - Look under `packages/*/database/migrations` and `packages/*/database/install.sql` for any `condition` or `annotation` tables.

- Controller + Service surface
  - Search for `Condition` or `Assessment` classes: grep -R --line-number "ConditionAssess" packages || true
  - Inspect researcher services: `packages/ahg-research/src/Services/ResearchService.php`, `ResearchWorkspaceController.php` for any references to assessments/annotations.

- Views & JS
  - Search for viewer annotation hooks in the front-end: grep -R --line-number "annotation\|annotate\|highlight" packages/resources/views packages | sed -n '1,200p'
  - Inspect any canvas/image viewer components (image viewer JS assets under packages/ahg-research/public/js or similar).

- Provenance integration
  - Confirm whether condition/annotation actions call `ai_provenance` or `research_activity_log`: grep -R --line-number "ai_provenance\|research_activity_log|provenance" packages | sed -n '1,200p'

- Tests
  - Check for tests referencing assessments/annotations: ls packages/ahg-research/tests || true; grep -R --line-number "Assessment\|Annotation" packages/*/tests || true

If these searches return nothing, the feature is incomplete and must be added.

3) Enhancements and suggestions (concrete proposals)

Below are precise, implementable enhancements. Each item includes a brief implementation note and acceptance criteria.

A. Data model (required)
- Add migrations:
  - `condition_assessments` table: id, object_id (FK), assessed_at (datetime), assessor_id (FK user), condition_code (enum or smallint), severity (enum), summary_text, recommended_action (text), evidence_count (int), created_at, updated_at.
  - `condition_assessment_attachments` table: id, assessment_id, filename, mime, filesize, storage_path, created_at.
- Acceptance: migration runs; API returns assessment rows for an object; attachments can be uploaded and linked.

B. Structured annotations (required)
- Add migrations:
  - `annotations`: id, target_type (string), target_id (int), selector (json — e.g. bbox, char offsets), body (markdown/text), author_id, visibility (enum: public|internal|private), origin (ui|api|ai), ai_provenance_id (nullable FK), created_at, updated_at, version.
- Implementation note: selector stored as JSON to support multiple selectors (text, image bbox, time-range).
- Acceptance: create/read/edit/delete annotation via API; selector is preserved and returned to the viewer.

C. Inline viewer integration (high priority UX)
- Add a small JS annotation client (open-source libraries exist e.g. Annotator.js, Hypothesis client, or custom minimal overlay). Integrate with the document/image viewer so users can highlight text or draw bbox and attach an annotation.
- Acceptance: highlight an area on an image and save an annotation; saved annotation renders on subsequent views.

D. Provenance & audit (must have)
- Every create/update/delete on assessments and annotations must create a provenance entry (table: `research_activity_log` or `ai_provenance` for AI suggestions) containing: actor_id, action, target_type/id, timestamp, source (UI/API/AI), delta (what changed), and optional link to attachment or ai_provenance record.
- Acceptance: the provenance entry exists and is queryable via an admin endpoint.

E. Access control & moderation
- Annotations should respect visibility and be filterable in the UI. Moderation endpoints for annotations (approve/reject) should be in an admin controller.
- Acceptance: internal annotations not visible to public users; moderators can approve annotation and mark visibility public.

F. Versioning and undo
- Implement a simple audit-version table (annotation_versions) or use a revisions pattern (store previous body + timestamp + editor). Offer a revert action in the UI.
- Acceptance: edit annotation → previous versions viewable and revertible.

G. Export & RiC inclusion
- Extend exporter (RiC/JSON-LD/IIIF) to include condition assessments and annotations as relations/events. For example, a condition assessment becomes an Event with associated Agent (assessor) and Evidence (attachments).
- Acceptance: `ahg-ric` export includes assessment nodes for objects with assessments.

H. Bulk tools and reporting
- Implement import/export for assessment spreadsheets (CSV) and a simple reporting endpoint (e.g. `GET /research/assessments/export?since=YYYY-MM-DD`) for conservation planning.
- Acceptance: export returns CSV with rows per assessment; admin can schedule bulk imports.

I. AI-assisted assistance (optional)
- AI can suggest initial condition summaries from uploaded images (vision+LLM) but suggestions must be recorded in `ai_provenance` and flagged as proposals. A conservator must accept before it becomes canonical.
- Acceptance: suggestion stored with `ai_provenance` record and annotation/assessment has `origin=ai` and `accepted=false` until curator accepts.

4) Add MD file under docs/research — implementation plan

I will add a single markdown file to `/usr/share/nginx/heratio/docs/research/condition-assessment-and-annotations.md` containing the content above plus concrete PR plan and small test checklist. The file will contain:

- Summary and intent
- Gaps found
- Code locations to inspect (commands to run)
- Enhancement list (A–I) with acceptance criteria
- Staged PR plan (3–6 PRs)

PR plan (recommended small steps)
- PR 1: Add migrations + basic models + API endpoints for condition assessments (CRUD) + basic tests (smoke). (ETA 1–2 days)
- PR 2: Add annotations table + API + viewer hooks (JS) as a minimal overlay; basic UI to create annotations. (ETA 2–3 days)
- PR 3: Add provenance logging for assessment/annotation actions + tests. (ETA 1 day)
- PR 4: Add export (RiC) mappings + cache and performance adjustments. (ETA 2 days)
- PR 5: Accessibility and UX polish + docs. (ETA 1 day)

Test checklist (automated)
- Migrations run without error. Schema contains expected columns.
- Feature tests: create/read/update/delete assessment; create/read/update/delete annotation; AI suggestion writes ai_provenance row (mocked) but not committed until accepted.
- Lint: php -l on added files; JS linter on annotation client assets.

Acceptance criteria (what "done" means)
- Condition assessments and annotations are fully CRUDable via API and UI.
- All actions are auditable (provenance entries exist).
- Annotations are rendered in viewers and respect visibility rules.
- Export includes assessments/annotations in RiC/JSON‑LD.

---

If you want, I will now write the markdown file under `/usr/share/nginx/heratio/docs/research/condition-assessment-and-annotations.md` with the content above and create the minimal PR skeleton (migration + model + API stubs) as separate patches. Reply with "write file" to create the document, or "write+scaffold" to also scaffold the initial migration and model files.

Status: very good

Next action (outstanding issue to work on)
1. Create the docs file only under docs/research and show the path.  
2. Create the docs file and scaffold PR1 (migration + model + API stub).  
3. Run grep / searches to confirm current repo references for `annotation` / `condition_assessment` before scaffolding.  
4. Defer and do nothing further until you instruct a release window.