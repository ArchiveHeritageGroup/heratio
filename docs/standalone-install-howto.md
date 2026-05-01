# Heratio - Standalone Install Guide

This guide walks an operator through installing **Heratio** on a fresh Linux box. By the end you will have a running archival platform with admin login, an empty GLAM browse, and the option to enable IIIF deep-zoom, semantic search, and remote AI services.

> **Heratio vs OpenRiC:** This guide installs Heratio only. OpenRiC (the standalone RiC SPARQL/SHACL engine) is a separate product with its own install - see the [OpenRiC repo](https://github.com/ArchiveHeritageGroup/openric). Heratio includes the in-app RiC views (RiC Context panel, JSON-LD export, Graph Explorer link) without OpenRiC. Add OpenRiC only if you want a public RiC-O endpoint.

---

## 1. Pick your install method

| Method | Use when | Update flow |
|---|---|---|
| **Git clone** (recommended) | Standard deployments, easy updates | `git pull && sudo bin/install` |
| **Tarball release** | Air-gapped, no `git` available | Re-download tarball, replace `/usr/share/nginx/heratio`, `sudo bin/install` |
| Docker | Future - not yet | n/a |

Both methods run the same `bin/install` and produce identical results.

---

## 2. System requirements

| Component | Minimum | Recommended | Install |
|---|---|---|---|
| OS | Ubuntu 22.04 LTS | Ubuntu 24.04 LTS | (any modern Linux works) |
| CPU / RAM | 2 vCPU / 4 GB | 4 vCPU / 8 GB | n/a |
| PHP | 8.3 | 8.3 | `sudo apt-get install -y php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-intl php8.3-gd php8.3-zip php8.3-bcmath` |
| MySQL | 8.0 | 8.0 / 8.4 | `sudo apt-get install -y mysql-server` |
| Nginx | 1.18+ | 1.24 | `sudo apt-get install -y nginx-full` |
| Elasticsearch | 7.17 / 8.x | 8.13 | see Elastic docs |
| Redis | 6+ | 7 | `sudo apt-get install -y redis-server` |
| Node | 18+ | 20 | `curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -` |
| Composer | 2.x | 2.x | `curl -fsSL https://getcomposer.org/installer \| php && sudo mv composer.phar /usr/local/bin/composer` |
| git, curl, unzip | any | any | `sudo apt-get install -y git curl unzip` |

**Optional (for sub-installers later):**
- Java 11+ (Cantaloupe IIIF)
- Disk for Qdrant storage

---

## 3. Pre-install checklist

```bash
# 1. install OS dependencies (Ubuntu/Debian - adapt for RHEL/Arch)
sudo apt-get update
sudo apt-get install -y \
  php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-intl php8.3-gd php8.3-zip php8.3-bcmath \
  mysql-server nginx-full redis-server \
  git curl unzip openssl

# 2. install Node 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# 3. install Composer 2
curl -fsSL https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# 4. install Elasticsearch (8.x example - see Elastic docs for current)
wget -qO- https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo gpg --dearmor -o /usr/share/keyrings/elasticsearch-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/elasticsearch-keyring.gpg] https://artifacts.elastic.co/packages/8.x/apt stable main" | sudo tee /etc/apt/sources.list.d/elastic-8.x.list
sudo apt-get update && sudo apt-get install -y elasticsearch
sudo systemctl enable --now elasticsearch

# 5. configure MySQL - set root password and allow socket auth
sudo mysql_secure_installation
```

---

## 4. Install Heratio

### Method A - Git clone (recommended)

```bash
# choose any path; /usr/share/nginx/heratio matches the existing convention
sudo git clone https://github.com/ArchiveHeritageGroup/heratio.git /usr/share/nginx/heratio
cd /usr/share/nginx/heratio

# pin to a specific release (recommended for production)
sudo git checkout v1.36.0    # or whatever the latest tag is

# run the installer
sudo bin/install --domain=mysite.example --admin-email=admin@mysite.example
```

### Method B - Tarball

```bash
VERSION=1.36.0
curl -L -o /tmp/heratio.tar.gz \
  https://github.com/ArchiveHeritageGroup/heratio/releases/download/v${VERSION}/heratio-${VERSION}.tar.gz
sudo tar -xzf /tmp/heratio.tar.gz -C /usr/share/nginx/
cd /usr/share/nginx/heratio
sudo bin/install --domain=mysite.example --admin-email=admin@mysite.example
```

### What `bin/install` does

The installer runs **14 idempotent stages**. Re-running picks up where it left off - safe to interrupt.

| # | Stage | Skip flag |
|---|---|---|
| 1 | Preflight (PHP 8.3+, MySQL, ES, Node, Composer, git) | - |
| 2 | `composer install --no-dev` | - |
| 3 | `npm ci && npm run build` | - |
| 4 | `.env` from `.env.example` + `php artisan key:generate` | - |
| 5 | `CREATE DATABASE heratio` | - |
| 6 | Load `database/core/0[0-3]_*.sql` (370 tables - Qubit + ACL + accession + framework) | - |
| 7 | `php artisan heratio:install-bootstrap --pass=2` (475 plugin tables) | - |
| 8 | Load `database/seeds/0[0-7]_*.sql` (50 taxonomies, 344 terms, 67 menus, 187 settings, 7 ACL groups, 3 static pages, eras, report templates) | - |
| 9 | Create admin user (`object` + `actor` + `actor_i18n` + `user`) | - |
| 10 | Create storage paths under `${INSTALL_DIR}/uploads` (override with `--storage-path`) | - |
| 11 | `php artisan ahg:es-reindex --drop` (build empty ES indices) | `--skip-es` |
| 12 | Render nginx vhost from `config/nginx/heratio.conf.template` and `systemctl reload nginx` | `--skip-nginx` |
| 13 | Curl smoke test of `/` | - |
| 14 | Print URL + admin email + one-time password | - |

**At the end you'll see:**

```
═══════════════════════════════════════════════════════════════════════════════
  Heratio installed
═══════════════════════════════════════════════════════════════════════════════
  URL          : https://mysite.example/
  Admin login  : https://mysite.example/admin/login
    Email      : admin@mysite.example
    Password   : a1B2c3D4e5F6g7H8    ← printed once. Save it now.
  Database     : heratio@127.0.0.1
  Storage      : /usr/share/nginx/heratio/uploads
  Elasticsearch: http://localhost:9200  (prefix: heratio_)
═══════════════════════════════════════════════════════════════════════════════
```

**Save the admin password immediately** - it is not stored anywhere else and not retrievable from the database (it's bcrypt-hashed on insert).

---

## 5. SSL - set up HTTPS

The installer renders an HTTP-only vhost; SSL is your concern. Recommended:

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d mysite.example
```

This adds `listen 443 ssl` blocks and HTTPS redirects to your vhost automatically.

---

## 6. Optional - IIIF deep-zoom (Cantaloupe)

Required only if you serve TIFF or JP2 master images and want pan-and-zoom. JPEGs and PNGs serve directly via nginx without it.

```bash
sudo apt-get install -y default-jre-headless
sudo bin/install-cantaloupe \
  --version=5.0.6 \
  --port=8182 \
  --domain=mysite.example \
  --uploads-path=/usr/share/nginx/heratio/uploads
```

Then edit your nginx vhost (`/etc/nginx/sites-available/mysite.example.conf`) and **uncomment** the line:

```nginx
include snippets/heratio-iiif.conf;
```

Reload: `sudo nginx -t && sudo systemctl reload nginx`.

---

## 7. Optional - Semantic search (Qdrant)

Required for: semantic search (`/search/semantic`), image-similarity search, NER vector index. Lexical search via Elasticsearch works without it.

```bash
sudo bin/install-qdrant \
  --version=1.17.0 \
  --port=6333 \
  --storage=/var/lib/qdrant
```

The installer creates three collections (`archive_records`, `archive_images`, `anc_records`) with 768-dim cosine vectors and sets the relevant Heratio settings.

To populate the index from existing records:

```bash
cd /usr/share/nginx/heratio
php artisan ahg:qdrant-index --collection=archive_records
```

---

## 8. Optional - AI services

> Heratio is an **AI client**, not an AI host. Set up your own Ollama / vLLM / OpenAI-compatible endpoint on a separate GPU box (or use a managed service), then point Heratio at it.

In `/admin/settings`, set:

| Setting | Example value |
|---|---|
| `voice_local_llm_url` | `http://192.168.0.78:11434/api/chat` |
| `htr_endpoint` | `http://192.168.0.78:11434/api/generate` |
| `ner_endpoint` | `http://192.168.0.78:11434/api/generate` |
| `condition_endpoint` | `http://192.168.0.78:11434/api/generate` |

Or via CLI: `php artisan ahg:settings-set voice_local_llm_url 'http://...'`.

See `docs/ai-host-setup.md` for AI host installation guidance (operator's responsibility - Heratio does not bundle Ollama or any AI runtime).

---

## 9. Optional - OpenRiC (separate product)

If you want a public RiC-O SPARQL endpoint (e.g. `ric.mysite.example`):

```bash
git clone https://github.com/ArchiveHeritageGroup/openric.git /usr/share/nginx/openric
cd /usr/share/nginx/openric
sudo bin/install --domain=ric.mysite.example
```

Then point Heratio at it:

```bash
cd /usr/share/nginx/heratio
php artisan ahg:settings-set openric_base_url https://ric.mysite.example
```

OpenRiC has its own database (`openric`), its own web service, and its own install procedure. See the OpenRiC repo for details.

---

## 10. Updating

```bash
cd /usr/share/nginx/heratio
sudo git fetch --tags
sudo git checkout v1.37.0          # or whatever new release
sudo bin/install                    # idempotent - re-runs migrations + reload
```

`bin/install` on an existing install:
- Skips composer/npm if vendor/ + public/build/ are up-to-date
- Leaves `.env` alone
- Doesn't touch the database (CREATE TABLE IF NOT EXISTS)
- Re-runs `heratio:install-bootstrap --pass=2` (idempotent)
- Re-runs seeds (INSERT IGNORE - no row duplication)
- Doesn't recreate the admin user
- Doesn't change nginx vhost
- Reloads nginx

---

## 11. Troubleshooting

### Install aborts at preflight

The error message names the missing dependency and the apt-get command to fix it. Install it and re-run.

### Stage 6 (core schema) fails with FK errors

The four files in `database/core/` are designed to load on an empty MySQL 8 with `utf8mb4_unicode_ci` collation. If you see FK errors:
1. Check MySQL version: `mysql --version` - must be 8.0+.
2. Drop and recreate the database: `mysql -u root -e 'DROP DATABASE heratio; CREATE DATABASE heratio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'`.
3. Re-run `bin/install`.

### Stage 7 (plugin schema) reports `ran=0 skipped=N` but tables are missing

Check the Laravel log: `tail /usr/share/nginx/heratio/storage/logs/laravel.log`. The `PackageInstaller` logs a `WARN` line per failed file.

### Stage 8 (seeds) errors with "Unknown column"

Indicates a schema/seed mismatch - usually an out-of-date `database/seeds/` against a newer schema. Re-generate seeds with:

```bash
cd /usr/share/nginx/heratio
php database/tools/atom-fixture-to-sql.php \
  /path/to/atom/data/fixtures/taxonomyTerms.yml \
  database/seeds/00_taxonomies.sql
```

### Stage 9 (admin user) fails with FK error on `actor.id`

The `object` table needs to exist before the admin user. This stage ordering is enforced by stages 6-8 - if those skipped or partially failed, the admin insert breaks. Re-run from stage 6 (DROP DATABASE + re-run `bin/install`).

### Stage 11 (Elasticsearch) - `php artisan ahg:es-reindex` returns warnings

ES reindex on an empty DB is fast but emits warnings about missing pre-existing indices. Safe to ignore on first install. Re-run later: `php artisan ahg:es-reindex --drop`.

### Stage 12 (nginx) - `nginx -t` fails

The rendered vhost path may collide with an existing site. Edit `/etc/nginx/sites-available/${DOMAIN}.conf` to fix, or pass `--skip-nginx` to the installer and write the vhost by hand.

### "Database lock timeout" during plugin schema load

A previous run is still holding a lock. Find and kill: `mysql -u root -e 'SHOW PROCESSLIST'` then `KILL <pid>`. Re-run `bin/install`.

### How to reset and start over

```bash
mysql -u root -e 'DROP DATABASE heratio'
sudo rm -rf /usr/share/nginx/heratio/uploads /usr/share/nginx/heratio/.env
sudo bin/install --domain=mysite.example --admin-email=...
```

---

## 12. What's next

- Browse `/admin/dropdowns` to customise enumerated values per institution.
- `/admin/repository/add` to create your first repository.
- `/informationobject/add` to add your first archival description, OR
- `/admin/import` to bulk-import EAD/CSV/MODS.
- Read `docs/data-migration-user-guide.md` if you're moving from AtoM.
- Read `docs/scanner-capture-user-guide.md` if you're capturing physical material.

For development / contributing, see `CLAUDE.md` and the per-package READMEs under `packages/*/README.md`.
