# Research OS Stage 5 - Source Triage + honest read-status

Per-project Source Triage board in `packages/ahg-research`. Over a project's sources (bibliography
entries + collection items) it adds triage categories and an HONEST read-status that the system
never fakes. Part of the Research OS epic (heratio#1227, ROS Stage 5, epic #1222).

## What it does

- Lists every source attached to a research project, joined from two existing tables:
  - `research_bibliography_entry` (via `research_bibliography.project_id`)
  - `research_collection_item` (via `research_collection.project_id`; title resolved from
    `information_object_i18n` where present, else the item's reference code)
- Lets the researcher set a **triage category** and a **read-status** per source, and add notes.
- Offers an OPTIONAL AI structured preview per source, always labelled "AI preview - not human
  verified".
- The system NEVER auto-marks a source `read`. Only an explicit researcher action on the
  read-status control moves it. Generating an AI preview does not change read-status.

## Sidecar table (no ALTER of existing tables)

`research_source_triage` is a new SIDECAR table created by
`database/install_source_triage.sql`. It keys back to a source by `(source_type, source_id)` and
is NEVER joined by foreign-key ALTER onto the bibliography/collection tables - those are left
untouched.

Columns: `id, project_id, source_type, source_id, triage_category, read_status, ai_preview,
ai_preview_at, notes, updated_by, updated_at`, with `UNIQUE(project_id, source_type, source_id)`.

`triage_category` and `read_status` are plain `VARCHAR` (no MySQL ENUM). The accepted values are
a dropdown-style allow-list in `SourceTriageService`:

- `triage_category`: essential, useful, background, contested, weak, duplicate, excluded,
  read-later, method-source, theory-source, evidence-source
- `read_status`: unread, previewed, skimmed, read, deeply-read

### Auto-install

`AhgResearchServiceProvider::boot()` creates the table on first boot using the
`Schema::hasTable` + single-outer-try pattern (per the CI schema gotcha), running
`install_source_triage.sql` via `DB::unprepared` only when the table is missing.

## Files

- `database/install_source_triage.sql` - CREATE TABLE IF NOT EXISTS for the sidecar.
- `src/Services/SourceTriageService.php` - board assembly, allow-lists, upsert, AI preview.
- `src/Controllers/SourceTriageController.php` - board + category/read-status/notes/ai-preview
  endpoints, validation, ownership guards.
- `routes/source-triage.php` - routes under `research.triage.*`, loaded from the provider.
- `resources/views/research/source-triage.blade.php` - the board view (Bootstrap 5, central
  theme, 2col layout, research sidebar).

## Routes (all two-segment+, under /research/projects/{projectId}/triage)

- `GET  research.triage.index`     - the board (filter by `?category=` and `?read=`)
- `POST research.triage.category`  - set triage_category
- `POST research.triage.readStatus`- set read_status (the only path that writes read-status)
- `POST research.triage.notes`     - save notes
- `POST research.triage.aiPreview` - generate/refresh the optional AI preview

All paths sit under `/research/projects/{projectId}/triage/...`, so the `/{slug}` catch-all in
`ahg-information-object-manage` never intercepts them.

## AI routing + label enforcement

AI previews call `app(\AhgAiServices\Services\LlmService::class)->complete(...)`, the same
abstraction the Research Copilot uses. That routes through the AHG AI gateway
(`https://ai.theahg.co.za/ai/v1/...`); no node port is ever wired directly. AI is optional - any
failure is logged and reported, and the board stays fully usable.

The label string lives once in `SourceTriageService::AI_PREVIEW_LABEL` ("AI preview - not human
verified") and the view always renders it above any stored `ai_preview`.

## Safety

- Every read is `Schema::hasTable`-guarded and wrapped in try/catch; a missing table or query
  error degrades to an empty board, never a 500. Empty projects show a "No sources yet"
  empty-state.
- The only writes are to `research_source_triage` (insert/update of triage rows) plus the
  boot-time CREATE of that table. No INSERT/UPDATE/ALTER touches existing data.
- Write endpoints validate `source_type` against the allow-list and confirm the source actually
  belongs to the project before writing.
