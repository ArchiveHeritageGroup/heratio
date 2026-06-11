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

## Entity endpoints for ACTORS and TERMS (next slice)

The entity identity surface now covers the ENTITIES records are about, not just records. Two new controllers in `ahg-api` follow the EntityController pattern exactly (same content negotiation, same `GraphSerializerService` reuse, same CORS, same resilient guards, same `url()`-based URIs).

### Routes (three segments, catch-all-safe)

- `GET /id/actor/{slug}` (`open-data.entity.actor`) and alias `GET /data/actor/{slug}` (`open-data.entity.actor.data`).
- `GET /id/term/{slug}` (`open-data.entity.term`) and alias `GET /data/term/{slug}` (`open-data.entity.term.data`).

These are THREE-segment paths, so neither the single-segment `/{slug}` archival-record catch-all nor the two-segment `/id/{slug}` record entity endpoint can capture them. They are declared FIRST in the same route group (before `/id/{slug}`), so the literal `actor` / `term` second segment binds before the generic `/id/{slug}` wildcard is consulted. Same `throttle:120,1` + `api.cors` + `OPTIONS` preflight.

### ActorEntityController (src/Controllers/ActorEntityController.php)

- Describes a person / corporate body / family. Actors are reference entities, so the actor row has NO publication gate - but every record it links out to is filtered through the same published-only gate (status type_id=158, status_id=160), so a draft is never disclosed.
- Loads from `slug` -> `actor` -> `actor_i18n` (culture-aware), excluding the synthetic root (id=1) and any row that is also a `repository` (a sibling actor subtype with its own ISDIAH surface).
- Type mapping by `actor.entity_type_id`: 131 Corporate body -> `schema:Organization` + `rico:CorporateBody`; 132 Person -> `schema:Person` + `rico:Person`; 133 Family -> `schema:Person` (no native schema.org family class) + `rico:Family`; NULL/other -> `schema:Thing` + `rico:Agent` (the RiC `additionalType` carries the precise distinction).
- Fields: name (`authorized_form_of_name` -> schema:name), additionalType (RiC), dates of existence (`actor_i18n.dates_of_existence` -> schema:temporalCoverage), biography / administrative history (`actor_i18n.history`, HTML-stripped -> schema:description), related PUBLISHED records (`event.actor_id` creator links + generic `relation` table on either side -> dcterms:relation, each a dereferenceable `/id/{slug}` record URI, capped at 200), rdfs:seeAlso (authority page + RiC agent JSON-LD export `/api/ric/v1/agents/{slug}`), schema:sameAs (the `/actor/{slug}` human authority page). HTML request -> 303 to `/actor/{slug}`.

### TermEntityController (src/Controllers/TermEntityController.php)

- Describes a place / subject / genre term as a `skos:Concept`. A place (taxonomy 42) is ALSO typed `schema:Place` so a schema.org consumer recognises the geography. Terms are reference entities (no publication gate); related records still go through the published-only gate.
- Loads from `slug` -> `term` -> `term_i18n` (culture-aware), root excluded.
- Fields: label (`term_i18n.name` -> schema:name + skos:prefLabel), `skos:broader` (term.parent_id, when a non-root term with a slug), `skos:narrower` (child terms with a slug, capped at 200), related PUBLISHED records (`object_term_relation` -> dcterms:relation, each a `/id/{slug}` record URI, capped at 200), rdfs:seeAlso + schema:sameAs to the GLAM browse filtered by this term (`/glam/browse?{subject|place|genre}={term_id}`, the facet param chosen by taxonomy: 42 place, 78 genre, else subject). HTML request -> 303 to that filtered browse page.

### Vocabulary additions in GraphSerializerService

To render the new predicates in Turtle / RDF-XML (the serializer is the single source - it ignores per-controller context for RDF and reads its own `context()`/`namespaces()`), the shared service gained: the `skos` namespace (`http://www.w3.org/2004/02/skos/core#`) plus `rdfs`; and the entity-description predicate terms `temporalCoverage`, `seeAlso`, `relation`, `creator`, `subject`, `spatial`, `publisher`, `isPartOf`, `prefLabel`, `broader`, `narrower`. This also closes a latent gap where EntityController's own seeAlso/creator/etc. were only appearing in JSON-LD, not in Turtle / RDF-XML.

### Known pitfall fixed during build

MySQL `ONLY_FULL_GROUP_BY`: a `->distinct()->orderBy('io.id')` while selecting only `s.slug` throws (order column not in the select list); the controller's try/catch swallowed it and silently dropped `dcterms:relation`. Fixed by ordering the term related-records query on the selected column (`s.slug`).

### Protocol doc update

ProtocolController now indexes three entity surfaces: `entity` (record), `entity-actor`, `entity-term`, each with its `urlTemplate` / `aliasTemplate` and the four media types. The header comment lists all three.

### Validation (actor + term slice)

- `php -l` clean on both new controllers, the serializer, ProtocolController, and the routes file.
- Live-app harness (boots the real app, loads the worktree classes, exercises `show()`): actor Person/Corporate/Family typing correct; actor `dcterms:relation` resolves to published record URIs; term renders `a skos:Concept` (proper CURIE, no `<skos:Concept>` bug), `schema:Place`, `skos:broader`/`skos:narrower` to term URIs, `dcterms:relation` to published records; JSON-LD parses; RDF/XML well-formed (SimpleXML); negotiated 404 in every media type; HTML -> 303.

## Notes

- nginx's static-file whitelist does not include `.ttl`/`.jsonld`/`.rdf`/these paths, so the requests reach Laravel.
- Jurisdiction-neutral: schema.org / RiC / CIDOC-CRM / SKOS / Dublin Core vocabularies, no market-specific assumptions.
