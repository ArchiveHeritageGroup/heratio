# Researcher Copilot (question -> cited synthesis)

heratio#1198 first slice. Admin page **/research/copilot** (Research Copilot in the admin menu
-> Research, and on the research dashboard). Ask a research question; Heratio finds the most
relevant catalogue records and the AI writes a concise answer that **cites them by number**,
grounded only in those records (it says when the sources do not answer - no invention).

## Pipeline
Question -> relevant records (keyword-scored over title + scope, stop-words removed) ->
LlmService writes a cited synthesis ([1],[2]...) from those sources only. Sources are listed
with links to the records. Saving into a research workspace is the next slice.

## Where it lives
- `packages/ahg-research/src/Services/ResearchCopilotService::ask($question)`.
- `ResearchCopilotController` (index, askAjax); routes `research.copilot(.ask)` (auth);
  view `research::copilot`.

## Save to a workspace (shipped)
A reviewed answer (with its citations) can be saved into one of the researcher's workspaces.
- `ResearchCopilotService::saveAnswer($workspaceId, $researcherId, $question, $answer, $sources)`
  stores a row in `research_copilot_answer` (`workspace_id, researcher_id, question, answer,
  sources_json, created_at`). Sources are frozen as `[{id,title,slug}]` so citations survive
  later catalogue changes. `listAnswers($workspaceId)` returns them newest-first with decoded
  sources. Table auto-created by the provider (idempotent `hasTable` guard +
  `database/install_copilot_answer.sql`) and in `install.sql` for fresh installs.
- Controller resolves the researcher via `ResearchService::getResearcherByUserId(Auth::id())`
  and lists/permission-checks workspaces via `CollaborationService` (`getWorkspaces`,
  `canAccess(..., 'editor')` to save). Routes: `research.copilot.save` (POST),
  `research.copilot.answers` (GET) - both under the `research` auth group.
- UI: a workspace picker + "Save answer" in the answer card footer, and a "Saved answers in
  this workspace" list that refreshes on save / workspace change. No workspaces -> a hint to
  join or create one (no save controls). No edits to the locked workspace views were needed.

## Notes
- AI via the gateway (LlmService) - no direct node calls.
- ahg-research is a LOCKED package; these are new files (unlocked for the release).
- Next slices: surface saved answers inside the workspace page itself; ES/vector recall;
  per-source pull-quotes; export to a finding aid / bibliography.
