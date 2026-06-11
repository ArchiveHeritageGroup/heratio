# Research Grant Engine (heratio#1239, Research OS #17, moonshot 24)

The Grant Engine is a per-project feature in the `ahg-research` package that
assembles funder-specific grant drafts from material that already exists on a
project. It pre-fills draft sections, read-only, from the project mission, the
Method Protocol, the Question Brief and the project claims, then lets the
researcher edit each section, optionally AI-draft a section through the gateway,
and track matching funder calls. It is additive: new tables, new files, no ALTER
of existing tables.

## Summary

- Pick a **funder template** (a `grant_funder_template` dropdown row whose
  ordered section list lives in the dropdown `metadata` JSON). Seeded examples:
  generic (jurisdiction-neutral default), NRF-, ERC-, NIH-, Wellcome-style.
- Starting a draft writes a `research_grant_draft` row plus one
  `research_grant_section` row per template section, each pre-filled from the
  project's own material.
- The researcher edits section bodies, sets a status, prints, and tracks calls.
- AI drafting is optional, labelled, gateway-only, and never auto-submitted.

## Tables (additive only)

- `research_grant_draft` - id, project_id, funder_template (VARCHAR, dropdown),
  title, status (VARCHAR, dropdown), created_by, timestamps.
- `research_grant_section` - id, draft_id, section_key (VARCHAR), label, body
  (MEDIUMTEXT), sort_order, timestamps.
- `research_grant_call` - id, researcher_id (nullable), project_id (nullable),
  funder, title, url, deadline, status (VARCHAR, dropdown), notes, timestamps.

No `ENUM` columns: every enumerated value (funder_template, draft status, call
status) is VARCHAR backed by `ahg_dropdown`.

## Dropdown taxonomies (Dropdown Manager)

- `grant_funder_template` - funder templates; the ordered section list is stored
  in each row's `metadata` JSON as `{"funder":..,"sections":[{"key","label","hint"}]}`.
- `grant_draft_status` - draft, in_review, ready, submitted.
- `grant_call_status` - watching, preparing, submitted, awarded, declined, closed.

A site can add its own funder templates from the Dropdown Manager (new taxonomy
row + a `metadata.sections` list) with no code change; the engine reads whatever
template rows exist.

## Pre-fill mapping (read-only over existing material)

`GrantEngineService::gatherProjectMaterial()` collects, each `Schema::hasTable`
guarded and try/catch wrapped:

- `research_project` - title, description (mission), institution, funding_source.
- `research_method_protocol` (#1231) - latest protocol's `fields` JSON.
- `research_question_brief` + `research_question_brief_version` (#1226) - latest
  version's topic/problem/gap/questions/hypothesis/scope.
- `research_assertion` (#1223) - the project's recent claims as short label lines.

`prefillSection()` maps these to section keys (summary/background <- mission +
brief; questions <- brief; aims/significance/innovation <- brief; methodology/
approach <- method design/sampling/data_sources/instruments/coding; feasibility
<- method validity/reliability/reproducibility; outputs/impact <- claims + scope;
ethics <- method ethics/consent/data_management/bias_control; team <- institution;
budget <- funding_source). Missing source material yields a thinner section, not
an error. Pre-fill never invents content - it only arranges the researcher's own
words. If a slice (method studio, claim ledger, question builder) is not
installed, the corresponding source is simply skipped.

## AI drafting path (gateway only, labelled, no auto-submit)

`GrantEngineService::draftSection()` builds a prompt from a compact digest of the
project material and the section's current text, then calls
`AhgAiServices\Services\LlmService::complete()` - the gateway abstraction
(`ai.theahg.co.za/ai/v1`), never a direct node port. The prompt instructs the
model to ground strictly in the supplied material and not to invent funding
amounts, dates, named people, institutions, or results. The controller endpoint
`research.grant.ai-draft` returns the suggestion as JSON with a label
("AI-assisted draft (review required before use)"); the editor asks the user to
confirm before replacing the section, and the user saves it themselves. When the
gateway is unavailable the call returns `ok:false` and the UI tells the user to
keep writing by hand - the engine is fully usable without AI.

## Routes (self-contained, catch-all-safe)

New file `routes/grant-engine.php`, its own
`prefix('research')->name('research.')->middleware(['web','auth'])` group. Names
`research.grant.*`; project paths under `/research/projects/{projectId}/grant`
and `/research/projects/{projectId}/grant-calls`, plus `/research/grant/templates`.
All matched before the IO `/{slug}` catch-all (which excludes `research`).

- `grant.templates` GET `/research/grant/templates`
- `grant.index` GET `/research/projects/{id}/grant`
- `grant.store` POST `/research/projects/{id}/grant`
- `grant.edit` GET `/research/projects/{id}/grant/{draftId}/edit`
- `grant.update` PUT|PATCH|POST `/research/projects/{id}/grant/{draftId}`
- `grant.show` GET `/research/projects/{id}/grant/{draftId}`
- `grant.ai-draft` POST `/research/projects/{id}/grant/{draftId}/ai-draft`
- `grant.calls` GET `/research/projects/{id}/grant-calls`
- `grant.calls.store` POST `/research/projects/{id}/grant-calls`
- `grant.calls.update` PUT|PATCH|POST `/research/projects/{id}/grant-calls/{callId}`
- `grant.calls.destroy` DELETE `/research/projects/{id}/grant-calls/{callId}`

## Files

- `database/install_grant_engine.sql` - three CREATE TABLE IF NOT EXISTS.
- `database/seed_grant_templates.sql` - INSERT IGNORE funder templates + status taxonomies.
- `src/Services/GrantEngineService.php` - templates, drafts, sections, pre-fill,
  AI draft, tracked calls, status options.
- `src/Controllers/GrantEngineController.php` - thin controllers, local project
  context (does not touch getSidebarData).
- `routes/grant-engine.php` - self-contained route group.
- `resources/views/grant/{templates,index,edit,show,calls}.blade.php` - BS5 +
  central theme, empty-state safe.

## Provider wiring

The service provider auto-installs the tables (idempotent, `Schema::hasTable`
guard around the `install_grant_engine.sql` + `seed_grant_templates.sql` load,
one outer try) and loads `routes/grant-engine.php` plainly, mirroring the
existing copilot install/route pattern. No existing table is altered;
`getSidebarData` is not edited.

## Safety properties

- Every DB read is `Schema::hasTable` guarded and try/catch wrapped; empty-state
  views never 500.
- Live writes are confined to the three new grant tables and the user's own
  drafts/sections/calls. No ALTER, no writes to existing tables.
- Funders are selectable examples; defaults are jurisdiction-neutral (Heratio is
  an international platform).
