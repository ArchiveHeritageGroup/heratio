# Browse by place: public place-grouping discovery surface (geography slice)

A discovery slice that complements **Explore by theme** (subjects). Where "Explore
by theme" groups the published collection by what its records are *about*
(subjects, taxonomy `35`), "Browse by place" groups the same holdings by the
*places* they are about (place access points, taxonomy `42`). Both are "ways into
the collection" that let a visitor start from a facet rather than a search box.

A place is simply a geographic access point: the place terms (taxonomy `42`) that
the most PUBLISHED records carry are the collection's busiest places. The surface
is a cheap, read-only aggregate over the existing taxonomy - it adds NO table,
never writes, never ALTERs, and touches no locked path. No geography is hardcoded
and there is no country default; the place names come entirely from the data.

Lives entirely in `packages/ahg-semantic-search` (alongside Explore-by-theme,
Discoveries, Research Leads, displaced-heritage, endangered-heritage,
language-corpus, related-records, timeline). It is additive: it does NOT touch the
`/themes`, `/related` or `/timeline` files.

## What it does

1. **Landing (`GET /places`).** One cheap bounded `GROUP BY` aggregate over
   `object_term_relation` joined to `term` (taxonomy `42`), gated to published
   records, ordered by published-record count, capped at 120 (default 80 shown).
   Rendered as a frequency-sized cloud (each chip scales by its share of the
   busiest place) and a ranked list, each place carrying its count.
2. **One place (`GET /places/{termId}`).** The place's label, optional scope note,
   total published-record count, and a paginated, bounded list of the published
   records about it - each linking to the record. A "browse all about this place"
   link drops into the canonical GLAM browse with the place facet applied.
   `{termId}` is numeric and must resolve to a place term (taxonomy `42`); a
   subject / genre / root id is rejected and redirects to the landing.
3. **Machine list (`GET /places.json`).** CORS-open, cacheable (`max-age=300`)
   JSON of the place list: `id`, `label`, `record_count`, `url`, `browse_url`.

## Place-term linkage (verified against the live catalogue)

A record's place is recorded exactly like its subject: a row in
`object_term_relation(object_id, term_id)` where `term_id` is a `term` row whose
`taxonomy_id = 42` (Places). This is the same join the rest of the codebase uses
for places (e.g. `AhgCore\Repositories\MysqlPlaceRepository`,
`DisplayController`'s `place=` browse facet). The only difference from the
Explore-by-theme aggregate is the taxonomy id (`42` vs `35`) and the browse facet
param (`place=` vs `subject=`).

## Aggregation (cheap, bounded, read-only)

The landing is a single `GROUP BY otr.term_id` with `COUNT(DISTINCT otr.object_id)`
over `object_term_relation`, joined to `term` on `taxonomy_id = 42`, with the
publication gate applied via an `EXISTS` sub-select on `status` (no wide join),
ordered by count and `LIMIT`ed. The per-place record list is paginated with
`offset`/`limit` (default 24/page, capped at 60) so a busy place can never run an
unbounded query. There are NO per-record loops over the collection: the per-place
page runs one small bounded query, then a single batched title/slug hydrate. There
is no full-catalogue PHP scan anywhere.

## Published gate

Mirrors the rest of Heratio: an item is "published" when its row in the `status`
table (`type_id = 158`) carries `status_id = 160`; the catalogue root (`id = 1`)
is excluded everywhere. Every count and record-page applies the gate, so
unpublished and root rows never surface.

## Where it lives (packages/ahg-semantic-search)

- `src/Services/PlaceService.php` - the read-only aggregate. `topPlaces()`,
  `placeList()`, `place()` (paginated), plus bounded helpers (`aggregate()`,
  `publishedRecordsPage()`, `hydrateRecords()`, `termLabels()`, `termNote()`,
  `placeUrl()`, `browseUrl()`). Every path is `Schema::hasTable`-guarded and
  try/catch-wrapped; no writes, no AI.
- `src/Controllers/PlacesController.php` - public `index`, `show` (paginated),
  and `json` (CORS-open). Never 500s - degrades to the empty-state / redirect.
- `resources/views/places/{index,show}.blade.php` - Bootstrap 5 + central theme
  (`theme::layouts.1col`), frequency-sized cloud + ranked list, empty-states
  throughout, manual bounded pagination.

No new table, no install SQL, no `boot()` table creation: it reads the existing
`object_term_relation` / `term` / `term_i18n` / `status` tables only.

## Routes (catch-all-safe)

Registered in `AhgSemanticSearchServiceProvider::register()` via
`callAfterResolving('router')`, exactly like `/themes`:

- `/places.json` (`places.json`) is declared FIRST - dotted, so a record slug that
  literally ends in ".json" can never be swallowed by the HTML index route.
- `/places` (`places.index`) is a single-segment path bound before the
  single-segment `/{slug}` archival-record catch-all in
  `ahg-information-object-manage`. (`register()` runs for all providers before any
  `boot()`, so this route wins the match. See
  `reference_slug_catchall_route_precedence`.)
- `/places/{termId}` (`places.show`) is a two-segment path with a numeric
  `{termId}` constraint, so it can never shadow the `/{slug}` catch-all; bound the
  same way for the same precedence guarantee.

## Reuse

The per-place "browse all about this place" link and the `.json` `browse_url` both
point at the single canonical GLAM browse (`ahg-display`) with its existing
`place=<term_id>` filter - no new browse page, no duplicated facet logic. Record
links use `url('/'.$slug)` to the existing archival-record show page.

## Constraints honoured

Read-only; no writes; no ALTER; NO new table (cheap on-the-fly COUNTs only);
catch-all-safe single-segment registration; published-only; root excluded;
`url()`-relative (no hardcoded host); Bootstrap 5 + central theme; empty-states
everywhere; never 500s; Plain Sailing / AGPL headers; international and
jurisdiction-neutral (no hardcoded geography, no country default). Does not touch
the `/themes`, `/related` or `/timeline` files.
