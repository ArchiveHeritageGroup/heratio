# Heratio/RiC split - Phase 4.3 + 4.4

**Last updated:** 2026-04-18
**Prerequisites:** ✓ Step 0 (capture UI), ✓ Step 1 (API-1 reads), ✓ Step 2 (API-2 writes), ✓ Step 3 (API-3 internal migration - Phases 4.1 + 4.2 closed)

**Goal:** extract the `ahg-ric` package out of Heratio into a standalone deployable service, and reduce Heratio to a pure HTTP consumer of that service.

---

## Target architecture

Two independent artifacts, deployed to two URLs, talking over HTTP only:

```
┌─────────────────────────────────┐           ┌──────────────────────────────────┐
│ Heratio (GLAM app)              │           │ OpenRiC Service                  │
│ heratio.theahg.co.za            │           │ ric.theahg.co.za                 │
│                                 │  HTTPS    │                                  │
│   - Records, digital objects,   │   JSON    │   - /api/ric/v1/* endpoints      │
│     repositories, access-       │  ◀──────▶ │   - ric_* MySQL tables           │
│     restrictions, GLAM UI       │  api key  │   - Fuseki SPARQL                │
│   - No ric_* tables             │           │   - SHACL validator              │
│   - ahg-ric is a thin HTTP      │           │                                  │
│     client wrapper              │           │                                  │
└─────────────────────────────────┘           └──────────────────────────────────┘
         ▲                                                 ▲
         │                                                 │
         │                                                 │
  Heratio users,                              capture.openric.org,
  IO/agent edit,                              viewer.openric.org,
  browse, search                              3rd-party clients
```

The separation is enforced at the network layer - not at the language or framework layer.

---

## Data strategy - shared DB vs separate DB

Two options, shipped in sequence:

### Option A - shared MySQL (ship first)

Both apps connect to the same MySQL instance; the RiC service only touches `ric_*`, `relation`, `object`, `slug`, `ahg_dropdown` tables.

**Pros**
- Zero data migration.
- Heratio continues to see its own tables during cutover.
- Fast to stand up: the service is literally a new Laravel app with the same DB credentials and a subset of the codebase.

**Cons**
- Heratio could still write to `ric_*` tables via the old service layer if any caller slips through - use `grep` to audit, or temporarily revoke table-level INSERT/UPDATE from Heratio's MySQL user on `ric_*` to force the contract.
- Two apps sharing DB credentials is a soft boundary.

### Option B - separate DB (ship second, later)

The RiC service gets its own MySQL instance or database (`openric_ric`). A migration script dumps `ric_*` + `relation` + relevant `object` rows from Heratio into the new DB.

**Pros**
- Hard boundary.
- Service can be hosted anywhere - DigitalOcean, Fly.io, Hetzner, AWS - without needing access to Heratio's DB.
- Mirrors how external adopters would run the service.

**Cons**
- Need a migration script.
- `relation` table references `object` which references `information_object` / `actor` / `repository` - these aren't RiC-native but are referenced by RiC relations. Either copy those rows too (denormalised snapshot) OR change the FK shape so RiC-service entities can point at "foreign" URIs it doesn't own.

**Recommended sequence:** A first (fast, low-risk). B later, once A is proven and external adopters start asking for deployment guidance.

---

## Service-to-service auth

Today Heratio's admin session is forwarded via cookie (`callRicApi`) to the in-process RiC API. After the split, cookies don't cross the origin boundary - we need an API key.

Concrete plan:

1. **Generate a service-level API key** in the `ahg_api_key` table with scopes `['read', 'write', 'delete']`, labelled `heratio-service-to-ric`, not rotated by human users.
2. Store the raw key (one-time visibility) in Heratio's `.env` as `RIC_SERVICE_API_KEY`.
3. Update `RicEntityController::callRicApi()` so when `config('app.url')` != `config('ric.api_url')` (i.e. we're calling an external service), it drops cookie forwarding and uses the `X-API-Key` header from the env variable instead.
4. Keep cookie forwarding for the transitional phase where Heratio calls its own RiC module in-process.

Once the split is deployed, `RIC_SERVICE_API_KEY` is the only auth path.

---

## Deployment choice - `ric.theahg.co.za` vs `ric.openric.org`

Both work. Trade-off:

- **`ric.theahg.co.za`** - AHG hosts the reference implementation's service tier. Clear ownership: AHG runs this.
- **`ric.openric.org`** - neutral domain. But then someone has to commit to running it long-term; and if it's still AHG, it's the same thing with a more confusing hostname.

Recommendation: **`ric.theahg.co.za`** for the reference instance. The *spec* is neutral; *this deployment* is AHG's. That matches how IIIF works - IIIF.io is the spec, Wellcome/Stanford/Getty run their own reference services.

TLS via Let's Encrypt (same nginx + certbot pattern as the rest of `theahg.co.za`).

---

## Step-by-step execution

### 4.3.1 - Scaffold the service (1–2 days)

- `mkdir /usr/share/nginx/openric-service` (or choose a different path).
- `composer create-project laravel/laravel .` (Laravel 12, matching Heratio).
- Copy `packages/ahg-ric/`, `packages/ahg-api/`, `packages/ahg-core/` (the shared bits it needs) into the new app's `packages/` with `path` repository entries.
- Register `AhgRicServiceProvider`, `AhgApiServiceProvider`, `AhgCoreServiceProvider` in the new app's `bootstrap/providers.php`.
- Point `.env` at Heratio's MySQL (Option A).
- Run `php artisan serve --port=8100` and verify `http://localhost:8100/api/ric/v1/health` returns `ok`.

### 4.3.2 - nginx + DNS + TLS (0.5 day)

- Add an nginx vhost for `ric.theahg.co.za` → `fastcgi_pass` / `php-fpm` pool pointing at the new app's `public/`.
- Add DNS A/AAAA (or CNAME to heratio host if same server).
- `certbot --nginx -d ric.theahg.co.za` for TLS.
- Verify `https://ric.theahg.co.za/api/ric/v1/health` returns 200.

### 4.3.3 - Mint the service API key (0.5 day)

- Run a one-off seeder in the new service that inserts a row in `ahg_api_key` with scopes `read,write,delete`.
- The raw secret is printed once to stdout - copy to Heratio's `.env` as `RIC_SERVICE_API_KEY=…`.
- Add a `config/ric.php` in Heratio: `'api_url' => env('RIC_API_URL')`, `'service_key' => env('RIC_SERVICE_API_KEY')`.

### 4.3.4 - Update `RicEntityController::callRicApi()` (0.5 day)

- When `config('ric.api_url')` is set and differs from `config('app.url')`, use that as the base URL and inject `X-API-Key` from `config('ric.service_key')` instead of forwarding cookies.
- When unset, keep the existing same-process cookie-forward behaviour (useful in dev + for a staged rollout).

### 4.3.5 - Flip Heratio to external service in staging (0.5 day)

- Set `RIC_API_URL=https://ric.theahg.co.za/api/ric/v1` in Heratio's `.env` on a staging environment.
- Smoke-test every admin page that touches RiC (capture studio, relations, place picker, entity panels on IO show).
- Verify the request actually crosses the network with `tcpdump` or by running `ric.theahg.co.za` on a different host.

### 4.3.6 - Production cutover (0.5 day, scheduled)

- Put Heratio in read-only mode or a short maintenance window.
- Flip `RIC_API_URL` in prod.
- Smoke-test.
- Monitor logs for the next 30 min.

### 4.4 - Collapse Heratio's `ahg-ric` package (1–2 days)

Once the service is serving real traffic and we trust it:

- Remove the Blade views + controllers + routes that are now served by the external service (entity CRUD pages, relations page). Heratio becomes a pure API consumer - it doesn't render RiC pages of its own.
- Keep the *embedded* views (`_ric-view-*.blade.php`, `_ric-entities-panel.blade.php`, the graph viewer partials) but have them fetch data from the external service via the same API client.
- `packages/ahg-ric/` shrinks to a thin HTTP client package: one `RicApiClient.php` facade + a couple of Blade partials. The bulk of the code lives in the new service.
- Remove `ric_*` tables from Heratio's DB (Option A → B transition, later).

---

## Rollback plan

At any stage the flip is reversible by unsetting `RIC_API_URL` in Heratio's `.env`. Heratio's `ahg-ric` package keeps the fallback direct-service-call code path (Phase 4.1–4.2 didn't delete the service classes - only the admin AJAX wrappers). As long as Heratio still has the service classes + the DB access, unsetting the env var reverts to in-process calls.

After the 4.4 collapse, the rollback is heavier: you'd need to `git revert` the package-shrink commit and restore Heratio's direct DB access. Worth keeping a tagged pre-collapse version for a while.

---

## Open questions for the user

1. **`ric.theahg.co.za` or `ric.openric.org`?** Both mean AHG operates the service. The latter is more visibly neutral but identical operationally. *Default: `ric.theahg.co.za`.*
2. **Shared DB or separate DB at cutover?** *Default: shared (Option A) for the first cutover; plan separate (Option B) as a follow-up.*
3. **Fuseki + SHACL - also move to the new service?** Yes, they belong with the RiC data. The Fuseki instance should serve from the service host.
4. **What about `heratio.theahg.co.za/ric-capture`?** After the split, three options:
   - *(a)* Stay as a thin proxy page in Heratio that just iframes or proxies the new service's capture UI.
   - *(b)* Move entirely to the service - the URL becomes `ric.theahg.co.za/capture`.
   - *(c)* Deprecated in favour of `capture.openric.org` (the neutral client).

   *Default: (c); keep `/ric-capture` working as-is for a transitional period but direct new users at `capture.openric.org`.*

---

## Change log

| Date | Change |
|---|---|
| 2026-04-18 | Initial plan drafted. Phases 4.1 + 4.2 of API-3 migration complete; 4.3 + 4.4 form the split proper. |
