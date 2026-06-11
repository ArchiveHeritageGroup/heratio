# Review Studio (Research OS Stage 14, heratio#1230)

Per-project Review Studio in `packages/ahg-research`. Two halves: supervisor/co-author comment threads anchored to claims (works without AI), and an adversarial reviewer-twin simulation that calls the AHG AI gateway (always labelled, degrades gracefully). Part of Research OS epic #1222. Additive only - no ALTER of existing tables, no edit to `getSidebarData`.

## Tables (new; `CREATE TABLE IF NOT EXISTS`)

`database/install_review_studio.sql`:

- **`research_review_comment`** - `id`, `project_id`, `assertion_id` (nullable; anchors to a claim row in `research_assertion`, NULL = project-level), `thread_id` (nullable self-ref to the root comment; NULL = root), `author_id` (users.id), `body` TEXT, `resolved` TINYINT(1), `created_at`, `updated_at`.
- **`research_review_run`** - `id`, `project_id`, `persona` VARCHAR(60), `model` VARCHAR(120) nullable (gateway model that answered, if known), `summary` TEXT, `findings` JSON (grouped buckets), `created_by` (users.id), `created_at`.

Auto-installed in `AhgResearchServiceProvider::boot()` via a `Schema::hasTable` guard + `DB::unprepared`, wrapped in one outer try/catch (per `reference_ci_schema_hastable`).

## Claim-anchored comments model

A claim is a row in `research_assertion` (the Claim Ledger / assertions surface). A comment carries `assertion_id` to anchor it to a specific claim, or NULL for a project-level comment. Replies set `thread_id` to the root comment id; a reply inherits the root's claim anchor and always attaches to the root (replies on replies are normalised up to the root). Resolving a thread cascades the `resolved` flag to its replies. The index view renders anchored comments with an "anchored-to-claim" chip linking back to that claim's filtered view; project-level comments get a "Project-level" chip. All claim reads are `Schema::hasTable`-guarded and project-scoped; anchor and thread ids are validated to belong to the project before any insert.

## Reviewer twin -> gateway

`ReviewStudioService::runReviewerTwin($project, $persona, $userId)`:

1. Assembles read-only context: `research_project.description` (the brief) + the project's claims from `research_assertion` (text + status), capped.
2. Builds a persona-driven adversarial system prompt (methodologist / theory_purist / statistician / reviewer_2) that requests strict JSON with the eight grouped keys.
3. Calls the gateway abstraction:

   ```php
   app(\AhgAiServices\Services\LlmService::class)
       ->complete($prompt, ['max_tokens' => 1100, 'temperature' => 0.4]);
   ```

   This is the same `LlmService::complete()` path the Research Copilot uses. It routes through the AHG gateway (`ai.theahg.co.za`) via the cloud-mode override / provider config. **No direct node port (11434/5004/5006/8011) is ever used.**
4. Parses the reply (strict JSON first, then a tolerant heading/bullet fallback so a non-JSON reply still yields a summary) into the grouped buckets and persists a `research_review_run` row. `model` is best-effort via `LlmService::getDefaultConfig()`.

### Mandatory AI label

Every AI output is labelled with `ReviewStudioService::AI_LABEL`:

> AI reviewer - via the AHG gateway, not a human reviewer

The label is shown on the reviewer panel, on each run, and on the run-detail page.

### Graceful degradation

If `complete()` throws or returns empty, `runReviewerTwin()` returns `['ok' => false, 'message' => ...]` **without writing a run**. The controller flashes the message as an `ai_warning`. The comment half is untouched and stays fully usable.

## Findings groups

`ReviewStudioService::FINDING_GROUPS` (JSON keys, render order): `major_concerns`, `minor_concerns`, `likely_objections`, `required_revisions`, `rejection_risks`, `strongest_contribution`, `weakest_section`, `missing_literature`.

## Routes (self-contained)

`routes/review-studio.php` declares its own `Route::prefix('research')->name('research.')->middleware(['web','auth'])->group(...)`, nested under `prefix('projects/{projectId}/review')->name('review.')`. Names: `research.review.index`, `research.review.comments.store|resolve|destroy`, `research.review.run`, `research.review.runs.show|destroy`. All paths are three-segment+ (`/research/projects/{id}/review/...`) so the locked `/{slug}` catch-all never intercepts them.

## Views

`resources/views/research/review-studio/{index,run}.blade.php`. Bootstrap 5 + central theme (`theme::layouts.2col`, `research::research._sidebar`). Empty-states on every list (no comments, no runs, no findings). Persona picker, run button, resolve toggle, reply collapse, anchored-to-claim chips. Never 500: every service read is guarded and returns `[]`/`null` on failure.

## Files

- `database/install_review_studio.sql`
- `src/Services/ReviewStudioService.php`
- `src/Controllers/ReviewStudioController.php`
- `routes/review-studio.php`
- `resources/views/research/review-studio/index.blade.php`
- `resources/views/research/review-studio/run.blade.php`
- small additive `AhgResearchServiceProvider` edit (install + route load)
- `docs/help/research-review-studio.md`, `docs/reference/research-review-studio.md`
