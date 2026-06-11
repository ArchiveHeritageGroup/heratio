# Research Impact Tracking (heratio#1241, Research OS #19, moonshot 25)

Per-project tracking of the downstream impact of a research project's PUBLISHED outputs: citations, mentions and dataset reuse. After a submission is published with a DOI, Heratio polls PUBLIC bibliographic services directly (never the AI gateway) and groups the resulting signals in a per-project Impact panel. Additive, resilient, network-independent: a failed fetch yields no new signals and the UI never 500s.

## Package and files
Lives in `packages/ahg-research`. All files are NEW and additive.

- `database/install_impact_tracking.sql` - `CREATE TABLE IF NOT EXISTS research_impact_signal`. No ALTER of any existing table.
- `src/Services/ImpactTrackingService.php` - sourcing, polling, signal reads.
- `src/Controllers/ImpactTrackingController.php` - the read/UI surface plus manual refresh.
- `src/Console/Commands/ImpactRefreshCommand.php` - `ahg:research-impact-refresh` (scheduled daily, also runnable by hand).
- `routes/impact-tracking.php` - self-contained route group, names `research.impact.*`, paths `/research/projects/{projectId}/impact/...`.
- `resources/views/research/impact-tracking.blade.php` - the Impact panel (Bootstrap 5 + central theme).
- `docs/help/research-impact-tracking.md` - in-app help article.

The route file and command must be wired into `AhgResearchServiceProvider` at integration: add `ImpactRefreshCommand` to the `commands([...])` list, schedule `ahg:research-impact-refresh` daily, add `'impact-tracking'` to the ROS route-file loop, and add `['research_impact_signal', 'install_impact_tracking.sql', null]` to the boot install map. (This agent does not edit the provider; the maintainer reconciles it.)

## Table: research_impact_signal
- `id` BIGINT UNSIGNED PK
- `project_id` INT - the research project
- `submission_id` BIGINT UNSIGNED NULL - research_submission.id when tied to a specific output
- `doi` VARCHAR(255) NULL - the published output's DOI
- `signal_type` VARCHAR(40) - `citation | mention | dataset_reuse | other` (VARCHAR, never ENUM; canonical list in `ImpactTrackingService::TYPES`)
- `title` VARCHAR(500) NULL - citing/mentioning work title or a count summary
- `detail` TEXT NULL
- `url` VARCHAR(1000) NULL - canonical URL; drives idempotency
- `source` VARCHAR(60) NULL - `openalex | openalex-summary | crossref-event | manual`
- `detected_at` DATETIME NULL, `created_at` TIMESTAMP NULL

## Where the DOIs come from (READ-ONLY)
`ImpactTrackingService::publishedOutputs($projectId)` reads `research_submission` (Publication Studio #1232) READ-ONLY: rows where `status IN (published, accepted)` AND `doi` is non-empty. Every column touched is `Schema::hasColumn`-guarded, so schema drift degrades to an empty list rather than an error. No writes to `research_submission` ever happen.

## Polling - public APIs, DIRECT (not the gateway)
- **OpenAlex** (`https://api.openalex.org`): `cited_by_count` summary per DOI (refreshed in place, one summary row per DOI) plus the cited-by works list (`filter=cites:<id>`), mapped to `citation` signals.
- **Crossref Event Data** (`https://api.eventdata.crossref.org/v1/events?obj-id=<doi>`): blogs, news, Wikipedia, social and dataset links, mapped to `mention` / `dataset_reuse` / `other`.

These are public bibliographic services, not AI services, so they are called directly per the standing gateway rule. Each call: 8s timeout, descriptive User-Agent (polite pool), its own try/catch, `[]` on any failure.

## Idempotency
`raiseSignal()` inserts only when no equivalent signal exists (same project + type + url, or same project + type + title when no url). The per-DOI OpenAlex citation summary is upserted in place (`source = openalex-summary`) so the count refreshes without spawning duplicate rows. A signal is never stored twice.

## Console command + scheduling
`ahg:research-impact-refresh` options: `--project=`, `--limit=`, `--json`. It self-gates on the tables being present, resolves the projects that have a published+DOI submission (or a single `--project`), and scans each inside its own try/catch so one failure never aborts the sweep. Scheduled daily (suggested `02:25`, `withoutOverlapping()->onOneServer()`), mirroring the Living Field Alerts command. Project owners/editors can also trigger a refresh from the panel.

## Empty-states / resilience
- No published output with a DOI: "No published outputs yet" with a link to Publication Studio.
- Published outputs but no signals: "No impact signals yet" with an optional "Check now".
- Every service query is `Schema::hasTable`-guarded and try/catch-wrapped. A network or API failure produces no new signals and never a 500.

## Routes (catch-all-safe)
`/research/projects/{projectId}/impact/` (GET, `research.impact.index`) and `/research/projects/{projectId}/impact/refresh` (POST|PATCH, `research.impact.refresh`). Three-plus segments under `/research/...`, so the `/{slug}` catch-all in `ahg-information-object-manage` never intercepts them. `auth` middleware; access mirrors the rest of the portal (owner + collaborators view; owner, editor collaborators, admins refresh).
