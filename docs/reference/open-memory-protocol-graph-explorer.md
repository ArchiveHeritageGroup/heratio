# Open Memory Protocol: public graph explorer

Summary: Heratio's Open Memory Protocol (north-star #1204) now has a public, human-friendly GRAPH EXPLORER. The `/id/...` entity endpoints already let MACHINES dereference and crawl the open linked-data graph; the explorer lets a HUMAN walk the same graph in a browser, hop by hop, following the connections between records, actors and terms. It lives in the `ahg-api` package, is read-only, exposes published records only, and builds every link from `url()` (no hardcoded host). It does NOT touch the locked ahg-ric RiC graph explorer or the archival-record show tree.

## Routes (packages/ahg-api/routes/api.php)

- `GET /graph-explorer` (name `graph-explorer.index`) - landing page: a search box plus a few high-degree starting entities, so a first-time visitor always has a way in. `?q=` runs a bounded multi-type search (records by title, actors by authorised name, terms by preferred label).
- `GET /graph-explorer/{type}/{slug}` (name `graph-explorer.show`) - ONE entity rendered as a human page. `{type}` is constrained to `record|actor|term`; `{slug}` to `[A-Za-z0-9][A-Za-z0-9\-_]*`.

Both run under `throttle:120,1` + `api.cors`.

## Catch-all safety

- `/graph-explorer/{type}/{slug}` is a THREE-segment path, so the single-segment `/{slug}` archival-record catch-all (in `ahg-information-object-manage`, constraint `[a-z0-9][a-z0-9-]*$`, one segment) can never capture it.
- The bare `/graph-explorer` IS a single segment, but `ahg/api` is discovered before `ahg/information-object-manage` (alphabetical package order in `bootstrap/cache/packages.php`), so its route registers first and wins the match (first-registered route wins). This is the same idiom as `/open-data`, `/explore` and `/collection-overview` in ahg-core. No edit to the (locked) catch-all exclusion list is needed.
- Verified with a route-dispatch probe: `/graph-explorer` and the three-segment entity paths resolve to the explorer; a bad `{type}` falls through (404); a bare single-segment slug still resolves to the catch-all.

## GraphExplorerController (src/Controllers/GraphExplorerController.php)

A thin presentation layer over `GraphExplorerService`. `index()` renders the landing (search results or seed list); `show()` resolves the entity, attaches a navigable explorer URL to every connection, and computes two out-links:

- `machine_url` - the `/id/...` linked-data document (via the existing named route `open-data.entity` / `open-data.entity.actor` / `open-data.entity.term`, with a literal `url()` fallback).
- `authority_url` - the canonical human page: `/{slug}` for a record, `/actor/{slug}` for an actor, or the GLAM browse filtered by the term (`/glam/browse?subject|place|genre={id}`) for a term (which has no standalone authority page).

An unknown `{type}`, or an unknown / unpublished / mistyped slug, yields a clean themed 404 (it does not say WHY, so a draft is never disclosed). Never 500s.

## GraphExplorerService (src/Services/GraphExplorerService.php)

The read-only data layer. It deliberately MIRRORS the exact fetch + gating logic of the three entity controllers so the human explorer can never drift from the `/id/...` linked-data output:

- Same publication gate as the rest of the public v1 API: `status.type_id=158`, `status_id=160` (Published); synthetic root `id=1` excluded.
- Same culture join, same access-point taxonomies (35 subject, 42 place, 78 genre), same actor `entity_type` ids (131 corporate body, 132 person, 133 family), same repository exclusion from the actor surface.
- `record($slug)` mirrors `EntityController::loadNode()` + creators/terms/publisher/parent, and adds published child records (the hierarchy below) as `Contains` edges. Groups: People and organisations, Subjects, Places, Repository, Related records.
- `actor($slug)` mirrors `ActorEntityController` (event table + generic `relation` table, published-only). Group: Records.
- `term($slug)` mirrors `TermEntityController` (skos broader / narrower + `object_term_relation`, published-only). Groups: Broader term, Narrower terms, Records.
- `startingPoints()` returns high-degree published records (ordered by `object_term_relation` count) to seed the landing.
- `search()` is a bounded `LIKE` over record titles, actor names and term labels (records published-only).

Each connection is a `['label','type','slug']` triple; the controller turns the slug into a `/graph-explorer/{type}/{slug}` URL. A connection without a slug is kept as a non-clickable label (honest, never a broken link). Every query is guarded (`Schema::hasTable` + try/catch) and bounded (`MAX_PER_GROUP=120`), so a schema variance yields a thinner page and a high-degree node cannot run away. No writes, no DDL.

## Views (resources/views/graph-explorer/)

`index.blade.php` (landing), `show.blade.php` (one entity + grouped clickable connections + out-links), `not-found.blade.php` (themed 404). All extend `theme::layouts.1col` (Bootstrap 5 + central theme), with `__()`-wrapped strings, friendly empty-states ("No connections recorded for this entity yet"), and FontAwesome icons.

## Verification

- `php -l` clean on the controller and service; all three blades compile via the real `BladeCompiler`.
- Route-dispatch probe: registration + ordering + catch-all immunity all pass.
- Live read-only DB exercise (as www-data): real grouped connections, the published gate, high-degree starting points, clean `null` for unknown / wrong-type slugs, working multi-type search, no exceptions.
- Direct controller invocation against the booted app: landing, search, and all three entity types render HTTP 200 with self-links and machine links; unknown slug and bad type both render a clean 404; high-degree `ai-test-19` (68 connections) renders without 500 or runaway. `./bin/check-locked` exits 0.

## Constraints honoured

International / jurisdiction-neutral; AHG / Plain Sailing / AGPL headers; `@copyright` "Plain Sailing Information Systems"; no em-dashes. Additive new files only; the `/id/...` entity controllers and the locked RiC / IO show tree are untouched.
