# ahg-rdm Feature 1 — Data Management Plan linkage (as-built)

Built 2026-06-26 on heratio-dev. Epic #1337 Feature 1. Spec context:
`ahg-rdm-feature2-spec.md` (step 7: "POPIA-flagged - restricted - DMP-linked").
This is the as-built record for the DMP slice of the RDM module.

## The key decision: orchestrate, do not rebuild
A full machine-actionable DMP (maDMP) **builder already exists** in `ahg-research`
(`DmpService` + `DmpController` + `routes/dmp-builder.php` + `dmp/*` views + tables
`research_dmp` / `research_dmp_section` + dropdown `dmp_status`), shipped as the
Research OS slice (#1222), RDA / Science Europe aligned. Re-implementing a DMP tool
inside ahg-rdm would violate the package's thin-orchestration principle. So
Feature 1 = **wire the existing builder into the RDM pipeline**, not a new tool.

## What was added (all in the net-new, unlocked `packages/ahg-rdm`)
- **Schema:** `rdm_dataset.dmp_id INT NULL` (FK-by-convention to `research_dmp.id`),
  added via the same guarded idempotent `ALTER` idiom as the verdict/disposition
  columns in `database/install.sql`; the provider boot-install guard now also
  fires when `dmp_id` is missing.
- **`DmpLinkService`** (the only new logic, pure orchestration over `DmpService`):
  - `context(dataset)` - read model for the views: is the DMP slice available
    (`class_exists` + `hasTable('research_dmp')` + `Route::has('research.dmp.index')`),
    the project's plans, the linked plan + `completeness()` %, and deep-link URLs
    into the research portal. Self-heals a dangling link if the plan was deleted.
  - `link(datasetId, dmpId)` - validates the plan belongs to the dataset's project
    (a DMP is project-scoped) before writing `dmp_id`.
  - `createAndLink(datasetId, meta)` - calls `DmpService::createPlan()` (which seeds
    the full maDMP section set) for the dataset's project, owner resolved from
    `research_project.owner_id`, then links it.
  - `unlink(datasetId)` - clears `dmp_id`; the plan itself is left intact.
- **Controller / routes:** `DatasetController::linkDmp` (create-and-link when a
  `new_title` is posted, else link an existing `dmp_id`) + `unlinkDmp`, on
  `POST`/`DELETE /research/datasets/{id}/dmp` (`rdm.datasets.dmp.link|unlink`).
- **Dataset show:** a "Data Management Plan" card - linked plan (title, status,
  funder, completeness bar, "Open DMP" deep-link, Unlink) OR a picker for the
  project's existing plans + an inline "create a new DMP" form. Degrades to a muted
  hint when the dataset has no project, and is hidden entirely when the DMP slice
  isn't installed (`$dmp['available']`).
- **Public landing:** a "Data management: Governed by a Data Management Plan
  [status]" line - the fact + plan status only, no link into the private builder.
- **Compliance scoreboard:** a "DMP-linked" summary stat + the DMP/Project column
  now shows a `DMP: <status>` badge (or "no DMP" when a project is linked but no
  plan). The join is guarded by `hasColumn('rdm_dataset','dmp_id') &&
  hasTable('research_dmp')` so the scoreboard still renders without the DMP slice.
- **Demo:** `ahg:rdm-demo` now `createAndLink`s a maDMP ("DMP — POPIA RDM Demo
  Study", funder NRF) for the demo project and reports `DMP linked: #N`. `--fresh`
  also deletes the demo's own plan + sections so re-runs don't accumulate plans.

## Verified on dev (2026-06-26)
`ahg:rdm-demo --fresh`: dataset #6, **DMP #1 created + linked** (maDMP, draft),
17 POPIA findings, open release blocked -> restrict -> DOI. ComplianceReportService
summary `dmp_linked:1`; row carries `dmp_id/dmp_title/dmp_status`; `context()`
returns the linked plan + a `research.dmp.show` deep-link; public landing renders
the governance line (HTTP 200). Completeness shows 0% (a fresh draft) with a CTA to
finish the sections in the research portal.

## Design notes / boundaries
- A DMP is **project-scoped**, so linkage requires the dataset to have a project;
  standalone datasets show a "link a project first" hint.
- ahg-rdm **never writes** `research_dmp*` except through `DmpService` (create) - it
  owns only the single `dmp_id` reference column. No ALTER of any ahg-research table.
- DMP linkage is **advisory** (surfaced everywhere, not a hard release gate) -
  funder mandates vary; a future option could make it a release precondition.

## Follow-ups (not in Feature 1)
- Optional: make "DMP linked" a configurable release precondition for funders that
  mandate it.
- Surface the maDMP JSON export (`DmpService::buildMadmp`) link on the dataset.
- Feature 3 (full RDM dashboard) remains the last epic child.
