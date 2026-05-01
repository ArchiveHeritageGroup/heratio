# Phase 4.4 - Collapse `ahg-ric` inside Heratio

> **Status:** ⚙ **PARTIAL - executed 2026-04-18**, skipping the "wait 1 week" recommendation at the user's direction. What landed:
>
> - ✅ **4.4.1** - `RicApiClient.php` thin HTTP facade in `packages/ahg-ric/src/Http/`.
> - ✅ **4.4.3** - `AhgRicServiceProvider` conditionally skips loading `routes/api.php` when `RIC_API_URL` is set. Heratio stopped serving `/api/ric/v1/*`; `ric.theahg.co.za` serves it authoritatively. `openric-service` keeps loading the routes because it does not set `RIC_API_URL`.
> - ✅ **4.4.5** - `/ric-capture` is a 302 redirect to `https://capture.openric.org/`. `captureStudio` controller method deleted; `capture-studio.blade.php` view orphaned (not yet deleted - harmless).
> - ✅ **Landing consolidation** - `ric.theahg.co.za/` replaced the Flask community site with a static HTML landing pointing at the four public surfaces; Flask proxy routes retired with 301 redirects in place.
> - ⚠ **4.4.2** (swap every embedded-view data fetch to `RicApiClient`) - *partial*. The write-side already routes through `callRicApi()` which is in turn a lightweight HTTP forwarder, and front-end JS fetches via `window.RIC_API_BASE`. The read-side of `RicEntityController` still calls `$this->service->*` for browse/show/edit pages - those go to in-process Laravel services. Not broken (shared DB), not fully "pure consumer" yet. Defer.
> - ⚠ **4.4.4** (delete the service classes) - *deferred*. Because `openric-service` reuses the same `ahg-ric` package via composer path repo, deleting the service classes would break the RiC service itself. The proper lift (split `ahg-ric` into `ahg-ric-client` + `ahg-ric-server` packages) is bigger work and not urgent.
> - ⏳ **4.4.6** (remove `ric_*` tables from Heratio's DB - Option B) - not scheduled; requires dedicated DB for `openric-service` first.

**Prerequisite (originally):** Phase 4.3 cutover complete and stable (Heratio running with `RIC_API_URL` pointing at `ric.theahg.co.za` for ≥ 1 week, no regressions).

**Goal:** Shrink the `packages/ahg-ric/` package inside Heratio so it contains only the thin slices that still need to live in the GLAM client - no duplicated business logic, no DB access to `ric_*` tables, no serialization code. Everything non-client moves to (or already lives in) `openric-service`.

---

## Before you touch anything

1. **Verify the external service has been the source of truth for ≥ 1 week.** Grep `storage/logs/laravel.log` for `[callRicApi]` warnings (the fallback path triggered only on failure). Zero warnings = safe to proceed.
2. **Tag Heratio at the pre-collapse point** so rollback is one `git reset --hard` away:
   ```bash
   cd /usr/share/nginx/heratio
   git tag pre-ric-collapse-$(date +%Y%m%d)
   git push origin --tags
   ```
3. **Back up `ric_*` tables** so the data can be rehomed if the service needs its own DB later (Option B):
   ```bash
   mysqldump heratio ric_place ric_place_i18n ric_rule ric_rule_i18n \
     ric_activity ric_activity_i18n ric_instantiation ric_instantiation_i18n \
     ric_relation_meta > ~/ric-tables-backup-$(date +%Y%m%d).sql
   ```

---

## What stays in Heratio's `ahg-ric`

After collapse, this package contains only:

| Path | Why it stays |
|---|---|
| `src/Http/RicApiClient.php` (NEW) | Thin HTTP client facade: `RicApiClient::places()`, `->get(...)`, `->create(...)` - every call hits `config('ric.api_url')` with the service API key. Replaces in-process `RicEntityService`. |
| `resources/views/_ric-api-base.blade.php` | Injects `window.RIC_API_BASE` into any page that embeds RiC JS - still needed so Blade-JS knows where to fetch. |
| `resources/views/_ric-view-*.blade.php` | Embedded RiC context panels on IO/Actor/Repository show pages. Fetch data via `RIC_API_BASE`. |
| `resources/views/_ric-entities-panel.blade.php` | Tabbed "RiC Context" section on IO show pages. Already API-driven. |
| `resources/views/_ric-entity-modal.blade.php` | Quick-create modal embedded on IO/Actor pages for adding linked entities. API-driven. |
| `resources/views/_relation-editor.blade.php` | Relations table + inline editor. API-driven. |
| `resources/views/_fk-autocomplete.blade.php` | Reusable FK picker partial. API-driven. |
| `resources/views/explorer.blade.php` | Admin graph explorer. Fetches via `/graph` over API. |
| `config/ahg-ric.php` | Config stub - may be empty post-collapse; keep for future client-side settings. |
| `composer.json` + `src/Providers/AhgRicServiceProvider.php` | Skeleton - registers the API client binding, loads the Blade views. |

Estimated size after collapse: **~500 lines of PHP + ~15 Blade partials**. Compare with current ~9,000 lines of code in the package.

## What leaves Heratio's `ahg-ric` (deleted)

| Path | Reason |
|---|---|
| `src/Services/RicSerializationService.php` | Serialization is the service's job. |
| `src/Services/RicEntityService.php` | CRUD is the service's job. |
| `src/Services/RelationshipService.php` | Graph walks are the service's job. |
| `src/Services/ShaclValidationService.php` | Validation is the service's job. |
| `src/Services/SparqlQueryService.php` | Fuseki lives with the service. |
| `src/Controllers/RicEntityController.php` | All its methods now live in the service - Heratio no longer renders entity CRUD pages. |
| `src/Controllers/RicController.php` | The `/ricExplorer/getData` legacy endpoint + graph-building code. |
| `src/Http/Controllers/LinkedDataApiController.php` | The entire public API - serves from `ric.theahg.co.za` now. |
| `routes/api.php` | `/api/ric/v1/*` routes are external now. |
| `routes/web.php` (most of it) | The `/admin/ric/entities/*` browse/show/edit/create pages - replaced by `ric.theahg.co.za/admin/*` or `capture.openric.org`. |
| `database/install_ric_entities.sql`, `database/seed_ric_from_existing.sql` | Schema lives with the service; only run when bootstrapping a fresh DB there. |
| `src/Models/Ric*.php` | Eloquent models for RiC tables - service's responsibility. |
| `tools/ric_*.py` | Python utilities bundled with the service. |

Estimated deleted LoC: **~8,000**.

## What about `/ric-capture` and the embedded admin pages in Heratio?

Three options per the earlier `ric-split-plan.md`:

**(a) Keep `/ric-capture` in Heratio as a thin proxy page** - it iframes or 302-redirects to `capture.openric.org`. Preserves the URL for anyone with a bookmark. ~20 lines of route + view.

**(b) Move to the service** - `ric.theahg.co.za/admin/...` becomes the canonical admin surface. Heratio loses the admin pages entirely.

**(c) Deprecate in favour of `capture.openric.org`** - the neutral browser tool. Heratio admin no longer creates RiC entities. Users doing capture go to `capture.openric.org`; users who want to view/browse use Heratio's embedded panels (which are read-only after the split).

**Recommended:** (c), with the `/ric-capture` page kept as a simple 302 → `capture.openric.org` for one release, then deleted.

---

## Execution order

Follow strictly. Each step is independently revertible until the next one starts.

### 4.4.1 - Introduce `RicApiClient` (0.5 day)

- New class: `packages/ahg-ric/src/Http/RicApiClient.php`.
- Method signatures mirror the `/api/ric/v1/*` surface: `places()->list()`, `places($id)->show()`, `places()->create($data)`, `relations()->forEntity($id)`, etc.
- Internally uses `Http::withHeaders(['X-API-Key' => config('ric.service_key')])` against `config('ric.api_url')`.
- Add a facade (`RicApi`) + service-container binding in `AhgRicServiceProvider`.

### 4.4.2 - Swap every embedded-view data fetch to `RicApiClient` (1 day)

- `_ric-entities-panel.blade.php` controller-side data fetches (currently in IO/Actor controllers) → `RicApi::records($id)->entities()`.
- `_ric-view-*.blade.php` same.
- Server-rendered counts on the dashboard → `RicApi::stats()`.

### 4.4.3 - Delete the no-longer-used controllers + routes (0.5 day)

In this order (each reversible with `git checkout`):

1. Delete `routes/api.php` - the service now serves `/api/ric/v1`.
2. Delete route groups in `routes/web.php`: `admin/ric/entities/*`, `admin/ric/relations`, `ricExplorer/*`.
3. Delete `src/Http/Controllers/LinkedDataApiController.php`.
4. Delete `src/Controllers/RicEntityController.php` (except the methods that `/ric-capture` + embedded-view controllers still need - move those to a thin new `RicEmbeddedController`).
5. Delete `src/Controllers/RicController.php` (except the graph-building code, if the embedded graph viewer needs a server-side fallback - which it shouldn't, since the graph endpoint now lives in the service).

### 4.4.4 - Delete the service classes (0.5 day)

After 4.4.3, no caller references them:

- `src/Services/RicSerializationService.php`
- `src/Services/RicEntityService.php`
- `src/Services/RelationshipService.php`
- `src/Services/ShaclValidationService.php`
- `src/Services/SparqlQueryService.php`
- `src/Models/Ric*.php`

Run `grep -r "RicSerializationService\|RicEntityService" /usr/share/nginx/heratio/packages /usr/share/nginx/heratio/app` to confirm no lingering references before deleting.

### 4.4.5 - Decide `/ric-capture` fate (10 min)

Apply option (c):
- Route `/ric-capture` → returns a 302 to `https://capture.openric.org/`.
- Delete `resources/views/capture-studio.blade.php` + the `captureStudio` controller method.

### 4.4.6 - Remove the `ric_*` tables from Heratio's DB (Option B - LATER)

Only after **all four** of the following are true for at least 2 weeks:
1. The external service is the sole writer to `ric_*` tables (grep the ahg-ric package for `DB::table('ric_` - should be zero).
2. Heratio has no direct SELECT on `ric_*` tables.
3. The service has its own DB copy provisioned.
4. A dry-run DB diff confirms no rows exist in Heratio's `ric_*` that aren't in the service's.

Then:
```bash
# On the Heratio MySQL user: revoke write perms on ric_* (sanity belt).
REVOKE INSERT, UPDATE, DELETE ON heratio.ric_place FROM 'heratio_app'@'localhost';
# ... repeat for each ric_ table.
# Service runs against its own DB; Heratio's ric_* becomes read-only archive.
# Eventually drop those tables in Heratio after service migration verified.
```

---

## Rollback matrix

| Failure at step | Rollback |
|---|---|
| 4.4.1 (new client) | `git checkout` the package path - nothing downstream depends yet |
| 4.4.2 (view data) | Revert the controller changes; in-process service still intact |
| 4.4.3 (routes/controllers) | `git reset --hard pre-ric-collapse-<date>` restores everything |
| 4.4.4 (services/models) | Same - tag is before any delete |
| 4.4.5 (/ric-capture) | Trivial revert |
| 4.4.6 (DB removal) | Restore from the mysqldump backup; re-grant perms |

---

## Success criteria

1. `packages/ahg-ric/` is < 1000 lines of PHP.
2. `grep -r "DB::table\('ric_\|use AhgRic\\\\Services\\\\" packages/ahg-ric` returns nothing (no direct DB access, no service-class imports).
3. `php artisan ric:verify-split --base=https://ric.theahg.co.za/api/ric/v1 --key=…` passes 12/12.
4. Heratio admin pages that embed RiC panels render and interact correctly - this is the real user-facing bar.
5. `capture.openric.org` is the only documented capture URL in the openric.org status table.

---

## Change log

| Date | Change |
|---|---|
| 2026-04-18 | Initial plan drafted. Waiting on Phase 4.3 cutover to execute. |
| 2026-04-18 | Phase 4.3 cutover executed same day; Phase 4.4.1 + 4.4.3 + 4.4.5 + landing consolidation landed. 4.4.2 partial, 4.4.4 + 4.4.6 deferred to a future package-split refactor. Skipped the "wait 1 week" guidance at the user's direction. |
