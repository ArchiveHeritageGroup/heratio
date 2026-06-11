# Research Time Machine (Research OS moonshot 19, heratio#1240)

The Time Machine is a per-project, READ-ONLY reconstruction of how a research project developed over time. It is "the honesty engine": it captures no new history of its own and writes nothing. It rebuilds the project's history purely from timestamped and versioned data that other Research OS slices already record. No new table, no ALTER, no writes to existing data.

## Package and files
Package: `packages/ahg-research`.

- Service: `src/Services/TimeMachineService.php`
- Controller: `src/Controllers/TimeMachineController.php`
- Routes: `routes/time-machine.php` (self-contained group; loaded by the service provider's ROS route loop)
- Views: `resources/views/research/timemachine/index.blade.php` (timeline) and `as-of.blade.php` (date scrubber snapshot)

## Routes
Self-contained `Route::prefix('research')->name('research.')->middleware(['web','auth'])` group. Paths are three segments or deeper, so the locked `/{slug}` catch-all never intercepts them.

- `research.timemachine.index`  GET  `/research/projects/{projectId}/timemachine`  - merged timeline, grouped by month, `?order=asc|desc`.
- `research.timemachine.asOf`   GET  `/research/projects/{projectId}/timemachine/as-of`  - "state as of" snapshot, `?date=YYYY-MM-DD`.

## Timeline sources merged
Each event is `[when (Carbon), kind, label, detail, link, icon, badge]`. Sources, all guarded with `Schema::hasTable` + `try/catch` so a missing slice contributes no events:

| Source table | Timestamp used | Event |
|---|---|---|
| `research_question_brief_version` (joined to `research_question_brief` on `brief_id` for `project_id`) | `created_at` | "Question brief vN saved" (+ change_reason / primary_question) |
| `research_decision_log` | `decided_at` (falls back to `created_at`) | the decision summary (+ reason) |
| `research_assertion` | `created_at` | "Claim recorded (status)" (+ subject/predicate/object label) |
| `research_argument` + `research_argument_step` | `created_at` | argument started + each step added (steps reach the project via their parent argument) |
| `research_inbox_item` | `captured_at` (falls back to `created_at`) | "Captured {kind}: {title}" |
| `research_method_protocol` | `created_at`, plus `updated_at` as a distinct "revised" event when meaningfully later | "Method protocol created/revised" |

## "State as of date" reconstruction
Given a cutoff date, `stateAsOf()` reconstructs what existed by then:

- **Question brief**: the brief version with the latest `created_at <= cutoff` (ordered by `created_at` then `version_no` descending). This is the version that was current on that date.
- **Claims / arguments / method protocols**: rows with `created_at <= cutoff`.
- **Decisions**: `decided_at <= cutoff`, or `created_at <= cutoff` where `decided_at` is null.
- **Inbox**: `captured_at <= cutoff`, or `created_at <= cutoff` where `captured_at` is null.
- **Argument step counts** are recomputed as of the cutoff per argument.

A bare `YYYY-MM-DD` is treated as the whole of that day (end of day) so same-day records are included.

## Date handling
Defensive: an invalid or empty date defaults to `now()`. Unparseable row timestamps and `0000-00-00 00:00:00` are dropped from the timeline rather than causing errors.

## Read-only / no schema change
- No new table was added (preferred - this is read-only reconstruction).
- No `ALTER`, no INSERT/UPDATE/DELETE against any existing table.
- `getSidebarData` was not edited. The provider gained only a small additive ROS route-load loop.

## Empty states
- Timeline with no dated activity renders a friendly empty card, never a 500.
- "State as of" with nothing recorded by the date shows a prompt to pick a later date.
- Cross-link routes (e.g. `research.questionbuilder.index`) are resolved via a safe-route helper that returns null if the route is not registered, so links degrade gracefully when a slice is absent.
