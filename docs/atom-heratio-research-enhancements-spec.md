# AtoM Heratio - Research Enhancements Specification

> **Target:** AtoM Heratio fork at `/usr/share/nginx/archive/atom-ahg-plugins/` (Symfony 1.4, PHP 8.x, MySQL 8, ProQubit + AHG plugins).
>
> **Source-of-truth implementation:** the Laravel Heratio side at `/usr/share/nginx/heratio/`, shipped 2026-05-16 as part of `docs/research-enhancements-roadmap.md`. This document is the port spec - same 13 features, AtoM idioms.
>
> **Status:** draft v1.0, 2026-05-16. To be implemented as a new release of `ahgResearchPlugin` (or split out as new plugins where the surface is large enough - see per-feature notes).
>
> **Owner:** AtoM Heratio team. Heratio (Laravel) is the reference implementation; bug-for-bug parity is not required, but the user-visible surface should match so cross-instance researchers experience the same workflow.

---

## Scope

13 features to port. They cluster as:

- **§1 NotebookLM-Studio additions** (6 items) - grounded AI artefact generator on every research project, citation popovers, private notebooks, cross-fonds queries.
- **§2 Audit gaps** (7 items) - per-record citation manager export, analytics dashboard, real-time collaboration, ORCID integration (extend existing stub), GraphQL for researchers, mobile/PWA, offline mode.

Each section below maps the Laravel-side implementation onto AtoM Heratio's plugin structure, listing:

1. **AtoM paths** - where the new code lives.
2. **Existing service to extend** - many of these already have stubs in `ahgResearchPlugin/lib/Services/`. Extend rather than re-create.
3. **DB tables** - new tables to add (use migrations under `ahgResearchPlugin/database/migrations/`). All AtoM tables use the `ahg_*` prefix or `qubit_*` for ProQubit-shipped tables. Stick with `ahg_*` for new ones to keep the AHG-fork sidecar pattern clear.
4. **Routes / actions / templates** - Symfony 1.4 routing (defined in `ahgResearchPluginConfiguration.class.php`), action class + per-action template.
5. **JS / CSS** - dropped under `ahgResearchPlugin/web/`.
6. **Integration touchpoints** - where the new feature shows up in existing AtoM Heratio menus, sidebars, and per-record action bars.

---

## Conventions

- **All AI calls go through the existing AHG AI gateway** at `https://ai.theahg.co.za/ai/v1/*`. No provider keys live in AtoM Heratio. Reuse `ahgAIPlugin/lib/Services/LlmService.php` (or whichever wrapper the AHG AI plugin exposes) - do NOT introduce a separate workbench-gateway driver. The gateway is already the central abstraction.
- **All new tables use `IF NOT EXISTS`** in their migration up() and `IF EXISTS` in down() so re-runs are idempotent.
- **Symfony slot scheme** - add new sidebar entries via the existing AHG sidebar slots, not by re-templating the layout. See `ahgResearchPlugin/modules/research/templates/_sidebar.php` for the pattern.
- **Permissions** - use the existing `ResearcherPermissionHelper` from `ahgResearchPlugin/lib/Helpers/`. Routes that mutate require a logged-in researcher; reads can be public where the existing patterns allow.
- **Heratio (Laravel) calls the gateway through `LlmService::completeFull()` with a system+user prompt and `temperature` / `max_tokens` options.** AtoM Heratio's `LlmService` should expose the same shape so prompt templates can be copy-pasted between the two sides.

---

## §1.1 - §1.3 Studio pane on research project show

**What:** every research project gets a Studio tab. Researchers drop in source items from their evidence sets, pick an output type, and an LLM-grounded artefact is produced with `[N]` citation markers. Supports 8 output types.

**Output types** (exact list - keep parity with Laravel side):
`briefing` | `study_guide` | `faq` | `timeline` | `diagram` (Mermaid) | `video_script` | `spreadsheet` | `audio`.

**AtoM paths:**

```
ahgResearchPlugin/
├── lib/Services/ResearchStudioService.php          (new)
├── modules/research/actions/
│   ├── studioAction.class.php                       (new) - list page
│   ├── studioGenerateAction.class.php               (new) - POST handler
│   ├── studioShowAction.class.php                   (new) - artefact show
│   ├── studioDownloadAction.class.php               (new) - .xlsx / mp3 download
│   └── studioDeleteAction.class.php                 (new)
├── modules/research/templates/
│   ├── studioSuccess.php                            (new)
│   └── studioShowSuccess.php                        (new)
├── web/js/citation-popover.js                       (new - see §1.4)
└── database/migrations/2026_05_16_research_studio_artefact.php   (new)
```

**Route additions** (in `ahgResearchPluginConfiguration.class.php::registerRoutes()`):

```php
'/research/studio/:projectId' => 'research/studio',
'/research/studio/:projectId/generate' => 'research/studioGenerate',     // POST
'/research/studio/:projectId/artefact/:artefactId' => 'research/studioShow',
'/research/studio/:projectId/artefact/:artefactId/download' => 'research/studioDownload',
'/research/studio/:projectId/artefact/:artefactId/delete' => 'research/studioDelete',  // POST
```

**New table** (`ahgResearchPlugin/database/migrations/2026_05_16_research_studio_artefact.php`):

```sql
CREATE TABLE IF NOT EXISTS ahg_research_studio_artefact (
    id INT NOT NULL AUTO_INCREMENT,
    project_id INT NOT NULL,
    created_by INT NULL,
    output_type VARCHAR(40) NOT NULL COMMENT 'briefing, study_guide, faq, timeline, diagram, video_script, spreadsheet, audio',
    title VARCHAR(500) NULL,
    body MEDIUMTEXT,
    body_format VARCHAR(20) DEFAULT 'markdown' COMMENT 'markdown, html, json, mermaid, csv',
    source_object_ids JSON NULL COMMENT 'IO ids the artefact was synthesised from',
    citations JSON NULL COMMENT 'list of {n, object_id, title, snippet, url} backing each [N] marker in body',
    model VARCHAR(120) NULL,
    tokens_used INT DEFAULT 0,
    generation_time_ms INT NULL,
    audio_url VARCHAR(500) NULL,
    audio_digital_object_id INT NULL,
    audio_duration_seconds INT NULL,
    audio_transcript MEDIUMTEXT,
    xlsx_path VARCHAR(500) NULL,
    status VARCHAR(20) DEFAULT 'ready' COMMENT 'pending, generating, ready, error',
    error_text TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project (project_id),
    KEY idx_output_type (output_type),
    KEY idx_status (status),
    KEY idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Service contract** (`ResearchStudioService::generate()`):

```php
public function generate(
    int $projectId,
    array $sourceObjectIds,
    string $outputType,
    array $options = [],
    ?int $createdBy = null
): int                                              // returns artefact id
```

**Per-output-type prompt templates** - lift verbatim from Heratio's `ResearchStudioService::promptFor()`. The system prompt is the same: archival-research assistant, must cite `[N]` only from supplied sources, no invention. The user prompt embeds the source block + the output-type-specific instruction.

**Spreadsheet generation** - locally, with `PhpOffice\PhpSpreadsheet`. Two-step:

1. LLM is asked for strict JSON `{header, intro, columns, rows}`.
2. `buildXlsxFile()` writes the .xlsx to `sf_upload_dir() . '/research-studio/<projectId>/artefact-<id>.xlsx'`.

**Audio generation** - LLM writes a two-voice script (Host + Curator); script is POSTed to `app_ahg_tts_endpoint` (new app.yml setting, defaults null). If unset, artefact lands in `status='error'` but transcript is persisted so operators can hand it to TTS manually. Configure in `apps/qubit/config/app.yml`:

```yaml
all:
    ahg:
        tts_endpoint: ~                  # set to https://your-tts-host
        tts_key:      ~
```

**Sidebar integration** - in `ahgResearchPlugin/modules/research/templates/_projectShow.php`, add a "Studio" link to the Research Output card alongside the existing RO-Crate / DOI / Compliance links.

**Source pool query** - sources for the Studio form are populated from the existing `ahg_research_collection_item` rows belonging to collections under the chosen project (filter on `ahg_research_collection.project_id`). Pattern is identical to what `bibliographyAction` does for the bibliography picker.

---

## §1.4 Citation hover popovers

**What:** any `[N]` inline marker in a Studio artefact, report, or research output renders as a hover popover (source title + 220-char snippet + "Open source" link) and a click that scrolls to the matching source list item.

**AtoM paths:**

```
ahgResearchPlugin/web/js/citation-popover.js     (new)
```

**Loading:** include in `_studioShow.php` (and any other view that renders `[N]` markers) via:

```php
<?php use_javascript('/ahgResearchPlugin/citation-popover.js') ?>
```

**Implementation:** copy the JS verbatim from Heratio's `public/vendor/ahg-research/citation-popover.js`. No build step. The script walks text nodes inside `.markdown-body`, `#studio-body`, `.studio-citations-host`, wraps `[N]` patterns in `<a class="citation-marker">`, builds a popover from `#studio-citations [data-citation-n="N"]` list items.

**Template contract:** any view that wants popovers must render a `<ul id="studio-citations">` with `<li data-citation-n="N">` rows, each containing an `<a>` (URL) and a `.small.text-muted` (snippet). See `_studioShow.php` for the template.

---

## §1.5 Researcher private notebooks

**What:** researcher-owned scratchpad. Items: saved queries, AI outputs, pinned source items, freeform notes. One-click promote-to-public turns a notebook into a research project.

**AtoM paths:**

```
ahgResearchPlugin/
├── lib/Services/NotebookService.php                            (new)
├── modules/research/actions/
│   ├── notebooksAction.class.php                                (new) - list + create
│   ├── notebookShowAction.class.php                             (new) - show + add/remove/pin
│   ├── notebookDeleteAction.class.php                           (new) - POST
│   └── notebookPromoteAction.class.php                          (new) - POST
├── modules/research/templates/
│   ├── notebooksSuccess.php
│   └── notebookShowSuccess.php
└── database/migrations/2026_05_16_research_notebook.php
```

**Route additions:**

```php
'/research/notebooks' => 'research/notebooks',
'/research/notebooks/:id' => 'research/notebookShow',
'/research/notebooks/:id/delete' => 'research/notebookDelete',   // POST
'/research/notebooks/:id/promote' => 'research/notebookPromote', // POST
```

**New tables:**

```sql
CREATE TABLE IF NOT EXISTS ahg_research_notebook (
    id INT NOT NULL AUTO_INCREMENT,
    researcher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT,
    cover_object_id INT NULL,
    promoted_to_project_id INT NULL,
    promoted_at DATETIME NULL,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_researcher (researcher_id),
    KEY idx_promoted (promoted_to_project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ahg_research_notebook_item (
    id INT NOT NULL AUTO_INCREMENT,
    notebook_id INT NOT NULL,
    item_type VARCHAR(30) NOT NULL COMMENT 'saved_query, ai_output, source_pin, note',
    title VARCHAR(500) NULL,
    body MEDIUMTEXT,
    source_object_id INT NULL,
    saved_search_id INT NULL,
    ai_output_payload JSON NULL,
    pinned TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notebook (notebook_id),
    KEY idx_item_type (item_type),
    KEY idx_source_object (source_object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Service contract:**

```php
NotebookService::ITEM_TYPES = ['saved_query', 'ai_output', 'source_pin', 'note'];

public function listForResearcher(int $researcherId): array;
public function get(int $id): ?stdClass;
public function getItems(int $notebookId): array;
public function create(int $researcherId, array $data): int;
public function update(int $id, array $data): bool;
public function delete(int $id): bool;
public function addItem(int $notebookId, array $data): int;
public function updateItem(int $itemId, array $data): bool;
public function removeItem(int $itemId): bool;
public function promoteToProject(int $notebookId, int $researcherId): ?int;
```

**Promote-to-project flow** - inside a single DB transaction:

1. INSERT into `ahg_research_project` (owner_id = researcher, title = notebook title, description = notebook summary, project_type = 'public').
2. INSERT into `ahg_research_project_collaborator` with role='owner', status='accepted'.
3. INSERT into `ahg_research_collection` (project_id = new, name = "Promoted from notebook: ..."), then for each `source_pin` item INSERT into `ahg_research_collection_item` (object_id = source_object_id).
4. UPDATE `ahg_research_notebook` SET `promoted_to_project_id`, `promoted_at`.
5. Idempotent: if `promoted_to_project_id` is already set, return it without re-promoting.

**Sidebar entry** - add a Notebooks link to the Research sidebar (in `_sidebar.php`) under the Bibliographies entry.

---

## §1.6 Cross-fonds reasoning queries

**What:** single user query fans out across N selected fonds in parallel; per-fonds top-K hits are merged and reranked by Elasticsearch score; single ranked list returned with `[N]` citation markers. Optional thesaurus expansion via `ahgSemanticSearchPlugin` (already present in AtoM Heratio).

**AtoM paths:**

```
ahgResearchPlugin/
├── lib/Services/CrossFondsQueryService.php
├── modules/research/actions/crossFondsQueryAction.class.php
├── modules/research/templates/crossFondsQuerySuccess.php
└── database/migrations/2026_05_16_research_cross_fonds_query.php
```

**Route:**

```php
'/research/cross-fonds-query' => 'research/crossFondsQuery',
```

**New table:**

```sql
CREATE TABLE IF NOT EXISTS ahg_research_cross_fonds_query (
    id INT NOT NULL AUTO_INCREMENT,
    researcher_id INT NULL,
    query_text VARCHAR(1000) NOT NULL,
    fonds_ids JSON NULL,
    results_count INT DEFAULT 0,
    elapsed_ms INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_researcher (researcher_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fonds picker:** query `qubit_information_object` joined to `qubit_term_i18n` where the level-of-description term name matches "Fonds" or "Collection". Cap to 200 results.

**Fan-out:** one ES query per selected fonds, scoped by `lft`/`rgt` range (`lft >= fonds.lft AND rgt <= fonds.rgt`). Use AtoM's existing `arElasticSearchPluginUtil` for the connection. Per-fonds top-K = 10; merge by `_score`; final top-K = 30.

**Service contract:**

```php
public function availableFonds(int $limit = 200): array;
public function query(string $query, array $fondsIds, ?int $researcherId = null, array $options = []): array;
// returns ['results' => [...], 'total' => N, 'elapsed_ms' => N, 'expanded_query' => '...']
```

**Optional expansion:** check `class_exists(SemanticSearchService::class)` and call `expandQuery()`. Fold expansion terms with `OR` into the query string.

**Sidebar entry** - add under "Research" with icon `network-wired`.

---

## §2.1 Per-record citation manager export (RIS / BibTeX / EndNote / APA / MLA / Chicago)

**What:** picker on `/research/cite/{slug}` that downloads a file in the chosen format. Six formats. The existing `bibliographyAction` has multi-record exporters for RIS / BibTeX / ZoteroRDF / MendeleyJSON / CSL-JSON; this adds **per-single-record** exporters in the formats academic researchers paste into Zotero / EndNote / LaTeX / etc.

**AtoM paths:**

```
ahgResearchPlugin/
├── lib/Services/CitationService.php                          (new)
└── modules/research/actions/
    ├── citeExportAction.class.php                            (new) - download endpoint
    └── (modify) citeAction.class.php                         (extend to pass exportFormats)
└── modules/research/templates/
    └── (modify) citeSuccess.php                              (add picker card)
```

**Route:**

```php
'/research/cite/:slug/export/:format' => array(
    'module' => 'research', 'action' => 'citeExport',
    'requirements' => array('format' => 'ris|bibtex|endnote|apa|mla|chicago'),
),
```

**Service contract:**

```php
public const FORMATS = [
    'ris'     => 'RIS (Zotero / Mendeley / EndNote)',
    'bibtex'  => 'BibTeX (LaTeX, JabRef)',
    'endnote' => 'EndNote XML',
    'apa'     => 'APA 7',
    'mla'     => 'MLA 9',
    'chicago' => 'Chicago 17 (Notes-Bibliography)',
];

public function export(int $objectId, string $format): array;
// returns ['format', 'label', 'body', 'filename', 'mime']
```

**Implementation notes:** the Laravel side has the full RIS / BibTeX / EndNote XML / APA / MLA / Chicago format methods - lift them verbatim from `packages/ahg-research/src/Services/CitationService.php`. Difference: the AtoM record loader joins `qubit_information_object` + `qubit_information_object_i18n` + `qubit_event` (creator + date) + `qubit_repository`. Pattern is identical to the existing `generateCitationFromRecord()` in `BibliographyService.php` - reuse the joins from there.

**MIME types:**
- `ris` → `application/x-research-info-systems` (filename: `.ris`)
- `bibtex` → `application/x-bibtex` (`.bib`)
- `endnote` → `application/xml` (`.xml`)
- `apa`/`mla`/`chicago` → `text/plain` (`.txt`)

**UI:** add the "Copy in citation manager format" card to `citeSuccess.php` above the existing styled citations card. Six anchor tags, each linking to `/research/cite/<slug>/export/<format>`.

---

## §2.2 Research analytics dashboard

**What:** date-filtered dashboard at `/research/analytics`. Aggregates the existing `ahg_research_activity_log` + `ahg_research_citation_log` into KPIs + top-N lists + weekly volume chart. **No new audit tables** - the data is already being logged by existing actions.

**AtoM paths:**

```
ahgResearchPlugin/
├── lib/Services/ResearchAnalyticsService.php
├── modules/research/actions/analyticsAction.class.php
└── modules/research/templates/analyticsSuccess.php
```

**Route:** `/research/analytics?from=YYYY-MM-DD&to=YYYY-MM-DD`. Defaults: last 30 days.

**Service shape:**

```php
public function dashboard(?string $from = null, ?string $to = null): array;
// keys: period, usage_totals, daily_series, top_activity_types,
//       top_researchers, popular_collections, popular_descriptions,
//       search_terms, citations_by_style, date_range_distribution
```

**KPIs rendered** (8 tiles): total events, unique researchers, unique objects, view events, search events, cite events, download events, annotation events.

**Lists rendered:** top researchers (10), popular descriptions (10), popular collections (10), top search terms (15), weekly volume bar.

**Charting:** the Laravel side uses inline `<div>` bars (no Chart.js). AtoM Heratio can do the same; if you'd rather use the existing `arChart` helper from `ahgDisplayPlugin`, that's fine.

**Sidebar entry** - "Analytics" under "Research".

---

## §2.3 Real-time collaboration (polling fallback)

**What:** project-scoped presence indicators + threaded comments on evidence sets + shared annotation layers on IIIF canvases. Updates every 3s via polling (no WebSocket broker on the AHG host). The existing `CommentService` and `CollaborationService` in `ahgResearchPlugin/lib/Services/` can be extended.

**AtoM paths:**

```
ahgResearchPlugin/
├── lib/Services/CollaborationRealtimeService.php       (new - polling-specific)
│   (extend existing CommentService where it already covers what you need)
├── modules/research/actions/
│   ├── collabPanelAction.class.php                     (new)
│   ├── collabJoinAction.class.php                      (new - JSON)
│   ├── collabPollAction.class.php                      (new - JSON)
│   ├── collabCommentAction.class.php                   (new - JSON)
│   └── collabCommentResolveAction.class.php            (new - JSON)
└── modules/research/templates/collabPanelSuccess.php
```

**Routes:**

```php
'/research/projects/:projectId/realtime/panel' => 'research/collabPanel',
'/research/projects/:projectId/realtime/join' => 'research/collabJoin',           // POST JSON
'/research/projects/:projectId/realtime/poll' => 'research/collabPoll',            // POST JSON
'/research/projects/:projectId/realtime/comment' => 'research/collabComment',      // POST JSON
'/research/projects/:projectId/realtime/comment/:commentId/resolve' => 'research/collabCommentResolve',  // POST JSON
```

**New tables:**

```sql
CREATE TABLE IF NOT EXISTS ahg_research_collaboration_session (
    id INT NOT NULL AUTO_INCREMENT,
    project_id INT NOT NULL,
    started_by INT NOT NULL,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    expires_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_project (project_id),
    KEY idx_active (project_id, ended_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ahg_research_collaboration_presence (
    id INT NOT NULL AUTO_INCREMENT,
    project_id INT NOT NULL,
    researcher_id INT NOT NULL,
    session_id INT NULL,
    cursor_target VARCHAR(200) NULL COMMENT 'route+anchor that identifies what the collaborator is viewing',
    user_color VARCHAR(7) NULL COMMENT '#rrggbb assigned for this session',
    last_seen_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_project_researcher (project_id, researcher_id),
    KEY idx_session (session_id),
    KEY idx_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ahg_research_evidence_comment (
    id INT NOT NULL AUTO_INCREMENT,
    collection_id INT NULL,
    item_id INT NULL,
    project_id INT NULL,
    author_id INT NOT NULL,
    parent_comment_id INT NULL,
    body TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'open',
    resolved_by INT NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_collection (collection_id),
    KEY idx_item (item_id),
    KEY idx_project (project_id),
    KEY idx_author (author_id),
    KEY idx_status (status),
    KEY idx_parent (parent_comment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Presence stale-out:** 90 seconds without a heartbeat removes the researcher from the "Online now" list.

**Polling JS:** ~80 lines in `collabPanelSuccess.php` `<script>` block. Pattern (verbatim from the Laravel side):

```javascript
setInterval(function () {
    fetch(pollUrl + '?since=' + cursor, { method: 'POST', ... })
        .then(r => r.json())
        .then(data => { renderPresence(data.presence); appendComments(data.comments); cursor = data.cursor; });
}, 3000);
```

**Shared annotation layers (IIIF):** the existing `qubit_iiif_annotation` table (assuming AtoM Heratio runs ProQubit's IIIF annotations or the AHG WebAnnotationService schema - check `lib/Services/WebAnnotationService.php`) needs two new columns:

```sql
ALTER TABLE qubit_iiif_annotation
    ADD COLUMN IF NOT EXISTS project_id INT NULL AFTER information_object_id,
    ADD COLUMN IF NOT EXISTS visibility VARCHAR(20) NOT NULL DEFAULT 'private' AFTER project_id,
    ADD KEY IF NOT EXISTS project_idx (project_id),
    ADD KEY IF NOT EXISTS visibility_idx (visibility);
```

(If the AHG table is named `ahg_web_annotation` or similar, substitute accordingly.) Then extend the existing `WebAnnotationService` (or the Annotation REST endpoint) to accept `projectId` + `visibility` on create and filter on `projectId` / `visibility` / `createdBy` query params in the W3C search endpoint. Default visibility stays `private`.

**Project show integration** - add "Live Collaboration" link to the Research Output card on `_projectShow.php`.

---

## §2.4 ORCID OAuth + Works push/pull (EXTEND existing)

**Important:** `ahgResearchPlugin/lib/Services/OrcidService.php` **already exists** on the AtoM Heratio side. Audit it first; this spec describes the full target shape - extend the existing service rather than replace it.

**Target service shape:**

```php
public function isConfigured(): bool;
public function authorizeUrl(?string $state = null): string;
public function exchangeCode(string $code): array;
public function linkResearcher(int $researcherId, array $tokenResponse): void;
public function unlink(int $researcherId): void;
public function getLink(int $researcherId): ?stdClass;
public function pullWorks(int $researcherId): array;
public function pushWork(int $researcherId, array $citation): ?string;   // returns put-code
```

**New table** (if not already present - the AtoM-side OrcidService may use `qubit_user` columns instead):

```sql
CREATE TABLE IF NOT EXISTS ahg_researcher_orcid_link (
    id INT NOT NULL AUTO_INCREMENT,
    researcher_id INT NOT NULL,
    orcid_id VARCHAR(19) NOT NULL,
    access_token_encrypted TEXT,
    refresh_token_encrypted TEXT,
    scope VARCHAR(200) NULL,
    expires_at DATETIME NULL,
    last_synced_at DATETIME NULL,
    last_works_count INT NULL,
    last_error TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_researcher (researcher_id),
    UNIQUE KEY uniq_orcid (orcid_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Tokens** stored AES-256-CBC encrypted using a key derived from sf_config('sf_app_secret'). Mirror the encrypt/decrypt helpers from `EmailService` or `LlmService` if they exist; otherwise port them from the Laravel side.

**OAuth scope to request:** `/authenticate /read-limited /activities/update`.

**Required config** (`apps/qubit/config/app.yml`):

```yaml
all:
    ahg:
        orcid_client_id: ~
        orcid_client_secret: ~
        orcid_redirect_uri: ~               # default {APP_URL}/research/orcid/callback
        orcid_base: 'https://orcid.org'     # or sandbox
        orcid_api_base: 'https://api.orcid.org'   # or pub
```

**Routes:**

```php
'/research/orcid' => 'research/orcid',
'/research/orcid/authorize' => 'research/orcidAuthorize',
'/research/orcid/callback' => 'research/orcidCallback',
'/research/orcid/sync' => 'research/orcidSync',       // POST
'/research/orcid/unlink' => 'research/orcidUnlink',   // POST
```

**Works push XML** - build a W3C namespace `<work:work>` document per the [ORCID Member API v3.0 schema](https://github.com/ORCID/orcid-model/blob/master/src/main/resources/record_3.0/work-3.0.xsd). The Laravel side has the full template in `OrcidService::buildWorkXml()` - lift it.

**Status page UX** - mirror Laravel's `orcid-link.blade.php`:

- Not configured → clean "ORCID not configured" alert listing the exact ENV keys.
- Configured but not linked → "Connect with ORCID" button.
- Linked → ORCID iD, scope, token expiry, last-sync, "Pull Works" + "Unlink" buttons.

**Sidebar entry** - "ORCID Link" under "Research".

---

## §2.5 GraphQL for researchers

**What:** five new queries on top of the existing `/admin/graphql` endpoint, enabling Zotero / Tropy / LMS to compose a single round-trip.

**AtoM paths:** if AtoM Heratio has an existing `ahgGraphqlPlugin` (or similar), extend it; otherwise create a new plugin shell. Per the Laravel-side controller this is a hand-rolled regex matcher, not a full GraphQL framework - keep it simple.

**New queries:**

| Query | Returns |
|---|---|
| `researchProject(id: Int!)` | id, title, description, project_type, status, collections[], studio_artefacts[] |
| `researchProjects(limit: Int)` | paginated list |
| `researchAnnotations(targetIri: String!)` | W3C Web Annotations for a canvas IRI, with project_id + visibility |
| `researchCollections(projectId: Int!)` | collections + items joined |
| `researcherView(researcherId: Int!)` | researcher + projects + recent annotations + ORCID link summary - **single round-trip query for external tools** |

**Implementation:** copy `resolveResearcherView()` and friends verbatim from the Laravel `packages/ahg-graphql/src/Controllers/GraphqlController.php`. The query string parsing is regex-based and trivial to port.

**Schema introspection:** extend `getSchemaInfo()` to add `ResearchProject`, `ResearchCollection`, `ResearchAnnotation`, `ResearcherView` types.

---

## §2.6 Mobile / PWA

**What:** `/research/mobile` is a phone-first researcher home. PWA manifest enables "Add to home screen" / standalone mode.

**AtoM paths:**

```
ahgResearchPlugin/
├── modules/research/actions/mobileHomeAction.class.php
└── modules/research/templates/mobileHomeSuccess.php

(AtoM web root)
├── web/manifest.webmanifest                              (new)
└── web/sw.js                                             (new)
```

**Route:** `/research/mobile` (under the `research` module).

**Manifest** (`web/manifest.webmanifest`):

```json
{
    "name": "AtoM Heratio",
    "short_name": "AtoM Heratio",
    "description": "Archival management portal - research",
    "start_url": "/research/mobile",
    "scope": "/",
    "display": "standalone",
    "background_color": "#ffffff",
    "theme_color": "#0d6efd",
    "icons": [{"src": "/favicon.ico", "sizes": "any", "type": "image/x-icon"}],
    "shortcuts": [
        {"name": "Mobile research", "url": "/research/mobile"},
        {"name": "Cross-fonds query", "url": "/research/cross-fonds-query"},
        {"name": "Notebooks", "url": "/research/notebooks"}
    ]
}
```

Serve at root via nginx (no rewrite). The standard AtoM nginx config already serves static files from `web/` at the root URL.

**Mobile template** - copy structure from Heratio's `mobile-home.blade.php`:

- Name/email + online/offline badge.
- Reading list (most-recent 50 collection items).
- 4-button grid: Search, Notes, Bibliographies, Journal.
- Quick journal entry form.
- Inline `<script>` block: register `/sw.js`, manage `navigator.onLine` events, localStorage queue (`heratio_offline_queue_v1`), POST to `/research/sync/offline` on flush.

**Meta head additions:**

```html
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#0d6efd">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="AtoM Heratio">
```

---

## §2.7 Offline mode + sync endpoint

**What:** service worker that caches the mobile shell. localStorage queue for journal entries written offline. Sync endpoint that drains the queue.

**AtoM paths:**

```
ahgResearchPlugin/
├── lib/Services/OfflineSyncService.php
└── modules/research/actions/offlineSyncAction.class.php

(AtoM web root)
└── web/sw.js                                          (new)
```

**Route:**

```php
'/research/sync/offline' => 'research/offlineSync',   // POST JSON
```

**New audit table:**

```sql
CREATE TABLE IF NOT EXISTS ahg_research_offline_sync_log (
    id BIGINT NOT NULL AUTO_INCREMENT,
    researcher_id INT NOT NULL,
    sync_started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sync_completed_at DATETIME NULL,
    queued_count INT DEFAULT 0,
    applied_count INT DEFAULT 0,
    conflict_count INT DEFAULT 0,
    payload_hash VARCHAR(64) NULL,
    error_text TEXT,
    PRIMARY KEY (id),
    KEY idx_researcher (researcher_id),
    KEY idx_started (sync_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Service worker** (`web/sw.js`) - copy verbatim from Heratio's `public/sw.js`. ~50 lines. Network-first-with-cache-fallback for GETs; POSTs pass through.

**Service contract:**

```php
public function applyQueue(int $researcherId, array $queue): array;
// returns ['applied' => N, 'conflicts' => N, 'log_id' => N]
```

Supported `kind` values: `journal_entry` (insert into `ahg_research_journal_entry`), `annotation` (insert into `qubit_iiif_annotation` / `ahg_web_annotation`).

---

## Shared infrastructure additions

### `LlmService` adjustment

If the AtoM-side `ahgAIPlugin/lib/Services/LlmService.php` doesn't expose `completeFull(string $systemPrompt, string $userPrompt, ?int $configId, array $options): array` with the result shape `['success' => bool, 'text' => string, 'tokens_used' => int, 'model' => string, 'generation_time_ms' => int]`, add it. The prompt templates and option keys (`temperature`, `max_tokens`, `config_id`) need to be portable between Laravel and AtoM.

### Activity log instrumentation

The `ResearchAnalyticsService` dashboard depends on the existing `ahg_research_activity_log` table being populated. Verify that:

- Studio generations log `activity_type='ai_studio'` (new value to add).
- Cross-fonds queries log `activity_type='search_cross_fonds'` (new).
- Notebook adds log `activity_type='notebook_item_added'` (new).
- Citation downloads (new picker) log `activity_type='cite_export'` (new).

These are additive - existing analytics queries continue to work; the dashboard just gets richer.

---

## Release plan

Suggested as one bundled release of `ahgResearchPlugin` (and a small bump of `ahgAIPlugin` if `LlmService` needs the new `completeFull()` shape):

1. **Phase 1 (no UI):** add the 9 new DB tables, register the new app.yml settings, ship `LlmService::completeFull()` extension. No researcher-visible change.
2. **Phase 2 (per-record export):** §2.1 - low-risk, no AI dependency. Validates the migration cadence.
3. **Phase 3 (Studio + popovers):** §1.1 / §1.2 / §1.3 / §1.4. The biggest single piece.
4. **Phase 4 (researcher productivity):** §1.5 Notebooks + §1.6 Cross-fonds + §2.2 Analytics.
5. **Phase 5 (collaboration + identity):** §2.3 + §2.4 (extend existing OrcidService) + §2.5 GraphQL.
6. **Phase 6 (mobile + offline):** §2.6 + §2.7.

Phases 2-6 can ship in any order after Phase 1 lands.

---

## Parity acceptance tests

For every feature, a smoke test confirming visible parity with Heratio (Laravel):

| Feature | Heratio URL | AtoM Heratio URL | Pass criterion |
|---|---|---|---|
| Studio list | `/research/studio/{id}` | `/research/studio/{id}` | renders form + Recent artefacts list |
| Studio generate | (POST) | (POST) | inserts row into studio_artefact, status='ready' |
| Studio show | `/research/studio/{id}/artefact/{a}` | same | renders body with `[N]` markers |
| Citation popovers | (any artefact show) | (any artefact show) | hover `[N]` shows snippet popover |
| Cite export | `/research/cite/{slug}/export/ris` | same | returns `.ris` file with TY/TI/AU/etc tags |
| Notebooks list | `/research/notebooks` | same | renders own list + create form |
| Notebook promote | (POST) | (POST) | creates project + collection, marks notebook |
| Cross-fonds | `/research/cross-fonds-query?q=test` | same | returns ranked merged result list |
| Analytics | `/research/analytics` | same | renders 8 KPI tiles |
| Collab panel | `/research/projects/{id}/realtime/panel` | same | presence + comment thread polls every 3s |
| ORCID link | `/research/orcid` | same | shows "Connect with ORCID" or linked status |
| GraphQL query | POST `/admin/graphql` `{researcherView(researcherId:1){researcher}}` | same | returns researcher row |
| Mobile shell | `/research/mobile` | same | renders 4-button grid + reading list |
| Offline sync | (POST queue, then offline) | (POST queue, then offline) | queue posts on `online` event |

---

## Open questions for the AtoM Heratio team

1. **WebAnnotationService schema** - does the AHG-side annotation table already have `project_id` / `visibility` columns, or are these new? Need a quick `DESCRIBE` to confirm before the migration is written.
2. **Existing OrcidService coverage** - what's already implemented vs. what's a stub? Extend, don't replace.
3. **Existing CommentService coverage** - same question. The `research_evidence_comment` table in this spec might overlap with whatever the existing CommentService already persists.
4. **Symfony 1.4 JSON action returns** - confirm the helper for `$this->setLayout(false); return json_encode(...)` is the convention; alternatively use `sfWebResponse::setContentType('application/json')`.
5. **PWA scope** - this spec puts the manifest at root scope (`/`). If AtoM Heratio runs behind a sub-path, the manifest scope and `start_url` need adjusting.
6. **GraphQL endpoint** - if AtoM Heratio doesn't have one yet, decide whether to bundle a minimal one or skip §2.5 for now.

---

## References

- **Heratio reference implementation:** `https://github.com/ArchiveHeritageGroup/heratio` (Laravel side).
- **Roadmap source-of-truth:** `docs/research-enhancements-roadmap.md` (in the Heratio repo).
- **Laravel user guide:** `docs/research-enhancements-user-guide.md` (in the Heratio repo) - copy text verbatim where appropriate for the AtoM-side user docs.
- **KM reference index:** `docs/reference/research-roadmap-2026-05-features.md`.
- **Lessons learned worth porting:**
  - Don't build a separate "workbench-gateway" driver. The gateway is reached through the existing `LlmService` cloud-mode override.
  - Audio TTS endpoint is operator-configurable; when unset, persist the script and surface a clean error - never 500.
  - ORCID is operator-configurable; when unset, the page is informative ("not configured" + the exact ENV keys), not broken.
  - Real-time collab uses polling because there's no broker. Architecture allows a Reverb/Pusher swap-in later.

---

*Spec drafted 2026-05-16. Owner: Johan Pieterse / Plain Sailing Information Systems / The Archive and Heritage Group.*
