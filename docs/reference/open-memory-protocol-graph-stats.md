# Open Memory Protocol: graph statistics surface

Summary: Heratio's Open Memory Protocol (north-star #1204) has a public "graph at a glance" statistics surface at `/data/stats`. It publishes cheap aggregate COUNTs that describe the SIZE and SHAPE of the published open-data graph - records, people / organisations, subjects / places / genres, relation edges, descriptive coverage and holding repositories - content-negotiated to an HTML dashboard, VoID-aligned JSON-LD, or plain JSON. It lives in the `ahg-api` package (not locked), is read-only (COUNT / GROUP BY only, no writes, no ALTER), CORS-open, and never 500s. No hardcoded host: every URI is built from `url()` / `route()`.

## Why a stats surface in addition to the catalogue and protocol

- `/open-data/protocol` (ProtocolController) is the machine INDEX of every open surface.
- `/data/catalog` (CatalogController) re-describes that same offering as W3C DCAT.
- `/data/stats` answers a different question: "how big and what shape is the open graph?" It is the human-facing "graph at a glance" plus a machine VoID-aligned figure set. The `/.well-known/void` Turtle already carries some headline dataset stats; this surface expands them into a browsable dashboard and a richer JSON/JSON-LD figure set.

## Routes (packages/ahg-api/routes/api.php)

Registered at the ROOT (no group prefix), under `throttle:120,1` + `api.cors`, with an `OPTIONS` preflight:

- `GET /data/stats` (name `open-data.stats`) - content-negotiated.
- `GET /data/stats.json` (name `open-data.stats.json`) - forces plain JSON (CORS-open).

Registered BEFORE the generic two-segment `/data/{slug}` record-entity wildcard, so the literal "stats" binds as the statistics surface, never as a record slug - exactly mirroring the proven `/data/catalog` placement. The `.json` form contains a dot, which the slug grammar (`[A-Za-z0-9\-_]`, no dot) already excludes.

## Catch-all safety

`/data/stats` and `/data/stats.json` are TWO-segment paths, so the single-segment `/{slug}` archival-record catch-all (constraint `[a-z0-9][a-z0-9-]*$`, one segment, no slash) can never capture them. Verified against the live route table: `data/catalog`, `data/actor/{slug}`, `data/term/{slug}` already coexist with `data/{slug}` by the same literal-before-wildcard rule.

## StatsController (src/Controllers/StatsController.php)

Content negotiation (`negotiate()`): the `.json` route forces JSON; `?format=json|jsonld|html` overrides the header; otherwise `Accept: application/ld+json` -> JSON-LD, `application/json` -> JSON, `text/html` (a browser) -> the HTML dashboard. A bare `*/*` curl gets plain JSON.

All figures are cheap aggregates - COUNT / GROUP BY only, no per-record loop. Each aggregate is independently `Schema::hasTable`-guarded and `try`-wrapped, so a missing table or schema variance yields a zero, never a 500. An empty corpus yields a valid all-zero document and an empty-state dashboard.

### The figures (StatsController::compute())

- `published_records` - the shared open gate: `information_object` joined to a Published status row (`status.type_id=158`, `status_id=160`), synthetic root id=1 excluded.
- `records_by_level` - published records grouped by `level_of_description_id`; the label is resolved per distinct level id (a tiny set), not per record.
- `actors_by_kind` - one grouped query over `actor.entity_type_id`: person (132), corporate body (131), family (133), other.
- `terms_by_kind` - one grouped query over `term.taxonomy_id`: subject (35), place (42), genre (78).
- `relation_edges_total` - `COUNT(*)` of the generic `relation` link table.
- `relation_record_to_record` - relation rows whose subject AND object are both `information_object`s (the associative cross-references that knit collections together; the parent/child hierarchy lives on `information_object.parent_id`, so these are non-hierarchical links).
- `records_with_uri` - published records that have a `slug` row (so `/id/{slug}` dereferences).
- `records_with_dates` / `records_with_creator` / `records_with_subject` - distinct published records with at least one `event` row / an `event.actor_id` / an `object_term_relation` row.
- `repositories` - distinct `information_object.repository_id` among published records.
- `triple_estimate` - a deliberately conservative order-of-magnitude figure for the VoID `void:triples` slot (records*8 + actors*5 + terms*4 + relation rows + object_term_relation rows). Labelled an estimate everywhere it surfaces; never claimed as an exact statement count.

### VoID alignment (the JSON-LD representation)

`Accept: application/ld+json` returns a `void:Dataset` / `dcat:Dataset`:

- `void:entities` = published records.
- `void:triples` = the triple estimate.
- `void:classPartition` = one entry per level, `void:class` mapped to a schema.org class (`schema:Collection` for collection/fonds, `schema:CreativeWork` for item, else `schema:ArchiveComponent` - the same mapping GraphController / DatasetController use, so the open surfaces stay consistent), with `void:entities` and `rdfs:label`.
- The extra shape figures (actors, terms, edges, coverage, repositories) ride as `schema:additionalProperty` name/value pairs (descriptive, not a misuse of VoID's exact-triple semantics).
- `rdfs:seeAlso` links to the catalogue, the protocol and the VoID description.

## HTML dashboard (resources/views/stats/dashboard.blade.php)

The human "graph at a glance": big-number stat cards plus plain CSS bars (NO charting library) for records-by-level, actor kinds, term kinds and descriptive-coverage percentages. Self-contained inline CSS so it renders without the app theme bundle. Empty-state copy when the corpus is empty. Links out to `/graph-explorer`, `/data/catalog`, `/open-data/protocol`, `/.well-known/void` and `/data/stats.json`.

## How it stays in sync with the protocol

`StatsController` is added to `ProtocolController::surfaces()` (the single source-of-truth list of open surfaces), so it automatically appears in both the capabilities document AND the DCAT catalogue - one list, two views. Links out of the stats surface are resolved via `Route::has()` + a literal fallback, so a slimmer install drops a dead link rather than emitting one.

## Read-only / safety guarantees

- No writes, no ALTER, no INSERT/UPDATE/DELETE anywhere. Only COUNT / GROUP BY SELECTs.
- Every query is `Schema::hasTable`-guarded and `try`-wrapped; the surface degrades to zeros, never a 500.
- Permissive open-data CORS (`Access-Control-Allow-Origin: *`, `Vary: Accept`, `X-Open-Data: true`).
- Every URI from `url()` / `route()`; no hardcoded host; jurisdiction-neutral.

Shipped as an additive slice of #1204 (epic remains OPEN).
