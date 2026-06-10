# Research Copilot answers on the workspace page (heratio#1198)

Saved Research Copilot answers are now surfaced directly on the research workspace
show page, not only on the standalone Copilot page.

## What it does

When a researcher views a team workspace (`/research/workspaces/{id}`), any cited
Copilot answers that were saved into that workspace appear in a "Saved Copilot
answers" card near the top of the page. Each entry shows:

- the original research question
- the grounded, cited answer text
- the cited catalogue sources (each linked to its archival-record page when a slug
  was stored)
- the date the answer was saved

## How it is wired

- Table: `research_copilot_answer` (id, workspace_id, researcher_id, question,
  answer, sources_json, created_at).
- `AhgResearch\Services\ResearchCopilotService::listAnswers(int $workspaceId)`
  returns the rows newest-first, with `sources_json` decoded onto a `->sources`
  array of `{id, title, slug}`.
- `ResearchController::viewWorkspace()` instantiates the service and passes
  `copilotAnswers` to the view. The call is wrapped in a try/catch so a missing
  table degrades gracefully (empty list, no error).
- View: `resources/views/research/view-workspace.blade.php` renders the
  "Saved Copilot answers" card only when `copilotAnswers` is non-empty. Source
  links use `url('/' . $slug)` to reach the archival-record show page; sources
  without a slug render as plain text.

## Permissions

No new permission surface was added. The workspace page already gates access
(owner or accepted member, or a public workspace viewed by anyone) before the
answers are loaded, so a viewer who can see the workspace can see its saved
answers. The standalone Copilot endpoints continue to enforce
`CollaborationService::canAccess()` for save (editor) and list (any access).

## Files

- `packages/ahg-research/src/Controllers/ResearchController.php` - `viewWorkspace()`
  passes `copilotAnswers`.
- `packages/ahg-research/resources/views/research/view-workspace.blade.php` -
  "Saved Copilot answers" card.

## Test URL

`/research/workspaces/{id}` - e.g. `/research/workspaces/1` for a workspace that
has at least one saved Copilot answer.
