# People and organisations: public creator-grouping discovery surface (creator slice)

A discovery slice that completes the set alongside **Explore by theme** (subjects)
and **Browse by place** (geography). Where "Explore by theme" groups the published
collection by what its records are *about* (subjects, taxonomy `35`) and "Browse by
place" groups them by the *places* they are about (place access points, taxonomy
`42`), "People and organisations" groups the same holdings by the *people and
organisations that created them* (the actors the `event` table credits as
creators). All three are "ways into the collection" that let a visitor start from a
facet rather than a search box.

A creator is simply an actor - a person or an organisation - credited with making a
record. The actors credited across the most PUBLISHED records are the collection's
busiest creators. The surface is a cheap, read-only aggregate over the existing
entity tables - it adds NO table, never writes, never ALTERs, and touches no locked
path. No person or organisation is hardcoded and there is no country default; the
names come entirely from the data.

Lives entirely in `packages/ahg-semantic-search` (alongside Explore-by-theme,
Browse-by-place, Discoveries, Research Leads, displaced-heritage,
endangered-heritage, language-corpus, related-records, timeline). It is additive:
it does NOT touch the `/themes`, `/related`, `/timeline` or `/places` files.

## What it does

1. **Landing (`GET /people`).** One cheap bounded `GROUP BY` aggregate over `event`
   joined to `actor`, gated to published records, ordered by published-record
   count, capped at 120 (default 80 shown). Rendered as a frequency-sized cloud
   (each chip scales by its share of the busiest creator) and a ranked list, each
   creator carrying its count.
2. **One creator (`GET /people/{actorId}`).** The creator's authorized form of
   name, optional dates of existence and history, total published-record count, and
   a paginated, bounded list of the published records they created - each linking to
   the record. A "browse all by this creator" link drops into the canonical GLAM
   browse with the creator facet applied. `{actorId}` is numeric and must resolve to
   an existing actor with at least one published record; otherwise it redirects to
   the landing.
3. **Machine list (`GET /people.json`).** CORS-open, cacheable (`max-age=300`) JSON
   of the creator list: `actor_id`, `name`, `record_count`, `url`, `browse_url`.

## Creator linkage (verified against the live catalogue)

A record's creator is recorded in the `event` table: an `event` row carries
`object_id` + `actor_id`, and a row whose `actor_id` is set links that record to its
creator actor. The creator's name is `actor_i18n.authorized_form_of_name`
(preferring the active culture). This is the same join the public v1 API uses for
`dcterms:creator` (`EntityController::creators`) and the same `event.actor_id` the
GLAM browse matches on for its `creator=` facet (`DisplayController`). The creation
event type is term id `111` ("Creation", DESCRIBE-verified); like
`EntityController::creators` the aggregate counts every actor-bearing event (so
co-creation / production credits are not silently dropped), while the published gate
keeps the surface to released holdings only.

## Aggregation (cheap, bounded, read-only)

The landing is a single `GROUP BY e.actor_id` with `COUNT(DISTINCT e.object_id)`
over `event`, joined to `actor` on `a.id = e.actor_id`, with the publication gate
applied via an `EXISTS` sub-select on `status` (no wide join), ordered by count and
`LIMIT`ed. The per-creator record list is paginated with `offset`/`limit` (default
24/page, capped at 60) so a prolific creator can never run an unbounded query. There
are NO per-record loops over the collection: the per-creator page runs one small
bounded query, then a single batched title/slug hydrate. There is no full-catalogue
PHP scan anywhere.

## Published gate

Mirrors the rest of Heratio: an item is "published" when its row in the `status`
table (`type_id = 158`) carries `status_id = 160`; the catalogue root (`id = 1`) is
excluded everywhere. Every count and record-page applies the gate, so unpublished
and root rows never surface.

## Where it lives (packages/ahg-semantic-search)

- `src/Services/PersonService.php` - the read-only aggregate. `topCreators()`,
  `creatorList()`, `creator()` (paginated), plus bounded helpers (`aggregate()`,
  `publishedRecordsPage()`, `hydrateRecords()`, `actorNames()`, `actorDetail()`,
  `creatorUrl()`, `browseUrl()`). Every path is `Schema::hasTable`-guarded and
  try/catch-wrapped; no writes, no AI.
- `src/Controllers/PeopleController.php` - public `index`, `show` (paginated), and
  `json` (CORS-open). Never 500s - degrades to the empty-state / redirect.
- `resources/views/people/{index,show}.blade.php` - Bootstrap 5 + central theme
  (`theme::layouts.1col`), frequency-sized cloud + ranked list, empty-states
  throughout, manual bounded pagination.

No new table, no install SQL, no `boot()` table creation: it reads the existing
`event` / `actor` / `actor_i18n` / `information_object_i18n` / `slug` / `status`
tables only.

## Routes (catch-all-safe)

Registered in `AhgSemanticSearchServiceProvider::register()` via
`callAfterResolving('router')`, exactly like `/places`:

- `/people.json` (`people.json`) is declared FIRST - dotted, so a record slug that
  literally ends in ".json" can never be swallowed by the HTML index route.
- `/people` (`people.index`) is a single-segment path bound before the
  single-segment `/{slug}` archival-record catch-all in
  `ahg-information-object-manage`. (`register()` runs for all providers before any
  `boot()`, so this route wins the match. See
  `reference_slug_catchall_route_precedence`.)
- `/people/{actorId}` (`people.show`) is a two-segment path with a numeric
  `{actorId}` constraint, so it can never shadow the `/{slug}` catch-all; bound the
  same way for the same precedence guarantee.

Verified live: on the running router `/places` resolves to `places.index` (not the
`{slug}` catch-all) thanks to this exact registration order; `/people` mirrors it
one-for-one. A normal record slug (for example `openric-demo-leroux-journals`) still
resolves through the `{slug}` catch-all, so the catch-all is not shadowed.

## Reuse

The per-creator "browse all by this creator" link and the `.json` `browse_url` both
point at the single canonical GLAM browse (`ahg-display`) with its existing
`creator=<actor_id>` filter (the same actor id the browse matches on
`event.actor_id`) - no new browse page, no duplicated facet logic. Record links use
`url('/'.$slug)` to the existing archival-record show page.

## Constraints honoured

Read-only; no writes; no ALTER; NO new table (cheap on-the-fly COUNTs only);
catch-all-safe single-segment registration; published-only; root excluded;
`url()`-relative (no hardcoded host); Bootstrap 5 + central theme; empty-states
everywhere; never 500s; Plain Sailing / AGPL headers; international and
jurisdiction-neutral (no hardcoded person or organisation, no country default). Does
not touch the `/themes`, `/related`, `/timeline` or `/places` files.
