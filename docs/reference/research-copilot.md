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

## Notes
- AI via the gateway (LlmService) - no direct node calls.
- ahg-research is a LOCKED package; these are new files (unlocked for the release).
- Next slices: save answer + sources to a workspace/notebook; ES/vector recall; per-source
  pull-quotes; export to a finding aid / bibliography.
