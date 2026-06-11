# Research Decision Log (per-project) - technical reference

The Decision Log (heratio#1224, Research OS Stage 9) is a per-project audit trail of THINKING: the recorded memory of every loop. It is distinct from `research_activity_log` (system audit of WHAT happened); the Decision Log records WHY. It answers an examiner's "why did you exclude X" with receipts and feeds the limitations section. It lives in the `ahg-research` package.

## Data model
Table `research_decision_log`:

- `id` BIGINT UNSIGNED PK
- `project_id` INT, FK to `research_project(id)` ON DELETE CASCADE
- `decision_type` VARCHAR(64) - NOT a MySQL ENUM. Values come from the `ahg_dropdown` taxonomy `decision_type` (scope_change, exclusion, hypothesis_revision, method_pivot, question_reformulation, supervisor_instruction, other), with a clearly-seeded fallback list in the service.
- `summary` VARCHAR(500) - one-line statement of the decision
- `reason` TEXT - the reasoning (recommended)
- `related_ref` VARCHAR(500) - e.g. excluded source id + label, dataset, case
- `decided_by` VARCHAR(255) - defaults to the acting researcher's name
- `decided_at` DATETIME - when the decision was made (defaults to now)
- `created_at` TIMESTAMP

Indexes: project_id; decision_type; (project_id, decided_at).

## decision_type handling
`decision_type` is a VARCHAR holding a dropdown code, never an ENUM (per project rules). The authoritative source is `ahg_dropdown` taxonomy `decision_type`; `DecisionLogService::types()` reads it (active rows, sorted) and falls back to `DecisionLogService::FALLBACK_TYPES` if the dropdown is absent. Create/update whitelist the type against `typeCodes()` and coerce unknowns to `other`. The seven values are seeded via `database/seed_decision_log_dropdowns.sql` (and mirrored in `seed_dropdowns.sql`), editable in the Dropdown Manager.

## Auto-install / auto-seed
`AhgResearchServiceProvider::boot()` runs a single guarded `app->booted` block (one outer try around `Schema::hasTable` + `DB::unprepared`, per the CI-schema rule):
1. If `research_decision_log` is missing, run `database/install_decision_log.sql`.
2. If the `decision_type` taxonomy is missing from `ahg_dropdown`, run `database/seed_decision_log_dropdowns.sql`.
The table is also appended to `database/install.sql` and the dropdown rows to `seed_dropdowns.sql` for fresh installs.

## Service
`AhgResearch\Services\DecisionLogService` (singleton): `types()`, `typeCodes()`, `typeMeta()`, `listForProject($projectId, $type=null)` (newest first, optional type filter), `find()`, `countsByType()`, `create()`, `update()`, `delete()`. Every method is `Schema::hasTable`-guarded and try/catch-wrapped, degrading to empty/null/false rather than throwing.

## Controller + routes
`AhgResearch\Controllers\DecisionLogController` is auth-gated. Access: owner + collaborators can view; owner, editor-collaborators (`role` in owner/editor/admin) and admins (`AclService::canAdmin`) can edit. Routes live in a separate file `routes/decision-log.php`, loaded from the service provider (kept out of the shared `routes/web.php`). All paths are two-segment-or-deeper under `/research/projects/{projectId}/decisions/...` so the global `/{slug}` catch-all never intercepts them. Names: `research.decisions.index|create|store|edit|update|destroy`.

## Views
- `research::research.decision-log` - the per-project timeline: type-filter chips with counts, coloured type badge + summary + reason + related ref + who/when, edit/delete actions, and an empty state ("No decisions recorded yet - the log is the memory of every loop"). Never 500s.
- `research::research.decision-log-form` - the add/edit form with the `decision_type` dropdown, summary, reason, related reference, decided-by, and decided-at.

Both extend `theme::layouts.2col` with the research sidebar, Bootstrap 5 + FontAwesome, central theme colours.

## Notes
- `getSidebarData` in `ResearchController` is intentionally NOT edited; Command Centre / sidebar wiring is tracked separately. The controller computes its own sidebar payload.
- Jurisdiction-neutral / international: no market-specific assumptions.
