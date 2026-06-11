# Method Design Studio (Research OS #9 / ROS Stage 10)

Per-project methodology design in the `ahg-research` package, delivered as
DISCIPLINE TEMPLATES rather than a native encoding of every methodology. A
researcher picks the template closest to their tradition and turns it into a
per-project Method Protocol whose answers are written once and reused by
downstream features (thesis methodology chapter, grant, ethics application).

Tracked as heratio#1231 under the Research OS epic (#1222).

## Data model (two new tables, additive, no ALTER)

`research_method_template`
- `code` VARCHAR unique - stable template key (e.g. `case-study`).
- `name`, `discipline`, `description`.
- `guidance` JSON - ordered map `area-key => {label, prompt, placeholder}`.
- `is_active`, `sort_order`.

`research_method_protocol`
- `project_id` - the owning `research_project`.
- `template_code` - references `research_method_template.code`.
- `title`.
- `fields` JSON - the researcher's answers keyed by guidance area.
- `status` VARCHAR - from the `method_protocol_status` Dropdown Manager taxonomy
  (draft, in_review, final). Never an ENUM.
- `created_by` - `research_researcher.id`.

The 13 canonical guidance areas are: design, sampling, data_sources,
instruments, coding_framework, variables, validity, reliability, ethics,
consent, bias_control, reproducibility, data_management. `MethodStudioService::AREAS`
is the source of truth; any area a template omits is back-filled from it.

## Templates seeded

design-science, archival-method, ethnography, case-study, qualitative,
quantitative, mixed-methods, discourse-analysis, historical-method, legal-policy,
computational-dh. Guidance is jurisdiction-neutral - ethics/consent/data-management
prompts speak to general principles (informed consent, lawful basis, retention,
anonymisation), never a country-specific regime.

## Install / seed wiring

`AhgResearchServiceProvider::boot()` has an idempotent `app->booted` block that
creates the two tables from `database/install_method_studio.sql` when missing,
then seeds templates from `database/seed_method_templates.sql` when the template
table is empty. One outer try wraps `Schema::hasTable` + `DB::unprepared`
(per the CI schema/hasTable rule). CREATE TABLE IF NOT EXISTS + INSERT IGNORE
make both files safe to re-run.

## Routes (self-contained file `routes/method-studio.php`)

Group: `Route::prefix('research')->name('research.')->middleware(['web','auth'])`.
Loaded directly via `loadRoutesFrom` (declares its own middleware).

- `GET  /research/method/templates` -> `research.method.templates` (gallery; `?project={id}` threads a project)
- `GET  /research/projects/{projectId}/method` -> `research.method.index`
- `POST /research/projects/{projectId}/method` -> `research.method.store` (create from template)
- `GET  /research/projects/{projectId}/method/{protocolId}/edit` -> `research.method.edit`
- `PUT|PATCH|POST /research/projects/{projectId}/method/{protocolId}` -> `research.method.update`
- `GET  /research/projects/{projectId}/method/{protocolId}` -> `research.method.show`
- `GET  /research/projects/{projectId}/method/{protocolId}/reuse` -> `research.method.reuse` (structured JSON)

All project/protocol params are `[0-9]+` constrained. Because every path sits
under the `research` prefix it is matched ahead of the IO slug catch-all
(which excludes `research`).

## Reuse read model

`MethodStudioService::getProtocolForReuse($id, $projectId)` returns
`{ protocol, template, areas: [{key, label, prompt, answer}, ...] }`. The
`reuse` route exposes this as `{ok:true, ...}` JSON so a thesis methodology
chapter, grant application, or ethics application can pull the methodology once,
already paired with its guidance prompts, instead of re-deriving it.

## Safety properties

- Every DB read is `Schema::hasTable`-guarded and try/catch wrapped; empty
  states render instead of 500s.
- Only the two new tables are written. No ALTER and no writes to existing tables
  beyond the boot auto-create + template seed.
- `getSidebarData` on `ResearchController` is NOT edited; the controller builds
  its own sidebar payload with `sidebarActive => 'projects'`.
- If any AI is added later it must route through the AHG gateway abstraction,
  never a direct node port (the computational-dh template's bias-control prompt
  states this for the researcher too).

## Files

- `database/install_method_studio.sql`
- `database/seed_method_templates.sql`
- `src/Services/MethodStudioService.php`
- `src/Controllers/MethodStudioController.php`
- `routes/method-studio.php`
- `resources/views/method/{templates,index,edit,show}.blade.php`
- Provider edit: singleton + boot install/seed + `loadRoutesFrom`.
