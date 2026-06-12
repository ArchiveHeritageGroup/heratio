# Open Memory Protocol: Linked-Data crawl sitemap

Summary: Heratio's Open Memory Protocol (north-star #1204) has a public crawl sitemap for the linked-data entity graph, so search engines and Linked-Open-Data crawlers discover the dereferenceable `/id/...` entity URIs. A sitemap INDEX at `/sitemap-data.xml` links per-type sitemaps for records (`/sitemap-data-records.xml`), actors (`/sitemap-data-actors.xml`) and terms (`/sitemap-data-terms.xml`); each per-type sitemap is a `<urlset>` of the `/id/...` URIs, bounded + paginated (`?page=N`, 50000 URLs per file, the sitemaps.org cap). It lives in `ahg-api` (NOT locked), is read-only (SELECT / COUNT only, no writes, no ALTER), CORS-open, and never 500s. No hardcoded host: every `<loc>` is built from `url()`.

## Why a third sitemap

Three sitemaps now cover three different things, each a connected discovery path:

- `/sitemap.xml` (PublicSitemapController) - the HUMAN `/{slug}` record PAGES, for search engines.
- `/api/v1/graph/sitemap.xml` (GraphController::sitemap) - the per-record `/api/v1/graph/{id}` NEIGHBOURHOOD URLs.
- `/sitemap-data.xml` (DataSitemapController, this slice) - the canonical dereferenceable `/id/...` ENTITY IDENTITY URIs (records, actors, terms). This is the surface that makes the open-data GRAPH ITSELF crawlable, not just the record pages or graph neighbourhoods.

## Routes (packages/ahg-api/routes/api.php)

Registered at the ROOT (no group prefix), under `throttle:120,1` + `api.cors`, immediately after the existing `/sitemap.xml` + `/robots.txt` block (which is left untouched):

- `GET /sitemap-data.xml` (name `public.data-sitemap`) - the `<sitemapindex>`.
- `GET /sitemap-data-records.xml` (name `public.data-sitemap.records`) - `<urlset>` of `/id/{slug}`.
- `GET /sitemap-data-actors.xml` (name `public.data-sitemap.actors`) - `<urlset>` of `/id/actor/{slug}`.
- `GET /sitemap-data-terms.xml` (name `public.data-sitemap.terms`) - `<urlset>` of `/id/term/{slug}`.

## Catch-all safety

Each path is a SINGLE segment that CONTAINS A DOT (`sitemap-data.xml`, `sitemap-data-records.xml`, ...). The single-segment `/{slug}` archival-record catch-all in `ahg-information-object-manage` is constrained to `[a-z0-9][a-z0-9-]*$` (no dot), so it can NEVER capture these names - they bind in `ahg-api` first, exactly like the existing `/sitemap.xml`, `/feed.atom`, `/robots.txt`. nginx's static-file whitelist does not include these names, so all four reach Laravel. Verified on the live route table: all four register under their names with no collision.

## DataSitemapController (src/Controllers/DataSitemapController.php)

- `index()` - builds the `<sitemapindex>`. For each of records/actors/terms it computes `ceil(count / 50000)` pages and emits one `<sitemap>` per page (page 1 = bare `.xml`, page N>1 = `?page=N`). A count fault treats the type as empty (still emits a valid page-1 entry), so the index never 500s.
- `records()` / `actors()` / `terms()` - each emits a `<urlset>` over ONE bounded page (`offset = (page-1) * 50000`, `limit = 50000`) of `/id/...` URIs. `?page=` is read 1-based; below 1 collapses to page 1; an out-of-range page yields a valid EMPTY `<urlset>` (verified).
- Bounded queries only - a single `SELECT ... ORDER BY id LIMIT/OFFSET` per page; the whole catalogue is never materialised in memory.
- Each `<url>` carries `<loc>` (the `/id/...` URI via `url()`), `<changefreq>monthly</changefreq>`, and `<lastmod>` for records (from `object.updated_at`); actors/terms have no per-row timestamp on the open path so they omit `<lastmod>`.
- XML is well-formed string concatenation with `htmlspecialchars(..., ENT_QUOTES | ENT_XML1)` escaping on every value.

## Published-only / reference gates (mirror the existing entity endpoints)

- RECORDS: `information_object` JOIN `status` on `type_id=158 AND status_id=160` (Published) JOIN `slug`, `io.id <> 1` (root excluded). Identical to `EntityController` / `PublicSitemapController`. Drafts are never listed.
- ACTORS: `actor` JOIN `slug`, `id <> 1`, repository subtype excluded (`whereNotIn id (SELECT id FROM repository)` when present) - mirrors `ActorEntityController::loadActor`. Authority entities, no publication gate of their own.
- TERMS: `term` JOIN `slug`, `id <> 1` - mirrors `TermEntityController`.

Every query is guarded by `Schema::hasTable(...)` + `try/catch`; a schema variance degrades to an empty set, never an exception.

## ProtocolController wiring (additive)

`ProtocolController::surfaces()` gained one entry, `id => 'sitemap-data'`, resolving `route('public.data-sitemap')` with a `/sitemap-data.xml` literal fallback, so `/open-data/protocol` (and the DCAT `/data/catalog`, which reuses the same `surfaces()` list) now advertise the crawl sitemap. The existing `/sitemap.xml` and `/robots.txt` surfaces and the `robots` route are unchanged.

## Verification (worktree, app booted from a temporary copy into the main checkout, then restored)

- `php -l` clean on the controller, routes and ProtocolController.
- All four routes register (`route:list`).
- HTTP-kernel dispatch (as www-data): all 200, `Content-Type: application/xml; charset=utf-8`, `Access-Control-Allow-Origin: *`, and `simplexml_load_string` well-formed for the index, each per-type sitemap, `?page=1`, and an out-of-range `?page=99999` (valid empty `<urlset>`).
- Sample data on this instance: index = 3 child sitemaps; records = 377 `/id/{slug}` URIs (with `<lastmod>`); actors = 389 `/id/actor/{slug}`; terms = 739 `/id/term/{slug}`. Sample `<loc>` values confirmed as `http://localhost/id/...` (host from `url()`, not hardcoded).
- `/open-data/protocol.json` now lists the `sitemap-data` surface at `/sitemap-data.xml`.

Epic #1204 remains OPEN.
