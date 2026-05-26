# ahg-statistics

Usage statistics and analytics dashboard for Heratio - page views, downloads, top items, geographic spread, and per-repository drill-down.

## Purpose

- Aggregated read-side analytics over the `ahg_pageview` / `ahg_download` ledger tables
- Bot filtering with admin-managed allow / block lists
- CSV export of the underlying counts
- Per-repository and per-item drill-down views

## Install

Auto-discovered. The ServiceProvider registers routes (`web` middleware) and the `ahg-statistics` view namespace. The underlying ledger tables are installed by the core analytics packages; this module only consumes them.

## Routes

All under `/statistics` and gated by `auth` + `admin`:

- `GET /statistics/dashboard` - top-level KPI dashboard
- `GET /statistics/views` - pageviews over time
- `GET /statistics/downloads` - download counters
- `GET /statistics/top-items` - hottest archival descriptions
- `GET /statistics/geographic` - country / region breakdown
- `GET /statistics/item` - per-item history
- `GET /statistics/repository/{id}` - per-repository drill-down
- `GET|POST /statistics/admin` - admin settings (retention etc.)
- `GET|POST /statistics/admin/bots` - bot allow / block list
- `GET /statistics/export` - CSV export

## Key classes

| Class | Role |
|---|---|
| `Controllers\StatisticsController` | Dashboard, drill-downs, export |
| `Services\StatisticsService` | Aggregation queries + bot filtering |

## Views

Bootstrap 5 with `bi-*` icons; extends the `ahg-theme-b5` layout. Charts use the central Chart.js bundle.

## Notes

- Heavy queries are scoped server-side - no `SELECT *` on raw ledger tables.
- Bot filtering uses the `ahg_settings.statistics.bots` JSON list maintained from the `/statistics/admin/bots` page.
