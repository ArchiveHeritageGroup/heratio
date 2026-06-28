# Heratio — Technical Manual

> Developer & operator manual for the Heratio platform. Covers architecture, the
> package map, the cross-cutting conventions every package must honour, and the
> operational workflows (release, locked paths, the AI gateway, storage hardening).
>
> Companion to the **User Manual** (task-oriented, per module) and the in-app Help
> Center (`docs/help/*.md` → `help_article`). Audit/onboarding scope: issue #1375.
>
> _Last reviewed: 2026-06-28._

## Table of contents
1. [What Heratio is](#1-what-heratio-is)
2. [Architecture](#2-architecture)
3. [Package map (by domain)](#3-package-map-by-domain)
4. [Data model essentials](#4-data-model-essentials)
5. [Cross-cutting conventions](#5-cross-cutting-conventions)
6. [Search & discovery](#6-search--discovery)
7. [AI subsystem & the gateway](#7-ai-subsystem--the-gateway)
8. [Interoperability](#8-interoperability)
9. [Operations & release workflow](#9-operations--release-workflow)
10. [Host hardening (php-fpm ProtectSystem)](#10-host-hardening-php-fpm-protectsystem)
11. [Knowledge Management (KM) publishing](#11-knowledge-management-km-publishing)

---

## 1. What Heratio is

Heratio is a **pure-Laravel reimplementation of AtoM 2.x** (Access to Memory), the
archival description platform. It keeps AtoM's data model (the `information_object`
hierarchy, `actor`/`repository`/`term` authorities, `digital_object`, the `status`
and `slug` tables) but replaces the Symfony 1.x application layer with a modern
Laravel monorepo of **~115 first-party packages** under `packages/ahg-*`.

The platform is multi-sector: archives, libraries (MARC/Z39.50/SRU), museums
(Spectrum/CCO), galleries, records management, research-data management (RDM), and
a large AI layer — all over one shared catalogue.

---

## 2. Architecture

- **Host app**: a thin Laravel application (`app/`, `bootstrap/`, `config/`,
  `routes/`, `public/`) that wires together the packages. Most domain behaviour
  lives in the packages, not the host app.
- **Packages**: `packages/ahg-<name>/` — each a Composer path-repository with its
  own `src/` (PSR-4 `Ahg<Name>\`), `routes/web.php`, `resources/views/`,
  `database/` (install.sql / migrations), and a `composer.json`. Each ships a
  **service provider** that registers routes, views (namespaced `ahg-<name>::`),
  config, and console commands.
- **Theme**: `ahg-theme-b5` provides the Bootstrap-5 layout, nav, and footer. All
  UI is **Bootstrap 5** (not Tailwind — a common doc error; `b5` is literal). New
  views extend the theme's layouts and use BS5 utility classes.
- **Core**: `ahg-core` holds the shared Eloquent models, base services, and
  cross-cutting support classes (e.g. `AhgCore\Support\TenantScope`,
  `AhgCore\Constants\TermId`, `AclService`).
- **Middleware aliases** are registered in `bootstrap/app.php` (~line 95): `acl:`
  → `CheckAcl`, `admin` → `RequireAdmin` (see §5).

### Adding a package (shape)
```
packages/ahg-foo/
  composer.json                 # name: ahg/ahg-foo, autoload PSR-4 AhgFoo\ -> src/
  src/Providers/AhgFooServiceProvider.php
  src/Controllers/  src/Services/  src/Models/
  routes/web.php                # loaded by the provider
  resources/views/              # ->loadViewsFrom(..., 'ahg-foo')
  database/install.sql          # schema (idempotent CREATE TABLE IF NOT EXISTS)
  docs/ or top-level docs/help/<name>-user-guide.md
```

---

## 3. Package map (by domain)

| Domain | Key packages |
|---|---|
| **Platform / core** | ahg-core, ahg-theme-b5, ahg-settings, ahg-help, ahg-acl, ahg-user-manage, ahg-multi-tenant, ahg-menu-manage, ahg-dropdown-manage, ahg-static-page, ahg-landing-page, ahg-articles, ahg-forms, ahg-translation, ahg-custom-fields |
| **Archival description** | ahg-information-object-manage, ahg-actor-manage, ahg-repository-manage, ahg-function-manage, ahg-term-taxonomy, ahg-accession-manage, ahg-display, ahg-version-control |
| **Descriptive standards** | ahg-dacs-manage, ahg-rad-manage, ahg-dc-manage, ahg-mods-manage, ahg-spectrum (museum), ahg-biblio-bf (BIBFRAME), ahg-biblio-frbr (FRBR) |
| **Sectors** | ahg-library, ahg-museum, ahg-gallery, ahg-ipsas, ahg-nmmz, ahg-naz, ahg-narssa, ahg-cdpa, ahg-heritage-manage, ahg-records-manage, ahg-storage-manage, ahg-loan, ahg-exhibition, ahg-condition, ahg-vendor |
| **Digital objects / preservation** | ahg-dam, ahg-media-processing, ahg-media-streaming, ahg-preservation, ahg-ocfl, ahg-integrity, ahg-3d-model, ahg-image-ar, ahg-pdf-tools, ahg-scan, ahg-ftp-upload, ahg-metadata-extraction, ahg-iiif-collection, ahg-c2pa |
| **Research / RDM** | ahg-research, ahg-researcher-manage, ahg-rdm, ahg-annotations, ahg-favorites, ahg-request-publish |
| **AI** | ahg-ai-services, ahg-ai-chatbot, ahg-ai-compliance, ahg-inference-receipts, ahg-provenance-ai, ahg-semantic-search |
| **Rights / privacy / compliance** | ahg-extended-rights, ahg-rights-holder-manage, ahg-rights, ahg-privacy, ahg-icip, ahg-security-clearance, ahg-access-request, ahg-donor-manage, ahg-provenance, ahg-share-link, ahg-audit-trail, ahg-workflow |
| **Interop / linked data** | ahg-oai, ahg-z3950, ahg-resourcesync, ahg-federation, ahg-sharepoint, ahg-api, ahg-graphql, ahg-metadata-export, ahg-ric, ahg-doi, ahg-doi-manage |
| **Search / discovery** | ahg-search, ahg-semantic-search, ahg-discovery, ahg-gis |
| **Commerce / engagement** | ahg-cart, ahg-marketplace, ahg-feedback |
| **Reporting / ops** | ahg-reports, ahg-statistics, ahg-observability, ahg-backup, ahg-jobs, ahg-jobs-manage, ahg-data-migration, ahg-export, ahg-portable-export, ahg-ingest, ahg-dedupe |

Backend-only libraries with no user UI (correctly exempt from the user manual):
`ahg-ocfl`, `ahg-inference-receipts`.

---

## 4. Data model essentials

Inherited from AtoM (Qubit), so ES indices keep the `qubit*` names.

- **`information_object`** (IO) — the archival description; self-referential
  hierarchy (`parent_id`); `id = 1` is the synthetic ROOT (always excluded from
  public queries via `where('id','!=',1)`). Multilingual text in
  `information_object_i18n` (keyed by `id` + `culture`). `repository_id` is the
  owning repository (also the multi-tenant scope key).
- **`status`** — publication state per object: `object_id`, `type_id`, `status_id`.
  Publication uses `type_id = 158`; `status_id = 160` is **published**, `159` is
  **draft**. The canonical public gate is "a `status` row with 158/160 exists".
- **`slug`** — URL slugs (`object_id` → `slug`).
- **`actor` / `repository` / `term`** — authorities (people/orgs, holding
  institutions/ISDIAH, taxonomy/ISDF). `repository` rows are a subtype of `actor`.
- **`digital_object`** — files attached to IOs (masters + derivatives); parent IO
  via `object_id`.

---

## 5. Cross-cutting conventions

Every package that exposes routes or serves records MUST honour these. They are
the source of the recurring security classes the E2E sweep (T1–T10) tracked.

### 5.1 Authorization (ACL)
- `acl:<action>` middleware → `App\Http\Middleware\CheckAcl` →
  `AclService::hasPermission($userId, $action)`, where action ∈ `create | update |
  delete | read`. Use on **mutation** routes (and read routes that expose
  sensitive data).
- `admin` middleware → `App\Http\Middleware\RequireAdmin` → `AclService::canAdmin()`
  which is **ADMINISTRATOR _or_ EDITOR**.
- `AclService::isAdministrator()` is **ADMINISTRATOR only** — use for
  destructive/separation-of-duties operations (e.g. DB restore, records
  destruction) where editor-level is too broad.
- Both aliases are registered in `bootstrap/app.php`.

> Pitfalls the sweep found: GET routes that mutate (CSRF-able; always POST +
> `acl:`); "legacy" route aliases left at a weaker gate than their canonical twin;
> in-controller IDOR (no `created_by === Auth::id()` / ownership check).

### 5.2 Publication-status gating (draft-leak prevention)
Guests must never see unpublished records. The canonical pattern, applied to any
public query that joins `information_object`:
```php
$query->when(! auth()->check(), function ($q) {
    $q->where('information_object.id', '!=', 1)
      ->whereExists(function ($s) {
          $s->select(DB::raw(1))->from('status')
            ->whereColumn('status.object_id', 'information_object.id')
            ->where('status.type_id', 158)->where('status.status_id', 160);
      });
});
```
In Elasticsearch the equivalent guest filter is
`['term' => ['publicationStatusId' => 160]]`.

### 5.3 ODRL gating (digital-object access)
`AhgResearch\Services\OdrlService::isDigitalObjectPermitted($digitalObjectId, 'use')`
maps a digital object to its parent IO and enforces the ODRL prohibition (admin
group bypass; open objects no-op). Returns `false` when denied. Always guard with
`class_exists(\AhgResearch\Services\OdrlService::class)` so packages don't hard-depend
on it. Wire it into every **binary-serving** route (media streaming, IIIF image,
AR MP4, C2PA verify-download).

### 5.4 Multi-tenant scoping
`AhgCore\Support\TenantScope::getActiveRepoId()` returns the active tenant's
`repository_id`, or **null** when: `tenant.current` isn't bound, the
`tenant_enforce_filter` setting is off, the user is an admin, or single-tenant.
When non-null, scope IO queries by `information_object.repository_id` (DB) /
`['term' => ['repository.id' => $repoId]]` (ES). A tenant maps to a repository via
`ahg_tenant.repository_id`; the active tenant is held in `session('current_tenant_id')`.

### 5.5 Dropdowns
Controlled vocabularies for forms are managed by `ahg-dropdown-manage` (the
`ahg_dropdown` mechanism) — do not hardcode option lists.

### 5.6 Theme & i18n
Extend `ahg-theme-b5` layouts; Bootstrap 5 only. UI strings go through the
translation layer (`ahg-translation`'s DbAwareLoader); locales are DB-registered.

---

## 6. Search & discovery

- **`ahg-search`** — Elasticsearch integration. Primary index
  `qubitinformationobject`; key fields include `publicationStatusId` (160 =
  published) and a nested `repository` object (`repository.id` = tenant scope).
  `ElasticsearchService` applies the published + tenant filters centrally;
  reindex via `ahg:es-reindex` (`EsReindexCommand`).
- **`ahg-semantic-search`** — vector/semantic enhancement (embeddings via the AI
  gateway; Qdrant vector store at `:6333`, infra not gated by the AI gateway).
- **`ahg-discovery`** — the public discovery/browse surface; both DB and ES query
  paths. Must apply the §5.2 published gate and §5.4 tenant scope itself (it does
  not route through `ElasticsearchService`).
- **`ahg-gis`** — spatial search (bbox/radius/GeoJSON).

Infra ports that are **out of AI-gateway scope**: Elasticsearch `:9200`, Qdrant
`:6333`, Fuseki triplestore `:3030`.

---

## 7. AI subsystem & the gateway

**The gateway rule (mandatory):** every application AI call — from any package —
MUST route through the AHG AI gateway at `https://ai.theahg.co.za/ai/v1/...`.
**Never** call a GPU node port directly (`:11434` Ollama, `:5004` workers, `:5006`
HTR-legacy, `:5008` Donut, `:5052` image-AR, `:8011` NuExtract). The gateway enforces
API keys + per-key quota, meters/bills every call, and does DB-driven node
selection + failover. A direct-to-node endpoint defeats all of that and rots when a
node moves. Direct `curl` to a node is for operator diagnostics only.

- **Gateway service**: a FastAPI app at `/opt/ahg-ai/gateway` (uvicorn on
  `127.0.0.1:8002` behind nginx) over Postgres `ahgai`. Three planes: inference
  proxy (`/ai/v1/*`), GPU allocation/preemption scheduler, and an admin console.
- **Routes/passthroughs** (`app/routes/ai_proxy.py`): worker-failover routes
  (`/ner`, `/htr`, `/summarize`, `/translate`, `/embed/image`, `/tts`) and
  transparent node passthroughs (`/ollama/{path}`, `/htr/legacy/{path}`,
  `/nuextract/{path}`, `/donut/{path}`, `/ar/{path}`, `/image-to-3d`). Adding a new
  upstream = one `*_UPSTREAM_URL` env const + an `api_route` forward; restart with
  `systemctl restart ahg-ai-gateway.service`.
- **Auth from PHP**: attach `Authorization: Bearer <key>` where the key resolves
  (in order) from `ahg_ner_settings.api_key`, then `ahg_ai_settings` feature
  `general` `api_key`. Default any service endpoint to the gateway and **reject
  raw-node overrides** (`looksLikeNode()` guard) so a stale settings row can't
  bypass it. Reference implementations: `OllamaPageIndexClient` (ahg-discovery),
  `NerService`/`HtrService`/`DonutService`/`LlmService` (ahg-ai-services),
  `AnimationService` (ahg-image-ar).
- **AI packages**: `ahg-ai-services` (LLM completion, NER, summarize, translate,
  HTR, Donut), `ahg-ai-chatbot` (RAG over the catalogue), `ahg-semantic-search`
  (embeddings), `ahg-ai-compliance` + `ahg-inference-receipts` + `ahg-provenance-ai`
  (EU AI Act record-keeping: tamper-evident SHA-256 hash-chained inference receipts).

---

## 8. Interoperability

- **Harvest/dissemination**: OAI-PMH (`ahg-oai`), ResourceSync (`ahg-resourcesync`),
  SRU + Z39.50 client/server (`ahg-z3950`). All public dissemination endpoints
  apply the §5.2 published gate (SRU/Z39.50 server disseminate from the gated main
  catalogue, not an ungated MARC store).
- **Federation**: `ahg-federation` — OAI-PMH peer harvest (imported records forced
  to Draft), Europeana/EDM export (published-only). Outbound fetches go through
  `HarvestClient` with a strong SSRF guard (metadata/private-IP block, DNS-rebind
  pin); peer API keys are encrypted at rest.
- **Linked data / RDF**: `ahg-metadata-export` (multi-format RDF), `ahg-biblio-bf`
  (BIBFRAME 2.0), `ahg-biblio-frbr` (FRBR), `ahg-ric` (Records in Contexts, Fuseki).
- **APIs**: `ahg-api` (REST v1), `ahg-graphql`. **IIIF**: `ahg-iiif-collection`
  (+ image/3D manifests). **DOI**: `ahg-doi-manage` (DataCite).
- **M365**: `ahg-sharepoint` (drive registration, manual/scheduled sync; client
  secret encrypted at rest).

---

## 9. Operations & release workflow

### 9.1 `bin/release`
`bin/release patch|minor|major "<message>" [--issue N]`:
1. bumps `version.json`,
2. `git add -A` + commit + push to `main` + tag `v<version>`,
3. generates `docs/sessions/<YYYY-MM-DD>-<version>.md` (a KM session log) and
   best-effort drops it into `/var/spool/km-ingest/`,
4. `--issue N` closes the GitHub issue (commit-message `Closes #N` keywords also
   auto-close on push to the default branch).

Version collisions (a concurrent push taking the same number) are recovered by
`git reset --hard origin/main`, re-applying the source files (`git checkout <orphan>
-- <files>`), and re-running `bin/release` (which renumbers off the new tip).

### 9.2 Locked paths
Sensitive files are listed in `.locked-paths` (exact path or directory prefix).
`bin/release` refuses to ship a change to a locked path until it is unlocked with
`./bin/unlock <path>` — a **one-shot** grant that auto-rearms after the next
release. `./bin/unlock --list` shows current grants. Editing a locked file is fine;
only the release is gated.

### 9.3 Conventions for changes
- Match surrounding code style; views are Bootstrap 5.
- `php -l` every changed PHP file; verify routes with
  `sudo -u www-data php artisan route:list` (never run artisan as **root** — see
  §10).
- Append a note to the running results matrix in `docs/reference/` for
  security/audit-relevant fixes.

---

## 10. Host hardening (php-fpm ProtectSystem)

The system unit `php8.3-fpm.service` ships `ProtectSystem=full`, which mounts
`/usr`, `/boot`, `/etc` **read-only for the php-fpm worker**. Any Laravel app under
`/usr/share/nginx/<app>/` therefore cannot write its own `storage/`,
`bootstrap/cache/`, sessions, views, or logs from a web request unless granted via
a systemd drop-in:
```ini
# /etc/systemd/system/php8.3-fpm.service.d/<app>-storage.conf
[Service]
ReadWritePaths=/usr/share/nginx/<app>/storage
ReadWritePaths=/usr/share/nginx/<app>/bootstrap/cache
```
Then `systemctl daemon-reload && systemctl restart php8.3-fpm`. Symptom when
missing: HTTP 500 with `Read-only file system` on `storage/logs/laravel-*.log`
(login GET often still works, masking it). Cron-driven `php artisan` is **not**
subject to ProtectSystem (forked from cron, not the fpm unit) — so daily logs can
write while web requests 500, a diagnostic split.

**Do not run `php artisan` as root** from these dirs — bootstrap can create the
daily log file owned by `root:root`, blocking the `www-data` worker. Use
`sudo -u www-data php artisan ...`. Belt-and-braces: default ACLs granting
`www-data:rwx` on `storage/logs`, `storage/framework/{cache,sessions,views}`,
`bootstrap/cache`.

### Static file gating (nginx)
`/uploads/` is a static nginx alias. Files physically under it are served directly,
bypassing Laravel gates. To gate sensitive media (e.g. AR MP4s), mark the subtree
`internal` and deliver via a gated route + `X-Accel-Redirect`:
```nginx
location ^~ /uploads/ar/ { internal; alias /mnt/nas/heratio/uploads/ar/; }
```

---

## 11. Knowledge Management (KM) publishing

Every meaningful release / closed issue / non-trivial decision publishes to KM
(`km.theahg.co.za`) so any agent or project can find it:
1. **In-repo session log** — `docs/sessions/<date>-<version>.md`, auto-generated by
   `bin/release`; git-tracked so the KM indexer crawls it.
2. **KM ingest folder** — `/var/spool/km-ingest/` (best-effort drop by `bin/release`).
3. **KM query** — via the `km_ask` / `km_health` / `km_stats` MCP tools.

KM is the canonical shared knowledge bus across all projects on the host; consult
it for project history, prior decisions, and cross-agent context, then verify
against the live code for anything code-specific.

---

_This manual is generated/maintained alongside `docs/help/`. When conventions
change, update §5 and the relevant package's user guide together. Coverage matrix:
`docs/reference/heratio-docs-coverage-matrix-2026-06-28.md`._
