# Open Memory Protocol: developer cookbook

Summary: Heratio's Open Memory Protocol (north-star #1204) now has a public developer COOKBOOK at `/open-data/cookbook` (machine index `/open-data/cookbook.json`). It is a developer-facing guide of copy-paste WORKED EXAMPLES for consuming the open data - content negotiation against the `/id/{slug}` entity URIs, the bulk CSV / JSON-LD / CIDOC-CRM dumps, OAI-PMH harvesting, the discovery documents, and loading the data into common tools (rdflib, Apache Jena, a triple store) to run SPARQL locally. Every example URL is generated from `ProtocolController::surfaces()` (the ONE canonical surface list) via `url()` / `route()`, so the commands target this deployment's real URLs and an example whose surface is absent is simply omitted (never a dead link). It lives in the `ahg-api` package, is read-only (no DB access, no AI), CORS-open, and never 500s. No hardcoded host.

## Why a cookbook

`/open-data/protocol` is the machine-discoverable INDEX of the surfaces; `/data/catalog` re-describes them in DCAT; `/open-data/maturity` GRADES the offering. The cookbook TEACHES it: it turns the surface list into runnable recipes a developer can copy and execute, closing the gap between "here are the surfaces" and "here is how to actually consume them".

## Routes (packages/ahg-api/routes/api.php)

Registered under `throttle:120,1` + `api.cors`, with an `OPTIONS` preflight, in their own route group after the maturity block:

- `GET /open-data/cookbook` (name `open-data.cookbook`) - content-negotiated (browser -> HTML guide, everyone else -> JSON example index).
- `GET /open-data/cookbook.json` (name `open-data.cookbook.json`) - JSON, forced via the `index($request, true)` closure.

## Catch-all safety

`/open-data/cookbook` and `/open-data/cookbook.json` are TWO-segment paths, so the single-segment `/{slug}` archival-record catch-all (constraint `[a-z0-9][a-z0-9-]*$`, one segment, no slash) can never capture them. The dotted `.json` form additionally contains a dot, which the slug grammar already excludes. `/open-data` itself is a registered single-segment public page (ahg-core); these two-segment children sit underneath it.

## CookbookController (src/Controllers/CookbookController.php)

- Content negotiation: a browser (`Accept: text/html`) gets a dependency-free human guide (inline HTML, no Blade - this package has no public layout); everyone else (including a bare curl) and `/open-data/cookbook.json` get the JSON example index. The `wantsHtml()` heuristic treats `application/json` / `application/ld+json` as machine.
- Read-only: NO database access, NO AI calls. It only inspects the protocol surface list and resolves route URLs, so it cannot 500 over data.
- Permissive open-data CORS (`Access-Control-Allow-Origin: *`, `Vary: Accept`, `X-Open-Data: true`).
- JSON document is a `schema:TechArticle` with `license` CC-BY-4.0, an explicit `sparqlEndpoint: null` + `sparqlNote`, `count`, and a `recipeGroups` array. Each example is `{id, title, description, command, mediaType}`.

## Surface reuse (one source of truth)

The recipes are generated from `ProtocolController::surfaces()`, indexed by id (`surfacesById()`, defensively wrapped in try/catch -> empty on failure). The example URLs come from each surface's `url` or `urlTemplate`; a `{slug}` template is filled with an obvious placeholder (`an-example-record-slug`) so the curl lines target this host's real `/id/` path. An example whose surface id is not present is skipped, so the cookbook never teaches a surface the deployment does not serve and never dead-links. This means the cookbook and the protocol index share ONE source of truth and cannot drift.

## Recipe groups

| Group id | Surfaces used | Teaches |
|---|---|---|
| `content-negotiation` | `entity`, `entity-actor`, `entity-term` | `curl -H "Accept: ..."` for JSON-LD / Turtle / RDF-XML against a `/id/{slug}` URI, plus the `.ttl` path-suffix variant |
| `bulk-and-harvest` | `dataset-csv`, `dataset-jsonld`, `dataset-cidoc-crm`, `oai-pmh` | download the dumps; OAI-PMH Identify + ListRecords (resumptionToken, `from=` incremental) |
| `discovery` | `open-data.protocol` (route), `discovery`, `open-data.catalog` (route), `dataset-schema-org`, `sitemap-data`, `crawl-seed` | fetch the capabilities doc, VoID, DCAT, schema.org Dataset, crawl sitemap / seed |
| `load-and-query` | `dataset-cidoc-crm` (dump target) | rdflib parse + SPARQL, Jena `riot` validate/convert, Jena TDB2 load + SPARQL, local Fuseki server |
| `licence-attribution` | (none required) | CC-BY-4.0 terms, attribution, the open-CORS `curl -I` header check |

## Honest framing: no live SPARQL endpoint

There is NO hosted SPARQL endpoint. The document carries an explicit `sparqlEndpoint: null` and a `sparqlNote` that says so, and the `load-and-query` group shows the local path only: download a bulk RDF dump (CIDOC-CRM Turtle or JSON-LD) and load it into a triple store you run yourself (rdflib / Jena / Fuseki at `http://localhost:3030/...`), then query locally. The guide never implies a hosted SPARQL service exists.

## ProtocolController surface registration

A new surface `cookbook` (title "Developer cookbook (worked examples)", media types `text/html` + `application/json`, url from `open-data.cookbook`) was added to `ProtocolController::surfaces()` so the cookbook is itself discoverable from the protocol index and the DCAT catalogue.

## Verification

A standalone harness booted the app container, forced a known root URL, loaded the worktree controllers and exercised `index()`: JSON returns 200 with `application/json` + open CORS; 22 examples generated; `sparqlEndpoint` is null with an honest note; licence CC-BY-4.0; every entity URI resolves to the forced host (no hardcoded host anywhere in the output); the local Fuseki / rdflib / Jena recipes are present; HTML returns 200 and renders recipe sections; and `ProtocolController::surfaces()` now includes `cookbook`. `php -l` clean on all three touched files. Catch-all safety confirmed: both paths fail the single-segment slug grammar.
