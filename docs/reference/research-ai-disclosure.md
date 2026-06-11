# Research OS Part IV - AI Disclosure Statement + interaction log (heratio#1242)

Per-project, one-click AI-use disclosure for any research output. It aggregates the project's AI usage from already-landed slices read-only, adds a manual interaction log, and assembles a journal-ready AI Disclosure Statement. Part of Research OS "AI Containment" (Part IV). Lives in `packages/ahg-research`. No existing table is altered; the only write is a manual log entry to the slice's own table.

## Files
- `database/install_ai_disclosure.sql` - `CREATE TABLE IF NOT EXISTS research_ai_disclosure_log`. The only table the slice owns.
- `src/Services/AiDisclosureService.php` - aggregation (read-only) + statement assembly + manual log writes + optional gateway summary.
- `src/Controllers/AiDisclosureController.php` - page, manual-log add/delete, statement download.
- `routes/ai-disclosure.php` - self-contained `research.aidisclosure.*` routes.
- `resources/views/research/ai-disclosure/index.blade.php` - disclosure page (statement + copy button + usage table + log form).
- `docs/help/research-ai-disclosure.md` - in-app help article.

Provider integration: a small additive edit to `AhgResearchServiceProvider` registers the install SQL (Schema::hasTable-guarded booted block) and loads the route file. The shared `routes/web.php` and `getSidebarData` are untouched.

## Table: research_ai_disclosure_log
`id`, `project_id`, `tool VARCHAR(160)`, `model VARCHAR(160) NULL`, `purpose TEXT NULL`, `output_ref VARCHAR(500) NULL`, `logged_by INT NULL`, `created_at`. VARCHAR throughout (Dropdown Manager pattern), never ENUM. Keyed on `project_id` (= `research_project.id`).

## Detected (read-only) AI sources
Each is Schema::hasTable-guarded and try/catch wrapped, so a missing table contributes nothing instead of 500ing:

- `research_review_run` (#1230 Review Studio) - persona, model, created_at. One disclosure line per AI peer-review run, model recorded per run.
- `research_source_triage` (#1227 Source Triage) - rows with a non-null `ai_preview`; uses `ai_preview_at`. One line per AI relevance preview.
- `research_contradiction` (#1236 Contradiction Engine) - rows where `source='ai'`; uses `kind`, `created_at`. One line per AI contradiction-detection finding.

Question Builder (#1226) diagnoses and Analysis Bridge (#1234) captions call AI transiently and persist no AI marker column, so they are not auto-detected; researchers record those via the manual log.

## Statement generation
`AiDisclosureService::buildStatement()` assembles the statement from the gathered records with NO AI call. It lists the slices/tools and models used, enumerates up to 25 recorded interactions (date-only), and asserts that all AI routed through the AHG AI gateway, that AI was assistive only, and that the author remains responsible. An empty project yields a clean "no AI assistance recorded" statement.

`summariseViaGateway()` is an optional narrative summary; it routes ONLY through `AhgAiServices\Services\LlmService->complete()` (the gateway abstraction), is clearly labelled, and returns null on failure. No node port is ever contacted directly.

## Routes (self-contained, catch-all-safe)
- `research.aidisclosure.index`     GET  `/research/projects/{projectId}/ai-disclosure`
- `research.aidisclosure.statement`  GET  `/research/projects/{projectId}/ai-disclosure/statement.txt`
- `research.aidisclosure.log.store`  POST `/research/projects/{projectId}/ai-disclosure/log`
- `research.aidisclosure.log.destroy` POST `/research/projects/{projectId}/ai-disclosure/log/{entryId}/delete`

All paths are three segments or deeper, so the locked `/{slug}` catch-all never intercepts them.

## Empty-state and defensiveness
Every detector is guarded; the page renders an empty state ("No AI assistance recorded for this project.") when nothing is found, and never 500s. The manual-log add/delete are scoped to the project id.
