# Research Question Builder (technical reference)

The Question Builder (heratio#1226, Research OS Stage 2, epic #1222) refines a
research project's question into a structured, VERSIONED Research Design Brief
before deep source collection. It lives in `packages/ahg-research`. Every save
appends a new immutable version that retains the reason for the change.

## Data model

Two tables, created idempotently on boot from
`packages/ahg-research/database/install_question_builder.sql`:

- `research_question_brief` - one row per project (UNIQUE on `project_id`).
  Holds `current_version`, `status` (VARCHAR, dropdown-backed, no ENUM),
  `created_by`, timestamps.
- `research_question_brief_version` - one row per save. Holds `version_no`
  (UNIQUE per brief), the ten design fields (`broad_topic`,
  `problem_statement`, `research_gap`, `primary_question`,
  `secondary_questions`, `hypothesis`, `scope_boundaries`, `key_definitions`,
  `assumptions`, `bias_risks`), the `change_reason`, `created_by`, `created_at`.

Versions are never updated in place. Saving computes `MAX(version_no)+1`,
inserts a new version row, and advances `research_question_brief.current_version`.

## Auto-install

`AhgResearchServiceProvider::boot()` runs a `booted()` callback that wraps
`Schema::hasTable('research_question_brief')` and the `DB::unprepared($sql)`
install in ONE outer try/catch (per the CI hasTable pattern). It is idempotent
and retries on the next boot if the DB is not ready.

## Code

- `AhgResearch\Services\QuestionBuilderService` - storage + diagnosis. Public
  API: `isReady()`, `getBrief()`, `getCurrentVersion()`, `getVersions()`,
  `getVersion()`, `saveVersion()`, `diagnose()`, `aiAvailable()`,
  `aiDiagnosis()`. The constant `QuestionBuilderService::FIELDS` is the ordered
  list of design fields. Every query is Schema::hasTable-guarded and wrapped in
  try/catch so a fresh install shows an empty state instead of a 500.
- `AhgResearch\Controllers\QuestionBuilderController` - `builder()`, `save()`,
  `history()`, `diagnose()` (AJAX JSON).

## Diagnosis

`diagnose(array $brief): array` is pure PHP / heuristic and never calls AI. It
returns flags `['key','label','level','message']` for: too broad, too narrow,
gap not stated (possibly already answered), method scaffolding thin, ethically
sensitive, evidence assumptions unrecorded, looks publishable. With no triggers
it returns a single "no issues detected" success flag.

### AI assist (optional, gateway-only)

`aiDiagnosis()` is OPTIONAL. It runs only when
`AhgAiServices\Services\LlmService` is installed (`aiAvailable()`), and routes
exclusively through `LlmService::complete()`, which talks to the AHG AI gateway
at `https://ai.theahg.co.za/ai/v1/...`. It NEVER calls a GPU node port directly.
On any error or when AI is unavailable it returns null and the UI shows only the
heuristic flags. AI output is labelled as AI-generated in the view.

## Routes

Registered in `packages/ahg-research/routes/question-builder.php`, loaded from
the service provider with `Route::middleware('web')->group(...)`. All under the
`research.` name prefix and the `auth` middleware, two-segment+ paths so the
`/{slug}` catch-all does not intercept them:

- `GET  /research/question-builder/{projectId}`          -> `research.question.builder`
- `POST /research/question-builder/{projectId}`          -> `research.question.save`
- `GET  /research/question-builder/{projectId}/history`  -> `research.question.history`
- `POST /research/question-builder/{projectId}/diagnose` -> `research.question.diagnose` (AJAX JSON)

## Views

In `packages/ahg-research/resources/views/question-builder/`:

- `builder.blade.php` - the brief form, the live diagnosis panel, and the
  current-version banner. Extends `theme::layouts.2col`, includes the research
  `_sidebar` partial (active `projects`). The diagnosis "Run diagnosis" button
  posts the in-form values to the diagnose endpoint and re-renders flags + the
  optional AI note without leaving the page. Script is pushed to the `js` stack.
- `history.blade.php` - newest-first accordion of every version with the change
  reason and full field values. Empty-state when no version exists.

## Constraints honoured

- No edit to `getSidebarData`; the sidebar partial is reused, not modified.
- No ALTER / INSERT / UPDATE of existing tables; only the two new tables are
  created on boot. The live DB is read-only otherwise.
- No MySQL ENUM; `status` is VARCHAR (dropdown-backed).
- Jurisdiction-neutral; no market-specific fields.
- AGPL / Plain Sailing headers on every new PHP file.
