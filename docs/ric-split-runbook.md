# Phase 4.3 cutover runbook — Heratio → openric-service

> **Status:** ✅ **EXECUTED 2026-04-18.** Heratio is live against `https://ric.theahg.co.za/api/ric/v1`, `ric:verify-split` 15/15 passing, admin pages smoke-tested green. This doc stays as the reference playbook; step-by-step annotations below mark what landed.

Single-page sequence to take the freshly-scaffolded `openric-service` from "green on localhost:8100" to "Heratio uses it in production". Each step is copy-pasteable. Every step can be rolled back in one line.

**You need root on 192.168.0.112 for steps 1–3, no root after that.**

---

## 1. DNS  (~2 min)

At your DNS provider (Hetzner / registrar), add:

| Type | Name | Value | TTL |
|---|---|---|---|
| CNAME | `ric` | `heratio.theahg.co.za` (or same host IP as Heratio) | 1200 |

Verify after save:

```bash
dig +short ric.theahg.co.za
# Should resolve to the same A records as heratio.theahg.co.za
```

## 2. nginx  (~5 min)

```bash
sudo cp /usr/share/nginx/openric-service/deploy/ric.theahg.co.za.conf /etc/nginx/sites-available/
sudo ln -s /etc/nginx/sites-available/ric.theahg.co.za.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Quick sanity check (HTTP, pre-TLS):

```bash
curl -I http://ric.theahg.co.za  # expect 301 redirect to https
```

## 3. TLS cert  (~3 min)

If the existing `theahg.co.za` cert doesn't already cover `ric.theahg.co.za`:

```bash
sudo certbot --nginx --expand -d ric.theahg.co.za
sudo systemctl reload nginx
```

Verify:

```bash
curl -sI https://ric.theahg.co.za/api/ric/v1/health
# HTTP/2 200
# content-type: application/json
# {"status":"ok","service":"RIC-O Linked Data API","version":"1.0"}
```

## 4. Mint the service API key  (~2 min)

Pick a user ID to own the key — any admin is fine:

```bash
mysql heratio -sNe "SELECT id,username FROM user WHERE username='johanpiet'"
# e.g. 900148
```

Run the artisan command (prints the raw key once — save it now):

```bash
cd /usr/share/nginx/openric-service
php artisan ric:mint-service-key --owner=900148 --name="heratio → openric-service"
# Output:
#   Key minted (row id=123, prefix=abc12345, scopes=read,write,delete).
#   Copy the following into Heratio's .env:
#     RIC_SERVICE_API_KEY=<64-char hex>
#   This is the LAST time the raw key is shown.
```

## 5. Configure Heratio to use the external service  (~2 min)

Edit Heratio's `.env`:

```bash
sudo -u www-data nano /usr/share/nginx/heratio/.env
```

Add at the bottom:

```
RIC_API_URL=https://ric.theahg.co.za/api/ric/v1
RIC_SERVICE_API_KEY=<paste from step 4>
RIC_HTTP_TIMEOUT=5
```

Reload config:

```bash
cd /usr/share/nginx/heratio
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## 6. Verify with `ric:verify-split`  (~1 min)

```bash
cd /usr/share/nginx/heratio
php artisan ric:verify-split
# Expected: 15 pass, 0 fail
```

Any FAIL here = stop; don't let users hit broken pages.

## 7. Smoke-test from the browser  (~5 min)

Log in to `https://heratio.theahg.co.za` as admin, then visit each:

- `/ric-capture` — tiles show correct counts, search works
- `/admin/ric/entities/places/create` — create a test Place, submit, should redirect to show page and be visible at `https://ric.theahg.co.za/api/ric/v1/places/<slug>`
- Open any IO show page, look at the "RiC Context" panel — tabs load, relations render
- Hover/click interactions on the graph viewer at `https://viewer.openric.org` pointing at `https://ric.theahg.co.za/api/ric/v1` — should be identical to before

## 8. Monitor  (~30 min observational)

Watch for `[callRicApi]` warnings in Heratio's logs — these indicate fallback to in-process:

```bash
sudo tail -f /usr/share/nginx/heratio/storage/logs/laravel-$(date +%Y-%m-%d).log | grep -i callRicApi
```

Zero warnings in 30 min = Phase 4.3 complete.

---

## Rollback (any time)

```bash
sudo -u www-data sed -i 's|^RIC_API_URL=.*|RIC_API_URL=|' /usr/share/nginx/heratio/.env
cd /usr/share/nginx/heratio && php artisan config:clear
```

Heratio reverts to in-process RiC. The `openric-service` on `ric.theahg.co.za` continues to run harmlessly; no data loss (shared DB).

---

## Problem map

| Symptom | Likely cause | Fix |
|---|---|---|
| `curl …/health` returns 502 | php-fpm can't find the openric-service path | Check nginx error log; confirm `/usr/share/nginx/openric-service/public/index.php` exists |
| 401 on every write | Service key not minted, or wrong key in `.env` | Re-mint; re-paste |
| 403 on every write | Key missing `write` scope | `UPDATE ahg_api_key SET scopes='["read","write","delete"]' WHERE id=<row>` |
| `[callRicApi]` warnings flood logs | DNS / certificate / service down | Verify curl from Heratio host → ric.theahg.co.za |
| Embedded panels say "Loading…" forever | Blade JS fetching old `/api/ric/v1` (relative) — browser cached HTML | Hard-refresh + `php artisan view:clear` |
| CORS error in browser console | Missing `api.cors` middleware on service | Confirm `/api/ric/v1` route group in service's `routes/api.php` includes `api.cors` |

---

## Done

When all 15 `ric:verify-split` tests pass, zero `[callRicApi]` warnings for 24 h, and no support issues raised — the split is live. Phase 4.4 (collapse) can begin whenever you're ready.

---

## Execution notes (2026-04-18)

What actually happened, in the order it happened, for the record:

- **DNS** — `ric.theahg.co.za` was already a CNAME to `theahg.ddns.net` from earlier infrastructure; no DNS work needed.
- **nginx** — an existing `ric.theahg.co.za.conf` vhost was already provisioning a `/app` slot for a Laravel app (the previous OpenRiC monorepo had been consolidated into Heratio; the slot was left empty). Rather than a fresh vhost, the scaffolded service was slotted in under `/usr/share/nginx/OpenRiC/public` and the vhost was rewritten to drop the dead `/app` prefix. `ric.theahg.co.za/api/ric/v1/*` became the canonical URL.
- **TLS** — reused the existing `/etc/letsencrypt/live/theahg.co.za/` certificate (already covers `ric.theahg.co.za` via SAN). No certbot run needed.
- **Service key mint** — `php artisan ric:mint-service-key --owner=900148 --name="heratio → openric-service"` — key copied into `/usr/share/nginx/heratio/.env` as `RIC_SERVICE_API_KEY=…`.
- **Config gotcha** — Heratio already had a `config/ric.php` file (Fuseki / Qdrant / Elasticsearch settings). The Phase 4.3 keys (`api_url`, `service_key`, `http_timeout`) were merged in rather than replacing. First `verify-split` reported `api_url=(null)` and mode `in-process` because the expected keys weren't yet in the config. Fixed by editing the existing file, not replacing.
- **Smoke test** — `ric:verify-split` returned **15 of 15 PASS**, including the POST/PATCH/DELETE cycle against a throwaway Place.
- **Browser smoke test** — `/ric-capture`, admin entity create/show/edit/browse pages, IO show-page RiC panels — all rendered and interacted correctly through the external service.

Phase 4.4 (collapse) began immediately after: `routes/api.php` load gated on `RIC_API_URL`, Blade JS routed through `window.RIC_API_BASE`, `/ric-capture` 302 → `capture.openric.org`. See `ric-split-collapse-plan.md`.

---

## Change log

| Date | Change |
|---|---|
| 2026-04-18 | Phase 4.3 executed. Heratio live as a consumer of `ric.theahg.co.za/api/ric/v1`. All 15 verify-split checks green. |
