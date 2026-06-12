# Explore by theme: public subject-grouping discovery surface (North Star #1210)

A discovery slice of the Heratio "North Star" vision (GitHub issue #1210). Where
**Discoveries** and **Research Leads** surface AI-found *connections* between
records, "Explore by theme" is the complementary *grouping* surface: it offers
the collection's strongest subjects as "ways into the collection", so a visitor
can start from a theme rather than a search box.

A theme is simply a strong subject: the subject terms (taxonomy `35`) under which
the most PUBLISHED records sit are the de-facto themes. The surface is a cheap,
read-only aggregate over the existing taxonomy - it adds NO table, never writes,
never ALTERs, and touches no locked path.

Lives entirely in `packages/ahg-semantic-search` (alongside Discoveries,
Research Leads, displaced-heritage, endangered-heritage, language-corpus).

## What it does

1. **Landing (`GET /themes`).** One cheap bounded `GROUP BY` aggregate over
   `object_term_relation` joined to `term` (taxonomy `35`), gated to published
   records, ordered by published-record count, capped at 60. Each theme is a card
   with its count and a few example published records.
2. **One theme (`GET /themes/{termId}`).** The subject's label, optional scope
   note, total published-record count, and a paginated, bounded list of the
   published records filed under it - each linking to the record. A "browse all in
   this theme" link drops into the canonical GLAM browse with the subject facet
   applied. `{termId}` is numeric and must resolve to a subject term (taxonomy
   35); a place / genre / root id is rejected and redirects to the landing.
3. **Machine list (`GET /themes.json`).** CORS-open, cacheable (`max-age=300`)
   JSON of the theme list: `id`, `label`, `record_count`, `url`, `browse_url`.

## Aggregation (cheap, bounded, read-only)

The landing is a single `GROUP BY otr.term_id` with `COUNT(DISTINCT otr.object_id)`
over `object_term_relation`, joined to `term` on `taxonomy_id = 35`, with the
publication gate applied via an `EXISTS` sub-select on `status` (no wide join),
ordered by count and `LIMIT`ed. The per-theme record list is paginated with
`offset`/`limit` (default 24/page, capped at 60) so a busy subject can never run
an unbounded query. There are NO per-record loops over the collection: example
records and the per-theme page each run one small bounded query, then a single
batched title/slug hydrate.

## Published gate

Mirrors the rest of Heratio: an item is "published" when its row in the `status`
table (`type_id = 158`) carries `status_id = 160`; the catalogue root (`id = 1`)
is excluded everywhere. Every count, example and record-page applies the gate, so
unpublished and root rows never surface.

## Where it lives (packages/ahg-semantic-search)

- `src/Services/ThemeService.php` - the read-only aggregate. `topThemes()`,
  `themeList()`, `theme()` (paginated), plus bounded helpers (`exampleRecords()`,
  `publishedRecordsPage()`, `hydrateRecords()`, `termLabels()`, `termNote()`,
  `browseUrl()`). Every path is `Schema::hasTable`-guarded and try/catch-wrapped;
  no writes, no AI.
- `src/Controllers/ThemesController.php` - public `index`, `show` (paginated),
  and `json` (CORS-open). Never 500s - degrades to the empty-state / redirect.
- `resources/views/themes/{index,show}.blade.php` - Bootstrap 5 + central theme
  (`theme::layouts.1col`), empty-states throughout, manual bounded pagination.

No new table, no install SQL, no `boot()` table creation: it reads the existing
`object_term_relation` / `term` / `term_i18n` / `status` tables only.

## Routes (catch-all-safe)

- PUBLIC: `/themes` (`themes.index`) and `/themes.json` (`themes.json`) are
  single-segment paths bound in the provider's `register()` via
  `callAfterResolving('router')` so they bind BEFORE the single-segment `/{slug}`
  archival-record catch-all in `ahg-information-object-manage` - the same proven
  precedence trick as `/discoveries`, `/research-leads` and `/at-risk`.
  (`register()` runs for all providers before any `boot()`, so these win the
  match. See `reference_slug_catchall_route_precedence`.)
- `/themes/{termId}` (`themes.show`) is a two-segment path with a numeric
  `{termId}` constraint, so it can never shadow the `/{slug}` catch-all; bound the
  same way for the same precedence guarantee.

## Reuse

The per-theme "browse all in this theme" link and the `.json` `browse_url` both
point at the single canonical GLAM browse (`ahg-display`) with its existing
`subject=<term_id>` filter - no new browse page, no duplicated facet logic. Record
links use `url('/'.$slug)` to the existing archival-record show page.

## Constraints honoured

Read-only; no writes; no ALTER; NO new table (cheap on-the-fly COUNTs only);
catch-all-safe single-segment registration; published-only; root excluded;
`url()`-relative (no hardcoded host); Bootstrap 5 + central theme; empty-states
everywhere; never 500s; Plain Sailing / AGPL headers; international and
jurisdiction-neutral. Epic #1210 stays OPEN.
