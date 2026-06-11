# Open Memory Protocol: DCAT data catalogue

Summary: Heratio's Open Memory Protocol (north-star #1204) now has a machine-readable W3C DCAT (DCAT-AP aligned) data catalogue at `/data/catalog`. It re-describes the platform's whole open-data offering as a `dcat:Catalog` of `dcat:Dataset` entries, each with its `dcat:distribution` access URLs and media types. It lives in the `ahg-api` package, is read-only (no DB access), CORS-open, and never 500s. No hardcoded host: every URI is built from `url()`.

## Why DCAT in addition to the capabilities document

`/open-data/protocol` (ProtocolController) is a bespoke "capabilities" document - good for an agent that learns this one shape. `/data/catalog` says the SAME thing in the open-data world's lingua franca (DCAT / DCAT-AP), so a generic data-portal harvester (CKAN, the European Data Portal, data.gov-style aggregators) can ingest the offering with no Heratio-specific knowledge.

## Routes (packages/ahg-api/routes/api.php)

Registered at the ROOT (no group prefix), under `throttle:120,1` + `api.cors`, with an `OPTIONS` preflight:

- `GET /data/catalog` (name `open-data.catalog`) - content-negotiated.
- `GET /data/catalog.{suffix}` (`open-data.catalog.suffixed`), `suffix` in `jsonld|ttl|rdf` - explicit format.

Registered BEFORE the generic two-segment `/data/{slug}` record-entity wildcard, so the literal "catalog" binds as the catalogue, never as a record slug. The dotted suffix forms contain a dot, which the slug grammar (`[A-Za-z0-9\-_]`, no dot) already excludes.

## Catch-all safety

`/data/catalog` (+ the dotted forms) are TWO-segment paths, so the single-segment `/{slug}` archival-record catch-all (constraint `[a-z0-9][a-z0-9-]*$`, one segment, no slash) can never capture them.

## CatalogController (src/Controllers/CatalogController.php)

- Content negotiation: path suffix wins, then `?format=`, then `Accept`. `application/ld+json`/`application/json` -> JSON-LD (machine default; a bare `*/*` curl gets JSON-LD), `text/turtle` -> Turtle, `application/rdf+xml` -> RDF/XML, `text/html` (browser, no suffix) -> a dependency-free human page. `?format=html` forces HTML.
- Read-only: NO database access, NO AI calls. It only resolves route URLs, so it cannot 500 over data. Every serialisation is well-formed even for an empty surface list.
- Permissive open-data CORS (`Access-Control-Allow-Origin: *`, `Vary: Accept`, `X-Open-Data: true`).

## DCAT structure (catalog -> datasets -> distributions)

- `dcat:Catalog` (`@id` = `{base}/data/catalog`): `dcterms:title`, `dcterms:description`, `dcterms:license` (CC-BY-4.0), `dcterms:publisher` (a `foaf:Agent` named from `config('app.name')`, homepage = this host), `dcterms:modified`, `dcat:landingPage` (`/open-data`), and one `dcat:dataset` link per surface.
- `dcat:Dataset` (one per open surface; `@id` = `{base}/data/catalog#dataset-{surfaceId}`): title, description, license, publisher, `dcat:landingPage`, and its `dcat:distribution` links.
- `dcat:Distribution` (`@id` = `{base}/data/catalog#dist-{hash}`, hash = first 12 of `sha1(accessUrl|mediaType)`): `dcat:mediaType`, `dcat:accessURL`, `dcat:downloadURL` (concrete surfaces only), `dcterms:license`. A surface that serves several media types yields several distributions.

### Template surfaces (per-entity endpoints)

A URL template like `/id/{slug}` is NOT a dereferenceable IRI (the `{}` braces are illegal in an IRIREF; a stripped `/id/slug` would be a misleading fake). For a template surface the distribution's `dcat:accessURL` points at `/open-data/protocol` (a real dereferenceable URL) and the literal template form is carried verbatim in `dcterms:description` ("URL template: ..."). No `dcat:downloadURL` is emitted for a template. The HTML view shows the template string with a "how to use" link to the protocol doc.

## How it stays in sync with ProtocolController

`ProtocolController::surfaces()` was extracted as a PUBLIC method holding the single source-of-truth list of open surfaces (the same array the capabilities document renders). `CatalogController` calls `app(ProtocolController::class)->surfaces()` and maps each surface to a `dcat:Dataset`. ONE list, TWO views - add or remove a surface in `surfaces()` and it appears/disappears in both the capabilities document and the DCAT catalogue automatically. `ProtocolController::document()` also now advertises `/data/catalog` as its `catalog` entry point (resolved via `Route::has('open-data.catalog')` + the `/data/catalog` literal fallback).

## Validation done

- `php -l` clean on CatalogController, ProtocolController, routes/api.php.
- A standalone harness (stubbing the Laravel helpers) exercised the controller: Turtle, RDF/XML, JSON-LD, and HTML all render; content-types are correct (`text/turtle`, `application/rdf+xml`, `application/ld+json`, `text/html`); a bare `*/*` curl gets JSON-LD; a `.ttl` suffix forces Turtle. RDF/XML parses well-formed via `DOMDocument::load`. JSON-LD parses via `json_decode` with `@type` = `dcat:Catalog`. Turtle escaping verified (string literals escape `"`/`\`; a datatyped `xsd:dateTime` literal; IRIs stripped of illegal chars). Template surfaces verified to carry the protocol URL as `accessURL` + the template in a description, with no `downloadURL`.
- `bin/check-locked` exits 0; only `packages/ahg-api/` + `docs/` touched.

## Constraints honoured

AGPL header with `@copyright Plain Sailing Information Systems`; no em-dashes; jurisdiction-neutral (publisher name, licence and base URI all from config / `url()`, never a tenant constant). Read-only; no DB writes; no ALTER. Epic #1204 stays OPEN.
