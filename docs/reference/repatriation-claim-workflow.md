# Repatriation claim / virtual-return workflow (heratio#1207)

Next slice of the repatriation engine in `packages/ahg-semantic-search`. Adds a
structured repatriation-claim workflow and a public virtual-return surface on top
of the existing read-only displaced-heritage register. The epic (#1207) stays
open.

## Layers

- **Detection (pre-existing, untouched):** `DisplacedHeritageService::scan()` is a
  conservative, read-only origin-vs-holding mismatch detector over `museum_metadata`
  + `information_object`. `DisplacedHeritageController` (admin) and
  `DisplacedHeritageRegisterController` (public `/displaced-heritage`) render it.
- **Claim workflow (this slice):** human-curated claim records that sit on top of a
  traced item, plus a public per-claim virtual-return page.

## New table: `displaced_heritage_claim`

Created idempotently. `CREATE TABLE IF NOT EXISTS` SQL at
`packages/ahg-semantic-search/database/install_repatriation_claim.sql`, auto-run on
first boot by `AhgSemanticSearchServiceProvider::bootRepatriationClaimTable()`
behind a `Schema::hasTable` probe wrapped in one outer try/catch (the canonical
package idiom - a fresh/locked-DB boot never fatals; a Schema-builder fallback
covers a missing SQL file).

Columns: `id`, `item_ref` (the information_object id - a soft reference, **no FK**,
so the additive table never constrains/ALTERs the catalogue), `claimant_community`,
`origin_place`, `current_holder`, `claim_status` (VARCHAR, **never ENUM** -
registered|under_review|acknowledged|returned|virtual_return|disputed, open to
Dropdown-Manager additions), `evidence_summary`, `contact`, `notes`, `created_by`,
`created_at`, `updated_at`. Indexes on `item_ref`, `claim_status`, and the pair.

No existing table is altered or written.

## Service: `RepatriationClaimService`

Writes only to `displaced_heritage_claim`. Methods: `register()`, `update()`,
`updateStatus()`, `list($statusFilter)`, `statusCounts()`, `find()`, and
`virtualReturn($claimId)`. Reuses `DisplacedHeritageService::scan()` read-only to
ground each virtual-return page in the same origin/holding framing the detection
register uses. `STATUSES` carries label/level/help per status; `DISCLAIMER` is the
standing framing. Every read path is `Schema::hasTable`-guarded and try/catch
wrapped (degrades to empty, never 500s).

The virtual-return record link surfaces **only for published items**: the service
checks publication via the `status` table (`type_id=158`, `status_id=160` =
published). Unpublished or absent items degrade to origin-context-only - never a
back door to a draft record.

## Controllers and routes

- `RepatriationClaimController` (admin, `auth`+`admin`), routes in
  `packages/ahg-semantic-search/routes/web.php` under the `repatriation` prefix:
  `/repatriation/claims` (index, status filter), `/claims/create`, `POST /claims`,
  `/claims/{id}/edit`, `POST /claims/{id}`, `POST /claims/{id}/status`. The create
  form prefills origin/holding from a traced item via `?item=<id>`. Full validation.
- `VirtualReturnController` (public, read-only): `GET /virtual-return/{id}` (claim
  id, numeric). Unknown id 404s; any failure degrades to 404/empty - never 500s.

### Catch-all safety

All admin claim paths are 2-segment+ (`/repatriation/claims...`) so they cannot
collide with the single-segment `/{slug}` archival-record catch-all in
`ahg-information-object-manage`. The public `/virtual-return/{id}` is registered in
`AhgSemanticSearchServiceProvider::register()` via
`callAfterResolving('router')` with a numeric `{id}` constraint - the same
precedence pattern used for `/discoveries` and `/displaced-heritage`, so it binds
before the catch-all. See `memory/reference_slug_catchall_route_precedence.md`.

## Sensitive-framing approach

Factual, non-partisan, jurisdiction-neutral copy throughout. Status is presented as
"where a dialogue stands", never a legal outcome. The standing disclaimer (a claim
is a documented request and its status, not a determination / finding of wrongful
removal / advice) is shown on the admin register, the form, and the public
virtual-return page. Empty-states everywhere; nothing 500s.

## Views

`resources/views/repatriation/index.blade.php` (claims register + status filter),
`repatriation/form.blade.php` (create/edit), `virtual-return/show.blade.php`
(public). Bootstrap 5 + `theme::layouts.1col` + FontAwesome + `__()` strings, in
line with the existing displaced-heritage views.

## Public dashboard (next slice)

Public, read-only aggregate VIEW over the same claims register. **No new table.**

- **Service:** `RepatriationClaimService::dashboard($topOrigins, $recentLimit)` -
  cheap aggregate COUNTs only (reuses `statusCounts()` + a small `topGroup()`
  GROUP-BY helper for top `origin_place` / `claimant_community`), the
  virtual_return vs returned vs in-dialogue split, the grand total, and a bounded
  recent-activity tail (the only individual-row read, decorated for linking to each
  `/virtual-return/{id}`). `available()`-guarded; degrades to a fully-zeroed shape,
  never 500s. No per-record loops, no heavy joins.
- **Controller:** `RepatriationDashboardController` - `index()` renders the HTML
  dashboard; `json()` returns the same aggregate as CORS-open
  (`Access-Control-Allow-Origin: *`) public read-only JSON.
- **Routes:** `GET /repatriation` (name `repatriation.dashboard`) and
  `GET /repatriation.json` (name `repatriation.dashboard.json`), both single-segment
  and both bound in `AhgSemanticSearchServiceProvider::register()` via
  `callAfterResolving('router')` so they win the match ahead of the single-segment
  `/{slug}` archival-record catch-all in `ahg-information-object-manage` (same
  pattern as `/at-risk`, `/displaced-heritage`, `/discoveries`). The `.json` suffix
  keeps the machine route a distinct path that can never shadow a slug. See
  `memory/reference_slug_catchall_route_precedence.md`.
- **View:** `resources/views/repatriation/dashboard.blade.php` - big numbers + simple
  Bootstrap `.progress` CSS bars (no charting library), top places / communities,
  recent-activity table, each row linking to `/virtual-return/{id}`. Dignified
  empty-state ("No repatriation claims recorded yet"). Same standing disclaimer and
  factual, non-partisan, jurisdiction-neutral framing as the rest of the feature.
  `url()` (never a hardcoded host), Bootstrap 5 + `theme::layouts.1col`.

Read-only; no DB writes; no ALTER; the existing claim / virtual-return feature is
untouched.
