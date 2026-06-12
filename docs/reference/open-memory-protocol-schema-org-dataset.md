# Open Memory Protocol: schema.org Dataset descriptor

Summary: Heratio's Open Memory Protocol (north-star #1204) now exposes a schema.org/Dataset descriptor at `/data/dataset.jsonld` so the general web search engines that index schema.org markup - Google Dataset Search and Bing in particular - index the whole published collection AS A DATASET (so it surfaces in dataset-search results, not only the generic web index). It lives in the `ahg-api` package (NOT locked), is read-only, CORS-open, and never 500s. No hardcoded host: every URI is built from `url()` / `route()`.

## Why schema.org/Dataset in addition to DCAT + the capabilities document

The offering was already machine-described twice:

- `/open-data/protocol` (ProtocolController) - a bespoke "capabilities" document.
- `/data/catalog` (CatalogController) - a W3C DCAT-AP `dcat:Catalog` for open-data-portal harvesters (CKAN, the European Data Portal).

Those speak to data portals. The general web search engines do NOT crawl DCAT; they crawl schema.org markup. Google Dataset Search recognises a single `schema.org/Dataset` node with `schema.org/DataDownload` distributions. `/data/dataset.jsonld` emits exactly that shape - the THIRD machine view, targeting web search rather than data portals. The three do not compete; they target different consumers.

## Routes (packages/ahg-api/routes/api.php)

Registered at the ROOT (no group prefix), under `throttle:120,1` + `api.cors`, with `OPTIONS` preflights:

- `GET /data/dataset.jsonld` (name `open-data.dataset.jsonld`) - always the schema.org/Dataset JSON-LD (`application/ld+json`).
- `GET /data/dataset` (name `open-data.dataset`) - content-negotiated: a browser (text/html) is 303-redirected to the `/open-data` human hub; everyone else (and a bare curl) gets the JSON-LD.

Registered BEFORE the generic two-segment `/data/{slug}` record-entity wildcard, so the literal "dataset" binds as the descriptor, never as a record slug. The `.jsonld` form carries a dot, which the slug grammar (`[A-Za-z0-9\-_]`, no dot) already excludes.

## Catch-all safety

`/data/dataset` (+ `/data/dataset.jsonld`) are TWO-segment paths, so the single-segment `/{slug}` archival-record catch-all (constraint `[a-z0-9][a-z0-9-]*$`, one segment, no slash) can never capture them. nginx's static-file whitelist does not include these names, so both reach Laravel.

## DatasetSchemaController (src/Controllers/DatasetSchemaController.php)

The single `schema.org/Dataset` node (`@context` = `https://schema.org/`, `@type` = `Dataset`):

- `name`, `description`, `url` (the `/open-data` hub), `sameAs` (`/data/catalog`), `identifier`, `license` (CC-BY-4.0), `isAccessibleForFree`, `keywords`, `inLanguage`, `dateModified`.
- `creator` + `publisher` - a `schema.org/Organization` named from `config('app.name')`, homepage = this host (jurisdiction-neutral; no tenant constant).
- `includedInDataCatalog` -> a `schema.org/DataCatalog` pointing at `/data/catalog` (the DCAT view of the same offering).
- `temporalCoverage` - the collection's date span as an ISO 8601 `START/END` (years), a cheap `MIN(start)/MAX(end)` over `event` restricted to published records. Best-effort + guarded + sanity-bounded (1..2200); omitted (never faked) when there are no usable dates.
- `spatialCoverage` - the top `SPATIAL_TOP_N` (12) place terms as `schema:Place` nodes, a single `GROUP BY` over `object_term_relation` joined to the place taxonomy (id 42). Omitted on empty / schema variance.
- `size` - a `schema:QuantitativeValue` (the published-record count) + `variableMeasured`. The count is REUSED from `StatsController::compute()['published_records']` (the same aggregate `/data/stats` shows), so the figures never drift; a guarded local count is the fallback.
- `distribution[]` - one `schema.org/DataDownload` per bulk dump + crawlable entry point, each with `name`, `encodingFormat`, `contentUrl`, `license`.

### How distributions reuse the canonical surface list

The distributions are derived from `ProtocolController::surfaces()` (the single source-of-truth open-surface list, also used by the DCAT catalogue), filtered through a small allow-list `DISTRIBUTION_SURFACE_IDS` = `dataset-csv`, `dataset-jsonld`, `dataset-cidoc-crm`, `graph-dataset`, `graph-entity`, `oai-pmh`, `discovery`. Only the bulk dumps + crawlable entry points become a `DataDownload`; presentation surfaces (HTML dashboards, Swagger UI, sitemaps, feeds) are intentionally excluded - they are not dataset distributions. A surface that serves several media types yields several `DataDownload`s (one per `encodingFormat`). Add or remove a surface in `surfaces()` and the Dataset's distributions follow automatically.

### Template surfaces (the per-entity graph endpoint)

`graph-entity` (`/api/v1/graph/{idOrSlug}`) is a URL TEMPLATE, not a dereferenceable IRI. Its `DataDownload.contentUrl` therefore points at `/open-data/protocol` (a real URL) and the literal template is carried verbatim in `description` ("URL template: ..."). The `text/html` media type of that surface is skipped as a download (a distribution is the data, not the page). No raw `{placeholder}` ever appears in a `contentUrl`.

## The surface entry

`ProtocolController::surfaces()` gained ONE new entry, `dataset-schema-org` (`title` "schema.org Dataset descriptor (search-engine indexing)", `url` resolved from `open-data.dataset` with a `/data/dataset` literal fallback, media types `application/ld+json` + `text/html`). So the capabilities document and the DCAT catalogue both now list this surface automatically.

## Optional /open-data embed

`DatasetSchemaController::dataset()` is PUBLIC, so the same schema.org Dataset array can be embedded as a `<script type="application/ld+json">` snippet on the `/open-data` landing page. That page lives in `ahg-core` and (like every Heratio blade) is locked, so it was NOT edited here; the public `dataset()` method makes the additive wire-up a one-liner (e.g. a `View::composer` in an unlocked provider) whenever the user wants it.

## Validation done

- `php -l` clean on DatasetSchemaController, ProtocolController, routes/api.php.
- A standalone harness (stubbing the Laravel helpers/facades; `Schema::hasTable` forced false to take the no-DB fallback paths) exercised `dataset()` and `index()`: `json_decode` succeeds; `@type` = `Dataset`; `@context` = `https://schema.org/`; `creator`/`publisher` are `Organization`; `includedInDataCatalog.@id` = `/data/catalog`; 7 keywords; 9 `DataDownload` distributions, every one with a non-empty `encodingFormat` + `contentUrl`, all `url()`-based, none carrying a raw `{placeholder}`; the `graph-entity` template correctly points `contentUrl` at the protocol doc and carries the template string only in its `description`; `temporalCoverage` + `size` omitted (not faked) when the source tables are unavailable. `index($request, true)` returns `application/ld+json` + CORS `*` + `@type` Dataset; a `text/html` request returns a 303 redirect to the landing page.
- `bin/check-locked` exits 0; only `packages/ahg-api/` + `docs/` touched.

## Constraints honoured

AGPL header with `@copyright Plain Sailing Information Systems`; no em-dashes; jurisdiction-neutral (org name, licence and base URI all from config / `url()`, never a tenant constant). Read-only; cheap COUNT / GROUP BY / MIN / MAX only; no DB writes; no ALTER; every figure guarded so it can never 500. Epic #1204 stays OPEN.
