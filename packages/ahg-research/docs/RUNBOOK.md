# ahg-research - Operator / Developer Runbook

Operational reference for running, configuring, testing, and troubleshooting the
`ahg-research` package. For the structural picture (controllers, services, data
model) see `ARCHITECTURE.md` in this folder; this runbook covers the runtime
knobs and the failure modes that actually bite in the field.

Every claim below is grounded in the package source. File paths are relative to
`packages/ahg-research/` unless stated otherwise.

---

## 1. Settings

### 1.1 AI is gateway-only

All AI inference in this package goes through the AHG AI gateway via the
`AhgAiServices\Services\LlmService` abstraction. No research service ever opens a
direct GPU-node port (no `:11434` Ollama, no `:5004` MT worker, etc.). The
research-side services that call the gateway all do so through `LlmService`
(`WritingStudioService`, `QuestionBuilderService`, `AnalysisBridgeService`,
`ContradictionEngineService`, `ReviewStudioService`, `GrantEngineService`,
`SourceTriageService`, `ArgumentBuilderService`, `PublicationStudioService`,
`ResearchCopilotService`, plus the optional narrative summary in
`AiDisclosureService`).

The gateway endpoint and key are read from `AhgAiServices\Support\AiServicesSettings`,
which is a read-only typed accessor over the `ahg_ner_settings` flat key/value
table (the settings dashboard owns the writes, at
`/admin/ahgSettings/aiServices`). The relevant keys:

| Accessor | `ahg_ner_settings` key | Notes |
|---|---|---|
| `apiUrl()` | `ai_services_api_url` | Gateway base URL (e.g. the `/ai/v1` door). Never a node port. |
| `apiKey()` | `ai_services_api_key` | Bearer key with the gateway scope. |
| `apiTimeout()` | `ai_services_api_timeout` | Default 60s. |
| `processingMode()` | `ai_services_processing_mode` | `local` / `cloud` / `hybrid`; default `local`. |
| `qdrantUrl()` | `qdrant_url` | Discovery / semantic search backend. |
| `qdrantCollection()` | `qdrant_collection` | |
| `qdrantModel()` | `qdrant_model` | Default `sentence-transformers/all-MiniLM-L6-v2`. |
| `qdrantMinScore()` | `qdrant_min_score` | Default 0.6. |

Note the embedding-related keys are `qdrant_*` (no `ai_services_` prefix); the
gateway endpoint keys are `ai_services_api_url` / `ai_services_api_key`.

Operator rule: if a research AI feature can't reach the gateway, fix the gateway
(add the route/model), do not point any setting at a node port.

### 1.2 ODRL rights-policy enforcement

Digital-rights enforcement is `OdrlService` (evaluates `research_rights_policy`)
plus `OdrlPolicyMiddleware` (`src/Middleware/OdrlPolicyMiddleware.php`). The
middleware is applied as `odrl:use` / `odrl:reproduce` / `odrl:distribute` on
record view/print routes.

Behaviour to remember when triaging an access complaint:

- **Admins bypass all policies.** The middleware calls `isAdmin($userId)`, which
  delegates to `UserProvisionerInterface::isInGroup($userId, 100)` - ACL group
  `100` is the administrator group.
- **No policies = access allowed.** If no `research_rights_policy` row targets the
  object, `OdrlService::isPermitted()` returns true (allow-by-default). So a "user
  can't see record X" report means a policy exists and denies them, not that
  policies are missing.
- The target object id is resolved from the route `slug` (looked up in the `slug`
  table to `object_id`) or a numeric `id` route param. If neither resolves, the
  request is allowed through.
- A denial returns HTTP 403 (JSON for `expectsJson()` requests, otherwise an abort
  with a "contact the repository administrator" message) and logs the denied
  access via `evaluateAccess()`.

### 1.3 Research mode (experience_level) and sidebar curation

`research_researcher.experience_level` holds `beginning` / `intermediate` /
`advanced` and curates which links the research sidebar shows plus the inline
mode guide.

- Resolved inside `resources/views/research/_sidebar.blade.php` itself (it reads
  `experience_level` for the current `user_id`), so the link set is correct on
  every page regardless of which controller rendered the sidebar. The rank map is
  `beginning=1, intermediate=2, advanced=3`; the **default when unset is
  `intermediate`**.
- Persisted two ways: the profile form (`resources/views/research/profile.blade.php`,
  the `experience_level` `<select>`) and the inline sidebar selector, which POSTs
  to `ResearchWorkspaceController::saveExperienceLevel`
  (route `research.saveExperienceLevel`) and writes via
  `ResearchService::updateResearcher`.
- The three values are a fixed select, not free text. They are validated on save.

### 1.4 Dropdowns - no ENUM

All enumerated values come from `ahg_dropdown` (Dropdown Manager pattern). Columns
that hold them are `VARCHAR`, never MySQL `ENUM`, and views never carry hardcoded
`<option>` lists for taxonomies. Even the AI-disclosure tables follow this (e.g.
`research_ai_disclosure_log` keeps all enumerated columns as `VARCHAR`).

### 1.5 Storage / ES

This package layers `research_*` sidecar tables on the shared Heratio DB and does
not own dedicated ES indices. It does perform storage writes (exports, generated
documents), so the php-fpm `ReadWritePaths` drop-in must be in place - see 4.5.

---

## 2. AI usage and disclosure

### 2.1 Which slices call the gateway

Research OS Part IV ("AI Containment", heratio#1242) tracks AI use across these
AI-capable slices, all routing through `LlmService`:

- Writing Studio
- Question Builder (#1226)
- Analysis Bridge (#1234)
- Grant Engine
- Publication Studio (#1232)
- Research Copilot
- Research Studio (#1240)
- Review Studio (#1230)
- Source Triage (#1227)
- Contradiction Engine (#1236)
- Argument Builder (no AI write path ships today - its detector stays empty until
  one is wired)

### 2.2 AiDisclosureService - read-time aggregation

`AiDisclosureService::gather($projectId)` assembles a per-project AI-use disclosure
by READING already-landed slices. It never alters those slice tables. The only
table this slice writes is `research_ai_disclosure_log` (defined in
`database/install_ai_disclosure.sql`), the manual log for AI assistance the system
cannot detect on its own (e.g. a model used outside Heratio).

Two kinds of detector run inside `gather()`, each `Schema::hasTable` /
`Schema::hasColumn` guarded and try/catch wrapped so a missing table contributes
nothing rather than 500ing:

1. **Structural detectors** - tables whose schema already records AI provenance:
   - `research_review_run` (Review Studio) - persona / model / created_at
   - `research_source_triage` (Source Triage) - ai_preview / ai_preview_at
   - `research_contradiction` (Contradiction Engine) - rows where `source='ai'`
   - `research_studio_artefact` (Research Studio) - each non-errored row is a
     gateway generation; model / created_at per row
2. **Marker detectors** - the `ai_at IS NOT NULL` rows in the slices that gained
   the #1252 per-row markers (see 2.3).

The disclosure statement is assembled from these records - no AI call is needed to
produce it. An optional narrative summary can be requested separately, and if so it
routes only through `LlmService` and is labelled AI-generated.

### 2.3 The ai_model / ai_at / ai_decision markers (#1252)

#1252 added per-row markers to the seven AI-capable slices that previously
persisted gateway output without a marker. The columns are added idempotently at
boot (see 4.4), never by `artisan migrate`:

| Column | Type | Meaning |
|---|---|---|
| `ai_model` | `VARCHAR(120) NULL` | The model the AHG gateway used. |
| `ai_at` | `DATETIME NULL` | Generation timestamp. **NULL means the row was NOT AI-produced** and is therefore not disclosed. |
| `ai_decision` | `VARCHAR(12) NULL` | `pending` / `accepted` / `rejected`. NULL = not an AI row. |
| `ai_decided_at` | `DATETIME NULL` | When the researcher decided. |
| `ai_decided_by` | `INT NULL` | User id that accepted/rejected. |

The seven tables: `research_writing_version`, `research_question_brief`,
`research_analysis_result`, `research_grant_draft`, `research_argument`,
`research_submission`, `research_copilot_answer` (copilot also gained `project_id`
so its answers attribute per project).

Critical invariant: the marker is stamped **only on the write where the gateway
actually produced the output**, never on a purely manual write - so hand-authored
work is never falsely disclosed as AI. Example: when Writing Studio inserts an AI
draft it sets `ai_model = <gateway model>`, `ai_at = now()`, and
`ai_decision = 'pending'`; a manual version insert leaves all three NULL.

### 2.4 Accept / Reject control (#1252)

The `<x-research::ai-decision>` Blade component fetch-POSTs `{slice, id, decision}`
to `ResearchAiDecisionController::decision`
(`src/Controllers/ResearchAiDecisionController.php`). On `{ok:true}` the component
swaps its Accept/Reject buttons for the decided badge in place.

Security shape - worth knowing before changing it:

- The `slice` key is validated against a fixed allowlist
  (`in:writing,question,analysis,grant,publication,copilot`) and the table name is
  looked up server-side from `AiDisclosureService::DECISION_TABLES`. **No table name
  ever derives from request input.**
- `decision` is validated `in:accepted,rejected`.
- The endpoint is auth-gated; `Auth::id()` is the acting user (401 if unauthenticated).
- `recordAiDecision()` only updates rows where `ai_decision IS NOT NULL` (i.e. an
  AI-produced row, which starts at `pending`), writing `ai_decision`,
  `ai_decided_at = now()`, `ai_decided_by = userId`. It returns false (422 to the
  caller) if no eligible row matched.
- Argument Builder has no AI write path today, so it is intentionally **not** an
  accept/reject target despite having the marker columns.

---

## 3. Tests

The package's tests live in `tests/Feature/` and run against a **pre-built**
`heratio_test` database with `DatabaseTransactions` for rollback - **not**
`RefreshDatabase`.

Why not `RefreshDatabase`: the ~995 AtoM base tables (`object`, `actor`, `user`,
`acl_user_group`, ...) come from `database/core/*.sql` dumps, not from Laravel
migrations. `RefreshDatabase` drops everything and rebuilds from migrations, which
cannot reconstruct those base tables - so it would tear down the schema the tests
depend on (issue #1136). `DatabaseTransactions` wraps each test in a transaction and
rolls back, leaving the pre-built schema intact.

### 3.1 Build / refresh the test DB

```bash
./bin/reset-test-db
```

This drops and recreates `heratio_test` as a clone of the `archive` database
(schema + data, FK checks disabled during the load), then runs the Laravel
framework migrations as `www-data`. The package's own `research_*` tables are
provisioned by the service-provider boot-ensure on first app boot under the
testing env. Re-run it whenever the `archive` schema or reference data changes.

### 3.2 Run phpunit as www-data

```bash
sudo -u www-data vendor/bin/phpunit --filter ResearchUserProvisionerTest
```

Run as `www-data` so any bootstrap log files Laravel creates are not owned
`root:root` (a root-owned `storage/logs/laravel-*.log` blocks the next web request -
see 4.5). Do not run phpunit as root from the app directory.

---

## 4. Common errors and fixes

### 4.1 `user` table has no `updated_at` (500 regression)

The AtoM `user` table has **no `updated_at` column** - timestamps live on the
`object` row instead. `EloquentUserProvisioner::updateUser()` therefore must never
inject `updated_at` into a `user` update; doing so throws a MySQL 1054 (unknown
column) and 500s the request. This was a real regression. The provisioner's
`createUser()` does write `created_at`/`updated_at`, but only on the **`object`**
insert, which is correct.

Symptom: a 500 on registration / approve / suspend / password-reset with a "1054
Unknown column 'updated_at'" against `user`. Fix: keep the provisioner the single
writer of the auth tables and never add `updated_at` to a `user` write.

### 4.2 MT / translate gateway response key is `translated`

The AHG gateway `/ai/v1/translate` route (opus-mt) returns the text under the key
`translated`, while the local MT adapter returns `translation`. Code that consumes
a translation must accept both - `LlmService` reads
`translation ?? translated ?? translatedText`. A translation that comes back empty
after pointing at the gateway is the classic symptom of reading only
`translation`.

### 4.3 Embeddings 404 when the gateway node lacks the model

Semantic / discovery features embed via the gateway. If the target gateway node
does not have the embedding model pulled, the embedding call 404s. The default
embedding model is `sentence-transformers/all-MiniLM-L6-v2` (`qdrant_model`); the
`all-minilm` model must be pulled on the node the gateway routes to. Fix the
gateway/node (pull the model) - do not work around it with a direct node call.

### 4.4 Boot-ensure schema pattern (no `artisan migrate` in prod)

This package provisions schema at app boot, not via `artisan migrate`.
`AhgResearchServiceProvider::boot()` runs idempotent installs inside
`$this->app->booted(...)` callbacks:

- New tables: `Schema::hasTable()` guard, then `DB::unprepared()` of the matching
  `database/install_*.sql` (and an optional dropdown-seed SQL when `ahg_dropdown`
  exists).
- New columns on existing sidecar tables: one guarded `ALTER TABLE` per column
  (`Schema::hasTable && ! Schema::hasColumn` then `DB::statement("ALTER TABLE ...")`),
  e.g. the `ai_model` / `ai_at` / `ai_decision` markers and `experience_level`.

Each block is try/catch wrapped so a DB-not-ready boot just retries on the next
boot. Base AtoM tables are never altered. The `database/migrations/` files exist
only for the Docker / `migrate` path. Practical consequence: to roll out a new
`research_*` table or column in production you add the install SQL / ALTER entry to
the boot map and deploy - the next app boot applies it.

### 4.5 php-fpm ReadWritePaths drop-in for storage writes

php-fpm on this host runs with `ProtectSystem=full`, which mounts the app tree
read-only for the worker. Research storage writes (exports, generated documents,
logs, sessions, compiled views) need the per-app systemd drop-in granting
`ReadWritePaths` for `storage/` and `bootstrap/cache/`, or the request 500s with a
"Read-only file system" error against `storage/logs/laravel-*.log`. The drop-in is
already installed for Heratio; if storage-write 500s appear, verify it with
`systemctl show php8.3-fpm | grep ReadWritePaths`. Companion failure mode: a
root-owned daily log file (from running `php artisan` as root) blocks the
`www-data` worker until chowned - prefer `sudo -u www-data php artisan ...`.

---

## See also

- `ARCHITECTURE.md` - structure, controllers, services, data model.
- `docs/research/condition.md`, `docs/research/provenance.md` - feature notes.
