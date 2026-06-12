# Related records: public per-record similarity discovery surface

A discovery surface that, given ONE published archival record, returns the most
similar OTHER published records. It is the per-record companion to the collection
-wide discovery surfaces (Explore by theme, Discoveries, Research Leads): where
those group the catalogue, "Related records" answers "what else is like THIS one?"

The defining constraint: it **reuses the EXISTING semantic vector index** - it
builds no new index and makes no AI call of its own. Lives entirely in
`packages/ahg-semantic-search` and touches no locked path.

## How relatedness is computed (stored-vector reuse, zero AI call)

Each archival record's embedding is ALREADY stored as a point in the existing
Qdrant collection (the same index behind the "Find Similar Records" widget and
`/api/search/semantic/similar/{ioId}` in `ahg-search`). The Qdrant point id is the
`information_object` id.

`RelatedRecordsService::relatedTo()` hands the record's id straight to
`AhgSearch\Services\VectorSearchService::searchSimilarToPoint($pointId, ...)`.
That method:

1. **Reads the record's already-stored vector** from Qdrant
   (`fetchPointVector` -> `GET /collections/{c}/points/{id}?with_vector=true`).
2. **Runs a bounded k-NN** `POST /collections/{c}/points/search` with that vector.

Because the source is a stored vector, **no query string is embedded and no
embedding / AI call is made at all**. The only network calls are Qdrant reads.
This surface never opens a socket to a GPU node port (`11434` / `5004` / `5006` /
`8011`) of its own. The AHG gateway rule is honoured by construction: AI/embedding
access happens ONLY through the `VectorSearchService` abstraction, and the
stored-vector path triggers none. If a deployment ever wires query-string
embedding, that stays inside `VectorSearchService`, where the operator points it
at `https://ai.theahg.co.za/ai/v1/...`.

After the k-NN, the service applies Heratio's publication gate to the neighbours,
drops the source record and the catalogue root, hydrates `{title, slug, url}` from
the existing catalogue tables, and caps the list.

## What it does

1. **JSON twin (`GET /related/{idOrSlug}.json`)** - declared FIRST, dotted,
   CORS-open, cacheable (`max-age=300`). The top N (default 12, cap 20) most-
   similar published records, each `{id, slug, title, score, url}`. The source
   record and root are excluded; only published neighbours appear.
2. **HTML page (`GET /related/{idOrSlug}`)** - a small Bootstrap 5 + central-theme
   (`theme::layouts.1col`) page listing the related records as cards linking to
   each record's show page, with an honest note that relatedness is the semantic
   similarity of the descriptions and that absence of results is shown plainly.

`{idOrSlug}` accepts a numeric `information_object` id OR a slug.

## Published gate + 404 vs empty-list

- An item is "published" when its `status` row (`type_id = 158`) carries
  `status_id = 160`; the catalogue root (`id = 1`) is excluded everywhere.
- **Unknown OR unpublished** source record -> **404** (`.json` returns a
  `not_found` JSON body; the HTML route `abort(404)`). The root id `1` is a 404.
- **Empty index / record has no stored vector / vector store unreachable** ->
  a calm **HTTP 200 with an empty list** on `.json`, and a plain "no related
  records available" empty-state on the HTML page. Never a 500.

Every neighbour is itself published-gated in one bounded `whereIn` query over
`status`, so an unpublished neighbour returned by the index never surfaces.

## Bounded

Single record in, capped N out (`MAX_LIMIT = 20`). The index fetch is padded
(roughly `3 x limit`, bounded by the service's own `<= 100` cap) so the
post-publication gate can still fill the page, but there is no full-catalogue scan
beyond the index's own k-NN. Title/slug hydration is a single batched query each.

## Routes (catch-all-safe)

Both paths are TWO-segment (`/related/...`). The single-segment `/{slug}`
archival-record catch-all in `ahg-information-object-manage` is constrained to one
path segment (no slash), so it can never intercept `/related/...`. The routes are
nonetheless bound in the provider's `register()` via `callAfterResolving('router')`
for the same precedence guarantee as the other public discovery surfaces (see
`reference_slug_catchall_route_precedence`).

- `/related/{idOrSlug}.json` (`related.json`) is declared **before**
  `/related/{idOrSlug}` (`related.show`), so a record slug ending in a literal
  `.json` can never be swallowed by the HTML route.
- The `{idOrSlug}` matcher is `[A-Za-z0-9][A-Za-z0-9/_-]*`, which **allows
  multi-segment slugs** and excludes `.` (real AtoM slugs are `[a-z0-9-]`, never
  dotted), so the `.json` suffix is unambiguous.

A normal single-segment slug (e.g. `/title-of-object`) is unaffected: it still
falls through to the `/{slug}` catch-all and the archival-record show page exactly
as before. Verified live: `/title-of-object` continued to resolve to the IO show
controller (302 to its sector show) while `/related/title-of-object.json` returned
200 and `/related/title-of-object` rendered.

## Where it lives (packages/ahg-semantic-search)

- `src/Services/RelatedRecordsService.php` - the read-only data layer.
  `resolvePublishedId()` (the 404 gate), `relatedTo()` (the published-gated
  k-NN reuse), `neighbourHits()` (the single integration point with
  `VectorSearchService`), `isPublished()` / `filterPublished()`, `hydrate()`,
  `recordHeader()`. Every path is `Schema::hasTable`-guarded and try/catch-wrapped;
  no writes, no AI of its own.
- `src/Controllers/RelatedRecordsController.php` - public `json` (CORS-open,
  404-on-unknown, 200-empty-list otherwise) and `show` (HTML, 404-on-unknown).
- `resources/views/related/show.blade.php` - Bootstrap 5 + central theme, card
  grid, calm empty-state, and the honest "how this is computed" note.

No new table, no install SQL, no `boot()` table creation: it reads the existing
vector index (via `ahg-search`) plus `information_object` / `slug` /
`information_object_i18n` / `status`.

## Graceful absence of ahg-search

`neighbourHits()` guards `class_exists('AhgSearch\Services\VectorSearchService')`.
If `ahg-search` is not installed there is no index to reuse, and the surface
degrades to an empty list rather than fataling.

## Constraints honoured

Read-only; no writes; no ALTER; NO new table and NO new index (reuses the existing
Qdrant collection); AI/embeddings ONLY via the existing `VectorSearchService`
abstraction (and the stored-vector path makes no AI call at all); never a node
port; catch-all-safe (`.json` first, multi-segment matcher, two-segment paths);
published-only; root excluded; unknown/unpublished -> 404; empty/unavailable ->
200 empty list (never 500); `url()`-relative (no hardcoded host); Bootstrap 5 +
central theme; Plain Sailing / AGPL headers; international and jurisdiction-neutral.
