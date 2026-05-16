# What's New (May 2026)

## Version 1.53.x | May 2026

This page summarises the user-visible features that landed across releases v1.53.21 through v1.53.27 (early May 2026). Each section links to the full reference doc when one exists. Items marked **operator** are admin-only; **researcher** are visible to logged-in users; **public** are visible without login.

---

## Research enhancements roadmap - 13 features (researcher) - 2026-05-16

The full roadmap at `docs/research-enhancements-roadmap.md` landed in one release. All 13 items are shipped and live; full user-facing guide at `docs/research-enhancements-user-guide.md`.

- **NotebookLM-style Studio pane** on every research project (Briefing / Study Guide / FAQ / Timeline / Diagram / Video Script / Spreadsheet / Audio Overview) - generations are grounded in the project's evidence-set items with `[N]` citation provenance. Spreadsheets export to real .xlsx via PhpSpreadsheet; audio uses a configurable `HERATIO_TTS_ENDPOINT`.
- **Citation hover popovers** on any inline `[N]` markers - shows source title + snippet + scroll-to-source. Plus a **Copy in citation manager format** picker on `/research/cite/{slug}` with RIS / BibTeX / EndNote XML / APA 7 / MLA 9 / Chicago 17 downloads.
- **Researcher private notebooks** at `/research/notebooks` - saved queries, AI outputs, pinned sources, freeform notes. One-click **Promote to project** turns a notebook into a public research project with its source pins materialised as a collection.
- **Cross-fonds reasoning queries** at `/research/cross-fonds-query` - ask one question across N fonds in parallel, merged and reranked. Optional thesaurus expansion via `ahg-semantic-search`.
- **Research analytics dashboard** at `/research/analytics` - 8 KPI tiles, top researchers, popular descriptions, popular collections, top search terms, weekly volume chart. Aggregates existing `research_activity_log`; no new audit tables.
- **Real-time collaboration** at `/research/projects/{id}/realtime/panel` - presence list + threaded comments on evidence sets, polling-based (3s) since this host has no WebSocket broker. Shared annotation layers via new `project_id` + `visibility` columns on `ahg_iiif_annotation`.
- **ORCID integration** at `/research/orcid` - OAuth authorize / pull Works / push Works. Tokens AES-256-CBC encrypted. Required ENV: `ORCID_CLIENT_ID`, `ORCID_CLIENT_SECRET`, `ORCID_REDIRECT_URI`.
- **GraphQL researcher types** in the existing `/admin/graphql` endpoint - `researchProject`, `researchProjects`, `researchAnnotations`, `researchCollections`, and a combined `researcherView(researcherId)` single-round-trip query for Zotero / Tropy / LMS integration.
- **Mobile / PWA** at `/research/mobile` - phone-first reading list + quick journal entry + four-button nav. `manifest.webmanifest` supports "Add to home screen" / standalone mode.
- **Offline mode** via `/sw.js` service worker - caches the mobile shell, journal entries queue in `localStorage` and post to `/research/sync/offline` when the browser comes back online.

Schema additions in `packages/ahg-research/database/install.sql`: `research_studio_artefact`, `research_notebook`, `research_notebook_item`, `research_collaboration_session`, `research_collaboration_presence`, `research_evidence_comment`, `researcher_orcid_link`, `research_cross_fonds_query`, `research_offline_sync_log`. Plus `project_id` + `visibility` columns on `ahg_iiif_annotation` (added with `IF NOT EXISTS` for safe upgrades).

References: `docs/research-enhancements-roadmap.md` (roadmap source-of-truth), `docs/research-enhancements-user-guide.md` (user guide), `docs/reference/research-roadmap-2026-05-features.md` (KM index).

---

## Heratio php-fpm `ProtectSystem=full` drop-in (operator) - 2026-05-16

`/etc/systemd/system/php8.3-fpm.service.d/heratio-storage.conf` added so php-fpm can write to `storage/` and `bootstrap/cache/`. Without this, `/help` (and any page that triggers Blade view compilation or log writes) returns 500 with a misleading `tempnam(): file created in the system's temporary directory` masking exception. See `docs/operations/php-fpm-protectsystem.md` for the host-wide pattern.

---

## AI services - 16 settings keys now wired (operator)

The form at `/admin/ahgSettings/aiServices` previously had 16 of 20 fields that saved to `ahg_ner_settings` but were never read by any consumer. v1.53.23 wired all 16 through a new `AhgAiServices\Support\AiServicesSettings` helper:

- **Master gates:** `summarizer_enabled`, `spellcheck_enabled`, `translation_enabled`, `ner_enabled` - global kill switches that sit above the per-session ingest toggles.
- **Processing mode:** `ai_services_processing_mode` (local | cloud | hybrid). Cloud mode posts to a single hosted endpoint configured via `ai_services_api_url` + `ai_services_api_key` + `ai_services_api_timeout`, bypassing the per-provider config table.
- **Summarizer:** `summarizer_max_length`, `summarizer_min_length`, `summary_field` (target IO field).
- **Spellcheck:** `spellcheck_language` (target locale).
- **Translation:** `translation_mode` (mt vs llm). `mt` mode posts to `mt_endpoint` with `mt_timeout` and falls through to the LLM round-trip on failure.
- **Discovery / Qdrant:** `qdrant_url`, `qdrant_collection`, `qdrant_model`, `qdrant_min_score`. The vector search service falls back to these when canonical `semantic_qdrant_*` settings are unset, so operators don't have to know about both tables. `qdrant_min_score` is sent to Qdrant as `score_threshold`.
- **Capture pipeline:** `auto_extract_on_upload` - when on, file uploads of digital objects auto-trigger Donut document extraction. URL/FTP-linked objects bypass since they have no local file.

Reference: see "AI Services & NER - User Guide" for the per-feature descriptions; this update only added the master-gate + cloud-mode wiring on top of the existing form.

---

## Donut form-save provenance (operator)

When the Donut document-understanding service extracts fields from an uploaded scan and an admin saves the resulting record, every Donut-derived field is now recorded in `ahg_ai_inference` with full PROV-O provenance. The flow:

1. `POST /admin/ai/donut/prefill` extracts fields and pre-records each one with `target_entity_id=0` plus a session UUID.
2. The form-save handler that creates the new IO calls `POST /admin/ai/donut/finalize` with the session UUID + new IO id.
3. The pending rows update to the real entity id and a Fuseki RDF-Star write is attempted inline. On failure the row's `fuseki_graph_uri` stays NULL and the new replay command picks it up later.

Reference: "AI Inference Provenance - User Guide".

---

## Fuseki replay (operator/dev)

New `php artisan ahg:provenance-ai:replay` artisan command. Picks up any `ahg_ai_inference` rows where `fuseki_graph_uri IS NULL` (and `ahg_ai_override` rows with `fuseki_override_uri IS NULL`), rebuilds the Turtle, and writes to Fuseki. Self-gated on the `fuseki_sync_enabled` setting. Scheduled every 5 minutes by the package service provider so a brief Fuseki outage doesn't permanently lose the AI provenance writes - SQL stays the source of truth, RDF catches up.

Flags:
- `--batch=200` (default 200 rows per pass)
- `--dry-run` (report only, no writes)

---

## Ingest pipeline AI orchestration (operator)

The ingest-wizard commit step now honours all 8 `ingest_session.process_*` toggles end-to-end:

| Toggle | Service called |
|---|---|
| `process_virus_scan` | `AhgAiServices\Services\VirusScanService` |
| `process_ocr` | `AhgAiServices\Services\OcrService` |
| `process_format_id` | `AhgAiServices\Services\FormatIdService` |
| `process_face_detect` | `AhgCore\Services\FaceDetectionService` (new in v1.53.21) |
| `process_ner` | `AhgAiServices\Services\NerService` |
| `process_summarize` | `AhgAiServices\Services\SummarizerService` |
| `process_spellcheck` | `AhgAiServices\Services\SpellcheckService` |
| `process_translate` | `AhgAiServices\Services\TranslateService` (target locale from `process_translate_lang`) |

Master gates from the AI Services settings page apply on top of the per-session toggles - both must be on. Missing service classes are logged and skipped without breaking the ingest commit.

---

## Face detection (operator)

New `face_enabled` + `face_backend` settings now end-to-end functional via `AhgCore\Services\FaceDetectionService`. Backends: `face_recognition` / `dlib` / `azure` / `noop`. Routes to the GPU pool with vRAM floor 4-6 GB. Persisted into `digital_object_faces` table.

The ingest pipeline's `process_face_detect` toggle and the upload-time `auto_extract_on_upload` toggle both call into this service.

---

## UI string storage moved from JSON to DB (operator)

Every `__()` lookup now reads from a new `ui_string` table first, falling back to `lang/{locale}.json` only when a key is missing in the DB. The migration is one-shot: `php artisan ahg:translation:import-json-to-db` walks every `lang/*.json` and seeds the table (54,959 rows across 64 cultures, idempotent on rerun).

Why: per-culture audit and diff become a single SELECT instead of `git log` over a 7000-key JSON, MT-translated values stop polluting git, and the deploy story collapses to one mysqldump instead of a filesystem volume mount.

The in-app editor at `/admin/translation/strings` (issue #54) is the next piece - lets admins edit translations through a form instead of editing JSON via SSH. Status: in flight at the time of writing.

---

## Browser-rendered function/route catalogues at /docs/functions/ (operator)

New admin-gated surface at `/docs/functions/{kind?}` where `kind` is one of `php`, `js`, `blade`, `py`, `routes`. Reads from the auto-generated `auto_functions_kb*.md` catalogues that the KM ingest pipeline consumes:

- **PHP:** ~7000 methods across ~950 files, paginated by class FQN, ~21 pages.
- **JS:** small first-party JS - few files (most interactivity is inline in Blade).
- **Blade:** ~2000 templates with @include/@extends/@section/@yield/@push map per template.
- **Py:** the operator-side ingest + audit + KB-build scripts under `/opt/ai/km/`.
- **Routes:** ~2800 routes from `php artisan route:list --json`, paginated by controller, ~6 pages.

Sidebar TOC, `?q=substring` filter, prev/next pagination, source-file mtime + size badges. The 5 source files regenerate every 10 minutes via `km-build-functions.timer` so the surface stays current automatically.

Tile added to `/help`.

---

## Operator-tunable RDF/SPARQL namespaces (admin)

Three new `config/heratio.php` keys, env-driven for fresh installs:

- `LD_TENANT` - short tenant token used in `urn:{tenant}:*` graph URIs (default `ahg` for backward compat).
- `LD_PROVENANCE_NS` - the provenance-ai vocabulary URI (default `{APP_URL}/ns/provenance-ai#`).
- `LD_RIC_NS` - the RiC application profile URI.

Applies to Donut finalise, InferenceService, OverrideService, and FusekiReplayCommand. Removes the previously-hardcoded AHG tenant string from every Turtle template - new tenants set one env var and their RDF graphs self-reference correctly.

---

## KM credential audit (operator)

New `/opt/ai/km/audit-qdrant.py` daily defence-in-depth scan over every Qdrant collection. Uses the same regex set the inbound ingest pipeline runs (passwords, API keys/tokens, RFC1918 IPs, SSH/PEM keys, OpenAI sk-*, JWT eyJ*) plus tightening so docs-style placeholders (`your-*`, `${VAR}`, `<API_KEY>`, etc.) and date-shaped 3-octet matches don't false-positive.

Daily 03:30 via `km-audit-qdrant.timer`. Exit 2 if leaks found (systemd marks unit FAILED).

A shared `/opt/ai/km/redact.py` module is now the single source of truth for the redaction patterns; all 6 ingest scripts (`ingest.py`, `ingest_qa.py`, `ingest_atom_docs.py`, `ingest_ric.py`, `ingest_v2101.py`, `ingest_upgrade.py`, `ingest_heratio.py`) import from it.

---

## GPU pool foundation (operator)

New `ahg_gpu_endpoint` table + `AhgGpuPoolService` + `php artisan ahg:gpu-pool` command for managing AI endpoints in one place. Lets operators swap GPUs without touching code or chasing per-service settings:

```bash
php artisan ahg:gpu-pool list
php artisan ahg:gpu-pool add gpu-115 http://192.168.0.115:11434 --vram=20 --models=qwen2.5:13b,llama3:13b --priority=50
php artisan ahg:gpu-pool health
php artisan ahg:gpu-pool enable gpu-115
php artisan ahg:gpu-pool disable gpu-78
```

Auto-seeds from legacy settings (`voice_local_llm_url`, `mt.endpoint`, `endpoint`) on first boot so pre-pool installs see no behaviour change. Strategy is `priority` (default) or `round-robin` via `ai_gpu_pool_strategy` setting. vRAM-aware: a 20GB model never lands on the 8GB host.

Per-consumer migration to `AhgGpuPoolService::pickEndpoint()` is a separate per-service follow-up; `VoiceLLMService` is the proof-of-concept consumer.

---

## Other notable

- **#55** - new `bin/lockstep-fork` rsync wrapper for the atom + dam fork installs (replaces per-file port-the-fix workflow).
- **#52** - per-user ACL editor at `/user/{slug}/edit-{informationObject,actor,repository,term}-acl` mirroring the per-group pattern.
- **#100** - Mirador image annotations now functional end-to-end (mirador-annotation-editor + Annotot-shaped persistence backend).
- **#125** - derivative file encryption end-to-end (encrypt-on-write + decrypt-on-stream + daily bulk-apply).
- **#118** - 4 treeview variants (sidebar / full / accordion / nested-list) selectable on `/admin/ahgSettings/treeview`.
- **#84** - featured-listing fee on the marketplace now drives a real PayFast purchase flow.
- **#106** - Heratio-branded media player as the default; 5-tier player_type dispatcher (heratio / heratio-minimal / plyr / videojs / native).
- **#41** - admin route 404s now render the styled error page instead of an empty 200.

For the technical detail on any of these, see the matching plugin reference under `/help/category/Plugin Reference` or the per-feature user guide.
