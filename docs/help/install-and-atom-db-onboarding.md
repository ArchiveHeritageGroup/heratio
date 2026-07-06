> Heratio Help Center article. Category: Installation.

# Install Heratio & onboard an existing AtoM database

Operator guide for standing up Heratio from git, and for bringing an **existing AtoM database** fully online over it. Heratio reads the AtoM tables directly (descriptions appear immediately), but several **derived layers** must be built or the site looks empty even though every record is present — the `ahg:onboard-atom-db` command does that in one step.

---

## 1. Install from git

Heratio is a Laravel 12 / PHP 8.3 application. Develop on a dev checkout, then push to prod — never edit application code directly on prod.

```bash
# 1. Clone
git clone git@github.com:ArchiveHeritageGroup/heratio.git /usr/share/nginx/<app>
cd /usr/share/nginx/<app>

# 2. Dependencies (production)
composer install --no-dev --optimize-autoloader

# 3. Environment
cp .env.example .env
php artisan key:generate            # sets APP_KEY (required for encryption at rest)
#   edit .env: DB_DATABASE=<db>, ELASTICSEARCH_HOST/_PREFIX, APP_URL=https://<host>

# 4. Storage perms
chown -R www-data:www-data storage bootstrap/cache
```

### php-fpm — the `ProtectSystem` drop-in (mandatory under /usr/share/nginx)

The system `php8.3-fpm` unit ships `ProtectSystem=full`, which mounts `/usr` **read-only for the worker**. A Laravel app under `/usr/share/nginx/<app>` therefore **cannot write its own `storage/` or `bootstrap/cache`** from a web request and will 500 on log/view writes. Grant it explicitly:

```ini
# /etc/systemd/system/php8.3-fpm.service.d/<app>-storage.conf
[Service]
ReadWritePaths=/usr/share/nginx/<app>/storage
ReadWritePaths=/usr/share/nginx/<app>/bootstrap/cache
```

```bash
systemctl daemon-reload && systemctl restart php8.3-fpm
```

A dedicated pool (`/etc/php/8.3/fpm/pool.d/<app>.conf`, its own socket, tuned `pm.max_children`) isolates the app from the shared `www` pool.

### nginx

Point the vhost `root` at `.../public`, `fastcgi_pass` at the app's php-fpm socket, and use a TLS cert that **includes the hostname** (expand a shared cert with `certbot certonly --nginx --cert-name <cert> --expand -d <all-existing> -d <newhost>`). Enable compression globally — `gzip on` alone only compresses HTML; add `gzip_types … application/javascript text/css image/svg+xml …` or JS/CSS ship uncompressed (a large mobile penalty).

---

## 2. Onboard an existing AtoM database

Heratio coexists with the AtoM schema (it's AtoM-compatible) and adds its own `ahg_*` tables. Over a fresh AtoM DB, **four derived layers start empty** and must be built, in order:

1. **Migrations** — create Heratio's tables against the AtoM base.
2. **Ancestor closure** (`information_object_closure`) — fast hierarchy / ancestor reads (else a slow nested-set fallback).
3. **Elasticsearch index** — full-text search + browse.
4. **GLAM object-type classification** (`display_object_config`) — the standard browse filters by object type; with nothing classified, the browse shows almost nothing. Unmatched records **default to `archive`**.
5. **Facet caches** — GLAM browse facets.

### One command

```bash
sudo -u www-data php artisan ahg:onboard-atom-db --workers=12
```

Runs, in order and idempotently: `migrate --force` → `ahg:build-closure --all` → `ahg:es-reindex --workers=N --bulk-mode` → `ahg:display-auto-detect` → `ahg:display-reindex`. Options:

- `--dry-run` — print the pipeline without running it.
- `--workers=N` — parallel shards for the reindex (the box has spare cores; N× throughput). Build the closure first (this command does) so ancestor lookups use the indexed fast path — essential at national-archive scale.
- `--fresh-index` — drop + rebuild the ES index (default is upsert = no search downtime).
- `--classify-archive` — classify with the fast set-based archive default instead of per-record detection (use when the corpus is a homogeneous archive).
- `--skip-migrate` — schema already current.

Safe to re-run — every step is idempotent.

### After onboarding — verify

```bash
./bin/deploy-check                                  # vendor↔lock, audit, boots
curl -s localhost:9200/_cat/indices?v | grep <prefix>
# open the browse page — the count should match your published record total
```

If records exist but the **browse shows a small subset**, the classification layer isn't built — run `ahg:display-auto-detect` (not a settings workaround). If it's **slow**, build the closure and reindex with `--workers`. Publication status, a stale index, or `default_sector` can *hide* present data — the data is not lost.

---

## Gotchas learned in the field

- **Deploys after a `composer.lock` change must run `composer install`** — a code-only pull leaves the old, possibly-vulnerable vendor. `./bin/deploy-check` catches this.
- **`display_type` lives in `display_object_config.object_type`**, not on `information_object`. Classification defaults unmatched records to `archive`.
- **A full reindex is DB-latency bound**, not CPU bound — parallel workers (`--workers`) + the ancestor closure are what make it scale (see the reindex performance work in the release notes).

---

## Related articles

- [Advanced search](/help/article/advanced-search-user-guide)
- [Backup & restore](/help/article/backup-restore-user-guide)
- [Semantic search plugin](/help/article/ahgsemanticsearchplugin)
