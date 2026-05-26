# Search ecosystem (#650) Phase 3 - cursor pagination, geo search, analytics

> Shipped 2026-05-26 as part of v1.102.0. Extends `packages/ahg-search/` with
> three orthogonal capabilities. All three are opt-in - existing search clients
> that send `?page=N` and no `geo[...]` keep working unchanged.

## 1. Cursor pagination

Elasticsearch `from + size` paging dies past `index.max_result_window`
(default 10 000). For large result sets (e.g. faceted browse over a
multi-million-doc tenant) the client has to switch to
`search_after`. Phase 3 adds an opaque cursor token that callers can
follow forward or backward without seeing the underlying sort values.

### URL contract

Legacy mode (unchanged):

```
GET /search?q=mining&page=3
```

Cursor mode:

```
GET /search?q=mining&paging=cursor           # first page, opt in to cursor mode
GET /search?q=mining&cursor=<token>          # forward page
GET /search?q=mining&cursor=<token>&dir=prev # backward page
```

Either `cursor=<token>` or an explicit `paging=cursor` triggers
cursor mode. Without either, the controller still emits `from/size`
paging.

### Token shape

A cursor is base64url-encoded JSON of the form:

```json
{"v":1,"d":"n","s":[<sort-values-from-the-boundary-hit>]}
```

- `v` - schema version. Unknown versions decode to `null` (caller
  falls back to page 1).
- `d` - direction. `n` = `search_after` (forward). `p` = the boundary
  hit was the FIRST hit on the previous page - the service flips the
  sort clause internally so ES returns the previous slice via
  `search_after`, then re-reverses it before transform.
- `s` - the raw sort array from `_search`.

### Response shape

`ElasticsearchService::advancedSearch()` returns:

```php
[
    'hits'         => [...],
    'total'        => 12345,
    'aggregations' => [...],
    'page'         => 1,           // unchanged; ignored by cursor clients
    'limit'        => 30,
    'paging'       => 'cursor',    // only when cursor mode is active
    'next_cursor'  => 'eyJ2IjoxLC...',  // null at end-of-set
    'prev_cursor'  => 'eyJ2IjoxLC...',  // null on the first page
]
```

### Stable tiebreaker

The sort clause always has `_id asc` appended. Without it, two hits
sharing the active sort value would silently shuffle between cursor
pages.

## 2. Geo search

Information-object documents that have at least one place with
coordinates now index three fields:

| Field      | Type       | Notes                              |
|------------|------------|------------------------------------|
| `gis_lat`  | `float`    | Convenience scalar                 |
| `gis_lng`  | `float`    | Convenience scalar                 |
| `gis`      | `geo_point`| `{lat: ..., lon: ...}` for queries |

### Data path

`information_object` -> `object_term_relation` -> `term`
(`taxonomy_id = 42` is "Places") -> `ric_place` -> `latitude` /
`longitude`. The first place with non-null coords per IO is indexed
(picking a deterministic "primary" coord is a future workstream).

### Reindex

`php artisan ahg:es-reindex --index=informationobject` now:

1. Ensures the `gis` geo_point mapping exists (idempotent PUT
   mapping; no-op when already present).
2. Pulls place coords in the same batch as the i18n / DO / creator
   fan-out so the IO reindex is not slower.

### Query contract

```
?geo[center]=-25.7479,28.2293&geo[radius]=5km
?geo[box]=-25.0,27.0,-26.5,29.0   # top-left lat,lng then bottom-right lat,lng
```

Center+radius wins when both are supplied. Malformed coordinates are
silently dropped (a bad URL never 500s the page). Supported radius
units: `km`, `m`, `mi`, `yd`, `ft`, `in`, `nmi`.

### Map UI (out of scope here)

Phase 3 ships only the index + query layer. The actual map-pin
browse front-end lives on top of `packages/ahg-display/`, which is
locked. Pinned to Phase 4 once the display package is unlocked.

## 3. Search analytics

New table `ahg_search_query_log` records every executed query:

```sql
CREATE TABLE ahg_search_query_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    anonymized_id VARCHAR(64) NULL,    -- sha256(IP) for non-logged-in users
    query VARCHAR(512) NOT NULL,
    filters_json JSON NULL,
    result_count INT NOT NULL DEFAULT 0,
    click_position INT NULL,           -- flipped by the click-tracking endpoint
    executed_at DATETIME NOT NULL,
    response_time_ms INT NOT NULL DEFAULT 0,
    -- indexes: executed_at, query(64), user_id, (result_count, executed_at)
);
```

Auto-installed on first boot by `AhgSearchServiceProvider`.

### Service API

`AhgSearch\Services\SearchAnalyticsService`:

- `recordQuery($q, $filters, $count, $ms, $ip = null): ?int`
  Called from `SearchController::search()`. Returns the inserted ID
  so the response can echo it back to the browser for click tracking.
- `recordClick($queryLogId, $position): bool`
  Called by the click-tracking POST endpoint.
- `topQueries($since, $limit = 20): array` - rows of `{query, count,
  click_count, ctr, avg_results, last_seen}`.
- `zeroResultQueries($since, $limit = 20): array` - same shape minus
  click columns.
- `totals($since): array` - `{total, zero, with_clicks, ctr,
  unique_queries}` for the dashboard hero strip.

### Routes

| Route                         | Method | Middleware | Purpose              |
|-------------------------------|--------|------------|----------------------|
| `/search/track-click`         | POST   | web        | Frontend click ping  |
| `/admin/search/analytics`     | GET    | web, admin | Dashboard            |

The click endpoint is intentionally public-POST so anonymous searchers
also contribute CTR data. It accepts `search_log_id` + `position` and
returns 200 unconditionally (the link still has to open).

### Privacy

- Logged-in searchers: `user_id` set, `anonymized_id` null.
- Anonymous searchers: `user_id` null, `anonymized_id` =
  `substr(sha256(ip), 0, 64)`. Raw IP is never stored. POPIA /
  GDPR-aligned by default.

### Retention

No automated purge yet. Operator-owned (e.g. via a `mysql` cron) -
typical retention is 90-180 days for analytics. Adding a
`search:analytics-prune` artisan command is a follow-up if and when
volume warrants.

## Out of scope - Phase 4 follow-ups

- **Search-within** (highlight + scope-and-content fragment with
  in-page context). The UI lives in `packages/ahg-display/` which is
  locked - resume after that subtree is unlocked.
- **Map-pin browse** front-end consuming the new geo_point field.
- **Synonym dictionary** wired off the zero-result top list.
- **Retention job** for `ahg_search_query_log`.
