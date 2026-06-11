# Research OS #10: Publication Studio (heratio#1232)

Per-project publication workflow in `ahg-research`, built on the existing
target-journal directory. Part of the Research OS epic (#1222), ROS Stage 15.
Additive slice: new tables, new service, new controller, new views, new
self-contained routes file. No existing table is altered; `getSidebarData` is
not edited.

## What it does

For one research project, the Publication Studio:

1. **Matches venues** from the target-journal directory (`research_target_journal`,
   #1107) by subject-scope overlap, with simple open-access / accreditation-market
   / reference-style filters.
2. **Creates submissions** against a matched directory journal or a free-text
   venue (conference, edited volume, preprint server).
3. **Tracks a compliance checklist** per submission (word count, formatting,
   reference style, data-availability statement, ethics statement, author
   declarations, ORCID/affiliations) - a default list is seeded per submission
   and stays operator-editable.
4. **Records response-to-reviewers** and revision history (reviewer point,
   author response, revision note).
5. **Drives status transitions** (drafting -> submitted -> reviewed -> revised
   -> accepted -> published, with rejected as a terminal branch) and stores the
   DOI and repository deposit URL.

It works with no AI. An optional venue-fit suggestion routes through the AHG
gateway via `AhgAiServices\Services\LlmService::complete()` only (never a GPU
node port) and is always labelled as AI in the UI.

## How matching reads the directory

`PublicationStudioService::matchVenues()` builds a scope string from the
project's `title` + `description` (plus optional extra scope terms) and delegates
scoring to `ResearchTargetJournalService::suggestForScope()` - the directory's
own term-overlap matcher. It then applies post-filters in PHP:

- `open_access` -> only journals with `open_access = 1`;
- `market` -> only journals whose `accreditation_market` equals the chosen code
  (e.g. `ZA`); markets are enumerated from the directory, not hard-coded;
- `reference_style` -> exact match.

If the project has too little text to produce any match, the full directory is
returned with `match_score = 0` so the page is never empty. DHET is one market
among many - nothing assumes a South-African regime.

## Tables (database/install_publication_studio.sql)

All `CREATE TABLE IF NOT EXISTS`; statuses are `VARCHAR` (Dropdown Manager
taxonomy `submission_status`), never ENUM.

- **research_submission** - id, project_id, `venue_ref` (nullable FK-by-value to
  `research_target_journal.id`), venue_name, status, manuscript_title,
  submitted_at, decision_at, doi, repository_url, notes, created_by, timestamps.
- **research_submission_requirement** - id, submission_id, label, met (tinyint),
  note, sort_order, timestamps. The compliance checklist.
- **research_submission_response** - id, submission_id, reviewer_label, point,
  response, revision_note, created_by, created_at. The response/revision thread.

`venue_ref` is a soft reference (no DB-level FK) so a free-text venue stores
NULL and the directory can be rebuilt independently.

## Status model

`PublicationStudioService::TRANSITIONS` is the allowed-forward map; the UI only
offers valid next states. `transition()` validates the move, stamps
`submitted_at` on first `submitted`, and `decision_at` on
accepted/rejected/published. Unknown or illegal transitions are rejected with a
flash message, never a 500.

## Routes (routes/publication-studio.php)

Self-contained:
`Route::prefix('research')->name('research.')->middleware(['web','auth'])->group(...)`.
All names under `research.publication.*`; all paths
`/research/projects/{projectId}/publication/...` (three+ segments, so the
`/{slug}` catch-all in `ahg-information-object-manage` never intercepts them).

- `GET  .../publication` -> `index` (matching + submissions)
- `POST .../publication/ai-fit` -> `aiFit` (AJAX, labelled AI)
- `POST .../publication/submissions` -> `submissions.store`
- `GET  .../publication/submissions/{id}` -> `submission`
- `POST .../publication/submissions/{id}` -> `submission.update` (deposit/meta)
- `POST .../publication/submissions/{id}/transition` -> `submission.transition`
- `POST/DELETE .../requirements[/{reqId}]` -> checklist add/update/delete
- `POST .../responses` -> `response.add`

## Files added

- `database/install_publication_studio.sql`
- `src/Services/PublicationStudioService.php`
- `src/Controllers/PublicationStudioController.php`
- `routes/publication-studio.php`
- `resources/views/publication-studio/index.blade.php`
- `resources/views/publication-studio/submission.blade.php`
- `resources/views/publication-studio/_status_badge.blade.php`
- `docs/help/research-publication-studio.md`
- `docs/reference/research-publication-studio.md`

Plus a small additive edit to `AhgResearchServiceProvider`: an idempotent
`booted()` install of the three tables and a plain route-load of the new file.
At integration these fold into the consolidated ROS install map and route-load
loop on main.

## Safety

Every query is `Schema::hasTable`-guarded and wrapped in try/catch; empty-states
render instead of errors. Writes only ever hit the three new tables. The
directory and `research_project` are read-only here. No ALTER, no ENUM, no
`getSidebarData` change.
