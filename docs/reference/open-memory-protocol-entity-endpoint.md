# Open Memory Protocol: entity endpoint + capabilities document

Summary: Heratio's Open Memory Protocol (north-star #1204) now has a content-negotiated per-entity linked-data endpoint and a machine-discoverable capabilities document. Both live in the `ahg-api` package, are read-only, expose published records only, and are CORS-open. No hardcoded host: every URI is built from `url()`.

## Routes (packages/ahg-api/routes/api.php)

Registered at the ROOT (no group prefix), so they sit alongside `/.well-known/void`, `/sitemap.xml`, and `/feed.atom`:

- `GET /id/{slug}` (name `open-data.entity`) and alias `GET /data/{slug}` (`open-data.entity.data`) - the content-negotiated entity description. Slug constraint `[A-Za-z0-9][A-Za-z0-9\-_]*`.
- `GET /open-data/protocol` (`open-data.protocol`) - capabilities document, content-negotiated (browser HTML vs JSON).
- `GET /open-data/protocol.json` (`open-data.protocol.json`) - JSON capabilities, always.

Each route group has an `OPTIONS` preflight and runs under `throttle:120,1` + `api.cors`.

## Catch-all safety

The single-segment `/{slug}` archival-record catch-all (in `ahg-information-object-manage`, constraint `[a-z0-9][a-z0-9-]*$`, one segment, no slash) can never capture a two-segment path. `/id/{slug}`, `/data/{slug}`, `/open-data/protocol[.json]` are all two segments, so they bind regardless of package load order. Verified with a route-dispatch probe: a bare single-segment slug still resolves to the catch-all; the two-segment paths resolve to the new routes.

## EntityController (src/Controllers/EntityController.php)

- Content negotiation by `Accept` header (with `?format=` override): `application/ld+json` -> JSON-LD (machine default), `text/turtle` -> Turtle, `application/rdf+xml` -> RDF/XML, `text/html` (browser) -> 303 See Other to the canonical `/{slug}` record page (httpRange-14 pattern). A bare `*/*` (curl default) gets JSON-LD.
- Published gate identical to the rest of the public v1 API: `information_object` join `status` (type_id=158, status_id=160 = Published), synthetic root id=1 excluded, slug resolved from the `slug` table. Unknown or draft slug -> clean negotiated 404 (never 500).
- Entity `@id` is the `/id/{slug}` URI itself. Fields: title (schema:name), type (schema.org + RiC `additionalType`), identifier, description (scope_and_content, HTML-stripped), dateModified, dates (event display date / start-end span -> schema:temporalCoverage), creators (actors via the `event` table -> dcterms:creator), subjects (taxonomy 35 -> dcterms:subject), places (taxonomy 42 -> dcterms:spatial), repository (`repository`+`actor_i18n.authorized_form_of_name` -> dcterms:publisher), parent (information_object.parent_id, only if itself published -> dcterms:isPartOf), and rdfs:seeAlso back-links (record page, `/api/v1/graph/{id}`, `.ttl`). schema:sameAs to the human record page.
- Every enrichment query is wrapped in try/catch (plus `Schema::hasTable` on the main load), so a schema variance yields a thinner description, never an error.
- RDF serialisation reuses `GraphSerializerService` (the single source of the @context, namespace table, and Turtle/RDF-XML rendering), so the three formats can never drift.

## ProtocolController (src/Controllers/ProtocolController.php)

- Enumerates every open surface (VoID, graph dataset front door, per-entity graph, the new `/id/{slug}` entity endpoint, JSON-LD @context, crawl seed, dataset.csv/.jsonld, OAI-PMH, public + graph sitemaps, Atom/RSS feeds, OpenAPI spec + Swagger UI) with URL (or URL template) and media types.
- Each surface resolved via `Route::has()` + a literal path fallback, so a slimmer install drops a surface rather than dead-linking. No DB access at all -> cannot 500 over data.
- Content-negotiated: a browser gets a dependency-free HTML table; a data client (or `.json`) gets JSON. Both views are built from the same `document()` array.

## CORS

All three controllers apply `Access-Control-Allow-Origin: *`, `Access-Control-Allow-Methods: GET, OPTIONS`, `Access-Control-Allow-Headers: Accept, Content-Type`, `Vary: Accept`, and `X-Open-Data: true`.

## Validation performed

- `php -l` clean on both controllers + the routes file.
- Route-dispatch probe: all four routes bind to the right URIs + names; catch-all-safety confirmed.
- Serialisation probe against the real `GraphSerializerService`: JSON-LD parses, Turtle has prefixes + escaped literals, RDF/XML validates via DOMDocument, and the empty-graph case stays valid in all three.
- ProtocolController probe: JSON parses, every surface has media types + a URL/template, the entity surface is indexed, the HTML page parses.

## Notes

- nginx's static-file whitelist does not include `.ttl`/`.jsonld`/`.rdf`/these paths, so the requests reach Laravel.
- Jurisdiction-neutral: schema.org / RiC / CIDOC-CRM / Dublin Core vocabularies, no market-specific assumptions.
