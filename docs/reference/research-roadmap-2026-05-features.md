# Research Enhancements Roadmap - Shipped 2026-05-16

Reference for KM ingest. Captures the 13 features that shipped from
`docs/research-enhancements-roadmap.md` so the KM RAG can answer
"how do I use the Studio pane?" / "where is the cross-fonds query?" /
"how does ORCID linking work?" without re-reading the source code.

All routes are under `/research/` and require `auth` middleware unless noted.

## §1 NotebookLM-Studio additions

### §1.1-§1.3 Studio pane on Research-Project show

- **Route:** `GET /research/studio/{projectId}` (page) / `POST /research/studio/{projectId}/generate` (form)
- **Show:** `GET /research/studio/{projectId}/artefact/{artefactId}`
- **Download:** `GET /research/studio/{projectId}/artefact/{artefactId}/download` (xlsx / mp3)
- **Delete:** `DELETE /research/studio/{projectId}/artefact/{artefactId}`
- **Sidebar entry point:** "Studio" link in the right-side "Research Output" card on every project show page.
- **Output types** (supplied by `ResearchStudioService::SUPPORTED_TYPES`):
  `briefing` | `study_guide` | `faq` | `timeline` | `diagram` (Mermaid) | `video_script` | `spreadsheet` | `audio`.
- **Sources:** the form populates from `research_collection_item` rows belonging to collections under the chosen project. Researchers tick the IO ids they want grounded in the output.
- **Service:** `AhgResearch\Services\ResearchStudioService`.
- **Backend:** LLM calls go through the existing `AhgAiServices\Services\LlmService::completeFull()` - same plumbing as every other AI feature in Heratio. The cloud-mode override (`ai_services_processing_mode=cloud`) routes everything to the AHG AI gateway when configured.
- **Spreadsheet generation:** local, via PhpSpreadsheet. The LLM is prompted to project sources into a `{header, intro, columns, rows}` JSON doc; `buildXlsxFile()` writes the .xlsx to `{HERATIO_STORAGE_PATH}/research-studio/{projectId}/artefact-{id}.xlsx`.
- **Audio:** the LLM writes a two-voice script (Host + Curator); the script is POSTed to a configurable TTS endpoint defined by `HERATIO_TTS_ENDPOINT` (with optional `HERATIO_TTS_KEY`). When no endpoint is configured the artefact lands in `status='error'` but the full script is persisted on `audio_transcript` so the operator can hand it to any TTS pipeline manually.
- **Storage:** `research_studio_artefact` table. Includes `source_object_ids` JSON (provenance) and `citations` JSON (the `[N]` map the popover JS uses).

### §1.4 Citation hover popovers

- **Where:** anywhere a Studio artefact, report, or research output renders inline `[N]` markers (Studio show page, future report renderer).
- **JS:** `/vendor/ahg-research/citation-popover.js`. Scans `.markdown-body`, `#studio-body`, and `.studio-citations-host` for `[N]` patterns; wraps each in `<a class="citation-marker">`; hover shows source title + 220-char snippet + "Open source" link; click scrolls to the matching `#studio-citations [data-citation-n="N"]` list item.
- **No build step required** - the file is shipped under `public/vendor/`.

### §1.5 Researcher private notebooks

- **Sidebar:** "Notebooks" entry under "Research" in the left research sidebar.
- **Routes:** `GET|POST /research/notebooks` (list + create) / `GET|POST /research/notebooks/{id}` (show + add/remove items + repin) / `DELETE /research/notebooks/{id}` (delete) / `POST /research/notebooks/{id}/promote` (promote).
- **Item types:** `saved_query` | `ai_output` | `source_pin` | `note`.
- **Promote-to-project:** click "Promote to project" on a notebook show page. Creates a new `research_project` (owner = current researcher), copies all `source_pin` items into a new collection named "Promoted from notebook: ...", and marks the original notebook with `promoted_to_project_id` + `promoted_at`. Idempotent - re-promoting returns the existing project id.
- **Tables:** `research_notebook`, `research_notebook_item`.
- **Service:** `AhgResearch\Services\NotebookService`.

### §1.6 Cross-fonds reasoning queries

- **Route:** `GET|POST /research/cross-fonds-query`.
- **Sidebar:** "Cross-fonds Query" entry.
- **Fonds picker:** populated by `CrossFondsQueryService::availableFonds()` which queries `information_object` for descriptions whose level_of_description term name matches `fonds` / `Fonds` / `collection` / `Collection`.
- **Fan-out:** one ES query per selected fonds, scoped by `lft`/`rgt` range so descendants of that fonds are included. Per-fonds top-K hits are merged + reranked by `_score`.
- **Semantic expansion:** optional checkbox "Expand with thesaurus synonyms" calls `AhgSemanticSearch\Services\SemanticSearchService::expandQuery()` if that service is available, ORs the expansion into the query.
- **Audit:** every query is logged to `research_cross_fonds_query` (researcher_id, query_text, fonds_ids JSON, results_count, elapsed_ms).
- **No gateway dependency** - this runs against Heratio's own Elasticsearch.

## §2 Audit gaps closed

### §2.1 Per-record citation export (RIS / BibTeX / EndNote / APA / MLA / Chicago)

- **Picker:** "Copy in citation manager format" card on every `/research/cite/{slug}` page.
- **Download route:** `GET /research/cite/{slug}/export/{format}` where format is one of `ris` | `bibtex` | `endnote` | `apa` | `mla` | `chicago`.
- **Service:** `AhgResearch\Services\CitationService` - one method per format.
- **MIME types:** `application/x-research-info-systems` (RIS), `application/x-bibtex` (BibTeX), `application/xml` (EndNote), `text/plain` (APA/MLA/Chicago).
- **Bibliography-wide exports:** unchanged - `BibliographyService` already handles RIS / BibTeX / ZoteroRDF / MendeleyJSON / CSL-JSON for multi-record exports.

### §2.2 Research analytics dashboard

- **Route:** `GET /research/analytics`.
- **Filters:** `?from=YYYY-MM-DD&to=YYYY-MM-DD` query params; defaults to last 30 days.
- **Data sources:** `research_activity_log` (every activity_type), `research_citation_log` (cite events). No new audit tables.
- **Metrics rendered:** 8 KPI cards (total events, unique researchers, unique objects, views, searches, citations, downloads, annotations) + top researchers (10) + popular descriptions (10) + popular collections (10) + top search terms (15) + weekly volume bar chart.
- **Service:** `AhgResearch\Services\ResearchAnalyticsService::dashboard($from, $to)`.

### §2.3 Real-time collaboration (polling fallback)

- **Panel:** `GET /research/projects/{projectId}/realtime/panel` (link in project show page right-side card).
- **Endpoints (JSON):** `POST /research/projects/{id}/realtime/join`, `GET /research/projects/{id}/realtime/poll?since=N`, `POST /research/projects/{id}/realtime/comment`, `POST /research/projects/{id}/realtime/comment/{commentId}/resolve`.
- **Polling interval:** 3 seconds (set in `collab-panel.blade.php`). No WebSocket broker (Reverb/Pusher) on the AHG host today; the polling fallback is the production transport.
- **Tables:** `research_collaboration_session`, `research_collaboration_presence`, `research_evidence_comment`.
- **Presence stale-out:** 90 seconds without a heartbeat removes the researcher from the "Online now" list.
- **Service:** `AhgResearch\Services\CollaborationRealtimeService`.
- **Shared annotation layers:** `ahg_iiif_annotation` gained `project_id` + `visibility` columns. Default is `visibility='private'`; setting `visibility='project'` makes annotations visible to every collaborator on `project_id`. Filter via `GET /api/annotations/search?targetId=...&projectId=N` (existing endpoint, new query params).

### §2.4 ORCID OAuth + Works push/pull

- **Sidebar:** "ORCID Link" entry under Research.
- **Routes:** `GET /research/orcid` (status page) / `GET /research/orcid/authorize` (start OAuth) / `GET /research/orcid/callback` (OAuth callback) / `POST /research/orcid/sync` (pull Works) / `POST /research/orcid/unlink`.
- **OAuth scope requested:** `/authenticate /read-limited /activities/update`.
- **Required ENV:** `ORCID_CLIENT_ID`, `ORCID_CLIENT_SECRET`, `ORCID_REDIRECT_URI` (defaults to `{APP_URL}/research/orcid/callback`), `ORCID_BASE` (defaults to `https://orcid.org`; use `https://sandbox.orcid.org` for testing), `ORCID_API_BASE` (defaults to `https://pub.orcid.org`; use `https://api.orcid.org` for Member API).
- **Status without config:** the page surfaces a clean "ORCID not configured" alert listing the exact ENV keys to set. Every endpoint returns 503/clean error rather than 500.
- **Tokens:** stored AES-256-CBC-encrypted in `researcher_orcid_link.access_token_encrypted` / `refresh_token_encrypted`. Encryption key derives from `APP_KEY`.
- **Works push:** `OrcidService::pushWork($researcherId, $citation)` builds the W3C Web Annotation Work XML and POSTs to `/v3.0/{orcid_id}/work`. Returns the ORCID put-code on success.
- **Works pull:** `OrcidService::pullWorks($researcherId)` GETs `/v3.0/{orcid_id}/works`, stores `last_synced_at` + `last_works_count`.
- **Service:** `AhgResearch\Services\OrcidService`.

### §2.5 GraphQL for researchers (Zotero / Tropy / LMS)

- **Endpoint:** `POST /admin/graphql` (existing) - extended in `AhgGraphql\Controllers\GraphqlController`.
- **Playground:** `GET /admin/graphql/playground`.
- **New queries:**
  - `researchProject(id: Int!)` - title, description, status, collections array, studio_artefacts array.
  - `researchProjects(limit: Int)` - paginated list.
  - `researchAnnotations(targetIri: String!)` - W3C Web Annotations for a canvas IRI, with project_id + visibility.
  - `researchCollections(projectId: Int!)` - collections + items joined.
  - `researcherView(researcherId: Int!)` - combined single-round-trip query: researcher profile + projects + recent annotations + ORCID link summary. This is the "compose one query for a typical researcher view" entry point external tools should use.
- **No new types in the resolver are LL-AST-driven** - the controller is a hand-rolled regex matcher (same pattern as the existing InformationObject/Actor/Repository queries). Add new queries by extending the `resolveQuery()` switch.

### §2.6 Mobile / responsive PWA

- **Route:** `GET /research/mobile`.
- **Manifest:** `/manifest.webmanifest` (in `public/`). `display: standalone`, `start_url: /research/mobile`. Three shortcuts: Mobile research, Cross-fonds query, Notebooks.
- **Mobile shell:** `research::research.mobile-home` blade - reading list (from any of the current researcher's collection items, most-recent 50) + 4-button grid (Search, Notes, Bibliographies, Journal) + quick-journal form.
- **Add to home screen:** standard PWA flow once the manifest is served. iOS Safari + Chrome both supported.

### §2.7 Offline mode + sync endpoint

- **Service worker:** `/sw.js`. Network-first-with-cache-fallback for GETs; never caches POSTs. Cache name is `heratio-mobile-v1` (bump `SW_VERSION` constant when the shell changes).
- **Cached shell:** `/research/mobile`, `/manifest.webmanifest`, `/favicon.ico`.
- **Page-side queue:** `localStorage["heratio_offline_queue_v1"]` - array of `{kind, ...}` items pushed by the mobile shell when the user types a journal entry. Flushed automatically on `online` event.
- **Sync endpoint:** `POST /research/sync/offline` - JSON body `{queue: [{kind, ...}, ...]}`. Supported `kind` values: `journal_entry`, `annotation`. Returns `{applied, conflicts, log_id}`.
- **Audit:** every sync run logs to `research_offline_sync_log` (researcher_id, sync_started_at, sync_completed_at, queued_count, applied_count, conflict_count, payload_hash).

## Schema additions

Added to `packages/ahg-research/database/install.sql`:

- `research_studio_artefact`
- `research_notebook`
- `research_notebook_item`
- `research_collaboration_session`
- `research_collaboration_presence`
- `research_evidence_comment`
- `researcher_orcid_link`
- `research_cross_fonds_query`
- `research_offline_sync_log`

Added to `packages/ahg-annotations/database/install.sql`:

- New columns on `ahg_iiif_annotation`: `project_id` (nullable int), `visibility` (varchar 20, default 'private'). Added with `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` so existing installs upgrade cleanly.

## Things deliberately NOT added

- **No `WorkbenchGatewayService` / `workbench-gateway` LlmService driver.** The roadmap doc mentions one but the existing `LlmService` already handles the AHG AI gateway through its cloud-mode override at the top of `complete()`. Adding a parallel abstraction would have created two ways to do the same thing.
- **No WebSocket broker / Reverb integration** for §2.3. Polling fallback is the production transport today. Swap-in is a one-liner change in `collab-panel.blade.php` (`setInterval(poll, 3000)` → presence channel subscription) once a broker lands.
- **No bundled TTS** for audio overviews. Heratio is an AI client, not host - `HERATIO_TTS_ENDPOINT` must point at remote TTS infra. The script is always persisted so an operator can hand-run TTS without losing work.

## Where to look next

- Source: `packages/ahg-research/src/Services/{ResearchStudioService,NotebookService,CrossFondsQueryService,ResearchAnalyticsService,CollaborationRealtimeService,OrcidService,CitationService}.php`
- Views: `packages/ahg-research/resources/views/research/{studio,studio-show,notebooks,notebook-show,cross-fonds-query,analytics,collab-panel,orcid-link,mobile-home,cite}.blade.php`
- Routes: `packages/ahg-research/routes/web.php`
- Roadmap source-of-truth: `docs/research-enhancements-roadmap.md`
