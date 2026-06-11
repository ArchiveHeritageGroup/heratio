# Research Analysis Bridge (Research OS Stage 11, heratio#1234)

The Analysis Bridge is a provenance layer in `ahg-research`. It does NOT run
analysis engines. It registers results produced elsewhere (Jupyter, R, QDA,
statistics packages) with their full origin metadata, and links each result to
the project claim(s) it supports, weakens or contextualises. The value is an
auditable chain from source data to claim - no black-box outputs.

## Data model

Three NEW tables (no ALTER of any existing table). Auto-created on boot by
`AhgResearchServiceProvider` from `database/install_analysis_bridge.sql`
(CREATE TABLE IF NOT EXISTS, Schema::hasTable-guarded).

- `research_analysis_result` - one registered result. Columns: `id`,
  `project_id`, `result_type` VARCHAR (chart|table|theme|statistic|other),
  `title`, `source_data_ref`, `source_data_version`, `method`, `code_ref`,
  `generated_at`, `researcher_decision`, `artifact_path` (nullable, relative),
  `created_by`, `created_at`, `updated_at`.
- `research_analysis_result_claim` - links a result to a Claim Ledger claim.
  Columns: `id`, `result_id`, `assertion_id` (-> `research_assertion.id`),
  `relationship` VARCHAR (supports|weakens|contextualises), `note`, `created_at`.
  Unique on (`result_id`, `assertion_id`). No FK so partial installs never block.
- `research_analysis_code` - light built-in thematic coding. Columns: `id`,
  `project_id`, `kind` VARCHAR (theme_tag|memo), `label`, `body`, `created_by`,
  `created_at`.

All enumerated values are VARCHAR, never ENUM (per project rule).

## Provenance model

Each result must surface its origin before it can mean anything: the source data
and its version, the method, the code/notebook reference, the generation date,
and the researcher's decision. The detail view renders these as a Provenance
card. Claims are linked from the Claim Ledger (`research_assertion`), and each
link carries a relationship so a result can explicitly strengthen or undermine a
claim.

## Storage

Optional artifact uploads land under
`config('heratio.storage_path').'/research-analysis/{projectId}/{resultId}/'`.
The stored `artifact_path` is RELATIVE to the storage root (portable); never a
hardcoded absolute path. Download is traversal-guarded with realpath containment
inside the storage root.

## Routes

Self-contained file `routes/analysis-bridge.php`, loaded by the provider via a
`Route::group([], ...)` include so `routes/web.php` is untouched. Names under
`research.analysis.*`, paths `/research/projects/{projectId}/analysis/...`
(three+ segments, so the locked `/{slug}` catch-all never intercepts them).
Middleware `['web','auth']`.

Produced names: `research.analysis.index`, `.store`, `.show`, `.update`,
`.destroy`, `.artifact`, `.link`, `.unlink`, `.codes.add`, `.codes.delete`.

## AI

Optional, clearly-labelled assistance only (e.g. `suggestCaption`), routed
exclusively through `AhgAiServices\Services\LlmService::complete()` - the AHG
gateway abstraction, never a node port. The feature works fully without AI and
degrades silently when AI is unavailable.

## Resilience

Every query is `Schema::hasTable`-guarded and wrapped in try/catch; missing
tables degrade to an empty state rather than a 500. Writes are confined to the
three new tables; existing tables are read-only here.
