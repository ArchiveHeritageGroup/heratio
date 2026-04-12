# CLAUDE.md - Heratio

## Project Overview

**Heratio** is a standalone Laravel 12 archival management system. It is a complete GLAM (Gallery, Library, Archive, Museum) platform with its own database, Elasticsearch indices, and storage. It is a monorepo with 88 packages in `packages/`.

**Market positioning:** Heratio is built for the **international** GLAM market. The core platform is jurisdiction-neutral. Country-specific compliance regimes (GRAP 103, POPIA, PAIA, IPSAS, NAZ, CDPA, NMMZ, etc.) are implemented as **pluggable per-market modules** that sit alongside the core — they are NOT the core. South Africa, Zimbabwe, the rest of SADC, Europe, North America, Asia-Pacific are all target markets and must be treated equally. Never frame the product, examples, or defaults as SA-specific. When adding a new compliance feature, ask "what other markets need this?" before merging it into core.

**Owner:** Johan Pieterse (johan@theahg.co.za)
**Organization:** The Archive and Heritage Group (Pty) Ltd
**GitHub:** https://github.com/ArchiveHeritageGroup/heratio

## Git & Commit Rules

- **NEVER execute git commit or git push directly.** Always supply the commands to the user and let them execute.
- **ALWAYS use `./bin/release` for pushes** — every push must bump a version (patch/minor/major).
- **Do NOT ask for permission to commit.** Just provide the commit/release command.
- Format: supply the user with the complete command(s) to run:
  ```bash
  cd /usr/share/nginx/heratio
  git add <files>
  ./bin/release patch "Short description of changes"
  ```
- For issue-linked releases: `./bin/release patch "Description" --issue 42`

## Server Environment

- **Server:** 192.168.0.112
- **PHP:** 8.3
- **Database:** MySQL 8 — database: `heratio`, user: `root`, password: `Merlot@123`, socket auth
- **App path:** `/usr/share/nginx/heratio`
- **AtoM path:** `/usr/share/nginx/archive` (read-only reference for migration)
- **AI Server:** 192.168.0.78 (RTX 3070, 8GB VRAM, Ollama with llava:7b/13b, mistral:7b)
  - Configured in `ahg_settings.voice_local_llm_url`
  - Firewall: port 11434 open, `OLLAMA_HOST=0.0.0.0`

### Storage Paths

Centralised in `config/heratio.php` — **no hardcoded paths anywhere**.

| Config Key | .env Variable | Default (local install) | This Server |
|---|---|---|---|
| `heratio.storage_path` | `HERATIO_STORAGE_PATH` | `{app}/uploads` | `/mnt/nas/heratio` |
| `heratio.uploads_path` | `HERATIO_UPLOADS_PATH` | `{storage_path}` | `/mnt/nas/heratio/archive` |
| `heratio.backups_path` | `HERATIO_BACKUPS_PATH` | `{storage_path}/backups` | `/mnt/nas/heratio/backups` |

### Elasticsearch

Heratio has its own ES indices, separate from any other application.

| Setting | .env Variable | Value |
|---|---|---|
| Host | `ELASTICSEARCH_HOST` | `http://localhost:9200` |
| Prefix | `ELASTICSEARCH_PREFIX` | `heratio_` |

**Indices:** `heratio_qubitinformationobject`, `heratio_qubitactor`, `heratio_qubitterm`, `heratio_qubitrepository`

**Reindex:** `php artisan ahg:es-reindex` (options: `--clone-from=archive_`, `--drop`, `--index=informationobject`, `--batch=500`)

### IIIF Image Server (Cantaloupe)

Deep-zoom viewer for TIFF/JP2 files via [Cantaloupe](https://cantaloupe-project.github.io/) IIIF Image API 3.0.

| Setting | Value |
|---|---|
| Install path | `/opt/cantaloupe-5.0.6/` |
| Port | `8182` |
| Config | `/opt/cantaloupe-5.0.6/cantaloupe.properties` |
| Delegates | `/opt/cantaloupe-5.0.6/delegates.rb` |
| Nginx proxy | `location ^~ /iiif/` → `http://127.0.0.1:8182/iiif/` |

- Path resolution is hostname-based via `delegates.rb` `INSTANCE_PATHS`
- IIIF identifiers use `_SL_` as path separator (e.g. `uploads_SL_r_SL_repo_SL_hash_SL_image.tiff`)
- `ahg-iiif-viewer.js` auto-detects TIFF/JP2 and routes to Cantaloupe
- Nginx `^~` prefix is required to prevent static file regex from intercepting `.jpg` tile requests
- Full setup: `docs/cantaloupe-iiif-setup.md`

## Architecture

```
/usr/share/nginx/heratio/          ← Laravel 12 app
├── app/                           ← Core app (auth, providers)
├── config/heratio.php             ← Central storage path config
├── packages/                      ← 88 packages (each = Laravel package)
│   ├── ahg-core/                  ← Models, base services, pagination, components
│   ├── ahg-theme-b5/             ← Layouts, nav, footer, static assets
│   ├── ahg-settings/             ← Settings dashboard, Dropdown Manager
│   ├── ahg-search/               ← ES service, reindex command
│   ├── ahg-display/              ← GLAM browse, advanced search
│   ├── ahg-information-object-manage/ ← IO browse/show/edit (ISAD), condition, provenance
│   ├── ahg-actor-manage/         ← Actor browse/show
│   ├── ahg-repository-manage/    ← Repository browse/show (ISDIAH)
│   ├── ahg-research/             ← Full research portal (83 tables, auto-seed)
│   ├── ahg-spectrum/             ← Spectrum 5.1 procedures
│   ├── ahg-heritage-manage/      ← Heritage accounting (jurisdiction-pluggable; ships with GRAP 103 module for SA market)
│   ├── ahg-ric/                  ← RiC entity management
│   ├── ahg-cart/                 ← Shopping cart & e-commerce
│   ├── ahg-ai-services/         ← AI tools (HTR, NER, condition scan)
│   ├── ahg-api/                  ← REST API v1/v2 with key auth
│   ├── ahg-ingest/               ← Data ingest wizard (CSV/file batch import)
│   ├── ahg-reports/              ← Central dashboards, report builder
│   └── ... (88 total)
├── docs/                          ← User guides, technical docs, ADRs
├── bin/release                    ← Version bump + git tag + push + GH release
├── version.json                   ← Current version
└── composer.json                  ← Requires all packages via path repositories
```

### Key Packages

| Package | Purpose |
|---|---|
| `ahg-research` | Full research portal: workspace, projects, reports, bibliographies, annotations, reproductions, bookings, workspaces, ODRL policies, API keys, notifications, walk-ins, equipment, seats |
| `ahg-information-object-manage` | Archival description CRUD, condition reports, provenance, digital objects, 3D viewer |
| `ahg-spectrum` | Spectrum 5.1 museum procedures, condition photos, privacy compliance hooks (POPIA module for SA, GDPR module for EU, etc.) |
| `ahg-settings` | AHG Settings, Dropdown Manager (all enumerated values) |
| `ahg-search` | Elasticsearch service, `ahg:es-reindex` command |
| `ahg-display` | GLAM browse (card/grid/table/full views), advanced search, ancestor filter (lft/rgt) |
| `ahg-ingest` | Data ingest wizard: configure → upload → map → validate → preview → commit. CSV templates, AI processing (OCR, NER, virus scan, summarize, spell check, format ID, face detect, translate) |
| `ahg-reports` | Central dashboards with links to all admin modules |
| `ahg-help` | 221+ help articles, searchable |

## Database Rules

- **Heratio has its own database** (`heratio`). AtoM's database (`archive`) is read-only reference.
- **NEVER execute INSERT, UPDATE, DELETE, ALTER, DROP** without asking the user first.
- **Read-only queries (SELECT) are permitted** for investigation.
- **The DB tables are the source of truth.** Always `DESCRIBE table_name` to verify before coding.

## Key Technical Patterns

### Class Table Inheritance
Entity hierarchy: Object → Actor → User/Donor/Repository/RightsHolder.
- `actor_i18n` has: authorized_form_of_name, history, mandates, etc.
- Entity-specific `*_i18n` tables have entity-specific fields only
- Controllers must join BOTH `actor_i18n` and entity-specific i18n tables

### Relation Table
Generic `relation` table (subject_id, object_id, type_id) for many-to-many links.

### Publication Status
Stored in `status` table (type_id=158), NOT in `information_object`.

### Dropdown Manager
**NEVER hardcode enumerated values.** All dropdowns, select options, and status values come from `ahg_dropdown` table via the Dropdown Manager (`/admin/dropdowns`). Each taxonomy is a group of values (e.g. `seat_type`, `equipment_type`, `id_type`, `equipment_condition`).

### ODRL Rights Policies
Digital rights enforcement via `OdrlService` and `OdrlPolicyMiddleware`. Policies stored in `research_rights_policy`, enforced on archival description viewing (`odrl:use`) and printing (`odrl:reproduce`). No policies = access allowed. Admins bypass all policies.

### Package Pattern
Each package has: `composer.json`, `ServiceProvider` (loads routes + views + commands), `Controllers/`, `Services/`, `routes/web.php`, `resources/views/`.

### Package Install Pattern
Packages with DB tables include:
- `database/install.sql` — `CREATE TABLE IF NOT EXISTS` for all tables
- `database/seed_dropdowns.sql` — `INSERT IGNORE` for dropdown values
- `ServiceProvider` auto-seeds on first boot if entries are missing

### BrowseService Pattern
`AhgCore\Services\BrowseService` is the base for all browse pages. Subclasses override `getTable()`, `getI18nTable()`, `getI18nNameColumn()`.

### Browse Routing
`/informationobject/browse` redirects to `/glam/browse` (GLAM display package) with param translation: `collection` → `ancestor`, `topLod` → `topLevel`, `onlyMedia` → `hasDigital`. There is only ONE browse page — the GLAM browse in `ahg-display`. The IO package does NOT have its own browse view.

### Ancestor/Collection Filter
The GLAM browse `ancestor` parameter uses MPTT `lft/rgt` range (`io.lft >= ancestor.lft AND io.rgt <= ancestor.rgt`) to include the record itself and all descendants. NOT `parent_id` (direct children only).

### Clipboard
Clipboard is handled entirely by the AtoM theme bundle JS (`ahgThemeB5Plugin.bundle.js`). It uses localStorage key `"clipboard"`. An inline normalizer script in `<head>` ensures all required type keys exist (`informationObject`, `actor`, `repository`, `accession`, `informationobject`, `library`, `dam`) to prevent `indexOf` crashes. Do NOT load standalone `display-mode.js` or `voiceCommands.js` — they are already in the bundle and double-loading causes `Identifier already declared` errors that kill all JS.

### Slug Catch-All Route
`/{slug}` route in `ahg-information-object-manage` is a catch-all for IO show pages. It has a regex exclusion list for all known path prefixes (`ingest`, `admin`, `research`, `glam`, etc.). When adding new top-level routes, **add the prefix to this exclusion list** or the slug resolver will intercept the URL.

## Reserved Words
MySQL 8 reserved words that need backtick escaping: `groups`, `rank`, `row_number`, `function`.

## Files That Must Not Be Modified Without Approval
```
bin/release
version.json (modified by bin/release only)
```

## No ENUM Columns

**NEVER use ENUM columns.** All enumerated values go in the Dropdown Manager. Use `VARCHAR(N)` columns. No hardcoded select options in views — always query `ahg_dropdown`.

## Branding

This is **Heratio** — not AtoM. Do NOT reference "AtoM" in code, comments, descriptions, or user-facing text. The only acceptable references are in technical documentation explaining migration source.

## Migration Rules (CRITICAL)

### Always Check AtoM First
**ALWAYS first check if code exists in `/usr/share/nginx/archive`** before writing new code. Search the AtoM codebase for existing templates, controllers, actions. Reuse and convert — do not reinvent.

### Migrate = Copy, Not Recreate
**Migration means copying the COMPLETE file from `archive/` to `heratio/` and converting syntax.** The AtoM source file is the blueprint — every field, every section, every conditional must be preserved.

### Compare Fields Before and After
**Compare ALL fields between AtoM source and Heratio target.** If any field exists in AtoM but is missing in Heratio, add it. Zero omissions.

### "Clone/Match/Migrate" = Do Everything in One Pass
**Do ALL of it in one pass.** If AtoM has it, Heratio gets it. The answer to "should I also do X?" is always **yes**.

## Quality Standard

Every page must match AtoM exactly. Full screens, full theme, full menus, full metadata, full digital objects.

- Never use placeholder comments like "// TODO: implement"
- Never return empty collections or hardcoded dummy data
- Every controller method must query the database via the injected Service class
- Every form must have full validation rules
- Views must render real data, not static HTML
- No hardcoded values — use Dropdown Manager/Taxonomy/DB
- Always apply the central theme and colours

## Fresh Install Procedure

```bash
# 1. Create database
mysql -u root -e "CREATE DATABASE heratio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Run install SQL for each package
mysql -u root heratio < packages/ahg-research/database/install.sql

# 3. Seed dropdowns (or auto-seeded on first boot)
php artisan ahg:seed-research-dropdowns

# 4. Create Elasticsearch indices
php artisan ahg:es-reindex --drop

# 5. Configure .env
HERATIO_STORAGE_PATH=/path/to/storage
HERATIO_UPLOADS_PATH=/path/to/uploads
ELASTICSEARCH_HOST=http://localhost:9200
ELASTICSEARCH_PREFIX=heratio_
```
