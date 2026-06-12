<?php

use AhgApi\Controllers\ActorEntityController;
use AhgApi\Controllers\CatalogController;
use AhgApi\Controllers\CookbookController;
use AhgApi\Controllers\DataSitemapController;
use AhgApi\Controllers\DatasetController;
use AhgApi\Controllers\DatasetSchemaController;
use AhgApi\Controllers\EntityController;
use AhgApi\Controllers\FeedController;
use AhgApi\Controllers\GraphController;
use AhgApi\Controllers\GraphExplorerController;
use AhgApi\Controllers\LegacyApiController;
use AhgApi\Controllers\MaturityController;
use AhgApi\Controllers\OaiPmhController;
use AhgApi\Controllers\OpenApiController;
use AhgApi\Controllers\ProtocolController;
use AhgApi\Controllers\PublicSitemapController;
use AhgApi\Controllers\StatsController;
use AhgApi\Controllers\TermEntityController;
use AhgApi\Controllers\V1\AccessionApiController;
use AhgApi\Controllers\V1\ActorApiController;
use AhgApi\Controllers\V1\DigitalObjectApiController;
use AhgApi\Controllers\V1\DonorApiController;
use AhgApi\Controllers\V1\FunctionApiController;
use AhgApi\Controllers\V1\InformationObjectApiController;
use AhgApi\Controllers\V1\PhysicalObjectApiController;
use AhgApi\Controllers\V1\RepositoryApiController;
use AhgApi\Controllers\V1\TaxonomyApiController;
use AhgApi\Controllers\V2\ApiKeyController;
use AhgApi\Controllers\V2\ApiRootController;
use AhgApi\Controllers\V2\AssetController;
use AhgApi\Controllers\V2\AuditController;
use AhgApi\Controllers\V2\AuthorityController;
use AhgApi\Controllers\V2\BatchController;
use AhgApi\Controllers\V2\ConditionController;
use AhgApi\Controllers\V2\DescriptionController;
use AhgApi\Controllers\V2\DigitalObjectController as V2DigitalObjectController;
use AhgApi\Controllers\V2\EventController;
use AhgApi\Controllers\V2\IdentifierController;
use AhgApi\Controllers\V2\MarketplaceController as V2MarketplaceController;
use AhgApi\Controllers\V2\PrivacyController;
use AhgApi\Controllers\V2\PublishController;
use AhgApi\Controllers\V2\RepositoryController as V2RepositoryController;
use AhgApi\Controllers\V2\SearchController;
use AhgApi\Controllers\V2\SpectrumApiController;
use AhgApi\Controllers\V2\SyncController;
use AhgApi\Controllers\V2\TaxonomyController as V2TaxonomyController;
use AhgApi\Controllers\V2\UploadController;
use AhgApi\Controllers\V2\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| OpenAPI Spec + Swagger UI (Issue #652 Phase 1)
|--------------------------------------------------------------------------
| /api/openapi.json - OpenAPI 3.1 JSON document (cached 60s server-side).
| /api/docs         - Swagger UI viewer.
| Both endpoints honour ahg_settings.openapi_public; admins always allowed.
*/

Route::middleware(['api.cors', 'api.etag'])->group(function () {
    Route::get('api/openapi.json', [OpenApiController::class, 'spec'])->name('api.openapi.spec');
    Route::get('api/docs', [OpenApiController::class, 'docs'])->name('api.openapi.docs');
});

/*
|--------------------------------------------------------------------------
| OAI-PMH 2.0 harvesting endpoint (deepens north-star #1204)
|--------------------------------------------------------------------------
| GET /api/oai?verb=...  - a single standards-based OAI-PMH 2.0 endpoint over
| the PUBLISHED archival corpus, serving simple Dublin Core (oai_dc). It makes
| the corpus harvestable by library/archive aggregators and crawling agents,
| complementing the Linked-Data graph endpoint (the graph is for per-entity
| crawling; OAI-PMH is for bulk metadata harvesting).
|
| Verbs: Identify, ListMetadataFormats, ListIdentifiers, ListRecords,
| GetRecord. Selective harvesting via from/until + an opaque resumptionToken
| (bounded page size). No API key (open data); permissive CORS; a light
| throttle keeps the open door cheap. The controller enforces the same
| publication-status gate as the rest of the public API (status.type_id=158,
| status_id=160), excludes the synthetic root (id=1), and never 500s - every
| error is a valid OAI <error> document.
|
| Route choice: /api/oai (single endpoint, OAI verbs via query string). It is
| under the "api/" path space, which the single-segment /{slug} information-
| object catch-all already excludes (its regex is ^(?!api|admin|...)... ), so
| it can never be shadowed. Registering it in this package also loads it well
| before the catch-all. It is consistent with the package's existing route base
| (api/openapi.json, api/docs, api/v1/...).
*/

Route::middleware(['throttle:120,1', 'api.cors'])->group(function () {
    Route::options('api/oai', [OaiPmhController::class, 'options']);
    Route::get('api/oai', [OaiPmhController::class, 'handle'])->name('api.oai');
    // POST is permitted by the OAI-PMH spec; arguments still arrive as query/
    // form params, which Request::query reads transparently for GET. Accept
    // POST too so a strict harvester that prefers POST is served.
    Route::post('api/oai', [OaiPmhController::class, 'handle'])->name('api.oai.post');
});

/*
|--------------------------------------------------------------------------
| Open Memory Protocol — public Linked-Data graph endpoint (north-star #1204)
|--------------------------------------------------------------------------
| A small crawlable open-data protocol around the heritage graph:
|
|   GET /api/v1/graph                  - VoID/DCAT dataset description (front door)
|   GET /api/v1/graph/context.jsonld   - the stable JSON-LD @context document
|   GET /api/v1/graph/index            - cursor-paginated crawl seed (alias /seed)
|   GET /api/v1/graph/{idOrSlug}       - a record's graph neighbourhood
|   GET /api/v1/graph/{idOrSlug}.{ext} - same, with .jsonld | .ttl | .rdf suffix
|
| Per-entity content negotiation: JSON-LD (default), Turtle, RDF/XML - chosen
| by Accept header, ?format= param, or the path suffix. JSON-LD stays the
| default so existing callers are unaffected.
|
| No API key (open data); permissive CORS via api.cors. Published records
| only (the controller enforces the same publication-status gate as the rest
| of the public v1 API). A light throttle keeps the open door cheap.
|
| IMPORTANT: the literal routes (/graph, /graph/index, /graph/context.jsonld)
| are registered BEFORE the {idOrSlug} wildcard, and the wildcard is
| constrained, so they can never shadow each other.
*/

Route::prefix('api/v1')->middleware(['throttle:120,1', 'api.cors'])->group(function () {
    // Literal protocol surfaces - registered first so the wildcard below
    // cannot capture them.
    Route::options('graph', [GraphController::class, 'options']);
    Route::get('graph', [GraphController::class, 'dataset'])
        ->name('api.v1.graph.dataset');

    Route::get('graph/context.jsonld', [GraphController::class, 'context'])
        ->name('api.v1.graph.context');

    Route::get('graph/index', [GraphController::class, 'index'])
        ->name('api.v1.graph.index');
    Route::get('graph/seed', [GraphController::class, 'index'])
        ->name('api.v1.graph.seed');

    // XML sitemap of per-entity graph URLs. Literal, registered BEFORE the
    // {idOrSlug} wildcard so "sitemap.xml" is never captured as an id. Roots a
    // <sitemapindex> when the published entity count exceeds one page; each
    // ?page=N child is a <urlset>.
    Route::get('graph/sitemap.xml', [GraphController::class, 'sitemap'])
        ->name('api.v1.graph.sitemap');

    // Bulk open-data dataset export (extends the #1204 open-data line). Lets a
    // researcher download the WHOLE published catalogue as a dataset:
    //   GET /api/v1/dataset.csv     - streamed CSV (server-side cursor).
    //   GET /api/v1/dataset.jsonld  - bounded JSON-LD @graph, ?after= cursor.
    // Literal routes, registered BEFORE the {idOrSlug} wildcard so the dotted
    // names can never be captured as an id/slug. Same published-only gate as
    // graph/OAI; read-only; open data (permissive CORS via api.cors).
    Route::options('dataset.csv', [DatasetController::class, 'options']);
    Route::get('dataset.csv', [DatasetController::class, 'csv'])
        ->name('api.v1.dataset.csv');

    Route::options('dataset.jsonld', [DatasetController::class, 'options']);
    Route::get('dataset.jsonld', [DatasetController::class, 'jsonld'])
        ->name('api.v1.dataset.jsonld');

    // Per-entity endpoint, with optional .jsonld/.ttl/.rdf suffix. The
    // wildcard is constrained to an id/slug grammar so it never swallows the
    // literals above.
    Route::options('graph/{idOrSlug}', [GraphController::class, 'options'])
        ->where('idOrSlug', '.*');

    Route::get('graph/{idOrSlug}.{suffix}', [GraphController::class, 'show'])
        ->where('idOrSlug', '[A-Za-z0-9\-_]+')
        ->where('suffix', 'jsonld|ttl|rdf')
        ->name('api.v1.graph.show.suffixed');

    Route::get('graph/{idOrSlug}', [GraphController::class, 'show'])
        ->where('idOrSlug', '[A-Za-z0-9\-_]+')
        ->name('api.v1.graph.show');
});

/*
|--------------------------------------------------------------------------
| Zero-knowledge discovery — /.well-known/void (root path, NOT /api/v1)
|--------------------------------------------------------------------------
| The single URL a standards-aware crawler dereferences when it knows nothing
| about this host. Returns a VoID/DCAT dataset description in Turtle that
| links to the graph front door, the JSON-LD @context, the crawl seed/index,
| and the XML sitemap — so discovery -> sitemap -> per-entity crawl is a
| connected path.
|
| Registered at the ROOT (this routes file is loaded without a group prefix).
| The path is multi-segment and begins with ".well-known", so it cannot be
| captured by the single-segment /{slug} catch-all (its regex is
| ^(?!...)[a-z0-9][a-z0-9-]*$ — no leading dot, no slash). RDF browsers that
| ask for ".../void.ttl" get the same document.
*/

Route::middleware(['throttle:120,1', 'api.cors'])->group(function () {
    Route::options('.well-known/void', [GraphController::class, 'options']);
    Route::get('.well-known/void', [GraphController::class, 'void'])
        ->name('wellknown.void');
    Route::get('.well-known/void.ttl', [GraphController::class, 'void'])
        ->name('wellknown.void.ttl');
});

/*
|--------------------------------------------------------------------------
| Public-website SEO surfaces — /sitemap.xml + /robots.txt (root path)
|--------------------------------------------------------------------------
| Search-engine discoverability for the PUBLIC RECORD pages (the canonical
| single-segment /{slug} archival-record views) plus the key static public
| pages (home, /glam/browse, /explore, /open-data, /reconstructions, /verify
| — only those whose routes are registered).
|
|   GET /sitemap.xml  - an XML sitemap of the public record pages. A
|                       <sitemapindex> over ?page=N child <urlset>s when the
|                       published count exceeds one file's cap, else a single
|                       <urlset>. Each url carries <loc> + <lastmod> + a
|                       <changefreq>. Streamed (keyset slice) — never loads the
|                       whole catalogue into memory. application/xml.
|   GET /robots.txt   - allow public content, disallow /admin and the private
|                       prefixes, and advertise the sitemap. text/plain.
|
| Both are PUBLIC (no auth), read-only, published-only (drafts are never
| exposed), and resilient: an empty catalogue still yields a valid sitemap with
| just the static pages, never a 500.
|
| Registered at the ROOT (this routes file is loaded without a group prefix).
| "sitemap.xml" and "robots.txt" each contain a dot, so the single-segment
| /{slug} archival-record catch-all (constraint '[a-z0-9][a-z0-9-]*$', no dot)
| can NEVER capture them — they bind here, before the catch-all. Note: nginx's
| static-file whitelist does not include .xml/.txt, so both reach Laravel.
*/

Route::middleware(['throttle:120,1', 'api.cors'])->group(function () {
    Route::get('sitemap.xml', [PublicSitemapController::class, 'sitemap'])
        ->name('public.sitemap');
    Route::get('robots.txt', [PublicSitemapController::class, 'robots'])
        ->name('public.robots');
});

/*
|--------------------------------------------------------------------------
| Linked-Data crawl sitemap — /sitemap-data*.xml (north-star #1204)
|--------------------------------------------------------------------------
| Search-engine + Linked-Open-Data discoverability for the dereferenceable
| ENTITY IDENTITY URIs (the /id/... surfaces served by EntityController,
| ActorEntityController and TermEntityController). Where /sitemap.xml lists the
| human /{slug} record PAGES and /api/v1/graph/sitemap.xml lists the per-record
| graph NEIGHBOURHOOD URLs, these list the canonical /id/... "thing" URIs so a
| crawler finds the open-data graph itself.
|
|   GET /sitemap-data.xml          - a <sitemapindex> linking the per-type
|                                    sitemaps below (one entry per page of each
|                                    type, ?page=N when a type exceeds the cap).
|   GET /sitemap-data-records.xml  - a <urlset> of /id/{slug} record URIs
|                                    (published-only, root-excluded).
|   GET /sitemap-data-actors.xml   - a <urlset> of /id/actor/{slug} actor URIs.
|   GET /sitemap-data-terms.xml    - a <urlset> of /id/term/{slug} term URIs.
|
| Each per-type sitemap is bounded + paginated (?page=N), capped at 50000 URLs
| per file (the sitemaps.org ceiling); the index lists every page. Each <loc>
| is the /id/... URI built with url() — never a hardcoded host. Read-only;
| PUBLIC (no auth); published-only for records (drafts never exposed); and
| resilient — an empty corpus still yields a valid empty <urlset> / minimal
| index, never a 500. Permissive open CORS via api.cors.
|
| Registered at the ROOT (this routes file is loaded without a group prefix).
| Each path is a single segment that CONTAINS A DOT ("sitemap-data.xml",
| "sitemap-data-records.xml", …), so the single-segment /{slug} archival-record
| catch-all (constraint '[a-z0-9][a-z0-9-]*$', no dot) can NEVER capture them —
| they bind here, before the catch-all. nginx's static-file whitelist does not
| include these names, so all four reach Laravel.
*/

Route::middleware(['throttle:120,1', 'api.cors'])->group(function () {
    Route::get('sitemap-data.xml', [DataSitemapController::class, 'index'])
        ->name('public.data-sitemap');
    Route::get('sitemap-data-records.xml', [DataSitemapController::class, 'records'])
        ->name('public.data-sitemap.records');
    Route::get('sitemap-data-actors.xml', [DataSitemapController::class, 'actors'])
        ->name('public.data-sitemap.actors');
    Route::get('sitemap-data-terms.xml', [DataSitemapController::class, 'terms'])
        ->name('public.data-sitemap.terms');
});

/*
|--------------------------------------------------------------------------
| Public content-syndication feeds — /feed.atom + /feed.rss (root path)
|--------------------------------------------------------------------------
| A small recency-window syndication surface over the PUBLISHED catalogue,
| complementing /sitemap.xml (whole catalogue, for indexing) and the bulk
| dataset export. Where the sitemap is "everything", these feeds are "what
| changed recently" — the surface a reader, an aggregator, or a change-watching
| agent subscribes to.
|
|   GET /feed.atom  - Atom 1.0 feed of the most recently UPDATED published
|                     records (ORDER BY object.updated_at DESC). Bounded to 50
|                     entries by default, ?limit= raisable up to 200.
|                     application/atom+xml.
|   GET /feed.rss   - the same data as an RSS 2.0 channel. application/rss+xml.
|
| Both are PUBLIC (no auth), read-only, published-only (drafts never exposed),
| and resilient: an empty catalogue still yields a valid empty feed, never a
| 500. Same published-status gate as the rest of the public API
| (status.type_id=158, status_id=160), synthetic root (id=1) excluded.
|
| Registered at the ROOT (this routes file is loaded without a group prefix).
| "feed.atom" / "feed.rss" each contain a dot, so the single-segment /{slug}
| archival-record catch-all (constraint '[a-z0-9][a-z0-9-]*$', no dot) can
| NEVER capture them — they bind here, before the catch-all. Note: nginx's
| static-file whitelist does not include .atom/.rss, so both reach Laravel.
*/

Route::middleware(['throttle:120,1', 'api.cors'])->group(function () {
    Route::options('feed.atom', [FeedController::class, 'options']);
    Route::get('feed.atom', [FeedController::class, 'atom'])
        ->name('public.feed.atom');

    Route::options('feed.rss', [FeedController::class, 'options']);
    Route::get('feed.rss', [FeedController::class, 'rss'])
        ->name('public.feed.rss');
});

/*
|--------------------------------------------------------------------------
| DCAT data catalogue - /data/catalog (north-star #1204)
|--------------------------------------------------------------------------
| A machine-readable W3C DCAT (DCAT-AP aligned) catalogue of the WHOLE
| open-data offering: a dcat:Catalog whose dcat:dataset entries are the very
| same open surfaces ProtocolController enumerates (the bulk dumps, the
| per-entity linked-data endpoints, the VoID description, OAI-PMH, the
| sitemaps, the feeds, the OpenAPI spec). Each dataset carries its
| dcat:distribution list (the actual access URLs + dcat:mediaType),
| dcterms:license, dcterms:publisher and dcat:landingPage. A DCAT-aware
| harvester (CKAN, the European Data Portal, ...) ingests this one document.
|
|   GET /data/catalog          - content-negotiated (Accept -> JSON-LD /
|                                Turtle / RDF-XML; a browser -> HTML page).
|   GET /data/catalog.jsonld   - JSON-LD, explicitly.
|   GET /data/catalog.ttl      - Turtle, explicitly.
|   GET /data/catalog.rdf      - RDF/XML, explicitly.
|
| STAYS IN SYNC: the dataset list comes from ProtocolController::surfaces() -
| ONE list, two views (the bespoke capabilities document + this DCAT catalogue)
| - so they can never drift. Read-only (no DB access); permissive open-data
| CORS; resilient (a valid empty catalogue rather than a 500).
|
| CATCH-ALL SAFETY: "/data/catalog" (+ the dotted .jsonld/.ttl/.rdf forms) are
| TWO-segment paths, so the single-segment /{slug} archival-record catch-all
| can never capture them. They are registered BEFORE the generic /data/{slug}
| record-entity wildcard below, so "catalog" binds as the literal catalogue,
| never as a record slug. The dotted suffixes contain a dot, which the slug
| grammar ([A-Za-z0-9\-_], no dot) already excludes.
*/

Route::middleware(['throttle:120,1', 'api.cors'])->group(function () {
    Route::options('data/catalog', [CatalogController::class, 'options']);
    Route::get('data/catalog', [CatalogController::class, 'index'])
        ->name('open-data.catalog');
    // Explicit format suffixes (a dot keeps them clear of the slug grammar).
    Route::options('data/catalog.{suffix}', [CatalogController::class, 'options'])
        ->where('suffix', 'jsonld|ttl|rdf');
    Route::get('data/catalog.{suffix}', [CatalogController::class, 'index'])
        ->where('suffix', 'jsonld|ttl|rdf')
        ->name('open-data.catalog.suffixed');
});

/*
|--------------------------------------------------------------------------
| Open graph statistics - /data/stats (north-star #1204)
|--------------------------------------------------------------------------
| The "graph at a glance" surface: cheap aggregate COUNTs describing the SIZE
| and SHAPE of the published open-data graph - published records (by level),
| people / organisations (by kind), subjects / places / genres, relation edges
| (total + the associative record-to-record cross-links), records carrying a
| linked-data URI, descriptive coverage (dates / creators / subjects) and the
| distinct holding repositories. VoID-aligned where it makes sense
| (void:entities / void:triples / void:classPartition).
|
|   GET /data/stats        - content-negotiated:
|                            text/html (a browser) -> the human dashboard
|                              (big numbers + plain CSS bars, no charting lib);
|                            application/ld+json   -> a VoID-aligned JSON-LD
|                              dataset description;
|                            everything else / ?format=json -> plain JSON.
|   GET /data/stats.json   - the machine JSON, explicitly (CORS-open).
|
| Read-only (COUNT / GROUP BY only; every aggregate Schema::hasTable-guarded);
| resilient (an empty corpus yields a valid all-zero document, never a 500);
| permissive open-data CORS. The dashboard links out to /data/catalog,
| /open-data/protocol and /graph-explorer.
|
| CATCH-ALL SAFETY: "/data/stats" and "/data/stats.json" are TWO-segment paths,
| so the single-segment /{slug} archival-record catch-all can never capture
| them. They are registered BEFORE the generic /data/{slug} record-entity
| wildcard below, so "stats" binds as the literal statistics surface, never as
| a record slug. The ".json" form carries a dot, which the slug grammar
| ([A-Za-z0-9\-_], no dot) already excludes.
*/

Route::middleware(['throttle:120,1', 'api.cors'])->group(function () {
    Route::options('data/stats', [StatsController::class, 'options']);
    Route::get('data/stats', [StatsController::class, 'index'])
        ->name('open-data.stats');
    Route::get('data/stats.json', fn (\Illuminate\Http\Request $request) => app(StatsController::class)->index($request, true))
        ->name('open-data.stats.json');
});

/*
|--------------------------------------------------------------------------
| schema.org/Dataset descriptor - /data/dataset.jsonld (north-star #1204)
|--------------------------------------------------------------------------
| A single schema.org/Dataset node shaped specifically for the general web
| search engines that index schema.org markup - Google Dataset Search and
| Bing in particular. Where /data/catalog speaks DCAT to open-data-portal
| harvesters, THIS surface speaks the schema.org vocabulary the web search
| engines crawl, so the WHOLE published collection is indexed AS A DATASET
| (and surfaces in dataset-search results, not only the generic web index).
|
| The Dataset carries name, description, url, creator + publisher (a
| schema.org/Organization named from config('app.name')), license (CC-BY-4.0),
| keywords, temporalCoverage (a cheap MIN/MAX date span), spatialCoverage (the
| top place terms), includedInDataCatalog -> /data/catalog, and a
| schema.org/DataDownload distribution per bulk dump + crawlable entry point
| (CSV, JSON-LD, CIDOC-CRM Turtle, the linked-data graph, OAI-PMH, VoID) - each
| with encodingFormat + contentUrl. The distribution list is derived from
| ProtocolController::surfaces() and the record-count size is reused from the
| StatsController aggregate, so neither can drift from the canonical surfaces /
| stats figures.
|
|   GET /data/dataset.jsonld  - the schema.org/Dataset as JSON-LD (always).
|   GET /data/dataset         - content-negotiated: a browser (text/html) is
|                               303-redirected to the /open-data human hub;
|                               everyone else (and a bare curl) gets JSON-LD.
|
| Read-only (cheap COUNT / GROUP BY / MIN / MAX only, every figure guarded);
| resilient (an empty corpus yields a valid minimal Dataset, never a 500);
| permissive open-data CORS; every URI from url() / route(), never a hardcoded
| host.
|
| CATCH-ALL SAFETY: "/data/dataset" and "/data/dataset.jsonld" are TWO-segment
| paths, so the single-segment /{slug} archival-record catch-all can never
| capture them. They are registered BEFORE the generic /data/{slug} record-
| entity wildcard below, so "dataset" binds as the literal descriptor, never as
| a record slug. The ".jsonld" form carries a dot, which the slug grammar
| ([A-Za-z0-9\-_], no dot) already excludes.
*/

Route::middleware(['throttle:120,1', 'api.cors'])->group(function () {
    Route::options('data/dataset', [DatasetSchemaController::class, 'options']);
    Route::get('data/dataset', [DatasetSchemaController::class, 'index'])
        ->name('open-data.dataset');
    Route::options('data/dataset.jsonld', [DatasetSchemaController::class, 'options']);
    Route::get('data/dataset.jsonld', fn (\Illuminate\Http\Request $request) => app(DatasetSchemaController::class)->index($request, true))
        ->name('open-data.dataset.jsonld');
});

/*
|--------------------------------------------------------------------------
| Content-negotiated entity endpoint — /id/{slug} (+ /data/{slug}) (north-star #1204)
|--------------------------------------------------------------------------
| Every published record gets a single, stable, dereferenceable Linked-Data
| identity. The format is chosen by the Accept header:
|
|   Accept: application/ld+json   -> JSON-LD (machine default)
|   Accept: text/turtle           -> Turtle
|   Accept: application/rdf+xml   -> RDF/XML
|   Accept: text/html (browser)   -> 303 See Other to the canonical /{slug} page
|
| (?format=jsonld|turtle|rdf|html overrides the header for convenience.) The
| description carries title, type, identifier, dates, creators, subjects,
| places, repository, parent (dcterms:isPartOf) and rdfs:seeAlso back-links;
| published records only; an unknown/draft slug -> a clean negotiated 404.
| Read-only; permissive open-data CORS.
|
| CATCH-ALL SAFETY: the single-segment /{slug} archival-record catch-all (in
| ahg-information-object-manage, constraint '[a-z0-9][a-z0-9-]*$' — ONE segment,
| no slash) can NEVER capture a TWO-segment path. "/id/{slug}" and
| "/data/{slug}" are two segments, so they bind here regardless of load order.
| The {slug} wildcard is constrained to the slug grammar so it cannot swallow a
| sibling literal. Note: nginx's static-file whitelist does not include these
| paths, so they reach Laravel.
*/

Route::middleware(['throttle:120,1', 'api.cors'])->group(function () {
    // ---------------------------------------------------------------------
    // ENTITY identity for ACTORS and TERMS (north-star #1204, next slice).
    //
    // These are THREE-segment paths (/id/actor/{slug}, /id/term/{slug}) and so
    // can never be captured by the single-segment /{slug} archival-record
    // catch-all, nor by the two-segment /id/{slug} record entity endpoint
    // below. They are declared FIRST so the literal second segment ("actor",
    // "term") binds before the generic /id/{slug} wildcard is ever consulted.
    //
    //   GET /id/actor/{slug}  (+ /data/actor/{slug}) - a person / corporate
    //       body / family: schema.org Person|Organization + RiC additionalType,
    //       dates of existence, biography / administrative history, the related
    //       PUBLISHED records, sameAs/seeAlso to the authority page + RiC export.
    //   GET /id/term/{slug}   (+ /data/term/{slug})  - a place / subject / genre
    //       term: skos:Concept (+ schema:Place for the spatial taxonomy),
    //       skos:broader / skos:narrower, and the PUBLISHED records referencing
    //       it; sameAs/seeAlso to the filtered browse page.
    //
    // Content-negotiated exactly like /id/{slug} (Accept -> JSON-LD / Turtle /
    // RDF-XML; a browser is 303-redirected to the human view). Read-only;
    // published-only for every record link; an unknown slug -> a clean
    // negotiated 404. Permissive open-data CORS.
    // ---------------------------------------------------------------------
    Route::options('id/actor/{slug}', [ActorEntityController::class, 'options'])
        ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-_]*');
    Route::get('id/actor/{slug}', [ActorEntityController::class, 'show'])
        ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-_]*')
        ->name('open-data.entity.actor');
    Route::options('data/actor/{slug}', [ActorEntityController::class, 'options'])
        ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-_]*');
    Route::get('data/actor/{slug}', [ActorEntityController::class, 'show'])
        ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-_]*')
        ->name('open-data.entity.actor.data');

    Route::options('id/term/{slug}', [TermEntityController::class, 'options'])
        ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-_]*');
    Route::get('id/term/{slug}', [TermEntityController::class, 'show'])
        ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-_]*')
        ->name('open-data.entity.term');
    Route::options('data/term/{slug}', [TermEntityController::class, 'options'])
        ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-_]*');
    Route::get('data/term/{slug}', [TermEntityController::class, 'show'])
        ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-_]*')
        ->name('open-data.entity.term.data');

    // ---------------------------------------------------------------------
    // ENTITY identity for a RECORD (the original slice). Two-segment paths.
    // Declared AFTER the actor/term routes above so "actor"/"term" never get
    // captured as a record {slug}.
    // ---------------------------------------------------------------------
    Route::options('id/{slug}', [EntityController::class, 'options'])
        ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-_]*');
    Route::get('id/{slug}', [EntityController::class, 'show'])
        ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-_]*')
        ->name('open-data.entity');

    // Explicit alias (some clients prefer a "/data/" path for the document).
    Route::options('data/{slug}', [EntityController::class, 'options'])
        ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-_]*');
    Route::get('data/{slug}', [EntityController::class, 'show'])
        ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-_]*')
        ->name('open-data.entity.data');
});

/*
|--------------------------------------------------------------------------
| Public GRAPH EXPLORER - /graph-explorer (+ /{type}/{slug}) (#1204)
|--------------------------------------------------------------------------
| The HUMAN-friendly counterpart to the machine /id/... entity endpoints above.
| Anyone can navigate the open linked-data graph in a browser, following the
| connections between records, people / organisations, places and subjects one
| hop at a time:
|
|   GET /graph-explorer                - landing: a search box + a few high-degree
|                                        starting entities (always an entry point).
|   GET /graph-explorer/{type}/{slug}  - ONE entity (type record|actor|term) as a
|                                        human page: its label + key facts and its
|                                        connections grouped and CLICKABLE, each
|                                        link navigating to the explorer for the
|                                        connected entity. Each page also links to
|                                        the machine /id/... document and the
|                                        canonical record / authority page.
|
| Thin presentation over GraphExplorerService, which mirrors the EXACT fetch +
| published-only gate (status.type_id=158, status_id=160; root id=1 excluded) of
| EntityController / ActorEntityController / TermEntityController, so the explorer
| can never drift from the linked-data output. Read-only; published records only;
| an unknown type or unknown / unpublished slug -> a clean themed 404, never a 500.
|
| CATCH-ALL SAFETY: "/graph-explorer/{type}/{slug}" is a THREE-segment path, so
| the single-segment /{slug} archival-record catch-all (ahg-information-object-
| manage) can never capture it. The bare "/graph-explorer" landing IS a single
| segment, but ahg-api is discovered before ahg-information-object-manage
| (alphabetical package order), so this route registers first and wins the match
| (first-registered route wins) - the same idiom as /open-data, /explore and
| /collection-overview in ahg-core. {type} is constrained to record|actor|term
| and {slug} to the slug grammar, so neither swallows a sibling path.
*/

Route::middleware(['throttle:120,1', 'api.cors'])->group(function () {
    Route::get('graph-explorer', [GraphExplorerController::class, 'index'])
        ->name('graph-explorer.index');

    Route::get('graph-explorer/{type}/{slug}', [GraphExplorerController::class, 'show'])
        ->where('type', 'record|actor|term')
        ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-_]*')
        ->name('graph-explorer.show');
});

/*
|--------------------------------------------------------------------------
| Open Memory Protocol capabilities document — /open-data/protocol (#1204)
|--------------------------------------------------------------------------
| The machine-discoverable INDEX of every open-data surface: VoID, the graph
| dataset front door + per-entity graph, the new /id/{slug} entity endpoint,
| the JSON-LD @context, the crawl seed, the bulk dataset dumps, OAI-PMH, the
| sitemaps, the syndication feeds, and the OpenAPI spec + Swagger UI — each
| with its URL (url()-based) and media types. One fetch tells an agent how to
| consume everything else.
|
|   GET /open-data/protocol        - content-negotiated (browser -> HTML page,
|                                     everyone else -> JSON capabilities).
|   GET /open-data/protocol.json   - the JSON capabilities, explicitly.
|
| Read-only (no DB access); permissive open-data CORS.
|
| CATCH-ALL SAFETY: "/open-data/protocol" and "/open-data/protocol.json" are
| TWO-segment paths, so the single-segment /{slug} catch-all cannot capture
| them. (The literal first segment /open-data is itself a registered single-
| segment public page in ahg-core; these two-segment children sit cleanly
| underneath it.)
*/

Route::middleware(['throttle:120,1', 'api.cors'])->group(function () {
    Route::options('open-data/protocol', [ProtocolController::class, 'options']);
    Route::get('open-data/protocol', [ProtocolController::class, 'index'])
        ->name('open-data.protocol');
    Route::get('open-data/protocol.json', fn (\Illuminate\Http\Request $request) => app(ProtocolController::class)->index($request, true))
        ->name('open-data.protocol.json');
});

/*
|--------------------------------------------------------------------------
| Open Data Maturity scorecard - /open-data/maturity (#1204)
|--------------------------------------------------------------------------
| A public scorecard that GRADES the platform's open-data offering against
| Tim Berners-Lee's 5-star Open Data deployment scheme, and shows the concrete
| EVIDENCE for each star (the real open surfaces that prove it). The evidence is
| resolved from ProtocolController::surfaces() - the one canonical surface list -
| so the scorecard can never drift from what is actually served; each star is
| marked achieved only when its evidence surface really resolves (honest on a
| slimmer install).
|
|   GET /open-data/maturity        - content-negotiated (browser -> HTML
|                                     scorecard, everyone else -> JSON).
|   GET /open-data/maturity.json   - the JSON scorecard, explicitly.
|
| Read-only (no DB access, no AI); permissive open-data CORS; never 500s.
|
| CATCH-ALL SAFETY: "/open-data/maturity" and "/open-data/maturity.json" are
| TWO-segment paths, so the single-segment /{slug} catch-all cannot capture
| them (the literal first segment /open-data is itself a registered single-
| segment public page in ahg-core; these two-segment children sit underneath).
*/

Route::middleware(['throttle:120,1', 'api.cors'])->group(function () {
    Route::options('open-data/maturity', [MaturityController::class, 'options']);
    Route::get('open-data/maturity', [MaturityController::class, 'index'])
        ->name('open-data.maturity');
    Route::get('open-data/maturity.json', fn (\Illuminate\Http\Request $request) => app(MaturityController::class)->index($request, true))
        ->name('open-data.maturity.json');
});

/*
|--------------------------------------------------------------------------
| Open-data developer cookbook - /open-data/cookbook (#1204)
|--------------------------------------------------------------------------
| A developer-facing guide of copy-paste WORKED EXAMPLES for consuming the
| open data: content negotiation against /id/{slug} entity URIs (JSON-LD /
| Turtle / RDF-XML), the bulk CSV / JSON-LD / CIDOC-CRM dumps, OAI-PMH
| harvesting, the discovery documents (protocol / VoID / DCAT / schema.org
| Dataset / crawl sitemap), and loading the data into common tools (rdflib,
| Apache Jena, a triple store) for LOCAL SPARQL. Every example URL is resolved
| from ProtocolController::surfaces() (the one canonical surface list) via
| url() / route(), so the commands target this deployment's real URLs and an
| example whose surface is absent is simply omitted (never a dead link).
|
| Honest where a capability is absent: there is no live, hosted SPARQL endpoint,
| so the SPARQL recipes show the local load-and-query path and say so plainly.
|
|   GET /open-data/cookbook        - content-negotiated (browser -> HTML guide,
|                                    everyone else -> a JSON example index).
|   GET /open-data/cookbook.json   - the JSON example index, explicitly.
|
| Read-only (no DB access, no AI); permissive open-data CORS; never 500s.
|
| CATCH-ALL SAFETY: "/open-data/cookbook" and "/open-data/cookbook.json" are
| TWO-segment paths, so the single-segment /{slug} archival-record catch-all
| cannot capture them (the literal first segment /open-data is itself a
| registered single-segment public page in ahg-core; these two-segment children
| sit cleanly underneath it).
*/

Route::middleware(['throttle:120,1', 'api.cors'])->group(function () {
    Route::options('open-data/cookbook', [CookbookController::class, 'options']);
    Route::get('open-data/cookbook', [CookbookController::class, 'index'])
        ->name('open-data.cookbook');
    Route::get('open-data/cookbook.json', fn (\Illuminate\Http\Request $request) => app(CookbookController::class)->index($request, true))
        ->name('open-data.cookbook.json');
});

/*
|--------------------------------------------------------------------------
| API v1 Routes (read-only + CRUD)
|--------------------------------------------------------------------------
*/

Route::prefix('api/v1')->middleware(['throttle:60,1', 'api.cors', 'api.etag', 'api.idempotency'])->group(function () {

    // Information Objects — READ
    Route::get('informationobjects/search', [InformationObjectApiController::class, 'search']);
    Route::get('informationobjects/tree/{slug}', [InformationObjectApiController::class, 'tree']);
    Route::get('informationobjects/{slug}/digitalobject', [InformationObjectApiController::class, 'digitalObject']);
    Route::get('informationobjects', [InformationObjectApiController::class, 'index']);
    Route::get('informationobjects/{slug}', [InformationObjectApiController::class, 'show']);

    // Information Objects — CRUD (authenticated)
    Route::middleware('api.auth:write')->group(function () {
        Route::post('informationobjects', [InformationObjectApiController::class, 'store']);
        Route::put('informationobjects/{slug}', [InformationObjectApiController::class, 'update']);
    });
    Route::delete('informationobjects/{slug}', [InformationObjectApiController::class, 'destroy'])
        ->middleware('api.auth:delete');

    // Actors — READ
    Route::get('actors/search', [ActorApiController::class, 'search']);
    Route::get('actors', [ActorApiController::class, 'index']);
    Route::get('actors/{slug}', [ActorApiController::class, 'show']);

    // Actors — CRUD (authenticated)
    Route::middleware('api.auth:write')->group(function () {
        Route::post('actors', [ActorApiController::class, 'store']);
        Route::put('actors/{slug}', [ActorApiController::class, 'update']);
        Route::patch('actors/{slug}', [ActorApiController::class, 'update']);
    });
    Route::delete('actors/{slug}', [ActorApiController::class, 'destroy'])
        ->middleware('api.auth:delete');

    // Repositories
    Route::get('repositories', [RepositoryApiController::class, 'index']);
    Route::get('repositories/{slug}', [RepositoryApiController::class, 'show']);

    // Accessions
    Route::get('accessions', [AccessionApiController::class, 'index']);
    Route::get('accessions/{slug}', [AccessionApiController::class, 'show']);

    // Donors
    Route::get('donors', [DonorApiController::class, 'index']);
    Route::get('donors/{slug}', [DonorApiController::class, 'show']);

    // Functions
    Route::get('functions', [FunctionApiController::class, 'index']);
    Route::get('functions/{slug}', [FunctionApiController::class, 'show']);

    // Physical Objects
    Route::get('physicalobjects', [PhysicalObjectApiController::class, 'index']);
    Route::get('physicalobjects/{slug}', [PhysicalObjectApiController::class, 'show']);
    Route::post('physicalobjects', [PhysicalObjectApiController::class, 'store'])
        ->middleware('api.auth:write');

    // Digital Objects
    Route::get('digitalobjects', [DigitalObjectApiController::class, 'index']);
    Route::get('digitalobjects/{id}', [DigitalObjectApiController::class, 'show'])->where('id', '[0-9]+');
    // Hyphenated alias (issue #747) - matches the documented URL form.
    Route::get('digital-object/{id}', [DigitalObjectApiController::class, 'show'])->where('id', '[0-9]+');
    Route::post('digitalobjects', [DigitalObjectApiController::class, 'store'])
        ->middleware('api.auth:write');

    // Taxonomies
    Route::get('taxonomies', [TaxonomyApiController::class, 'index']);
    Route::get('taxonomies/{id}/terms', [TaxonomyApiController::class, 'terms'])->where('id', '[0-9]+');
});

/*
|--------------------------------------------------------------------------
| API v2 Routes (full REST)
|--------------------------------------------------------------------------
*/

Route::prefix('api/v2')->middleware(['api.cors', 'api.auth:read', 'api.ratelimit', 'api.log', 'api.etag', 'api.idempotency'])->group(function () {

    // Root — endpoint listing
    Route::get('/', [ApiRootController::class, 'index'])->withoutMiddleware('api.auth:read');

    // Descriptions — full CRUD
    Route::get('descriptions', [DescriptionController::class, 'index']);
    Route::get('descriptions/{slug}', [DescriptionController::class, 'show']);
    Route::post('descriptions', [DescriptionController::class, 'store'])->middleware('api.auth:write');
    Route::match(['put', 'patch'], 'descriptions/{slug}', [DescriptionController::class, 'update'])->middleware('api.auth:write');
    Route::delete('descriptions/{slug}', [DescriptionController::class, 'destroy'])->middleware('api.auth:delete');

    // Authorities — full CRUD
    Route::get('authorities', [AuthorityController::class, 'index']);
    Route::get('authorities/{slug}', [AuthorityController::class, 'show']);
    Route::post('authorities', [AuthorityController::class, 'store'])->middleware('api.auth:write');
    Route::match(['put', 'patch'], 'authorities/{slug}', [AuthorityController::class, 'update'])->middleware('api.auth:write');
    Route::delete('authorities/{slug}', [AuthorityController::class, 'destroy'])->middleware('api.auth:delete');

    // Repositories
    Route::get('repositories', [V2RepositoryController::class, 'index']);

    // Taxonomies
    Route::get('taxonomies', [V2TaxonomyController::class, 'index']);
    Route::get('taxonomies/{id}/terms', [V2TaxonomyController::class, 'terms'])->where('id', '[0-9]+');

    // Search
    Route::match(['get', 'post'], 'search', [SearchController::class, 'search']);

    // Batch operations
    Route::post('batch', [BatchController::class, 'process'])->middleware('api.auth:write');

    // API Keys management
    Route::get('keys', [ApiKeyController::class, 'index']);
    Route::post('keys', [ApiKeyController::class, 'store']);
    Route::delete('keys/{id}', [ApiKeyController::class, 'destroy'])->where('id', '[0-9]+');

    // Webhooks
    Route::get('webhooks', [WebhookController::class, 'index']);
    Route::post('webhooks', [WebhookController::class, 'store'])->middleware('api.auth:write');
    Route::get('webhooks/{id}', [WebhookController::class, 'show'])->where('id', '[0-9]+');
    Route::match(['put', 'patch'], 'webhooks/{id}', [WebhookController::class, 'update'])->where('id', '[0-9]+')->middleware('api.auth:write');
    Route::delete('webhooks/{id}', [WebhookController::class, 'destroy'])->where('id', '[0-9]+')->middleware('api.auth:delete');
    Route::get('webhooks/{id}/deliveries', [WebhookController::class, 'deliveries'])->where('id', '[0-9]+');
    Route::post('webhooks/{id}/regenerate-secret', [WebhookController::class, 'regenerateSecret'])->where('id', '[0-9]+')->middleware('api.auth:write');

    // Events (webhook delivery audit trail)
    Route::get('events', [EventController::class, 'index']);
    Route::get('events/{id}', [EventController::class, 'show'])->where('id', '[0-9]+');
    Route::get('events/correlation/{id}', [EventController::class, 'correlation'])->where('id', '[0-9]+');

    // Audit (API request log)
    Route::get('audit', [AuditController::class, 'index']);
    Route::get('audit/{id}', [AuditController::class, 'show'])->where('id', '[0-9]+');

    // Publishing
    Route::get('publish/readiness/{slug}', [PublishController::class, 'readiness']);
    Route::post('publish/execute/{slug}', [PublishController::class, 'execute'])->middleware('api.auth:write');

    // File Uploads
    Route::post('upload', [UploadController::class, 'upload'])->middleware('api.auth:write');
    Route::post('descriptions/{slug}/upload', [UploadController::class, 'uploadForDescription'])->middleware('api.auth:write');

    // Conditions
    Route::get('conditions', [ConditionController::class, 'index']);
    Route::post('conditions', [ConditionController::class, 'store'])->middleware('api.auth:write');
    Route::get('conditions/{id}', [ConditionController::class, 'show'])->where('id', '[0-9]+');
    Route::match(['put', 'patch'], 'conditions/{id}', [ConditionController::class, 'update'])->where('id', '[0-9]+')->middleware('api.auth:write');
    Route::delete('conditions/{id}', [ConditionController::class, 'destroy'])->where('id', '[0-9]+')->middleware('api.auth:delete');
    Route::get('descriptions/{slug}/conditions', [ConditionController::class, 'forDescription']);
    Route::get('conditions/{id}/photos', [ConditionController::class, 'photos'])->where('id', '[0-9]+');
    Route::post('conditions/{id}/photos', [ConditionController::class, 'uploadPhoto'])->where('id', '[0-9]+')->middleware('api.auth:write');
    Route::delete('conditions/{id}/photos/{photoId}', [ConditionController::class, 'deletePhoto'])->where(['id' => '[0-9]+', 'photoId' => '[0-9]+'])->middleware('api.auth:delete');

    // Heritage Assets & Valuations
    Route::get('assets', [AssetController::class, 'index']);
    Route::post('assets', [AssetController::class, 'store'])->middleware('api.auth:write');
    Route::get('assets/{id}', [AssetController::class, 'show'])->where('id', '[0-9]+');
    Route::match(['put', 'patch'], 'assets/{id}', [AssetController::class, 'update'])->where('id', '[0-9]+')->middleware('api.auth:write');
    Route::get('descriptions/{slug}/asset', [AssetController::class, 'forDescription']);
    Route::get('valuations', [AssetController::class, 'valuations']);
    Route::post('valuations', [AssetController::class, 'storeValuation'])->middleware('api.auth:write');
    Route::get('assets/{id}/valuations', [AssetController::class, 'assetValuations'])->where('id', '[0-9]+');

    // Privacy / DSAR / Breaches
    Route::get('privacy/dsars', [PrivacyController::class, 'dsarIndex']);
    Route::post('privacy/dsars', [PrivacyController::class, 'dsarStore'])->middleware('api.auth:write');
    Route::get('privacy/dsars/{id}', [PrivacyController::class, 'dsarShow'])->where('id', '[0-9]+');
    Route::match(['put', 'patch'], 'privacy/dsars/{id}', [PrivacyController::class, 'dsarUpdate'])->where('id', '[0-9]+')->middleware('api.auth:write');
    Route::get('privacy/breaches', [PrivacyController::class, 'breachIndex']);
    Route::post('privacy/breaches', [PrivacyController::class, 'breachStore'])->middleware('api.auth:write');

    // Mobile Sync
    Route::get('sync/changes', [SyncController::class, 'changes']);
    Route::post('sync/batch', [SyncController::class, 'batch'])->middleware('api.auth:write');

    // Identifier API (ISBN/ISSN lookup, validation, barcode generation)
    Route::get('identifiers/lookup', [IdentifierController::class, 'lookup']);
    Route::get('identifiers/validate', [IdentifierController::class, 'validate']);
    Route::get('identifiers/detect', [IdentifierController::class, 'detect']);
    Route::get('identifiers/barcode/{objectId}', [IdentifierController::class, 'barcode'])->where('objectId', '[0-9]+');
    Route::get('identifiers/types/{objectId}', [IdentifierController::class, 'types'])->where('objectId', '[0-9]+');
    Route::get('identifiers/all/{objectId}', [IdentifierController::class, 'all'])->where('objectId', '[0-9]+');

    // Marketplace public API (issue #736)
    Route::get('marketplace/search',                        [V2MarketplaceController::class, 'search']);
    Route::post('marketplace/bid',                          [V2MarketplaceController::class, 'bid'])->middleware('api.auth:write');
    Route::get('marketplace/auction/{id}/status',           [V2MarketplaceController::class, 'auctionStatus'])->where('id', '[0-9]+');
    Route::post('marketplace/favourite',                    [V2MarketplaceController::class, 'favourite'])->middleware('api.auth:write');
    Route::get('marketplace/currencies',                    [V2MarketplaceController::class, 'currencies']);
    Route::get('marketplace/categories',                    [V2MarketplaceController::class, 'categories']);

    // Digital Object - embedded metadata standalone endpoint (issue #747)
    Route::get('digital-object/{id}/embedded-metadata', [V2DigitalObjectController::class, 'embeddedMetadata'])->where('id', '[0-9]+');

    // Spectrum public API (issue #737)
    Route::get('spectrum/statistics',                       [SpectrumApiController::class, 'statistics']);
    Route::get('spectrum/events',                           [SpectrumApiController::class, 'events']);
    Route::get('spectrum/activity/{objectId}',              [SpectrumApiController::class, 'activity'])->where('objectId', '[0-9]+');
});

/*
|--------------------------------------------------------------------------
| Legacy API Routes (for test compatibility)
|--------------------------------------------------------------------------
*/

Route::prefix('api')->middleware(['throttle:60,1', 'api.cors'])->group(function () {
    // Legacy routes for backward compatibility with tests
    Route::get('actor', [ActorApiController::class, 'index'])->name('api.actor.index');
    Route::get('actor/search', [ActorApiController::class, 'search'])->name('api.actor.search');
    Route::get('actor/{id}', [ActorApiController::class, 'show'])->name('api.actor.show');
    Route::post('actor', [ActorApiController::class, 'store'])->name('api.actor.store');
    Route::put('actor/{id}', [ActorApiController::class, 'update'])->name('api.actor.update');
    Route::delete('actor/{id}', [ActorApiController::class, 'destroy'])->name('api.actor.destroy');

    Route::get('term', [TaxonomyApiController::class, 'terms'])->name('api.term.index');
    Route::get('term/search', [TaxonomyApiController::class, 'search'])->name('api.term.search');
    Route::get('term/{id}', [TaxonomyApiController::class, 'show'])->name('api.term.show');
    Route::post('term', [TaxonomyApiController::class, 'store'])->name('api.term.store');
    Route::put('term/{id}', [TaxonomyApiController::class, 'update'])->name('api.term.update');
    Route::delete('term/{id}', [TaxonomyApiController::class, 'destroy'])->name('api.term.destroy');

    Route::get('records', [InformationObjectApiController::class, 'index'])->name('api.records.index');
    Route::get('records/search', [InformationObjectApiController::class, 'search'])->name('api.records.search');
    Route::get('records/{slug}', [InformationObjectApiController::class, 'show'])->name('api.records.show');
    Route::get('records/{slug}/children', [InformationObjectApiController::class, 'children'])->name('api.records.children');
    Route::post('records', [InformationObjectApiController::class, 'store'])->name('api.records.store');
    Route::put('records/{slug}', [InformationObjectApiController::class, 'update'])->name('api.records.update');
    Route::delete('records/{slug}', [InformationObjectApiController::class, 'destroy'])->name('api.records.destroy');

    Route::match(['get', 'post'], 'search/io', [LegacyApiController::class, 'searchIo']);
    Route::match(['get', 'post'], 'autocomplete/glam', [LegacyApiController::class, 'autocompleteGlam']);
    Route::get('export-preview', [LegacyApiController::class, 'exportPreview']);
    Route::get('reports/pending-counts', [LegacyApiController::class, 'pendingCounts']);
});

/*
|--------------------------------------------------------------------------
| API 404 Fallback — catch unmatched /api/* requests
|--------------------------------------------------------------------------
*/

Route::fallback(function (\Illuminate\Http\Request $request) {
    if ($request->is('api/*')) {
        return response()->json([
            'success' => false,
            'error' => 'Not Found',
            'message' => 'API endpoint not found: /'.$request->path(),
            'timestamp' => now()->toIso8601String(),
        ], 404);
    }
    // Route::fallback() is global, not api-scoped. Without this, any non-api
    // unmatched URL fell into the closure's null return and rendered as
    // empty 200 (issue #41 — /admin/typo, /admin/dashboard, etc.).
    abort(404);
})->middleware('api.cors');
