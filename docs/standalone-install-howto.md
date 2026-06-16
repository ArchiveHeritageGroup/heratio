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
| Redis | optional | 7 | `sudo apt-get install -y redis-server` (only if switching cache/queue to redis) |
| Node | 18+ | 20 | `curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -` |
| Composer | 2.x | 2.x | `curl -fsSL https://getcomposer.org/installer \| php && sudo mv composer.phar /usr/local/bin/composer` |
| git, curl, unzip | any | any | `sudo apt-get install -y git curl unzip` |

**Optional (for sub-installers later):**
- Java 11+ (Cantaloupe IIIF)
- Disk for Qdrant storage

---

## 3. Pre-install checklist

> Install these core prerequisites with the commands below. (`bin/install-host-tools.sh` is a *separate* installer for the optional 3D / provenance tools - FBX2glTF, c2patool, point-cloud converter - covered in Section 8; it does **not** install PHP/MySQL/nginx/ES.)

```bash
# 1. install OS dependencies (Ubuntu/Debian - adapt for RHEL/Arch)
sudo apt-get update
sudo apt-get install -y \
  php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-intl php8.3-gd php8.3-zip php8.3-bcmath \
  mysql-server nginx-full \
  git curl unzip openssl
# redis-server is OPTIONAL - only if you switch cache/queue to redis; the
# installer does not require it.

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

# 5. MySQL: on a fresh box, leave root on the default auth_socket login and do
#    nothing here - bin/install auto-provisions a dedicated `heratio` user.
#    (Optional hardening: `sudo mysql_secure_installation`. But if you set a root
#     password, you must then pass it with --db-pass=... - see Section 4.)
```

---

## 4. Install Heratio

### Method A - Git clone (recommended)

```bash
# choose any path; /usr/share/nginx/heratio matches the existing convention
sudo git clone https://github.com/ArchiveHeritageGroup/heratio.git /usr/share/nginx/heratio
cd /usr/share/nginx/heratio

# (optional) pin to the latest release tag for production
sudo git fetch --tags && sudo git checkout "$(git describe --tags --abbrev=0)"

# run the installer - one command (COMPOSER_ALLOW_SUPERUSER lets composer run as root)
sudo COMPOSER_ALLOW_SUPERUSER=1 bin/install \
  --domain=mysite.example \
  --admin-email=admin@mysite.example \
  --admin-password='choose-a-strong-one'
```

**Worked example - a LAN box reached by its IP** (here `192.0.2.50` - **replace with your server's own IP or resolvable hostname**):

```bash
git clone https://github.com/ArchiveHeritageGroup/heratio.git /usr/share/nginx/heratio
cd /usr/share/nginx/heratio
sudo COMPOSER_ALLOW_SUPERUSER=1 bin/install \
  --domain=192.0.2.50 \
  --admin-email=admin@heratio.local \
  --admin-password='HeratioDev2026'
```

Then browse **`http://192.0.2.50/login`** and sign in with `admin@heratio.local` / the password you set.

> The `--domain` value must be **exactly how you will reach the box** - the IP if you browse by IP, or a hostname that resolves to it. Do **not** leave the `mysite.example` placeholder: the post-install smoke test will report `GET / -> 000000` and the site will be unreachable / show no menu, because nginx's `server_name` and the app's `APP_URL` won't match your browser's address.

**Flags - everything needed for a hands-off run:**

| Flag | Required? | Notes |
|---|---|---|
| `--domain=` | **yes** | The host you browse to. Use the **IP** (e.g. `192.168.0.60`) if you reach the box by IP; use an **FQDN/hostname** only if it resolves to this box (DNS or `/etc/hosts`) on the machine you log in from. The post-login redirect uses this value, so a mismatch breaks login. |
| `--admin-email=` | **yes** | Login email for the first admin user. |
| `--admin-password=` | no | If omitted, a **random password is generated and printed once** at the end - copy it immediately (it is bcrypt-hashed, not retrievable later). Pass one to set your own. |
| `--db-pass=` | only for existing MySQL | See "MySQL credentials" below. |
| `--non-interactive` | no | Never prompt; abort if a required value is missing. Use in scripts/CI. |
| `--skip-es` / `--skip-nginx` | no | Skip the Elasticsearch reindex / nginx vhost render. |
| `--fresh` | no | Drop+recreate the DB before loading (clean rebuild; **wipes data**). Only for a re-install onto a populated DB - a fresh box does not need it. |
| `--https` | no | Set when TLS terminates in front of the app (secure cookies + https URLs). |

**MySQL credentials - the installer auto-detects which applies:**

- **Fresh box (recommended):** if MySQL `root` is still on the default `auth_socket`
  login (you did NOT set a root password), the installer reaches MySQL over the local
  socket and **auto-provisions a dedicated `heratio` DB user with a generated password**,
  written into `.env`. Add nothing - not even `--db-pass`.
- **Existing MySQL with a known password** (e.g. installing alongside AtoM): pass it with
  `--db-pass='<mysql root password>'`.

> **Do NOT pre-create the `heratio` database** - let the installer create it. If a stale
> `heratio` DB already exists, either `DROP DATABASE heratio` (don't recreate it) or add
> `--fresh`. Pre-creating it lets stage 4's boot auto-install package tables first, which
> then collide with the core schema (FK error 3780).

**When it finishes** it prints the URL, admin email and password (see the box below). Log
in at **`http://<your --domain>/login`** - note `/login`, *not* `/admin/login` - with that
exact email + password. **Copy the password before closing the terminal** if you let it
auto-generate one.

### Pre-selecting which plugins are enabled (optional)

A fresh install registers **every** plugin and enables them all by default, except
`ahgFederationPlugin` (shipped installed but **disabled**). To choose a different
starting set, edit **`database/seeds/08_base_plugins.sql` before running `bin/install`** -
set a plugin's `is_enabled` to `0` and `status` to `'disabled'` (federation is the worked
example already in that file). Plugins can also be toggled any time after install from
**`/admin/ahgSettings/plugins`**.

> **Do not disable the foundation set or the theme** - the platform requires them
> (without them you get no admin UI, no styling, and broken auth):
> `ahgCorePlugin`, `sfPropelPlugin`, `qbAclPlugin`, `sfPluginAdminPlugin`,
> `ahgSettingsPlugin`, `ahgSecurityClearancePlugin`, and the theme `ahgThemeB5Plugin`.
> The seed's trailing `UPDATE` force-re-enables exactly these, so disabling them in the
> INSERT block has no effect anyway.

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
  Login        : https://mysite.example/login
    Email      : admin@mysite.example
    Password   : <auto-generated-password>    ← printed once. Save it now.
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

## 8. Optional - 3D + provenance host tools

Required for: 3D model optimisation (OBJ/FBX -> glTF + Draco), FBX uploads, and C2PA Content
Credentials embedding. Each feature degrades gracefully when its tool is absent, so this is
optional - but run it on any host that should support 3D ingest or provenance embedding.

```bash
cd /usr/share/nginx/heratio
sudo bin/install-host-tools.sh
```

Idempotent (re-run safe). It installs, per host:
- **model-tools** (`obj2gltf` + `gltf-transform`) into `/opt/ahg-model-tools` - see `docs/model-optimisation-setup.md`
- **FBX2glTF** into `/opt/ahg-model-tools/FBX2glTF` - see `docs/fbx2gltf-setup.md`
- **c2patool** into `/usr/local/bin/c2patool` - see `docs/c2patool-setup.md`
- **PotreeConverter** (point clouds) is flagged but built separately - see `docs/pointcloud-setup.md`

Heratio reads these via `config/heratio.php` (`model_tools_bin`, `fbx2gltf_bin`, `c2patool_bin`,
`pointcloud_bin`). After installing c2patool, backfill provenance for existing masters with
`php artisan ahg:c2pa-provenance-backfill --commit`.

---

## 9. Optional - AI services

> Heratio is an **AI client**, not an AI host. Run your own Ollama / vLLM / OpenAI-compatible endpoint on a separate GPU box (or use a managed service), then point Heratio at it. Heratio bundles no AI runtime.

Configure the AI endpoints from the **admin Settings UI** (stored in `ahg_ai_settings` / `ahg_ner_settings`):

| Settings page | Configures |
|---|---|
| **Settings → Voice AI** | the chat / LLM endpoint (`voice_local_llm_url`), e.g. `http://YOUR-AI-HOST:11434/api/chat` |
| **Settings → AI Services** (`/admin/ahgSettings/aiServices`) | HTR, NER, summarise and translate endpoints + API key |
| **Settings → AI Condition** (`/admin/ahgSettings/aiCondition`) | the condition-assessment model endpoint |

Use your own host/IP in place of `YOUR-AI-HOST`. Each AI feature degrades gracefully when its endpoint is unset, so configure only what you use.

---

## 10. Optional - OpenRiC (separate product)

If you want a public RiC-O SPARQL endpoint (e.g. `ric.mysite.example`):

```bash
git clone https://github.com/ArchiveHeritageGroup/openric.git /usr/share/nginx/openric
cd /usr/share/nginx/openric
sudo bin/install --domain=ric.mysite.example
```

Then wire Heratio to it from the **RiC dashboard** at `/admin/ric`: it runs a readiness check and, once the SPARQL/Fuseki target is configured there, enables the **Sync to Fuseki** button. See [`docs/ric-sync-setup.md`](ric-sync-setup.md) for the readiness gate and sync configuration.

OpenRiC has its own database (`openric`), its own web service, and its own install procedure. See the OpenRiC repo for details.

---

## 11. Updating

```bash
cd /usr/share/nginx/heratio
sudo git fetch --tags
sudo git checkout "$(git describe --tags --abbrev=0)"   # latest release tag
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

## 12. Troubleshooting

### Install aborts at preflight

The error message names the missing dependency and the apt-get command to fix it. Install it and re-run.

### Stage 6 (core schema) fails with FK errors (error 3780)

Almost always means the DB was not empty when the schema loaded - typically you
pre-created `heratio`, so stage-4's Laravel boot auto-installed package tables
first and they collide with the core schema. Fix:
1. Check MySQL version: `mysql --version` - must be 8.0+.
2. Re-run with a guaranteed-clean DB: `sudo bin/install --fresh …` (drops +
   recreates `heratio` before loading). Or drop it and do NOT recreate -
   `sudo mysql -e 'DROP DATABASE IF EXISTS heratio'` - then re-run (stage 5
   creates it fresh). **Never `CREATE DATABASE heratio` yourself.**

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

The `object` table needs to exist before the admin user. If stages 6-8 skipped or partially failed, the admin insert breaks. Re-run clean: `sudo bin/install --fresh …` (or `sudo mysql -e 'DROP DATABASE IF EXISTS heratio'` without recreating, then re-run).

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

## 13. What's next

- Browse `/admin/dropdowns` to customise enumerated values per institution.
- `/admin/repository/add` to create your first repository.
- `/informationobject/add` to add your first archival description, OR
- `/admin/import` to bulk-import EAD/CSV/MODS.
- Read `docs/data-migration-user-guide.md` if you're moving from AtoM.
- Read `docs/scanner-capture-user-guide.md` if you're capturing physical material.

For development / contributing, see `CLAUDE.md` and the per-package READMEs under `packages/*/README.md`.
