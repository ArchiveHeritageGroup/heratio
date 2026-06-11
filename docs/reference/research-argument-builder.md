# Research OS Stage 12: Argument Builder (heratio#1229)

Per-project tool in `packages/ahg-research` that sequences existing CLAIMS into
an ordered nine-step argument and runs a heuristic weak-spot warnings pass. Part
of the Research OS epic (#1222); upstream dependency is the Claim Ledger
(#1223). Claims are NOT rebuilt - they are read live from `research_assertion`
(+ evidence in `research_assertion_evidence`).

## Tables (two NEW; no ALTER of existing tables)

Auto-installed on boot via `CREATE TABLE IF NOT EXISTS`
(`database/install_argument_builder.sql`):

- `research_argument` - one argument header per project.
  `id, project_id, title, central_thesis, created_by, created_at, updated_at`.
- `research_argument_step` - the ordered slots.
  `id, argument_id, slot VARCHAR(40), assertion_id (nullable FK to
  research_assertion), note, sort_order, created_at`.

`slot` is a plain VARCHAR (never a MySQL ENUM). The nine canonical slots are
validated in PHP via `ArgumentBuilderService::SLOTS`:
`problem, gap, frame, method, evidence, analysis, counterargument,
contribution, implication`.

A step's `assertion_id` is nullable so an empty slot can exist; it is validated
to belong to the project before it is stored.

## How claims are reused

`ArgumentBuilderService::availableClaims($projectId)` reads every
`research_assertion` row for the project and decorates it with a one-line label
and evidence aggregates (count + distinct source count) computed with a single
batched `GROUP BY` over `research_assertion_evidence`. `getSteps()` batch-loads
the attached claims and the same aggregates. Nothing about a claim or its
evidence is duplicated into the argument tables.

## Warnings logic (heuristic PHP, no AI)

`ArgumentBuilderService::computeWarnings(array $steps)` returns
`[severity, slot|null, message]` rows:

1. **No evidence** (danger) - step claim has `evidence_count === 0`
   (the LEFT-JOIN-against-`research_assertion_evidence` signal).
2. **Single-source over-reliance** (warning) - `evidence_count >= 2` but
   `distinct_sources === 1`.
3. **Missing slot** (info) - any of the nine slots not present among the steps.
4. **Contested / contradiction** (danger inline + info at argument level) -
   claim `status` in `rejected, contested, disputed, weak`.
5. **Conclusion stronger than evidence** (warning) - a `contribution` or
   `implication` slot whose claim is low-confidence
   (`confidence <= 0.40`), weak-status, or uncited.

A populated slot with no claim attached is also flagged (warning).

An optional AI critique is NOT shipped; if added it MUST route through the AHG
gateway via `LlmService` (never a node port) and be labelled as AI-generated.

## Routes (self-contained file)

`routes/argument-builder.php` declares its own
`Route::prefix('research')->name('research.')->middleware(['web','auth'])->group(...)`.
All paths are `/research/projects/{projectId}/argument/...` (two-plus segments,
so the locked `/{slug}` catch-all never intercepts them). Names:

- `research.argument.show` (GET canvas + warnings)
- `research.argument.update` (POST thesis/title)
- `research.argument.steps.add` / `.reorder` / `.claim` / `.note` / `.delete`

## Provider wiring

The worktree provider gained a small additive block: a `booted()` install guard
for the two tables and a `Route::group([], '.../argument-builder.php')` load.
The integrator may fold both into the consolidated ROS install map / route
foreach.

## Empty states / resilience

Every query is `Schema::hasTable`-guarded and wrapped in try/catch
(reference_ci_schema_hastable). The canvas degrades to an empty-state card when
there are no steps; the picker shows a "add claims in the Claim Ledger first"
hint when the project has no claims; missing tables yield empty arrays rather
than a 500. The argument header is auto-created on first visit.

## Files

- `packages/ahg-research/database/install_argument_builder.sql`
- `packages/ahg-research/src/Services/ArgumentBuilderService.php`
- `packages/ahg-research/src/Controllers/ArgumentBuilderController.php`
- `packages/ahg-research/routes/argument-builder.php`
- `packages/ahg-research/resources/views/research/argument-builder/show.blade.php`
- `docs/help/research-argument-builder.md`
- Additive edit: `packages/ahg-research/src/Providers/AhgResearchServiceProvider.php`
