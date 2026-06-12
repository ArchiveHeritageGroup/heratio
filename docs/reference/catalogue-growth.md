# Catalogue growth report - technical reference

Summary: an admin, read-only catalogue-growth and composition aggregate at `/admin/catalogue-growth` in `packages/ahg-reports`, mirroring the Collection data-quality and AI-usage report pattern (Schema::hasTable / Schema::hasColumn-guarded aggregate COUNTs, Route::has-gated links, `theme::layouts.2col`, a two-segment `/admin/...` route). Shipped in the same `ahg-reports` wave as the trust-console, data-quality and ai-usage slices. It is a management metric (size, growth, composition) and is deliberately distinct from the data-quality report (descriptive completeness) and the ai-usage report (where AI assisted). Reads catalogue tables; writes nothing.

## What it reads

Existing tables only (read-only, no ALTER, no new table):

- `information_object` - the archival descriptions. The real-record base excludes the synthetic root and its direct children (`id != 1 AND parent_id != 1`), matching the sibling reports. Columns used: `id`, `parent_id`, `repository_id`, `level_of_description_id`.
- `object` - the Class-Table-Inheritance root (`object.id = information_object.id`). Carries `created_at` (and `updated_at`). This is the creation-timestamp source for the time series. `information_object` itself has NO `created_at` / `updated_at` column (DESCRIBE-verified), so the timestamp is read from `object`.
- `status` - publication-status signal. Published = `type_id = 158 AND status_id = 160` (matches the data-quality report). The `status` table has NO timestamp column, so there is NO publication-time signal.
- `digital_object` - one row per digital surrogate (`object_id` links to the record). Drives "records with a digital object" and the total digital-object count.
- `term_i18n` - resolves the `level_of_description_id` label (culture `en`).
- `actor_i18n` - resolves the repository name (`authorized_form_of_name`); a repository is an actor under Class-Table-Inheritance, so `repository.id = actor.id = actor_i18n.id`.
- `actor` / `repository` - the authority-record and repository totals. The actor total excludes the synthetic root actor (`id != 3`).

## The growth-time-series honesty rule

A "records created per month" series is shown ONLY when a real creation timestamp exists:

- The controller probes `Schema::hasTable('object') && Schema::hasColumn('object','created_at')`.
- If present, it renders records-created-per-month from `object.created_at` (joined to the real `information_object` base on `object.id = io.id`), trailing 12 months, back-filled with zeros, as CSS bars (no charting library).
- If absent, it OMITS the series entirely and the view states plainly that creation timestamps are not recorded, showing current composition only. No date is invented.

There is no publication-time signal on this schema (`status` carries no timestamp), so a published-per-month series is never fabricated. When the created-per-month series is shown, the view notes explicitly that the bars count creation, not publication.

## Metrics (each a single grouped/aggregate COUNT or EXISTS)

| Metric | Query shape |
|---|---|
| Total records | `COUNT(*)` over the real-record base (the denominator for composition shares) |
| Published / unpublished | `WHERE EXISTS` a published `status` row; unpublished = total - published |
| Records with a digital object | `WHERE EXISTS` a `digital_object` row for the record |
| Digital objects held | `COUNT(*)` over `digital_object` |
| Authority records (actors) | `COUNT(*)` over `actor` excluding the root actor |
| Repositories | `COUNT(*)` over `repository` |
| Created over time | `GROUP BY DATE_FORMAT(object.created_at,'%Y-%m')` `COUNT(*)`, trailing 12 months, zero-filled (only when the timestamp column exists) |
| By level of description | `GROUP BY level_of_description_id` `COUNT(*)`, top 10, label from `term_i18n`, share of total, plus a "(no level of description)" bucket |
| By repository | `GROUP BY repository_id` `COUNT(*)`, top 10, name from `actor_i18n`, share of total, plus a "(no repository assigned)" closing row |
| By digital surrogate | with / without a digital object, each a share of total |

Term and repository labels are resolved with a single batched `whereIn(...)->pluck()` over the small set of ids actually present - no per-row query in a loop.

## Properties

- Read-only: SELECT / aggregate only; no writes, no ALTER, no new table.
- Bounded: grouped aggregate COUNTs and EXISTS checks only; no per-row PHP scan of the catalogue.
- Resilient: the whole build is wrapped in try/catch and every probe is Schema::hasTable / Schema::hasColumn-guarded; a missing table, a missing timestamp column, or an empty catalogue degrades to a calm "Nothing catalogued yet" / "Growth over time not available" state, never a 500.
- Admin-gated route; two-segment `/admin/catalogue-growth` (catch-all-safe against the `/{slug}` archival-record route).
- Links (back to Reports, across to the Trust & Transparency console, the data-quality report and the ai-usage report) are Route::has-gated so no dead links render.
- International: no jurisdiction-specific framing.

## Files

- `packages/ahg-reports/src/Controllers/CatalogueGrowthController.php`
- `packages/ahg-reports/resources/views/catalogue-growth/index.blade.php`
- route in `packages/ahg-reports/routes/web.php` (`reports.catalogue-growth`)
