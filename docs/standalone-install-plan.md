# Heratio Standalone Install — Plan

**Status:** Planning document. No implementation yet. Scope is "what must exist before a fresh Linux box can become a working Heratio without touching AtoM."

**Author:** Johan Pieterse / Plain Sailing Information Systems
**Last reviewed:** 2026-04-17

---

## 1. The problem

Heratio lives in `/usr/share/nginx/heratio` and runs against a MySQL database (`heratio`) whose schema and core data were **inherited from AtoM**. The tables `information_object`, `actor`, `term`, `taxonomy`, `setting`, `menu`, and ~200 others come from AtoM's Propel ORM; the seed data in those tables was loaded by AtoM's YAML fixture loader; the 91 AHG plugins living under `/usr/share/nginx/archive/atom-ahg-plugins/` each contributed their own `install.sql`.

When Heratio is deployed to a customer who is not running AtoM, **none of that exists**. A fresh `CREATE DATABASE heratio` will not produce a working Heratio — it will produce a system that crashes on the first page load because `taxonomy.id = 30` (QubitTaxonomy root) isn't there, `ahg_dropdown` is empty, `setting` has no entries, etc.

This plan defines **what a standalone install needs to ship** so that one command on a fresh box yields a functional Heratio with:
- All tables created (core Qubit + all AHG plugin tables + all Heratio-specific tables).
- All static seed data loaded (taxonomies, terms, dropdowns, settings, ACL groups, menus, static pages, eras).
- All derived data bootstrapped (Elasticsearch indices, storage paths, default admin user).

The AtoM codebase itself is **not** a runtime dependency. Heratio is Laravel; AtoM is Symfony/Propel. We only need AtoM's **seed artifacts**, ported into Heratio's install surface.

---

## 2. Current state — what we're starting from

### 2.1 Heratio side

- `packages/` — 88 Laravel packages. Each has a `ServiceProvider`, views, routes, controllers.
- `packages/ahg-research/database/install.sql` — the **only** install.sql in Heratio today.
- `packages/ahg-research/database/seed_dropdowns.sql` — dropdown values for the research module.
- `packages/ahg-ric/database/seed_ric_from_existing.sql` — RiC sync from an existing DB.
- `packages/ahg-settings/database/seed_help_settings.sql` — help article settings.
- `bin/release` — version-bump + push. Not relevant to install.
- Package `ServiceProvider::boot()` methods auto-seed dropdowns on first boot if entries are missing — this is the **existing** safety net.
- `config/heratio.php` — storage path config; no hardcoded paths elsewhere.

Running DB on this server has **974 tables** and **3,824 `ahg_dropdown` rows**. That's the reference we need to reproduce.

### 2.2 AtoM side (read-only reference)

- `/usr/share/nginx/archive/data/sql/lib.model.schema.sql` — **1,467 lines.** Core Qubit schema (information_object, actor, term, taxonomy, slug, object, etc.).
- `/usr/share/nginx/archive/data/sql/plugins.qbAclPlugin.lib.model.schema.sql` — **114 lines.** ACL tables.
- `/usr/share/nginx/archive/data/sql/plugins.qtAccessionPlugin.lib.model.schema.sql` — **203 lines.** Accession tables.
- `/usr/share/nginx/archive/data/fixtures/*.yml` — **6 YAML fixture files** (~17,400 lines total):

  | File | Lines | Content |
  |---|---|---|
  | `taxonomyTerms.yml` | 13,014 | All Qubit taxonomies + terms, multilingual |
  | `menus.yml` | 2,402 | Admin + public navigation menus |
  | `settings.yml` | 1,586 | System settings defaults |
  | `acl.yml` | 309 | ACL groups, permissions, roles |
  | `staticPages.yml` | 101 | Home, About, Contact, Privacy, Terms |
  | `fixtures.yml` | 17 | Small bootstrap — admin user, etc. |

- `/usr/share/nginx/archive/atom-framework/database/install.sql` — AtoM framework tables (access_audit_log, access_request, heritage_*, etc.) — a consolidated schema added by AHG.
- `/usr/share/nginx/archive/atom-framework/database/migrations/*.sql` — 10 migration SQL files layered on the framework.
- `/usr/share/nginx/archive/atom-framework/data/eras/periodo-eras.sql` — PeriodO historical-eras seed.
- `/usr/share/nginx/archive/atom-framework/database/seeders/ExtendedRightsSeeder.php` — PHP seeder for rights taxonomy.
- `/usr/share/nginx/archive/atom-ahg-plugins/*/database/install.sql` — **81 plugin install.sql files**, one per AHG plugin.
- `/usr/share/nginx/archive/atom-ahg-plugins/ahgReportBuilderPlugin/database/seed_templates.sql` — system report templates.
- `/usr/share/nginx/archive/atom-ahg-plugins/ahgHeritagePlugin/data/graph_seed.sql` — heritage graph demo data *(demo only — skip for production standalone install)*.

### 2.3 The gap

- Heratio currently has **1** install.sql. AtoM has **81 + 2 core = 83 schema files** producing the tables Heratio relies on. **All 83 need to be consolidated into Heratio's install surface.**
- The 6 YAML fixtures are only loadable by AtoM's Symfony CLI (`php symfony propel:data-load`). **They must be converted to raw SQL INSERTs** once so Heratio can load them without a Symfony runtime.
- Heratio's `ahg_dropdown` table (3,824 rows) is a newer mechanism that replaces ENUMs — **already** seeded per-package via `ServiceProvider::boot()`. That stays.
- `docs/x7b-psis-install-sql-worklist.md` already tracks 50 Cat-B tables across 22 plugins that Heratio code references but no plugin provisions. Those need to be merged into the source-of-truth install.sql files first, then consolidated.

---

## 3. The target end state

A fresh Ubuntu 22/24 LTS box with MySQL 8, PHP 8.3, Nginx, Elasticsearch 7/8, Redis, Composer, Node, and git, given only:

```bash
git clone git@github.com:ArchiveHeritageGroup/heratio.git /usr/share/nginx/heratio
cd /usr/share/nginx/heratio
./bin/install --domain=mysite.example --admin-email=me@mysite.example
```

…ends up with:

1. `heratio` MySQL database created and populated (~970 tables, ~4,000 dropdown rows, taxonomies and terms loaded, default settings in place, admin user created with a printed-once password).
2. Elasticsearch indices `heratio_qubitinformationobject`, `heratio_qubitactor`, `heratio_qubitterm`, `heratio_qubitrepository` created and reindexed from the empty DB.
3. Storage paths created under the configured `HERATIO_STORAGE_PATH` root.
4. Nginx vhost written, PHP-FPM wired, app URL resolves.
5. `/admin/login` works. `/admin/dashboards` loads. `/admin/dropdowns` shows values. `/informationobject/browse` loads an empty GLAM grid.
6. **Zero** references to AtoM at runtime. AtoM codebase not present, not required.

Cantaloupe (IIIF), Fuseki (RiC), Qdrant (semantic search), Ollama (AI) are **optional services** — install walks the admin through each but does not block install.

---

## 4. Strategy

Three approaches were considered:

| Option | Description | Pros | Cons |
|---|---|---|---|
| **A — Dump-and-ship** | `mysqldump heratio --no-data` for schema + `mysqldump heratio` for seed rows on ~20 reference tables, commit the .sql to the repo | Fastest to produce; reflects exact production reality | Schema + data become opaque binary-ish blobs; drift is invisible; no per-plugin ownership; hard to diff in PR review |
| **B — Compose from sources** | Port the 83 AtoM/AHG schema files and 6 YAML fixtures into Heratio's `packages/*/database/` folders, one package owns one install.sql | Per-package ownership; readable diffs; aligns with existing Heratio `ServiceProvider` auto-seed pattern; clean break from AtoM tree | More one-time work; YAML→SQL conversion needs a script |
| **C — Hybrid (recommended)** | Do B as canonical; auto-generate A in CI as a release artifact for fast install + verification | Both benefits; B stays the source of truth, A is a cached build output | Requires install-verification CI job |

**Recommendation: C.** Own the schema/seeds per-package (option B). Treat the monolithic dump as a *build artifact*, not source.

---

## 5. The install surface (what ships with Heratio)

After this plan is executed, the install surface is:

```
/usr/share/nginx/heratio/
├── bin/
│   └── install                        # NEW — bootstrap script (see §6)
├── database/
│   ├── core/
│   │   ├── 00_core_schema.sql         # NEW — ported lib.model.schema.sql
│   │   ├── 01_acl_schema.sql          # NEW — ported qbAclPlugin schema
│   │   ├── 02_accession_schema.sql    # NEW — ported qtAccessionPlugin schema
│   │   └── 03_framework.sql           # NEW — ported atom-framework/install.sql
│   ├── seeds/
│   │   ├── 00_taxonomies.sql          # NEW — YAML→SQL from taxonomyTerms.yml
│   │   ├── 01_settings.sql            # NEW — YAML→SQL from settings.yml
│   │   ├── 02_menus.sql               # NEW — YAML→SQL from menus.yml
│   │   ├── 03_acl.sql                 # NEW — YAML→SQL from acl.yml
│   │   ├── 04_static_pages.sql        # NEW — YAML→SQL from staticPages.yml
│   │   ├── 05_bootstrap.sql           # NEW — YAML→SQL from fixtures.yml
│   │   ├── 06_eras.sql                # NEW — copied from periodo-eras.sql
│   │   └── 07_report_templates.sql    # NEW — copied from ahgReportBuilderPlugin
│   └── build/
│       └── heratio-full-install.sql   # NEW — auto-generated catenation (option A dump)
├── packages/
│   └── ahg-*/database/
│       ├── install.sql                # NEW — ported from atom-ahg-plugins/<plugin>/database/install.sql
│       └── seed_dropdowns.sql         # EXISTING pattern, extended where needed
└── docs/
    ├── standalone-install-plan.md     # THIS DOC
    ├── standalone-install-howto.md    # NEW — admin-facing install guide
    └── x7b-psis-install-sql-worklist.md  # EXISTING — feeds into §7
```

The convention:

- **Core schema and global fixtures** live in `database/core/` and `database/seeds/` at the repo root. They are not owned by any one package because they underpin everything.
- **Plugin-specific schema** lives in `packages/<plugin>/database/install.sql`. Each Laravel package already has a `ServiceProvider` — that provider becomes responsible for running its own install.sql on first boot (following the existing `ahg-research` pattern).
- **Dropdown seeds** continue to use the existing per-package `seed_dropdowns.sql` + `ServiceProvider::boot()` auto-seed mechanism. No change needed — that's already the Heratio standard.

---

## 6. The `bin/install` script

Single entry point for a fresh install. Idempotent — safe to re-run.

```
bin/install [--domain=<host>] [--admin-email=<email>] [--admin-password=<pw>]
            [--storage-path=<path>] [--skip-es] [--skip-nginx]
            [--non-interactive]
```

Stages, in order:

1. **Preflight** — check PHP 8.3, MySQL 8, Elasticsearch 7+, Redis, Node 18+, Composer, `git`. Abort with a checklist on miss.
2. **Composer install** — `composer install --no-dev` in project root.
3. **NPM build** — `npm ci && npm run build` for bundled assets.
4. **.env bootstrap** — copy `.env.example` → `.env`, fill `APP_URL`, `DB_*`, `ELASTICSEARCH_*`, `HERATIO_*` from flags/prompts, run `php artisan key:generate`.
5. **Database create** — `CREATE DATABASE heratio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`.
6. **Core schema** — load `database/core/*.sql` in name order. 4 files, ~2,000 lines.
7. **Plugin schemas** — load every `packages/*/database/install.sql` in alphabetical order. 88 files target.
8. **Global seeds** — load `database/seeds/*.sql` in name order. 8 files.
9. **Laravel boot sweep** — `php artisan heratio:install-bootstrap` (new command) — fires every `ServiceProvider::boot()` so the auto-seed dropdowns run.
10. **Admin user** — create a single admin with the flag-provided or randomly generated password; print it once.
11. **Storage** — create `HERATIO_STORAGE_PATH`, `HERATIO_UPLOADS_PATH`, `HERATIO_BACKUPS_PATH` with correct permissions.
12. **Elasticsearch** — `php artisan ahg:es-reindex --drop` to create and populate indices.
13. **Nginx** (unless `--skip-nginx`) — write `/etc/nginx/sites-available/heratio.conf` from a template, symlink, `nginx -t && systemctl reload nginx`.
14. **Smoke test** — `curl -I https://<domain>/` expect 200 or redirect; `curl -sS /admin/dropdowns` expect HTML with "Dropdown Manager"; `curl -sS /informationobject/browse` expect 200.
15. **Report** — print URL, admin email+password, ES index status, storage paths, Cantaloupe/Fuseki/Qdrant/Ollama opt-in hints.

Crash behaviour: every stage wraps in `set -e`. Failure at any stage prints the failing stage and exits non-zero. Re-running skips already-done stages (detects via schema presence checks).

---

## 7. Work items — what has to be built

Ordered by dependency. Each item is ~small, scoped, independently releasable.

### Phase 1 — Consolidate schema sources (blocking)

1. **Port core AtoM schema** — Copy `data/sql/lib.model.schema.sql` + `qbAclPlugin` + `qtAccessionPlugin` from AtoM into `database/core/00..02_*.sql`. Find-replace any `CREATE TABLE <name>` → `CREATE TABLE IF NOT EXISTS <name>`. Verify by `mysql -e "SOURCE database/core/00_core_schema.sql"` on an empty DB.
2. **Port AtoM framework schema** — Copy `atom-framework/database/install.sql` into `database/core/03_framework.sql`. Apply the 10 migration files from `atom-framework/database/migrations/*.sql` inline (so the result is one consolidated `CREATE TABLE IF NOT EXISTS` file with everything the migrations would have added).
3. **Port each of the 81 AHG plugin install.sql** into its matching Heratio package:
   - `atom-ahg-plugins/ahgCorePlugin/database/install.sql` → `packages/ahg-core/database/install.sql`
   - `atom-ahg-plugins/ahgSettingsPlugin/database/install.sql` → `packages/ahg-settings/database/install.sql`
   - …one per package. Mapping table in §9 below.
4. **Resolve the x7b worklist** — the 50 Cat-B tables in `docs/x7b-psis-install-sql-worklist.md` need to land in their owning package install.sql files. Already has DDL drafted — just needs placement.
5. **Add `CREATE TABLE IF NOT EXISTS` guard** to every ported file so re-running is safe.
6. **Update each package's `ServiceProvider::boot()`** to invoke its `install.sql` on first boot (follow `ahg-research` pattern — check if a sentinel table exists, if not run the SQL).

### Phase 2 — Convert AtoM YAML fixtures to SQL

1. Write `tools/atom-fixture-to-sql.php` — one-shot converter. Input: one of the 6 YAML files. Output: an SQL file of `INSERT IGNORE` statements. Must handle:
   - Nested `i18n` arrays → multiple rows in `*_i18n` tables (one per culture).
   - Symbolic IDs (`QubitTaxonomy_root`, `QubitAclGroup_anonymous`) → resolve to numeric IDs via a first pass building a symbol-table.
   - `source_culture` field → carry through as-is.
   - Parent references (`parent_id: QubitTaxonomy_root`) → resolve via symbol table.
2. Run the converter once per YAML file. Commit the resulting SQL to `database/seeds/`. The converter itself is a one-off and lives under `tools/` (not shipped at runtime).
3. **Verification:** load the generated SQL into a fresh DB, compare `SELECT COUNT(*) FROM taxonomy`, `term`, `setting`, `acl_group`, `menu`, `static_page` against a production Heratio DB. Counts must match.

### Phase 3 — bin/install script

1. Write `bin/install` in bash. Stages per §6.
2. Write `php artisan heratio:install-bootstrap` — fires every ServiceProvider's install + seed. Replaces the first-visit auto-seed for the install-time bulk load.
3. Write `.env.example` with every `HERATIO_*`, `RIC_*`, `ELASTICSEARCH_*` key referenced in the codebase, with sensible defaults and inline comments.
4. Write `config/nginx/heratio.conf.template` with placeholders for domain + doc_root. Apply during install.

### Phase 4 — Build artifact (option A side of the hybrid)

1. GitHub Actions workflow `build-install-artifact.yml`:
   - Spin up MySQL 8.
   - Run `bin/install --non-interactive` against it.
   - `mysqldump heratio --no-data --routines --triggers` → `database/build/heratio-schema.sql`.
   - `mysqldump heratio <seed-tables-only>` → `database/build/heratio-seeds.sql`.
   - Publish both as release artifacts.
2. Document in `standalone-install-howto.md` that impatient admins can load these two dumps instead of running `bin/install`.

### Phase 5 — Admin-facing documentation

1. Write `docs/standalone-install-howto.md` — the user-facing install guide. System requirements, the `bin/install` command, post-install steps for each optional service (Cantaloupe, Fuseki, Qdrant, Ollama), troubleshooting.
2. Ingest it into the help system via `php artisan ahg:help-ingest`.

---

## 8. Open questions / decisions needed

- **Q1: Which taxonomies are "core" vs. AHG-extension?** The fixture `taxonomyTerms.yml` contains both AtoM-vanilla taxonomies (description level, media type, ISO languages) and AHG-added ones (heritage sectors, POPIA categories). For an international install, should we ship both, or split into "core" (Heratio ships) + "optional SA" (customer opts in)? — *Matches the existing "pluggable per-market" positioning.*
- **Q2: What is the minimum ES index state?** Do we ship empty indices, or seeded with a "welcome record"? — *Recommend empty. Admin can ingest their own.*
- **Q3: License + attribution.** AtoM is AGPL-3.0. Heratio is AGPL-3.0. Ported YAMLs and schema SQLs retain upstream copyright attribution in the file header. **Confirm this is acceptable before the port begins.**
- **Q4: Cantaloupe/Fuseki/Qdrant/Ollama — ship installers?** Option: `bin/install --with-cantaloupe` downloads and configures. Option: bin/install only prints instructions and the admin runs a separate `bin/install-cantaloupe` later. **Recommend: separate sub-installers**, simpler to maintain.
- **Q5: Upgrade path.** Existing sites running Heratio against an AtoM-seeded DB need a no-op upgrade — the new `bin/install` must detect "DB already populated" and skip §6.5–§6.8. **Yes. Idempotence is a hard requirement.**
- **Q6: Sample data.** Should standalone install offer `--with-demo-data` that loads a few fonds/actors/digital objects so admins can see the system work immediately? — *Recommend: yes, small curated set (~20 records, 1 fonds, 3 actors, 5 digital objects). Ships as `database/seeds/99_demo_data.sql`, opt-in only.*

---

## 9. Plugin mapping (AtoM → Heratio)

Required for Phase 1 step 3. Below is the mapping between the 91 AtoM AHG plugins and the 88 Heratio packages. Names differ in case convention (camelCase → kebab-case).

*Generated mechanically — each row is "AtoM plugin name" → "Heratio package name". Validate before bulk port.*

| AtoM plugin | Heratio package |
|---|---|
| ahg3DModelPlugin | ahg-3d-model |
| ahgAccessionManagePlugin | ahg-accession-manage |
| ahgAccessRequestPlugin | ahg-access-request |
| ahgActorManagePlugin | ahg-actor-manage |
| ahgAIPlugin | ahg-ai-services |
| ahgAiConditionPlugin | ahg-ai-condition |
| ahgAPIPlugin | ahg-api |
| ahgAuditTrailPlugin | ahg-audit-trail |
| ahgAuthorityPlugin | ahg-authority |
| ahgBackupPlugin | ahg-backup |
| ahgCartPlugin | ahg-cart |
| ahgCDPAPlugin | ahg-cdpa |
| ahgConditionPlugin | ahg-condition |
| ahgContactPlugin | ahg-contact |
| ahgCorePlugin | ahg-core |
| ahgCustomFieldsPlugin | ahg-custom-fields |
| ahgDacsManagePlugin | ahg-dacs-manage |
| ahgDAMPlugin | ahg-dam |
| ahgDataMigrationPlugin | ahg-data-migration |
| ahgDcManagePlugin | ahg-dc-manage |
| ahgDedupePlugin | ahg-dedupe |
| ahgDiscoveryPlugin | ahg-discovery |
| ahgDisplayPlugin | ahg-display |
| ahgDoiPlugin | ahg-doi |
| ahgDonorAgreementPlugin | ahg-donor-agreement |
| ahgDonorManagePlugin | ahg-donor-manage |
| ahgExhibitionPlugin | ahg-exhibition |
| ahgExportPlugin | ahg-export |
| ahgExtendedRightsPlugin | ahg-extended-rights |
| ahgFavoritesPlugin | ahg-favorites |
| ahgFederationPlugin | ahg-federation |
| ahgFeedbackPlugin | ahg-feedback |
| ahgFormsPlugin | ahg-forms |
| ahgFtpPlugin | ahg-ftp |
| ahgFunctionManagePlugin | ahg-function-manage |
| ahgGalleryPlugin | ahg-gallery |
| ahgGISPlugin | ahg-gis |
| ahgGraphQLPlugin | ahg-graphql |
| ahgHelpPlugin | ahg-help |
| ahgHeritageAccountingPlugin | ahg-heritage-accounting |
| ahgHeritagePlugin | ahg-heritage-manage |
| ahgICIPPlugin | ahg-icip |
| ahgIiifPlugin | ahg-iiif |
| ahgInformationObjectManagePlugin | ahg-information-object-manage |
| ahgIngestPlugin | ahg-ingest |
| ahgIntegrityPlugin | ahg-integrity |
| ahgIPSASPlugin | ahg-ipsas |
| ahgJobsManagePlugin | ahg-jobs-manage |
| ahgLabelPlugin | ahg-label |
| ahgLandingPagePlugin | ahg-landing-page |
| ahgLibraryPlugin | ahg-library |
| ahgLoanPlugin | ahg-loan |
| ahgMarketplacePlugin | ahg-marketplace |
| ahgMenuManagePlugin | ahg-menu-manage |
| ahgMetadataExportPlugin | ahg-metadata-export |
| ahgMetadataExtractionPlugin | ahg-metadata-extraction |
| ahgModsManagePlugin | ahg-mods-manage |
| ahgMultiTenantPlugin | ahg-multi-tenant |
| ahgMuseumPlugin | ahg-museum |
| ahgNAZPlugin | ahg-naz |
| ahgNMMZPlugin | ahg-nmmz |
| ahgPortableExportPlugin | ahg-portable-export |
| ahgPreservationPlugin | ahg-preservation |
| ahgPrivacyPlugin | ahg-privacy |
| ahgProvenancePlugin | ahg-provenance |
| ahgRadManagePlugin | ahg-rad-manage |
| ahgRegistryPlugin | ahg-registry |
| ahgReportBuilderPlugin | ahg-report-builder |
| ahgReportsPlugin | ahg-reports |
| ahgRepositoryManagePlugin | ahg-repository-manage |
| ahgRequestToPublishPlugin | ahg-request-to-publish |
| ahgResearcherPlugin | ahg-researcher |
| ahgResearchPlugin | ahg-research |
| ahgRicExplorerPlugin | ahg-ric |
| ahgRightsHolderManagePlugin | ahg-rights-holder-manage |
| ahgRightsPlugin | ahg-rights |
| ahgSearchPlugin | ahg-search |
| ahgSecurityClearancePlugin | ahg-security-clearance |
| ahgSemanticSearchPlugin | ahg-semantic-search |
| ahgSettingsPlugin | ahg-settings |
| ahgSpectrumPlugin | ahg-spectrum |
| ahgStaticPagePlugin | ahg-static-page |
| ahgStatisticsPlugin | ahg-statistics |
| ahgStorageManagePlugin | ahg-storage-manage |
| ahgTermTaxonomyPlugin | ahg-term-taxonomy |
| ahgThemeB5Plugin | ahg-theme-b5 |
| ahgTiffPdfMergePlugin | ahg-tiff-pdf-merge |
| ahgTranslationPlugin | ahg-translation |
| ahgUiOverridesPlugin | ahg-ui-overrides |
| ahgUserManagePlugin | ahg-user-manage |
| ahgUserRegistrationPlugin | ahg-user-registration |
| ahgVendorPlugin | ahg-vendor |
| ahgWorkflowPlugin | ahg-workflow |

**3 AtoM plugins with no direct Heratio counterpart** — decide per plugin whether to fold into an existing Heratio package or create a new one:
- `ahgMetadataExtractionPlugin` — likely folds into `ahg-ingest` or `ahg-ai-services`.
- `ahgLoanPlugin` — likely its own new `ahg-loan` package.
- `ahgRequestToPublishPlugin` — likely folds into `ahg-workflow` or `ahg-access-request`.

---

## 10. Time and effort estimate

| Phase | Items | Estimate |
|---|---|---|
| Phase 1 (schema consolidation) | 6 items | 2–3 days — most of it is mechanical copy-with-guards + running against a scratch DB to confirm `CREATE TABLE IF NOT EXISTS` guards catch everything. |
| Phase 2 (YAML → SQL converter) | 3 items | 2 days — the symbol-table pass is the tricky part; everything else is straightforward. |
| Phase 3 (bin/install + bootstrap command) | 4 items | 1–2 days. |
| Phase 4 (build artifact + CI) | 2 items | 0.5 day. |
| Phase 5 (documentation + help ingest) | 2 items | 0.5 day. |
| **Total** | **17 items** | **~1 week of focused work** |

---

## 11. Out of scope for this plan

- **Data migration from an existing AtoM install to a fresh Heratio** — that's a separate doc (`docs/data-migration-user-guide.md` already exists; cross-link it from the install doc).
- **Multi-tenant install** — single-tenant is the target here. Multi-tenant uses the same install but adds tenant bootstrapping via `ahg-multi-tenant`.
- **Kubernetes / Docker deployment** — bare-metal / VM install first. Docker image build is a future task, trivial on top of `bin/install --non-interactive`.
- **Backup / restore** — Heratio already has `ahg:backup` and `/admin/restore`. Install doesn't touch those.

---

## 12. Success criteria

Install is "done" when:

1. A fresh Ubuntu 24 LTS box with only MySQL, PHP, Nginx, Redis, Elasticsearch installed can become a running Heratio with one `bin/install` invocation and no manual DB work.
2. The resulting DB has ≥ 97% of the table count of the current production heratio DB (allowing for demo-data-only tables).
3. `/admin/dropdowns` shows ≥ 3,800 rows without the auto-seed having to run.
4. `/admin/login` works with the printed credentials.
5. `/informationobject/browse` renders the empty GLAM grid at HTTP 200 with no PHP notices.
6. `php artisan ahg:es-reindex --drop` completes with no errors against the empty DB.
7. CI job `build-install-artifact` succeeds against a fresh MySQL 8 container and publishes `heratio-schema.sql` + `heratio-seeds.sql` release artifacts.

---

## 13. Addendum 2026-04-30 — recon refresh + Phase 1 kickoff

Re-ran the schema diff against the current production `heratio` DB before starting the build.

### Refreshed numbers vs §2.1

| Bucket | 2026-04-17 plan | 2026-04-30 recon |
|---|---:|---:|
| Total `heratio` tables | 974 | **993** |
| `ahg_*` sidecars | (not separated) | 163 |
| Non-`ahg_*` (vanilla AtoM + un-prefixed Heratio additions) | 974 | 830 |
| `ahg_dropdown` rows | 3,824 | (unchanged — verify before Phase 1 close) |
| Vanilla AtoM tables (in `data/sql/*.schema.sql`) | (not measured) | **63** |

**The headline:** of the 830 non-`ahg_` tables, **only ~63 are vanilla AtoM** — the surface that this plan must capture from upstream. The other ~767 are Heratio additions that just don't follow the `ahg_` prefix convention. They're already in our control; the plan's existing per-package install.sql consolidation handles them. Renaming to `ahg_*` is out of scope (cosmetic; high refactor cost across views/services).

### AtoM seed fixture inventory (verified 2026-04-30)

`/usr/share/nginx/archive/data/fixtures/`:

| File | Lines | What it seeds |
|---|---:|---|
| `taxonomyTerms.yml` | 13,014 | All taxonomies + terms, multilingual |
| `menus.yml` | 2,402 | Admin + public navigation |
| `settings.yml` | 1,586 | System defaults |
| `acl.yml` | 309 | ACL groups + permissions |
| `staticPages.yml` | 101 | Home / About / Contact / Privacy / Terms |
| `fixtures.yml` | 17 | Bootstrap (admin user, etc.) |

Phase 2's YAML→SQL converter is the heaviest one-time work. The pre-existing plan §7 covers it; nothing changes here.

### Phase 1 build kickoff

Starting Phase 1 item #1: port `lib.model.schema.sql` → `database/core/00_core_schema.sql` with `CREATE TABLE IF NOT EXISTS` guards. Subsequent items (qbAclPlugin, qtAccessionPlugin, atom-framework) follow in 01–03.

Verification gate before proceeding to Phase 2: the four `database/core/*.sql` files must (a) load cleanly on a fresh `heratio_standalone_test` DB, and (b) be no-op-safe when re-run on the existing production `heratio` DB.
