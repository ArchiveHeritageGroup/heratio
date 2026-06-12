# Open Memory Protocol: Open Data Maturity scorecard

Summary: Heratio's Open Memory Protocol (north-star #1204) now has a public Open Data Maturity scorecard at `/open-data/maturity` (machine view `/open-data/maturity.json`). It grades the platform's open-data offering against Tim Berners-Lee's 5-star Open Data deployment scheme (https://5stardata.info/) and shows the concrete EVIDENCE for each star - the real surfaces that prove it - resolved from `ProtocolController::surfaces()` so the rating can never drift from what is actually served. It lives in the `ahg-api` package, is read-only (no DB access, no AI), CORS-open, and never 500s. No hardcoded host: every URI is built from `url()` / `route()`.

## Why a maturity scorecard

`/open-data/protocol` is the machine-discoverable INDEX of the surfaces. `/data/catalog` re-describes them in DCAT. The maturity scorecard GRADES the same offering against an external, widely-recognised model (the 5-star scheme), turning "we are open data" into a self-verifying claim: each star links the live surfaces that prove it, and a star is only marked achieved when its evidence surface actually resolves.

## Routes (packages/ahg-api/routes/api.php)

Registered under `throttle:120,1` + `api.cors`, with an `OPTIONS` preflight, in their own route group after the protocol block:

- `GET /open-data/maturity` (name `open-data.maturity`) - content-negotiated (browser -> HTML scorecard, everyone else -> JSON).
- `GET /open-data/maturity.json` (name `open-data.maturity.json`) - JSON, forced via the `index($request, true)` closure.

## Catch-all safety

`/open-data/maturity` and `/open-data/maturity.json` are TWO-segment paths, so the single-segment `/{slug}` archival-record catch-all (constraint `[a-z0-9][a-z0-9-]*$`, one segment, no slash) can never capture them. The dotted `.json` form additionally contains a dot, which the slug grammar already excludes. `/open-data` itself is a registered single-segment public page (ahg-core); these two-segment children sit underneath it.

## MaturityController (src/Controllers/MaturityController.php)

- Content negotiation: a browser (`Accept: text/html`) gets a dependency-free human scorecard; everyone else (including a bare curl) and `/open-data/maturity.json` get JSON. The `wantsHtml()` heuristic treats `application/json` / `application/ld+json` as machine.
- Read-only: NO database access, NO AI calls. It only inspects the protocol surface list and resolves route URLs, so it cannot 500 over data.
- Permissive open-data CORS (`Access-Control-Allow-Origin: *`, `Vary: Accept`, `X-Open-Data: true`).
- License constant CC-BY-4.0; model https://5stardata.info/. JSON document is a `schema:Rating` with `bestRating` 5 / `worstRating` 0, `ratingValue` = count of achieved stars, `ratingScale` "N/5", and a `stars` array.

## The five stars and their evidence surfaces

Each star declares a list of `ProtocolController` surface ids; the first that resolves to a URL is cited as evidence, and the star is achieved when at least one resolves. Reusing `surfaces()` (indexed by id, defensively wrapped in try/catch -> empty on failure) means the scorecard and the protocol index share ONE source of truth.

| Star | Requirement | Evidence surface ids |
|---|---|---|
| 1 - Open licence | data on the web under an open licence | `discovery`, `dataset-schema-org`, `graph-dataset` (+ the CC-BY-4.0 license field) |
| 2 - Machine-readable | structured machine-readable data | `dataset-csv`, `dataset-jsonld`, `dataset-schema-org` |
| 3 - Open format | non-proprietary open format | `graph-entity`, `graph-dataset`, `dataset-cidoc-crm`, `context` |
| 4 - URIs | URIs to denote things | `entity`, `entity-actor`, `entity-term` (the `/id/*` dereferenceable URIs) |
| 5 - Linked data | linked to other data | `discovery` (VoID linkset), `graph-entity` (sameAs/seeAlso), `dataset-cidoc-crm`, `entity` |

A full Heratio install scores 5/5. Honest degradation: if a star's evidence surfaces are all absent (or `ProtocolController` is unavailable), that star reports `achieved=false` and the total drops, rather than asserting a capability the deployment does not expose. Verified by harness: full install -> 5/5 all achieved; `surfaces()` throwing -> 0/5 all not-achieved; both JSON and HTML return 200.

## ProtocolController surface registration

A new surface `open-data-maturity` (title "Open data maturity scorecard", media types `text/html` + `application/json`, url from `open-data.maturity`) was added to `ProtocolController::surfaces()` so the scorecard is itself discoverable from the protocol index and the DCAT catalogue.
