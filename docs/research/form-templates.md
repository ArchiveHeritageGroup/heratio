# Form Templates — Assessment, gaps, incomplete code, and enhancements

Location added: /usr/share/nginx/heratio/docs/research/form-templates.md

1. Overview

Form templates (the library of reusable form definitions, field groups and render helpers) are a key part of the Research UX: they speed project intake, ensure metadata consistency, and power imports. This note summarises what I found in the codebase, concrete gaps, incomplete code, and recommended enhancements with a pragmatic implementation plan.

2. First look: concrete gaps (repo-grounded)

- No centralised template registry UI: there is no single admin page to list, version, enable/disable form templates. (inspect: packages/ahg-forms, but no admin UI under packages/ahg-research).
- Template versioning and migration absent: templates are stored as JSON/YAML blobs in files or DB but there is no versioning policy or upgrade path when a template changes.
- No structured import preview: when an import uses a template, operator cannot preview mapping or validation results before running the import (packages/ahg-data-migration has import code but mapping UI is limited).
- Sparse test coverage: packages/ahg-forms and research import glue have few unit/feature tests; changes to templates risk breaking multiple consumers.
- Limited field type coverage in templates: many advanced field types (controlled vocabulary picklists, RiC agent pickers, date-range + partial dates, multi-language labels) are only partially implemented in template renderers.

3. Incomplete / fragile code locations (file pointers)

(These are concrete files to inspect and remediate)

- packages/ahg-forms/src/Services/FormTemplateService.php — provides CRUD but lacks versioning APIs and validation hooks.
- packages/ahg-forms/resources/views/* — view fragments exist but lack centralised partial for rendering advanced field types.
- packages/ahg-data-migration/src/Controllers/ImportController.php — mapping UI exists but preview/validate step is shallow.
- packages/ahg-research/src/Services/ProjectImportService.php — uses templates during import but does not surface validation errors in a structured way.
- packages/ahg-research/resources/views/import/preview.blade.php — missing or minimal; should be a full preview with sample row mapping and validation errors.

4. Enhancement suggestions (prioritised)

High priority (user-visible / low-effort)
- Add a Template Admin index (list + enable/disable + version) under Research admin. Wire to FormTemplateService. (files: new controller packages/ahg-research/src/Controllers/FormTemplateAdminController.php, views under packages/ahg-research/resources/views/admin/templates/).
- Add an import Preview step that validates the CSV/Excel sample rows against the selected template and shows errors/warnings. (enhance ImportController + preview blade)
- Ensure all template field types render a consistent preview widget in the admin UI (text, date, controlled-vocab, agent-picker, file, boolean, multi-lang). Move renderer helpers to a single Blade partial: packages/ahg-forms/resources/views/partials/_field_preview.blade.php.

Medium priority (infrastructure / safety)
- Add versioning to FormTemplateService: create template_versions table and APIs to create / rollback versions. Provide a migration path for existing templates. (DB migration + service + UI).
- Add template validation schema (JSON Schema or internal) and run validation on create/update; reject templates that reference missing controlled vocabularies or unknown field types.
- Expand field-type coverage: implement RiC agent-picker field type (autocomplete that resolves to actor table), date-range + partial date support, and controlled-vocab multi-select.

Lower priority (scale & integrations)
- Add a Template Repository API (read-only) so other services (repository, accession, cataloguing) can fetch canonical templates via HTTP internal API. (packages/ahg-forms expose API controller)
- Add an export/import (JSON) for templates so operators can move templates between instances.

5. Suggested implementation plan (staged PRs)

PR A — Admin index + field preview partial (safe, small)
- Create FormTemplateAdminController with index/create/edit actions.
- Add views: admin/templates/index.blade.php, admin/templates/edit.blade.php.
- Add partial: packages/ahg-forms/resources/views/partials/_field_preview.blade.php.
- Add one feature test that the index renders and field preview renders for a sample template.
- Effort: 1–2 days.

PR B — Import preview + validation (medium)
- Enhance ImportController preview action to validate sample rows using template validation rules and render structured errors (row/col → message).
- Add preview blade with sample row table and error markers; add an Ajax endpoint for live preview on file select.
- Add unit tests for sample validations.
- Effort: 2–3 days.

PR C — Template versioning and migration (larger)
- Migration to add template_versions table + roll-forward logic in FormTemplateService.
- UI to view versions and rollback to a previous version.
- Tooling to migrate existing template blobs into initial versions.
- Effort: 3–5 days.

PR D — Field-type coverage expansion (incremental)
- Implement RiC agent-picker field (autocomplete + validation), date-range/partial date field type, and controlled-vocab multi-select component with thesaurus integration.
- Add tests for each field type preview and import mapping.
- Effort: 3–5 days.

6. Acceptance criteria (how to know it’s done)

- Admin index present and can list templates, create new, edit, enable/disable. (UI test + feature test)
- Import Preview shows sample rows and line-level validation errors before committing import. (manual + automated test)
- Templates have versions; operator can view and rollback. (migration run + UI)
- Field preview partial renders all field types consistently and is re-used across admin and preview screens. (code reuse verified by grep)
- No uncontrolled direct writes to template storage outside FormTemplateService. (grep-audit)

7. Files I will add now

If you instruct me to proceed I will create a small PR A that adds:
- packages/ahg-research/src/Controllers/FormTemplateAdminController.php (index + show minimal)
- packages/ahg-forms/resources/views/partials/_field_preview.blade.php
- packages/ahg-research/resources/views/admin/templates/index.blade.php
- packages/ahg-research/tests/Feature/FormTemplateAdminTest.php (simple smoke test)

I will also lint (php -l) all new PHP files before presenting the unified patch.

---

File created: /usr/share/nginx/heratio/docs/research/form-templates.md

Next action — pick one:
1. Scaffold PR A (admin index + field preview partial) and post unified patch for review.
2. Only link this doc into the research docs index.
3. Run a repo search for existing template usage points to prepare further PRs.
4. Do nothing.

Reply with the single digit (1–4).