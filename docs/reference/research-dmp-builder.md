# Research OS - Data Management Plan (DMP) Builder

A researcher-facing Data Management Plan builder, scoped to a research project, in
the `ahg-research` package. It is a Research OS (epic #1222) slice that sits
alongside the Grant Engine (#1239) and Writing Studio slices and mirrors their
end-to-end pattern exactly. A DMP is the standard FAIR research artifact funders
require (Horizon Europe, NSF, Wellcome, NRF and others); this slice models the
RDA / Science Europe machine-actionable DMP (maDMP) common standard.

## Slice pattern mirrored

Built on the **Grant Engine** slice (#1239) as the reference sibling:

- Service (`DmpService`) + Controller (`DmpController`) + self-contained
  `routes/dmp-builder.php` + `resources/views/dmp/*` blades.
- Auto-install through the `AhgResearchServiceProvider` `$installs` array
  (`[table, install.sql, seed.sql]`), guarded by `Schema::hasTable` inside one
  outer try/catch per the CI schema rule.
- Routes file is added to the provider's ROS routes `foreach` list.
- The tool is added to `CommandCentreService::tools()`, gated by `Route::has`, so
  it appears on the researcher journey only when its route is registered.
- Project + researcher resolution is local to the controller
  (`projectContext()` -> `getResearcherByUserId`), never touching `getSidebarData`.

## Tables (additive only - no ALTER of any existing table)

- `research_dmp` - one row per plan: `project_id`, `title`, `status` (VARCHAR,
  dropdown-backed), `funder` (free text, recorded as data), `funder_template`
  (optional dropdown code), `language` (BCP-47), `contact_name`, `contact_email`,
  `owner_id`, `created_by`, timestamps.
- `research_dmp_section` - one row per maDMP section per plan: `dmp_id`,
  `section_key`, `label`, `body`, `sort_order`, timestamps.

Install SQL: `database/install_dmp_builder.sql` (CREATE TABLE IF NOT EXISTS).

## Dropdown taxonomies (ahg_dropdown, never ENUM, never hardcoded options)

Seeded via `database/seed_dmp_dropdowns.sql` with `INSERT IGNORE`:

- `dmp_status` - draft, in_review, approved, published, superseded.
- `dmp_funder_template` - generic (default, jurisdiction-neutral), horizon_europe,
  nsf, wellcome, nrf (all labelled "(example)").

Both are surfaced in the Dropdown Manager. Views read them via
`DmpService::statusOptions()` / `funderTemplateOptions()`, each with a safe
fallback map, so a site with no rows still renders.

## maDMP sections covered

The canonical section template lives in `DmpService::MADMP_SECTIONS` (stable keys,
ordered, seeded into `research_dmp_section` on create): data_description,
documentation, findable, accessible, interoperable, reusable, storage_backup,
preservation, sharing_access, ethics_legal, responsibilities, costs.

## maDMP JSON export shape

Endpoint: `GET /research/projects/{projectId}/dmp/{dmpId}/madmp.json`
(`research.dmp.export`), `DmpController::exportJson` -> `DmpService::buildMadmp`.
RDA / Science Europe maDMP common-standard aligned:

```
{ "dmp": {
    "title", "language" (ISO 639-3), "created", "modified",
    "ethical_issues_exist", "dmp_id": {identifier, type},
    "contact": {name, mbox, contact_id},          // when set
    "project": [{ title, description, funding: [{name, funding_status}] }],
    "dataset": [{ title, description, personal_data, sensitive_data, dataset_id }],
    "extension": [{ "heratio": {                    // full fidelity round-trip
        status, funder, funder_template,
        sections: [{ key, label, answer }, ...]
    }}]
}}
```

The standard `project`/`dataset` blocks carry the human-readable plan; the
namespaced `extension.heratio.sections` preserves the complete structured answer
set so nothing is lost.

## Routes (catch-all-safe)

All under `prefix('research')->middleware(['web','auth'])`, names `research.dmp.*`:
index, store, edit, update, show, destroy, and the multi-segment
`.../dmp/{dmpId}/madmp.json` export. The `/{slug}` IO catch-all excludes
`research`, and the export path is multi-segment, so nothing is intercepted.

## Completeness

`DmpService::completeness()` counts how many sections carry a non-empty body and
returns `{filled, total, pct}`. Rendered as a Bootstrap 5 progress bar on the
list, editor and show views.

## Constraints honoured

- AHG / Plain Sailing / AGPL headers; `@copyright` Plain Sailing Information
  Systems; no em-dashes.
- International and funder-neutral; funder is data, never SA-defaulted.
- `ahg_dropdown`-backed VARCHAR columns, never ENUM, no hardcoded `<option>` lists.
- Bootstrap 5 + central theme (`theme::layouts.2col`, `research::research._sidebar`).
- Full form validation; views render real data.
- Chained into the existing `AhgResearchServiceProvider` (`$installs` + routes
  `foreach`) and `CommandCentreService::tools()` - no new provider.
- No writes outside `research_dmp` / `research_dmp_section`; no ALTER anywhere.
