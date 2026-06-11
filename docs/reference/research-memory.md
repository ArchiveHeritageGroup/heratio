# Research Memory (ahg-research, heratio#1233)

Research OS Stage 16 (epic #1222). Retains the researcher's intellectual memory
after a project so the next one starts smarter. Per-project curation plus a
cross-project carry-forward pool. Additive slice: one new table, new
service/controller/views/routes, and a small additive provider edit. No ALTER of
any existing table; no edit to `ResearchController::getSidebarData`.

## What it does

- **Per project** (`/research/projects/{id}/memory`): the researcher curates
  memory items grouped by kind (unresolved question, future article, unused
  source, abandoned hypothesis, reusable dataset, collaboration, conference,
  grant, other). Add / edit / delete, plus quick status toggles (carry forward,
  mark done).
- **Suggestions** are read read-only from existing artefacts and offered for
  acceptance. Today the single source is `research_decision_log`: entries of
  type `question_reformulation`, `hypothesis_revision` and `exclusion` are
  mapped to memory kinds (`unresolved_question`, `abandoned_hypothesis`,
  `unused_source`). **Accepting a suggestion is the only write a suggestion
  produces** - it materialises the suggestion as a curated `research_memory_item`
  row; the source Decision Log entry is never touched.
- **Cross-project carry forward** (`/research/memory/carry-forward`): aggregates
  every `open` / `carried_forward` item across all of the researcher's projects
  (left-joined to `research_project` for the project title, read-only), grouped
  by kind, so a new project can start from them.

## Table: research_memory_item

`database/install_research_memory.sql` (CREATE TABLE IF NOT EXISTS, idempotent):

| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| researcher_id | INT | owner of the item |
| project_id | INT NULL | nullable so an item can be detached / carried forward |
| kind | VARCHAR(64) | code; never a MySQL ENUM |
| title | VARCHAR(500) | one-line statement |
| body | TEXT NULL | notes |
| source_ref | VARCHAR(500) NULL | free-form; for accepted suggestions it leads with the suggestion signature (e.g. `decision-log#42`) |
| status | VARCHAR(32) | open / carried_forward / done / dropped |
| created_by | VARCHAR(255) NULL | display name |
| created_at / updated_at | TIMESTAMP NULL | |

`kind` and `status` are VARCHAR per project rules; the service holds the
canonical option lists, with an optional `ahg_dropdown` taxonomy
`research_memory_kind` override (no schema change needed). Every query is
`Schema::hasTable`-guarded and try/caught; the feature degrades to an empty state
rather than throwing a 500.

## Files

- `database/install_research_memory.sql`
- `src/Services/ResearchMemoryService.php` - option lists, per-project CRUD,
  read-only `suggestionsForProject()` / `findSuggestion()` over the Decision Log
  (with `acceptedSignatures()` de-dupe), and the cross-project
  `carryForwardForResearcher()` aggregate.
- `src/Controllers/ResearchMemoryController.php` - per-project index/create/
  store/edit/update/status/destroy, `accept` (the only suggestion write), and
  `carryForward`. Owner/collaborator/admin access gate; sidebar data built
  locally (no `getSidebarData` edit).
- `resources/views/research/research-memory.blade.php` - per-project view
  (suggestions card + items grouped by kind), Bootstrap 5 + central theme,
  empty-states.
- `resources/views/research/research-memory-form.blade.php` - add/edit form.
- `resources/views/research/research-memory-carry-forward.blade.php` -
  cross-project pool with "start new project from this" links.
- `routes/research-memory.php` - self-contained
  `Route::prefix('research')->name('research.')->middleware(['web','auth'])`
  group; per-project paths under `/research/projects/{projectId}/memory/...`
  (names `research.memory.*`) and the cross-project
  `/research/memory/carry-forward` (`research.memory.carryForward`). All paths
  are two-segments-deep so the `/{slug}` catch-all never intercepts them.
- Provider: additive boot block to install the table + a plain
  `Route::group([], '.../routes/research-memory.php')` load. No other edits.

## Constraints honoured

- AHG / Plain Sailing / AGPL headers; `@copyright "Plain Sailing Information
  Systems"`; no em-dashes; international framing.
- VARCHAR + service/dropdown option lists, never ENUM.
- Read-only over the live DB except the boot auto-create and the researcher's own
  writes to `research_memory_item`. No ALTER, no writes to existing tables.
- No AI is used in this slice (suggestions are deterministic reads of the
  Decision Log). If added later it must route through the AHG gateway abstraction.
