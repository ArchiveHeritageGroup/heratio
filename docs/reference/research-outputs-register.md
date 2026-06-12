# Research Outputs register (CRIS/RIM) - technical reference

Summary: a Research Operating System slice in `packages/ahg-research` that records the scholarly outputs of a research project (journal articles, datasets, software, presentations, theses, reports, chapters) with a resolvable identifier and an optional link to the project's Data Management Plan. Shipped in Heratio v1.142.48. Epic #1222.

## Storage

Table `research_output` (additive, auto-installed at boot via the `AhgResearchServiceProvider` `$installs` array, `Schema::hasTable`-guarded in one try/catch):

- `project_id`, `output_type`, `title`, `authors`, `venue`
- `identifier_type`, `identifier`, `identifier_url`
- `output_date`, `status`, `notes`
- `dmp_id` (nullable, FK-by-convention to `research_dmp.id`; validated to point at a DMP on the same project before write)
- `owner_id`, `created_by`, timestamps

All enumerated columns are `VARCHAR`, never MySQL `ENUM`. No `ALTER` of existing tables.

## Dropdown taxonomies

Seeded via `seed_research_outputs_dropdowns.sql` (`INSERT IGNORE`) into `ahg_dropdown`:

- `research_output_type`: journal_article, dataset, software, presentation, thesis, report, chapter, other
- `research_output_identifier_type`: doi, handle, isbn, url, other
- `research_output_status`: planned, in_progress, published

Views read these from the Dropdown Manager - no hardcoded `<option>` lists.

## Identifier resolver

`ResearchOutputService` builds a resolvable URL from the identifier (a link, not an API call):

- doi -> `https://doi.org/{doi}` (strips any `doi:` / `https://doi.org/` prefix)
- handle -> `https://hdl.handle.net/{handle}`
- isbn -> catalogue search
- url -> scheme-ensured as given
- an explicit `identifier_url` overrides all of the above; `other` with a non-URL value resolves to null

## Routes

Under the research portal: `/research/projects/{projectId}/outputs[...]` (list/create/edit/show/delete) plus a multi-segment `.../outputs/export.json` machine export. All `web`+`auth`, project+researcher scoped. The `research` prefix is in the slug catch-all exclusion list and the export is multi-segment, so the single-segment `/{slug}` archival-record catch-all never intercepts them.

## JSON export shape

`{ project:{id,title}, generated_at, count, outputs:[{id, type, type_label, title, authors, venue, identifier_type, identifier, identifier_label, url, date, status, status_label, dmp_id, notes}] }`

## Wiring

`ResearchOutputService` singleton + the `$installs` entry + a `research-outputs` route-file entry are chained into the already-discovered `AhgResearchServiceProvider` (not a new provider, which `installed.json` would not load). `CommandCentreService::tools()` adds a `research.outputs.index` tool, `Route::has`-gated with a live `research_output` count, so it appears on the researcher journey and drops cleanly if unregistered.
