# Research OS Stage 3 - Living Field Alerts (heratio#1235)

Per-project alerting on the works a research project cites. Watches each cited
DOI and raises an alert when the work is retracted, updated (correction / erratum
/ new version), or has new related work. Lives in `packages/ahg-research`.

## Summary

- Source of watched works: the project's bibliography DOIs, read READ-ONLY from
  `research_bibliography_entry.doi` joined to `research_bibliography.project_id`.
  Researchers can also add a manual watch.
- Polling: the scheduled command `ahg:research-field-alerts` queries the PUBLIC
  scholarly APIs Crossref (`https://api.crossref.org`) and OpenAlex
  (`https://api.openalex.org`) DIRECTLY over Laravel's `Http` client. These are
  public bibliographic services, NOT AI services, so they do NOT route through
  the AHG AI gateway. Short timeout (8s) and a descriptive `User-Agent`.
- Resilience: every outbound call is wrapped in its own try/catch; a slow or
  failing API yields no new alerts. The build and tests never depend on the
  network. Alerts are idempotent - an equivalent alert (same project + type +
  url, or title when no url) is never inserted twice.
- No existing tables are altered. Only the two new tables are written. Every
  query is `Schema::hasTable`-guarded and try/catch-wrapped so the feature
  degrades to an empty state, never a 500.

## Tables (`database/install_field_alerts.sql`, CREATE TABLE IF NOT EXISTS)

`research_field_watch`
: `id`, `project_id`, `doi` (nullable), `title`, `source_ref` (`bibliography` |
  `manual`), `added_by`, `last_checked_at`, `created_at`. FK to `research_project`.

`research_field_alert`
: `id`, `project_id`, `watch_id` (nullable), `alert_type` VARCHAR
  (`retraction` | `update` | `new_related`), `title`, `detail`, `url`,
  `is_read` tinyint, `detected_at`, `created_at`. FK to `research_project`.

`alert_type` is a VARCHAR, never a MySQL ENUM; the canonical list lives in
`FieldAlertService::TYPES`.

## Files

- `database/install_field_alerts.sql` - the two tables.
- `src/Services/FieldAlertService.php` - cited-DOI sourcing, watch CRUD, alert
  reads/mark-read, the Crossref/OpenAlex polling and signal detection
  (`detectRetraction` / `detectUpdates` / `detectNewRelated`), DOI normalisation.
- `src/Controllers/FieldAlertController.php` - alerts panel, mark-read /
  mark-all-read, watch list, add/remove watch. Access mirrors the research
  portal (owner + collaborators view; owner + editors + admins manage).
- `src/Console/Commands/FieldAlertsCommand.php` - `ahg:research-field-alerts`
  (`--project=`, `--limit=`, `--json`). Scheduled daily at 02:10 from the
  service provider.
- `routes/field-alerts.php` - self-contained `research.alerts.*` routes under
  `/research/projects/{projectId}/alerts/...` (catch-all-safe, two-segment).
- `resources/views/research/field-alerts.blade.php` - alerts panel (retractions
  prominent / red), filter chips, empty-state.
- `resources/views/research/field-watch.blade.php` - watch list + manual-add
  form, empty-state.

## Provider wiring (additive)

`AhgResearchServiceProvider` gains, additively:
1. command registration (`FieldAlertsCommand`),
2. a daily `$schedule->command('ahg:research-field-alerts')->dailyAt('02:10')`,
3. a booted install block that creates the two tables if absent,
4. a `Route::group([], routes/field-alerts.php)` load.

`getSidebarData` is NOT touched; the controllers build their own sidebar array.

## Detection signals

- Retraction: OpenAlex `is_retracted`; Crossref `update-to` of type
  retract/withdraw, or `relation.is-retracted-by` / `has-retraction`.
- Update: Crossref `updated-by` entries (excluding retractions).
- New related: up to three entries from OpenAlex `related_works`.

DOIs are normalised to a bare lower-cased `10.x/...` form (doi.org URL and `doi:`
prefixes stripped); anything that does not look like a DOI is dropped so the APIs
are never queried with junk.

## Routes

| Name | Method | Path |
|------|--------|------|
| `research.alerts.index` | GET | `/research/projects/{projectId}/alerts` |
| `research.alerts.read` | POST/PATCH | `/research/projects/{projectId}/alerts/{id}/read` |
| `research.alerts.read-all` | POST/PATCH | `/research/projects/{projectId}/alerts/read-all` |
| `research.alerts.watches` | GET | `/research/projects/{projectId}/alerts/watches` |
| `research.alerts.watches.add` | POST | `/research/projects/{projectId}/alerts/watches` |
| `research.alerts.watches.remove` | POST/DELETE | `/research/projects/{projectId}/alerts/watches/{id}/delete` |
