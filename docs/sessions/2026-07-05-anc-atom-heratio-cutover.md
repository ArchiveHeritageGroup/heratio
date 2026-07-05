# 2026-07-05 — ANC atom.theahg.co.za brought back as Heratio (Laravel) over DB `atom`

**Type:** operational / deployment (no heratio-dev code change)
**Host:** the shared multi-tenant server (`/usr/share/nginx/*`)
**Outcome:** `https://atom.theahg.co.za` is live again, now served by **current Heratio (Laravel v1.154.240)** over the 16 GB ANC database `atom` — finishing the 2026-04-27 AtoM→Heratio cutover that had been left disabled.

## Background / why it was down
- On **2026-04-27** the domain `atom.theahg.co.za` was cut over from **Symfony AtoM** to **Laravel Heratio**, but the vhost + the dedicated `atom` php-fpm pool were left **disabled** (nginx conf renamed `…​.conf.disabled`; nginx only includes `sites-enabled/*.conf`). No crash/OOM evidence was found in the AtoM logs — it was a planned-but-unfinished cutover, not a session-exhaustion crash. (KM `km_ask` was consulted but its RAG endpoint timed out; conclusion is from local evidence.)
- The two front-ends over the **same** `atom` DB:
  - `/usr/share/nginx/atom.anc` — **Symfony/Qubit AtoM** (71 GB, `apps/qubit`, Propel). Left untouched.
  - `/usr/share/nginx/atom` — **Laravel Heratio** (`ahg/heratio`), the cutover target. This is what we finished.

## The "cert is missing" fix
- The shared LE cert `theahg.co.za` had **19 SANs but NOT `atom.theahg.co.za`**, so the domain fell through to a default block and served the wrong `mogalakwena` cert.
- Fix: `certbot certonly --nginx --cert-name theahg.co.za --expand -d <all 19> -d atom.theahg.co.za` (dry-run first; `certonly` so no server blocks were edited). Now **20 SANs**; all 19 existing sites verified still-valid after reload. TLS validates for atom.

## Schema reconciliation (the real work — DB `atom`, 16 GB, 454k information objects)
Problem: `atom` had a populated schema but an **empty Laravel `migrations` ledger**, and was missing tables/columns vs current Heratio (`heratio` DB is the reference: 248 ahg_ tables, 75 migrations). A blind `migrate` would collide (MySQL DDL is non-transactional).

Approach — **fully additive clone from the authoritative `heratio` schema**, backed up first:
1. Backups → scratchpad: full schema dump + `ahg_*` data dump (124 MB) + `.env`.
2. Created **all missing tables** (71 ahg_ + ~100 non-ahg = the full delta) via `mysqldump --no-data heratio <tables>` applied to `atom`. Views included (DEFINER stripped → INVOKER).
3. **FK caveat:** the non-ahg clone hit an incompatible-type FK (`atom_landing_page_block.page_id` → `atom_landing_page.id`, atom's pre-existing `id` type differs). Rather than alter core columns on 16 GB of production data, the ~100 new tables were created **without FK constraints** (Laravel enforces relations at the app layer). Functionally fine; FKs can be reconciled later if desired.
4. Reconciled **all column drift** (157 columns) via additive `ALTER … ADD COLUMN` from `heratio`'s `information_schema` (the one NOT-NULL-no-default column, `ahg_tenant.code`, given `DEFAULT ''`; table was empty anyway).
5. Copied the `migrations` ledger `heratio`→`atom` (75 rows), then ran the **8 guarded pending migrations** (`migrate --force`, all DONE). Migrate is now consistent.

Net: `atom` schema now matches current Heratio (0 missing tables, 0 column drift), all additive, fully reversible from backups.

## Code + runtime
- `/usr/share/nginx/atom` updated **v1.67.0 → v1.154.240** by `rsync -a --delete` from prod `/usr/share/nginx/heratio` (excluding `.git`, `.env`, `storage/`, `public/uploads/`). This pulls in all the #1395 security hardening the stale copy lacked. `.env` preserved (`DB_DATABASE=atom`, `APP_URL=https://atom.theahg.co.za`). Then `composer dump-autoload -o`, nuked `bootstrap/cache/*.php`, `package:discover`.
- **php-fpm:** enabled a **dedicated tuned `atom` pool** (`/run/php-fpm.atom.sock`, `pm.max_children` 50→**15**, `start_servers` 4, spare 2/8, `request_terminate_timeout=300`) — the preventive fix for the "too many sessions/processes" concern (isolates atom from the shared `www` pool). Added the **ProtectSystem `ReadWritePaths` drop-in** (`/etc/systemd/system/php8.3-fpm.service.d/atom-storage.conf`) for `storage` + `bootstrap/cache`, per the standing rule for Laravel apps under `/usr/share/nginx` (else web requests 500 on log writes). `setfacl` on storage.
- **nginx:** enabled `atom.theahg.co.za.conf` (already a full Heratio vhost — root `/usr/share/nginx/atom/public`, `/explorer` `/ric-api` `/sparql` `/iiif`), repointed `fastcgi_pass` → `/run/php-fpm.atom.sock`, using the expanded cert. Removed the `.disabled` symlink.

## Verification
- `deploy-check` passes (vendor sync, `composer audit` clean, boots).
- DB confirmed `atom`; `information_object` count **454,393**.
- Live: `/`, `/index.php`, `/login`, `/index.php/actor/browse` → 200; `informationobject/browse` → 302. No errors in the app log.
- TLS validates without `-k`; cert SAN matches `atom.theahg.co.za`.

## Reversibility
- Old Symfony AtoM (`atom.anc`, 71 GB) + its disabled pool untouched on disk.
- Backups in the session scratchpad (`atom-schema-*.sql.gz`, `atom-ahg-tables-*.sql.gz`, `atom.env.bak`). All schema changes additive.

## Follow-ups (optional)
- Reconcile the ~100 tables' FK constraints properly (blocked today by the `atom_landing_page.id` type divergence).
- Decide the long-term disposition of the Symfony `atom.anc` (retire once Heratio-over-atom is confirmed good).
