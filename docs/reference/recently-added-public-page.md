# Recently added (public "what's new" page)

Heratio exposes a public **Recently added** surface that lists the newest
PUBLISHED archival descriptions, most-recent first, so visitors and returning
researchers can see what is new in the collection. It has three faces of one
bounded, read-only list:

- `GET /recent` - a Bootstrap-5 / central-theme card grid (the single-segment
  public page). Each card shows a thumbnail (when present), title, date added,
  and a short snippet, and links to the record. Simple Newer/Older offset paging.
- `GET /recent.json` - the same list as machine data: each item is
  `{id, slug, title, created_at, url}` inside a paging envelope. CORS-open.
- `GET /recent.atom` - a valid Atom 1.0 feed of recent additions (escaped XML),
  for feed readers / aggregators.

## The creation signal (the load-bearing fact)

`information_object` has **no `created_at` column**. The real creation timestamp
is the class-table-inheritance ROOT row: **`object.created_at`** where
`object.id = information_object.id`. On this instance it is fully populated
(6090/6090 rows). So the list is ordered `ORDER BY object.created_at DESC,
object.id DESC`.

If `object.created_at` is ever absent (a slimmer schema / future migration), the
service degrades honestly to `ORDER BY object.id DESC` (newest auto-increment
first) and sets `ordered_by_created = false`; the page then shows an honest note
that an exact "date added" is not available on this instance. This is guarded by
`Schema::hasColumn('object', 'created_at')`.

## Published gate

Published = a `status` row with `type_id = 158` (publication status) and
`status_id = 160` (published). The synthetic root description (`id = 1`) is
excluded. This mirrors `CollectionOverviewService` and `OpenSearchController`
exactly (a reusable `publishedIdSub()` subquery).

## Title / slug / snippet / thumbnail resolution

- **Title + snippet** come from `information_object_i18n` joined on the record's
  OWN `information_object.source_culture` (so multi-culture records yield the
  authoritative title, not a stray translation). Snippet is `scope_and_content`,
  tag-stripped, whitespace-collapsed, trimmed to ~220 chars on a word boundary.
- **Slug** comes from the `slug` table (`slug.object_id = record id`); the record
  URL is built with `url('/'.$slug)` - never a hardcoded host.
- **Thumbnail** (optional, cheap): the master digital object
  (`digital_object.object_id = record id`) and, when present, its
  `usage_id = 141` THUMBNAIL child (`parent_id = master.id`). The public URL is
  `url(path . name)`; `digital_object.path` already begins with `/uploads/` which
  nginx serves directly. Resolved in ONE batched query per page (no per-row IO);
  records with no thumbnail simply render a neutral placeholder icon.

## Files (all in `packages/ahg-core`, none locked)

- `src/Services/RecentlyAddedService.php` - the read-only aggregator
  (`recent($page, $perPage)`). Bounded `LIMIT` + `OFFSET` over the indexed ORDER
  BY (fetches one extra row to compute `has_more` cheaply). Every query is
  `Schema::hasTable` / `hasColumn`-guarded and wrapped in its own try/catch.
  `MAX_PER_PAGE = 100`, default 24.
- `src/Controllers/RecentlyAddedController.php` - `index()` (HTML), `json()`
  (CORS JSON), `atom()` (Atom 1.0, escaped via `htmlspecialchars(ENT_XML1)`,
  RFC-3339 dates, minimal-but-valid fallback feed).
- `resources/views/recently-added/index.blade.php` - the card grid +
  empty-state, `@extends('theme::layouts.1col')`, FontAwesome icons.
- `routes/web.php` - the three routes, registered next to the other
  single-segment public routes (`/explore`, `/collection-overview`,
  `/open-data`, `/accessibility-statement`).

## Catch-all safety

The dotted `.json` / `.atom` paths are inherently safe from the single-segment
`/{slug}` archival-record catch-all in `ahg-information-object-manage` (that
route only matches `[a-z0-9][a-z0-9-]*` - a dot disqualifies it), and are
declared first.

`/recent` is a single-segment public path, exactly like `/explore`. `ahg-core`
boots early, so it is registered **before** the `/{slug}` catch-all and wins the
match (first-registered route wins). A normal record slug still resolves -
verified: a real published slug returns a 302 into the GLAM browse/show flow, not
a 404, with `/recent` present.

## Guarantees

Read-only; no DB writes; no `ALTER`; no new table; no AI calls. Bounded - never a
full scan. A zero-result / missing-table state degrades to a calm "nothing
published yet" empty page (or an empty but valid feed / JSON), never a 500.
Jurisdiction-neutral, internationalised copy. There is no pre-existing RSS/Atom
feed surface to align with (only OpenSearch); the Atom feed mirrors
`OpenSearchController`'s XML-escaping pattern.
