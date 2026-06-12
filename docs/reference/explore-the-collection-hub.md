# Explore the collection: public browse-by discovery hub

A public hub at `/explore-collection` that gathers the browse-by discovery
surfaces this package already ships - **Explore by theme**, **Browse by place**,
**People and organisations**, and the **Collection timeline** - into one coherent
entry point, with a small live teaser from each and an onward link to the full
slice page.

It is a HUB, not a new surface: it REUSES the existing read-only slice services
for the teasers and edits none of the slice files. It also COMPLEMENTS (does not
duplicate or edit) the existing `/explore` capability hub in `ahg-core`
(`explore.index`, `ExploreController`): `/explore` lists public capabilities;
`/explore-collection` focuses on the browse-by surfaces specifically.

Lives entirely in `packages/ahg-semantic-search`. The package is NOT in
`.locked-paths`.

## Services reused (read-only, cited)

The controller adds NO queries of its own; it calls each existing service's
already-bounded top-N method:

- `Services/ThemeService::topThemes()`    -> strongest subject themes (-> `/themes`)
- `Services/PlaceService::topPlaces()`    -> busiest places            (-> `/places`)
- `Services/PersonService::topCreators()` -> busiest creators          (-> `/people`)
- `Services/TimelineService::buckets()`   -> period buckets            (-> `/timeline`)

Each teaser is capped small on top of the service's own cap (themes 6, places 12,
creators 8, timeline periods 12). The timeline strip is `array_slice`d to the
first N chronological buckets.

## What it does

1. **Hub (`GET /explore-collection`).** A short intro framing it as "ways to
   explore the collection", then one section per installed surface: top themes
   (cards), top places (chips), top creators (cards), and a compact timeline
   strip whose bars deep-link into the GLAM browse. Each section is
   `Route::has`-gated, so it renders only when that surface's full-page route
   (`themes.index` / `places.index` / `people.index` / `timeline.index`) is
   registered - and every onward link is therefore guaranteed to resolve. When
   every teaser is empty (or no surface is installed) a calm "exploration tools
   are warming up" empty-state is shown.
2. **Machine twin (`GET /explore-collection.json`).** CORS-open, cacheable
   (`max-age=300`) JSON of the same per-surface teaser data, only for the
   installed surfaces.

## Never 500s

Each slice call is wrapped in its own try/catch so a single failing surface
degrades to an empty teaser rather than fataling the hub. The slice services are
themselves `Schema::hasTable`-guarded and read-only, so a fresh/unbooted install
renders the warming-up state. No DB writes, no ALTER, no new table.

## Where it lives (packages/ahg-semantic-search)

- `src/Controllers/ExploreCollectionController.php` - `index` (hub), `json`
  (twin), and a shared `gather()` that does the `Route::has` gating + try/catch
  teaser fetch once for both. No queries of its own.
- `resources/views/explore-collection/index.blade.php` - Bootstrap 5 + central
  theme (`theme::layouts.1col`), one `*Enabled`-gated `<section>` per surface,
  the warming-up empty-state, and a JSON-twin link.
- `src/Providers/AhgSemanticSearchServiceProvider.php` - the only modified file:
  an additive route block in `register()` (see below).

## Routes (catch-all-safe)

Registered in the provider's `register()` via `callAfterResolving('router')`,
EXACTLY like `/themes`, so they bind BEFORE the single-segment `/{slug}`
archival-record catch-all in `ahg-information-object-manage` (`register()` runs
for all providers before any `boot()`, where the catch-all is bound). See
`reference_slug_catchall_route_precedence`.

- `/explore-collection.json` (`explore-collection.json`) is declared FIRST
  (dotted, so a slug literally ending in `.json` can never be swallowed - and the
  `.` is not in the catch-all's `[a-z0-9-]` charset anyway).
- `/explore-collection` (`explore-collection.index`) is single-segment. It
  matches the catch-all's `[a-z0-9][a-z0-9-]*` pattern and is NOT in its
  exclusion list - just like `/themes`, `/places`, etc. - so it relies on the
  same `register()` precedence to win the match. A normal record slug still
  resolves: the catch-all is unchanged and continues to serve every other
  single-segment slug.

## Constraints honoured

Read-only; no writes; no ALTER; NO new table (reuses the slice services'
on-the-fly bounded aggregates); catch-all-safe single-segment registration;
published-only (inherited from the services); `url()` / `route()` only (no
hardcoded host); every onward link `Route::has`-gated; Bootstrap 5 + central
theme; empty / warming-up states; never 500s; Plain Sailing / AGPL headers;
international and jurisdiction-neutral. The discovery slice files
(`/themes`, `/places`, `/people`, `/timeline`, `/related`) are untouched.
