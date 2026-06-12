# Trust and Transparency Console (/admin/trust-console)

A single read-only operator console that ties together the many trust,
preservation, accessibility and open-data surfaces shipped across recent
releases. Those surfaces are real and live, but were scattered and hard to find
from one place. This console is a HUB: it LINKS to each surface and
re-implements none of them.

## What it is

- Route: `GET /admin/trust-console`, name `trust.console`.
- Package: `packages/ahg-reports` (the central-dashboard package).
- Middleware: `admin` (admins only; unauthenticated requests 302 to login).
- Controller: `AhgReports\Controllers\TrustConsoleController`.
- View: `ahg-reports::trust-console.index`.
- Two URL segments (`/admin/trust-console`) so the single-segment `/{slug}`
  archival-record catch-all never intercepts it.

## Pattern it follows

It is a direct sibling of the existing North Star Cockpit
(`AhgReports\Controllers\NorthStarCockpitController` + view
`ahg-reports::north-star-cockpit.index`, route `/admin/north-stars`). Same
shape:

- Each surface is a card with a title, a one-line description, an icon, a
  status badge, and an optional metric badge.
- Links resolve through `Route::has()` then `route()`. A card whose route is
  not registered on this install renders as "Not configured" with a disabled
  button - never a dead link, never a `Symfony RouteNotFoundException`.
- Metrics are a single cheap `COUNT` against a table that is first confirmed
  with `Schema::hasTable()`, wrapped in `try/catch`. A missing table simply
  yields no badge.
- Read-only end to end: no writes, no ALTER, no AI calls, no new table. The
  console never 500s - every link and every metric is guarded.

## Sections and the real surfaces linked (all Route::has-gated)

Authenticity and provenance:
- Trust home - `c2pa.trust`
- Verified records - `c2pa.verified.records` (metric: `ahg_c2pa_manifest`)
- Authenticity report (per record) - `c2pa.authenticity.report` (sample slug)
- AI inference provenance (per record) - `c2pa.inference.provenance`
  (sample slug; metric: `ahg_ai_inference`)
- Verify authenticity - `c2pa.authenticity`
- Check content credentials of a file - `c2pa.verify.check`
- Authenticity coverage (operator) - `c2pa.coverage`

Preservation:
- Preservation dashboard - `preservation.index`
- Fixity and integrity report - `fixity.index` (metric: `core_fixity_check_log`)
- Preservation maturity (NDSA levels) - `preservation-maturity.index`
- Preservation timeline (per record) - `c2pa.preservation.timeline` (sample slug)

Accessibility:
- Accessibility coverage report - `accessibility.index`
- Alt-text curation - `alt-text.index` (metric: `image_alt_text`)

Open data and transparency:
- Open data home - `open-data.index`
- Open data protocol - `open-data.protocol`
- Open data maturity scorecard - `open-data.maturity`
- DCAT data catalog - `open-data.catalog`
- Linked-data / RDF dataset dump - `open-data.dataset` (fallback
  `api.v1.graph.dataset`)
- Union catalogue - `union.catalogue`
- OAI-PMH harvest endpoint - `api.oai`
- Public themes - `themes.index`

## How dead links are avoided

Every card resolves its URL only via `Route::has($name)` before calling
`route()`, inside a `try/catch`. Per-record surfaces (authenticity report,
inference provenance, preservation timeline) pass a sample slug parameter so
`route()` can resolve to a demonstrable link without inventing data. No route
name is hardcoded into an `<a href>`; the view only renders an anchor when the
controller produced a non-null URL.

## Where the linked surfaces live (for maintainers)

- `c2pa.*` - `packages/ahg-c2pa` (routes/web.php + provider register()).
- `preservation.index` - `packages/ahg-preservation`.
- `fixity.index`, `preservation-maturity.index`, `accessibility.index`,
  `alt-text.index`, `open-data.index` - `packages/ahg-core`.
- `open-data.catalog`, `open-data.protocol`, `open-data.maturity`,
  `open-data.dataset`, `api.oai`, `api.v1.graph.dataset` - `packages/ahg-api`.
- `union.catalogue` - `packages/ahg-federation` (union-catalogue provider).
- `themes.index` - `packages/ahg-semantic-search`.

## Lock note

`packages/ahg-reports/` is a locked path (`.locked-paths`). The console's
controller, view and the one route line must be added under a one-shot
`./bin/unlock packages/ahg-reports` before they can be committed/released. The
docs files here are not locked.
