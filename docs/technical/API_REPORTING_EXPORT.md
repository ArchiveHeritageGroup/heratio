# Heratio - API, Reporting & Export Technical Manual

**Framework Version:** 2.8.2
**Document Date:** 2026-03-04
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## Overview

This document catalogs all API endpoints, reporting systems, export functionality, and data integration capabilities in the Heratio platform. It covers the REST API v2, GraphQL API, OAI-PMH federation, reporting and analytics, export pipelines, CLI commands, and background queue infrastructure.

---

## 1. REST API v2 (ahgAPIPlugin)

**Plugin:** ahgAPIPlugin v1.2.0
**Base URL:** `/api/v2`
**Authentication:** API key in header `X-Api-Key: {key}` or query parameter `?api_key={key}`

### 1.1 API Key Management

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v2/keys` | GET | List API keys |
| `/api/v2/keys` | POST | Create API key (scopes, rate limit, expiry) |
| `/api/v2/keys/:id` | PUT | Update API key |
| `/api/v2/keys/:id` | DELETE | Revoke API key |

**Database:** `ahg_api_key` (scopes, rate limits, expiry), `ahg_api_log` (request/response audit), `ahg_api_rate_limit` (sliding window)

### 1.2 Core Entity Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v2/descriptions` | GET | Browse archival descriptions (paginated, filterable) |
| `/api/v2/descriptions` | POST | Create archival description |
| `/api/v2/descriptions/:slug` | GET | Read single description |
| `/api/v2/descriptions/:slug` | PUT | Update description |
| `/api/v2/descriptions/:slug` | DELETE | Delete description |
| `/api/v2/authorities` | GET | Browse authority records |
| `/api/v2/authorities/:slug` | GET | Read single authority |
| `/api/v2/repositories` | GET | Browse repositories |
| `/api/v2/taxonomies` | GET | Browse taxonomies |
| `/api/v2/taxonomies/:id/terms` | GET | Get taxonomy terms |
| `/api/v2/events` | GET | Browse events |
| `/api/v2/events/:id` | GET | Read single event |
| `/api/v2/events/correlation/:id` | GET | Event correlation |
| `/api/v2/search` | GET/POST | Full-text search |
| `/api/v2/batch` | POST | Batch operations |

### 1.3 Digital Object & Upload Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v2/upload` | POST | Upload digital object |
| `/api/v2/descriptions/:slug/upload` | POST | Upload and attach to description |

### 1.4 Condition Assessment Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v2/conditions` | GET | Browse condition assessments |
| `/api/v2/conditions` | POST | Create condition assessment |
| `/api/v2/conditions/:id` | GET | Read condition assessment |
| `/api/v2/conditions/:id` | PUT/DELETE | Update/delete condition |
| `/api/v2/conditions/:id/photos` | GET/POST | List/upload condition photos |
| `/api/v2/conditions/:id/photos` | DELETE | Delete condition photo |
| `/api/v2/descriptions/:slug/conditions` | GET | Conditions for a description |

### 1.5 Heritage Accounting Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v2/assets` | GET/POST | Browse/create heritage assets |
| `/api/v2/assets/:id` | GET/PUT/DELETE | Read/update/delete heritage asset |
| `/api/v2/descriptions/:slug/asset` | GET | Asset for a description |
| `/api/v2/valuations` | GET/POST | Browse/create valuations |
| `/api/v2/assets/:id/valuations` | GET | Valuations for an asset |

### 1.6 Privacy & Compliance Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v2/privacy/dsars` | GET/POST | Browse/create Data Subject Access Requests |
| `/api/v2/privacy/dsars/:id` | GET/PUT/DELETE | DSAR CRUD |
| `/api/v2/privacy/breaches` | GET/POST | Data breach management |

### 1.7 Publishing Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v2/publish/readiness/:slug` | GET | Check publish readiness |
| `/api/v2/publish/execute/:slug` | POST | Execute publish action |

### 1.8 Audit Trail Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v2/audit` | GET | Browse audit entries |
| `/api/v2/audit/:id` | GET | Read single audit entry |

### 1.9 Mobile Sync Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v2/sync/changes` | GET | Delta sync (changed records since timestamp) |
| `/api/v2/sync/batch` | POST | Batch sync from mobile |

### 1.10 Webhook System

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v2/webhooks` | GET/POST | Browse/create webhooks |
| `/api/v2/webhooks/:id` | GET/PUT/DELETE | Webhook CRUD |
| `/api/v2/webhooks/:id/deliveries` | GET | Delivery log |
| `/api/v2/webhooks/:id/regenerate-secret` | POST | Regenerate HMAC secret |

**Database:** `ahg_webhook` (registrations with HMAC secrets, event filters), `ahg_webhook_delivery` (delivery log with retry state)

**Webhook Events:** Record create/update/delete, publish, digital object upload, accession create, user login

**Security:** HMAC-SHA256 signature in `X-Webhook-Signature` header. Exponential backoff for failed deliveries.

**CLI:** `php symfony api:webhook-process-retries` - process failed webhook deliveries

### 1.11 Legacy API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/search/io` | GET | Search information objects (legacy) |
| `/api/autocomplete/glam` | GET | GLAM autocomplete (legacy) |
| `/api/plugin-protection` | GET | Plugin protection check |

### 1.12 Base AtoM REST API (arRestApiPlugin)

**Location:** `/plugins/arRestApiPlugin/` (base AtoM - read-only)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/informationobjects` | GET | List archival descriptions |
| `/api/informationobjects/{id}` | GET | Single description |
| `/api/actors` | GET | List authority records |
| `/api/actors/{id}` | GET | Single actor |
| `/api/repositories` | GET | List repositories |
| `/api/repositories/{id}` | GET | Single repository |
| `/api/taxonomies/{id}/terms` | GET | Taxonomy terms |

**Authentication:** `REST-API-Key` header (stored in user properties)

---

## 2. GraphQL API (ahgGraphQLPlugin)

**Plugin:** ahgGraphQLPlugin v1.0.0
**Depends on:** ahgAPIPlugin (reuses API keys for authentication)

### 2.1 Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/graphql` | POST | Execute GraphQL queries/mutations |
| `/api/graphql` | GET | Introspection |
| `/api/graphql/playground` | GET | Interactive playground (dev mode only) |

### 2.2 Schema

**Types:** Item, Actor, Repository, Term, Taxonomy, User
**Pagination:** Relay-style cursor pagination (ConnectionTypes)
**Custom Scalars:** Date, DateTime, JSON

**Queries:**
- `item(slug: String!)` - single description
- `items(filter: ItemFilter, first: Int, after: String)` - paginated descriptions
- `actor(slug: String!)` - single authority
- `actors(filter: ActorFilter, first: Int, after: String)` - paginated authorities
- `repositories(first: Int, after: String)` - paginated repositories
- `taxonomy(id: ID!)` - single taxonomy with terms
- `taxonomies` - all taxonomies
- `search(query: String!, first: Int, after: String)` - full-text search
- `me` - current authenticated user

### 2.3 Security

| Rule | Limit |
|------|-------|
| Max query depth | 10 |
| Max complexity score | 1000 |
| Rate limiting | Via ahgAPIPlugin API key settings |

**Database:** `ahg_graphql_log` (query analytics: operation_name, complexity_score, depth, execution_time_ms)

---

## 3. OAI-PMH & Federation

### 3.1 Base AtoM OAI-PMH (arOaiPlugin)

**Endpoint:** `/index.php/;oai`

**Verbs:** Identify, ListMetadataFormats, ListSets, ListIdentifiers, ListRecords, GetRecord

**Formats:** Dublin Core (`oai_dc`), EAD (`oai_ead`)

**Configuration:** Admin > Settings > OAI Repository

| Setting | Description |
|---------|-------------|
| Repository Identifier | e.g., `theahg.co.za` |
| Administrator Email | Contact for OAI issues |
| Sets Enabled | Group by repository/collection |
| Resume Token Limit | Records per page |

### 3.2 Federation (ahgFederationPlugin)

**Plugin:** ahgFederationPlugin v1.0.0

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/admin/federation` | GET | Federation dashboard |
| `/admin/federation/peers` | GET | Peer list |
| `/admin/federation/peers/add` | GET/POST | Add peer |
| `/admin/federation/peers/:id` | GET/POST | Edit peer |
| `/admin/federation/harvest/:peerId` | GET | Harvest records from peer |
| `/admin/federation/harvest/:peerId/status` | GET | Harvest status |
| `/admin/federation/log` | GET | Harvest log |
| `/admin/federation/api/test-peer` | POST | Test peer connection (AJAX) |
| `/admin/federation/api/harvest/:peerId` | POST | Run harvest (AJAX) |

**Custom OAI Format:** `oai_heritage` (Heritage Platform XML)
**Custom OAI Set:** `heritage:federation` (federation-eligible records)

**Database Tables:**
- `federation_peer` - peer OAI-PMH endpoints (base_url, auth, harvest_interval_hours, last_harvest_status)
- `federation_harvest_log` - per-record harvest audit (source_oai_identifier, metadata_format, action)
- `federation_harvest_session` - session-level harvest tracking

---

## 4. DOI Integration (ahgDoiPlugin)

**Plugin:** ahgDoiPlugin v1.0.0
**Backend:** DataCite API

### 4.1 Admin Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/admin/doi` | GET | DOI dashboard |
| `/admin/doi/config` | GET | DataCite configuration |
| `/admin/doi/config/save` | POST | Save configuration |
| `/admin/doi/config/test` | POST | Test DataCite connection |
| `/admin/doi/browse` | GET | Browse DOIs |
| `/admin/doi/view/:id` | GET | View DOI details |
| `/admin/doi/mint/:id` | POST | Mint DOI for a record |
| `/admin/doi/batch-mint` | POST | Batch mint DOIs |
| `/admin/doi/update/:id` | POST | Update DOI metadata |
| `/admin/doi/queue` | GET | View mint queue |
| `/admin/doi/queue/:id/retry` | POST | Retry failed mint |
| `/admin/doi/mapping` | GET/POST | AtoM→DataCite field mapping |
| `/admin/doi/report` | GET | DOI statistics report |
| `/admin/doi/export` | GET | Export DOI data |
| `/admin/doi/sync` | POST | Sync metadata to DataCite |
| `/admin/doi/deactivate/:id` | POST | Deactivate (tombstone) DOI |
| `/admin/doi/reactivate/:id` | POST | Reactivate DOI |
| `/admin/doi/verify/:id` | POST | Verify DOI resolution |

### 4.2 API & Public Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/doi/mint/:id` | POST | API: mint DOI |
| `/api/doi/status/:id` | GET | API: check DOI status |
| `/doi/:doi` | GET | Public DOI landing page (resolves `10.*` pattern) |

### 4.3 Auto-Mint

Hooks `QubitInformationObject.postSave` - if `shouldAutoMint($record)` is true, queues for background minting without blocking save.

### 4.4 CLI Commands

| Command | Description |
|---------|-------------|
| `doi:mint` | Mint DOI for a description |
| `doi:deactivate` | Deactivate (tombstone) a DOI |
| `doi:sync` | Sync metadata to DataCite |
| `doi:process-queue` | Process the mint queue |
| `doi:verify` | Verify DOI resolution |

### 4.5 Database Tables

| Table | Description |
|-------|-------------|
| `ahg_doi` | DOI records (status: draft/registered/findable/failed/deleted) |
| `ahg_doi_config` | Per-repository DataCite credentials (prefix, environment, auto_mint, suffix_pattern) |
| `ahg_doi_queue` | Minting queue with exponential backoff |
| `ahg_doi_mapping` | AtoM→DataCite field mapping |
| `ahg_doi_log` | Audit trail |

---

## 5. IIIF API (ahgIiifPlugin)

**Plugin:** ahgIiifPlugin v1.0.0 (stable)

### 5.1 Manifest Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/iiif/manifest/:slug` | GET | IIIF Presentation API v2 manifest |
| `/iiif/manifest/id/:id` | GET | Manifest by object ID |
| `/iiif/v3/manifest/:slug` | GET | IIIF Presentation API v3 manifest |
| `/manifest-collection/:slug/manifest.json` | GET | Collection manifest |

### 5.2 Viewer & Annotations

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/iiif/viewer/:id` | GET | Embedded IIIF viewer |
| `/iiif/annotations/object/:id` | GET | Annotation list for object |
| `/iiif/annotations` | POST | Create annotation |
| `/iiif/annotations/:id` | PUT/DELETE | Update/delete annotation |

### 5.3 IIIF Authentication (Auth API 1.0)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/iiif/auth/login/:service` | GET/POST | Login service |
| `/iiif/auth/token/:service` | GET | Token service |
| `/iiif/auth/logout/:service` | GET | Logout service |
| `/iiif/auth/confirm/:service` | GET | Confirm service |
| `/iiif/auth/check/:id` | GET | Access check for image |

### 5.4 Collection Management

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/manifest-collections` | GET | List collections |
| `/manifest-collections` | POST | Create collection |
| `/manifest-collection/:id/view` | GET | View collection |
| `/manifest-collection/:id/edit` | GET/POST | Edit collection |
| `/manifest-collection/:id/delete` | POST | Delete collection |
| `/manifest-collection/:id/items/add` | POST | Add items |
| `/manifest-collection/item/:id/remove` | POST | Remove item |

### 5.5 Media Streaming

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/media/stream/:id` | GET | Stream audio/video |
| `/media/download/:id` | GET | Download media file |
| `/media/snippets/:id` | GET | List media snippets |
| `/media/snippets` | POST/DELETE | Create/delete snippet |
| `/media/extract/:id` | POST | Extract audio from video |
| `/media/transcribe/:id` | POST | Transcribe audio |
| `/media/transcription/:id` | GET | Get transcription |
| `/media/transcription/:id/:format` | GET | Export transcription (json/vtt/srt/txt) |
| `/media/convert/:id` | POST | Convert media format |
| `/media/metadata/:id` | GET | Media technical metadata |

### 5.6 Database Tables

| Table | Description |
|-------|-------------|
| `iiif_collection` | Curated IIIF collection definitions |
| `iiif_collection_item` | Items within each collection |

---

## 6. Discovery & Search

### 6.1 Discovery Search (ahgDiscoveryPlugin)

**Plugin:** ahgDiscoveryPlugin v0.2.0

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/discovery` | GET | Discovery landing page |
| `/discovery/search` | GET | Natural language search |
| `/discovery/related/:id` | GET | Related content sidebar |
| `/discovery/click` | POST | Click tracking |
| `/discovery/popular` | GET | Popular topics |

**Features:**
- Natural language query expansion with synonym lookup
- Three-strategy search: keyword, NER entity, hierarchical
- Result grouping by collection/fonds
- Related content sidebar for record views
- Optional integration with ahgAIPlugin (NER) and ahgSemanticSearchPlugin

**Database:** `ahg_discovery_cache` (cached results with expiry), `ahg_discovery_log` (query analytics)

### 6.2 Global Search (ahgSearchPlugin)

**Plugin:** ahgSearchPlugin v1.0.0

Overrides base AtoM search endpoints:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/search/autocomplete` | GET | Enhanced autocomplete |
| `/search/index` | GET | Global search |
| `/search/descriptionUpdates` | GET | Recent updates |
| `/search/globalReplace` | GET/POST | Global search and replace |
| `/search/semantic` | GET | Semantic search (via RiC) |

---

## 7. Reporting System

### 7.1 Central Reports Dashboard (ahgReportsPlugin)

**Plugin:** ahgReportsPlugin v1.0.0
**URL:** `/reports`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/admin/dashboard` | GET | Admin dashboard |
| `/reports` | GET | Reports index |
| `/reports/view/:code` | GET | View specific report |
| `/reports/descriptions` | GET | Description reports |
| `/reports/authorities` | GET | Authority reports |
| `/reports/repositories` | GET | Repository reports |
| `/reports/accessions` | GET | Accession reports |
| `/reports/storage` | GET | Physical storage reports |
| `/reports/recent` | GET | Recent activity |
| `/reports/activity` | GET | Activity timeline |
| `/reports/spatial-analysis` | GET | Spatial analysis reports |

**Report Categories:**

| Category | Reports |
|----------|---------|
| **Archive** | Descriptions by level, repository statistics, physical storage, finding aids |
| **Library** | Catalog by format, holdings statistics |
| **Museum** | Objects by classification, condition assessment summary, acquisition by year |
| **Gallery** | Artworks, exhibitions, loans |
| **DAM** | Digital objects by MIME type, storage usage, format distribution |
| **Researchers** | Active researchers, request statistics |

### 7.2 Enterprise Report Builder (ahgReportBuilderPlugin)

**Plugin:** ahgReportBuilderPlugin v2.0.0
**URL:** `/admin/report-builder`

#### Admin Pages

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/admin/report-builder` | GET/POST | Report list |
| `/admin/report-builder/create` | GET/POST | Create new report |
| `/admin/report-builder/:id/edit` | GET/POST | Drag-drop designer |
| `/admin/report-builder/:id/preview` | GET | Preview report |
| `/admin/report-builder/:id/export/:format` | GET | Export (pdf, xlsx, csv, docx) |
| `/admin/report-builder/:id/schedule` | GET/POST | Schedule recurring report |
| `/admin/report-builder/:id/clone` | POST | Clone report |
| `/admin/report-builder/:id/delete` | POST | Delete report |
| `/admin/report-builder/:id/history` | GET | Version history |
| `/admin/report-builder/:id/query` | GET/POST | SQL query builder |
| `/admin/report-builder/archive` | GET | Archived reports |
| `/admin/report-builder/templates` | GET | Template library |
| `/admin/report-builder/template/:id/edit` | GET/POST | Edit template |
| `/admin/report-builder/template/:id/preview` | GET | Preview template |
| `/admin/report-builder/template/:id/delete` | POST | Delete template |

#### Public/Shared Pages

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/reports/custom/:id` | GET | View shared/public report |
| `/reports/shared/:token` | GET | Public share by token |
| `/report-widget/:id` | GET | Embeddable widget |

#### API Endpoints (`/api/report-builder/...`)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/report-builder/save` | POST | Save report |
| `/api/report-builder/delete/:id` | POST | Delete report |
| `/api/report-builder/data` | POST | Fetch report data |
| `/api/report-builder/chart-data` | POST | Fetch chart data |
| `/api/report-builder/columns/:source` | GET | Get columns for data source |
| `/api/report-builder/section/save` | POST | Save section |
| `/api/report-builder/section/:id/delete` | POST | Delete section |
| `/api/report-builder/section/reorder` | POST | Reorder sections |
| `/api/report-builder/widget/save` | POST | Save widget |
| `/api/report-builder/widget/:id/delete` | POST | Delete widget |
| `/api/report-builder/widgets` | GET | List widgets |
| `/api/report-builder/snapshot` | POST | Create data snapshot |
| `/api/report-builder/query/tables` | GET | Available tables |
| `/api/report-builder/query/columns/:table` | GET | Table columns |
| `/api/report-builder/query/relationships/:table` | GET | Table relationships |
| `/api/report-builder/query/validate` | POST | Validate SQL query |
| `/api/report-builder/query/save` | POST | Save query |
| `/api/report-builder/query/execute` | POST | Execute query |
| `/api/report-builder/version/create` | POST | Create version |
| `/api/report-builder/version/restore` | POST | Restore version |
| `/api/report-builder/versions/:id` | GET | List versions |
| `/api/report-builder/comment` | POST | Add comment |
| `/api/report-builder/status` | POST | Change workflow status |
| `/api/report-builder/template/save` | POST | Save template |
| `/api/report-builder/template/apply` | POST | Apply template |
| `/api/report-builder/template/:id/delete` | POST | Delete template |
| `/api/report-builder/link/save` | POST | Save cross-reference link |
| `/api/report-builder/link/:id/delete` | POST | Delete link |
| `/api/report-builder/og-fetch` | POST | Fetch OpenGraph metadata |
| `/api/report-builder/entity-search` | GET | Entity search for linking |
| `/api/report-builder/attachment/upload` | POST | Upload attachment |
| `/api/report-builder/attachments` | GET | List attachments |
| `/api/report-builder/attachment/:id/delete` | POST | Delete attachment |
| `/api/report-builder/share/create` | POST | Create share link |
| `/api/report-builder/share/:id/deactivate` | POST | Deactivate share |

#### Features

| Feature | Description |
|---------|-------------|
| Rich text editing | Quill.js WYSIWYG editor |
| Drag-drop sections | Reorderable report sections with security clearance |
| Export formats | Word (.docx with cover/TOC), PDF, XLSX, CSV |
| Charts | Bar, line, pie, doughnut, radar, polar area (Chart.js) |
| Data sources | 54 data sources, visual query builder + raw SQL for admins |
| Workflow | draft → in_review → approved → published → archived |
| Data modes | Live data binding vs. point-in-time snapshot |
| Scheduling | Daily, weekly, monthly, quarterly; email delivery |
| Version history | Full version snapshots with restore |
| Comments | Section-level review annotations |
| Templates | Built-in: NARSSA, GRAP 103, Accession, Condition |
| Sharing | Token-based public sharing with expiry |
| Widgets | Embeddable dashboard widgets |

#### Database Tables (12)

| Table | Description |
|-------|-------------|
| `custom_report` | Report definitions (JSON layout, filters, charts, workflow status, data_mode) |
| `report_schedule` | Recurring schedules (daily/weekly/monthly/quarterly), email recipients |
| `report_archive` | Archived report outputs |
| `report_section` | Drag-drop sections with security clearance |
| `report_template` | Reusable templates |
| `report_version` | Version history snapshots |
| `report_comment` | Section-level comments and review annotations |
| `report_attachment` | File attachments and image galleries |
| `report_share` | Public sharing with expiry tokens |
| `report_link` | Internal cross-references and external links with OpenGraph |
| `report_query` | Saved visual/SQL queries |
| `dashboard_widget` | Embeddable dashboard widgets |

### 7.3 Reporting Views (BI Tool Integration)

**Location:** `atom-framework/database/views/reporting_views.sql`

Denormalized SQL views for Power BI, Tableau, Metabase, and other BI tools:

| View | Description | Key Fields |
|------|-------------|------------|
| `v_report_descriptions` | Flattened archival descriptions | id, reference_code, title, scope_and_content, level_of_description, repository_name, event_date, publication_status |
| `v_report_authorities` | Flattened authority records | id, authorized_form_of_name, entity_type, description_status, dates_of_existence, history |
| `v_report_accessions` | Flattened accessions | id, identifier, title, accession_date, source_of_acquisition, acquisition_type, processing_status |

### 7.4 Usage Statistics (ahgStatisticsPlugin)

**Plugin:** ahgStatisticsPlugin v1.0.0
**URL:** `/statistics`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/statistics` | GET | Statistics dashboard |
| `/statistics/dashboard` | GET | Dashboard (alias) |
| `/statistics/views` | GET | Page view analytics |
| `/statistics/downloads` | GET | Download analytics |
| `/statistics/geographic` | GET | Geographic distribution (GeoIP) |
| `/statistics/top-items` | GET | Most viewed items |
| `/statistics/item/:object_id` | GET | Per-item statistics |
| `/statistics/repository/:id` | GET | Per-repository statistics |
| `/statistics/export` | GET | Export statistics data |
| `/statistics/admin` | GET | Admin settings |
| `/statistics/admin/bots` | GET | Bot management |
| `/statistics/api/chart/:type` | GET | Chart data (AJAX) |
| `/statistics/api/summary` | GET | Summary data (AJAX) |
| `/statistics/pixel/:token` | GET | Tracking pixel |

**Event Tracking:** Auto-tracks page views (informationobject/index) and downloads (digitalobject/download|view) via `response.filter_content` hook.

**Features:** GeoIP location, bot detection/filtering, daily/monthly aggregation, export to CSV

**Database Tables:**

| Table | Description |
|-------|-------------|
| `ahg_usage_event` | Raw events (view, download, search, login, api) with GeoIP and bot detection |
| `ahg_statistics_daily` | Pre-aggregated daily summaries |
| `ahg_statistics_monthly` | Pre-aggregated monthly summaries |
| `ahg_bot_list` | Known bot user agents for filtering |
| `ahg_statistics_config` | Plugin configuration |

---

## 8. Export Functionality

### 8.1 Standard Export Formats (Base AtoM)

| Format | Plugin | Extension |
|--------|--------|-----------|
| EAD 2002 | sfEadPlugin | `.xml` |
| EAD3 | sfEad3Plugin | `.xml` |
| Dublin Core | sfDcPlugin | `.xml` |
| MODS | sfModsPlugin | `.xml` |
| SKOS | sfSkosPlugin | `.xml` |
| CSV | clipboard/export | `.csv` |

**Export Access Points:**
1. **Clipboard Export** - Select records → Export
2. **Individual Record** - View page → Export sidebar
3. **Admin Bulk Export** - Admin → Import/Export
4. **CLI Export** - `php bin/atom export:bulk`

### 8.2 AHG Export UI (ahgExportPlugin)

**Plugin:** ahgExportPlugin v1.0.0

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/export` | GET | Export dashboard |
| `/export/archival` | GET/POST | Archival description export |
| `/export/authority` | GET/POST | Authority record export |
| `/export/repository` | GET/POST | Repository export |
| `/export/csv` | GET/POST | CSV export |
| `/export/ead` | GET/POST | EAD XML export |
| `/export/grap` | GET/POST | GRAP 103 export |
| `/export/accession-csv` | GET/POST | Accession CSV export |

### 8.3 GLAM Metadata Export (ahgMetadataExportPlugin)

**Plugin:** ahgMetadataExportPlugin v1.0.0

#### Web Interface

| Endpoint | Method | Description |
|----------|--------|-------------|
| `metadataExport/index` | GET | Export UI with format selection |
| `metadataExport/preview` | GET | Preview export output |
| `metadataExport/download` | GET | Stream file download |
| `metadataExport/bulk` | GET/POST | Bulk/batch export |

#### Supported Formats (11)

| Format | Sector | Output |
|--------|--------|--------|
| EAD3 | Archives | XML |
| RIC-O | Archives | RiC-O (Records in Contexts Ontology)/RDF/RiC-O JSON-LD |
| LIDO | Museums | XML |
| CIDOC-CRM | Museums | RiC-O (Records in Contexts Ontology)/RDF/RiC-O JSON-LD |
| MARC21 | Libraries | MARCXML |
| BIBFRAME | Libraries | RiC-O (Records in Contexts Ontology)/RDF/RiC-O JSON-LD |
| VRA Core 4 | Visual Arts | XML |
| PBCore | Media | XML |
| EBUCore | Media | XML |
| PREMIS | Preservation | XML |
| Schema.org | Web/SEO | RiC-O JSON-LD |

#### Linked Data Endpoints

| Endpoint | Description |
|----------|-------------|
| `/{slug}.jsonld` | RiC-O JSON-LD for any description |
| `/repository/{slug}.jsonld` | RiC-O JSON-LD for repository |
| `/actor/{slug}.jsonld` | RiC-O JSON-LD for actor |
| `/sitemap-ld.xml` | Linked data sitemap |

#### CLI Command

```bash
php symfony metadata:export \
  --format=ead3 \
  --slug=my-fonds \
  --repository=my-repo \
  --output=/tmp/export.xml \
  --include-digital-objects \
  --include-children \
  --max-depth=5 \
  --list          # List available formats
  --preview       # Preview without download
```

**Database:** `metadata_export_config` (per-format settings), `metadata_export_log` (export audit trail)
**Reporting Views:** `v_metadata_export_stats` (per-format aggregate), `v_metadata_export_daily` (30-day activity)

### 8.4 Portable Export (ahgPortableExportPlugin)

**Plugin:** ahgPortableExportPlugin v3.0.0

Generates standalone offline catalogues (CD/USB/ZIP) with embedded HTML viewer and search.

#### Web Interface

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/portable-export` | GET | Export wizard |
| `/portable-export/api/start` | POST | Start export job |
| `/portable-export/api/quick-start` | POST | Quick start (defaults) |
| `/portable-export/api/clipboard-export` | POST | Export clipboard selection |
| `/portable-export/api/fonds-search` | GET | Search for fonds to export |
| `/portable-export/api/progress` | GET | Job progress polling |
| `/portable-export/api/list` | GET | List completed exports |
| `/portable-export/api/delete` | POST | Delete export package |
| `/portable-export/api/token` | POST | Generate download token |
| `/portable-export/api/estimate` | POST | Estimate export size |
| `/portable-export/download` | GET | Download package (token-secured) |

#### Import Interface

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/portable-export/import` | GET | Import wizard |
| `/portable-export/api/start-import` | POST | Start import job |
| `/portable-export/api/import-progress` | GET | Import progress |
| `/portable-export/api/import-validate` | POST | Validate import package |
| `/portable-export/api/import-list` | GET | List available imports |

#### CLI Commands

| Command | Description |
|---------|-------------|
| `portable:export` | Export from command line |
| `portable:import` | Import package (merge/replace/dry-run modes) |
| `portable:verify` | Verify package integrity (SHA-256 checksums) |
| `portable:cleanup` | Purge old export packages |

#### Export Scope Modes

| Mode | Description |
|------|-------------|
| `all` | Entire repository |
| `fonds` | Single fonds and descendants |
| `repository` | Single repository |
| `custom` | Clipboard/custom selection |

#### Entity Types Exported (15)

descriptions, authorities, taxonomies, rights, accessions, physical_objects, events, notes, relations, digital_objects, repositories, object_term_relations, settings, users, menus

#### Package Features

- Standalone HTML/JS viewer with FlexSearch client-side search
- SHA-256 checksummed manifest with self-documenting README
- Three modes: read_only, editable, archive
- Secure download tokens with max downloads and expiry
- Queue integration (dispatch via QueueService with nohup fallback)

**Database:** `portable_export` (export jobs), `portable_export_token` (download tokens), `portable_import` (import jobs)

### 8.5 Finding Aid Generation

**URL:** `/informationobject/findingAid/{slug}`

**Features:** PDF/A output, customizable templates, hierarchical structure, digital object thumbnails

**CLI:** `php bin/atom finding-aid:generate`

### 8.6 TIFF to PDF Merge

**URL:** `/index.php/tiff-pdf-merge`

| Feature | Description |
|---------|-------------|
| Multi-TIFF Upload | Batch upload images |
| Reorder | Drag-drop page ordering |
| PDF/A Output | Archival-quality output |
| Job Queue | Background processing |
| Integration | Links to Information Objects |

### 8.7 Label Printing (ahgLabelPlugin)

**Plugin:** ahgLabelPlugin v1.0.0

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/label/:slug` | GET | Generate label for archival object |

### 8.8 Configurable Forms (ahgFormsPlugin)

**Plugin:** ahgFormsPlugin v1.0.0

#### Admin Interface

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/admin/forms` | GET | Forms dashboard |
| `/admin/forms/templates` | GET | Template list |
| `/admin/forms/template/create` | GET/POST | Create template |
| `/admin/forms/template/:id/edit` | GET/POST | Edit template |
| `/admin/forms/template/:id/delete` | POST | Delete template |
| `/admin/forms/template/:id/clone` | POST | Clone template |
| `/admin/forms/template/:id/export` | GET | Export template JSON |
| `/admin/forms/template/import` | POST | Import template JSON |
| `/admin/forms/template/:id/builder` | GET | Drag-drop form builder |
| `/admin/forms/assignments` | GET | Assignment management |
| `/admin/forms/assignment/create` | POST | Create assignment |
| `/admin/forms/assignment/:id/delete` | POST | Delete assignment |
| `/admin/forms/mappings` | GET | Field mappings |
| `/admin/forms/library` | GET | Community template library |
| `/admin/forms/library/:template/install` | POST | Install from library |

#### API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/forms/template/:id/fields` | POST | Save field definitions |
| `/api/forms/template/:id/reorder` | POST | Reorder fields |
| `/api/forms/render/:type/:id` | GET | Render form for entity |
| `/api/forms/autosave` | POST | Autosave draft |

#### CLI Commands

| Command | Description |
|---------|-------------|
| `forms:export` | Export form template definitions |
| `forms:import` | Import form template definitions |
| `forms:list` | List installed form templates |

#### Field Types (14)

text, textarea, date, number, boolean, dropdown, url, select, multiselect, radio, checkbox, richtext, file, hidden

**Database:** `ahg_form_template`, `ahg_form_field`, `ahg_form_assignment`, `ahg_form_field_mapping`, `ahg_form_draft`, `ahg_form_submission_log`

---

## 9. CLI Commands Reference

### 9.1 Framework Export Commands (`php bin/atom`)

| Command | Description |
|---------|-------------|
| `csv:export` | Export descriptions as CSV (`--standard`, `--single-slug`, `--rows-per-file`) |
| `export:bulk` | Bulk EAD/XML/MODS export (`--format`, `--single-slug`, `--public`, `--criteria`) |
| `export:bulk-csv` | Bulk CSV export of descriptions |
| `csv:accession-export` | Export accessions as CSV |
| `csv:authority-export` | Export authority records as CSV |
| `csv:repository-export` | Export repositories as CSV |
| `csv:export-term-usage` | Export term usage statistics (`--taxonomy-id`) |
| `csv:physicalstorage-holdings` | Export physical storage holdings |
| `export:auth-recs` | EAC-CPF authority records export |
| `finding-aid:generate` | Generate EAD finding aid |

### 9.2 Framework Import Commands (`php bin/atom`)

| Command | Description |
|---------|-------------|
| `import:csv` | Import descriptions from CSV |
| `import:bulk` | Bulk EAD/XML import |
| `import:csv-accession` | Import accessions |
| `import:csv-authority-record` | Import authority records |
| `import:csv-authority-record-relation` | Import authority relationships |
| `import:csv-event` | Import events |
| `import:csv-deaccession` | Import deaccessions |
| `import:csv-physical-object` | Import physical objects |
| `import:csv-repository` | Import repositories |
| `import:csv-custom` | Custom CSV import |
| `import:csv-check` | Validate CSV before import |
| `import:csv-digital-object-paths-check` | Validate digital object paths |
| `import:csv-audit` | Import audit log data |
| `import:dip-objects` | Import DIP digital objects |
| `import:delete` | Delete import records |

### 9.3 Plugin CLI Commands (`php symfony`)

| Command | Plugin | Description |
|---------|--------|-------------|
| `ai:install` | ahgAIPlugin | Install AI database tables |
| `ai:ner-extract` | ahgAIPlugin | Extract named entities |
| `ai:ner-sync` | ahgAIPlugin | Sync NER training data |
| `ai:translate` | ahgAIPlugin | Translate records |
| `ai:summarize` | ahgAIPlugin | Summarize content |
| `ai:spellcheck` | ahgAIPlugin | Check spelling |
| `ai:suggest-description` | ahgAIPlugin | LLM-powered description suggestions |
| `ai:process-pending` | ahgAIPlugin | Process pending AI tasks |
| `ai:sync-entity-cache` | ahgAIPlugin | Sync entity cache |
| `preservation:convert` | ahgPreservationPlugin | Convert formats |
| `preservation:fixity` | ahgPreservationPlugin | Run fixity checks |
| `preservation:identify` | ahgPreservationPlugin | Format identification |
| `preservation:migration` | ahgPreservationPlugin | Format migration |
| `preservation:package` | ahgPreservationPlugin | Create preservation package |
| `preservation:pronom-sync` | ahgPreservationPlugin | Sync PRONOM registry |
| `preservation:replicate` | ahgPreservationPlugin | Replicate to backup |
| `preservation:scheduler` | ahgPreservationPlugin | Run scheduled tasks |
| `preservation:verify-backup` | ahgPreservationPlugin | Verify backup integrity |
| `preservation:virus-scan` | ahgPreservationPlugin | Virus scan |
| `doi:mint` | ahgDoiPlugin | Mint DOI |
| `doi:deactivate` | ahgDoiPlugin | Deactivate DOI |
| `doi:sync` | ahgDoiPlugin | Sync to DataCite |
| `doi:process-queue` | ahgDoiPlugin | Process mint queue |
| `doi:verify` | ahgDoiPlugin | Verify DOI resolution |
| `cdpa:license-check` | ahgCDPAPlugin | License compliance check |
| `cdpa:report` | ahgCDPAPlugin | CDPA report |
| `cdpa:requests` | ahgCDPAPlugin | Process CDPA requests |
| `cdpa:status` | ahgCDPAPlugin | CDPA status |
| `naz:closure-check` | ahgNAZPlugin | 25-year closure check |
| `naz:permit-expiry` | ahgNAZPlugin | Permit expiry check |
| `naz:report` | ahgNAZPlugin | NAZ report |
| `naz:transfer-due` | ahgNAZPlugin | Transfer due check |
| `dedupe:merge` | ahgDedupePlugin | Merge duplicates |
| `dedupe:report` | ahgDedupePlugin | Duplicate report |
| `dedupe:scan` | ahgDedupePlugin | Scan for duplicates |
| `forms:export` | ahgFormsPlugin | Export form templates |
| `forms:import` | ahgFormsPlugin | Import form templates |
| `forms:list` | ahgFormsPlugin | List form templates |
| `heritage:build-graph` | ahgHeritagePlugin | Build heritage graph |
| `heritage:install` | ahgHeritagePlugin | Install heritage tables |
| `heritage:region` | ahgHeritagePlugin | Manage regions |
| `display:auto-detect` | ahgDisplayPlugin | Auto-detect GLAM types |
| `display:reindex` | ahgDisplayPlugin | Reindex display cache |
| `privacy:jurisdiction` | ahgPrivacyPlugin | Manage jurisdictions |
| `privacy:scan-pii` | ahgPrivacyPlugin | Scan for PII |
| `embargo:process` | ahgExtendedRightsPlugin | Process embargoes |
| `embargo:report` | ahgExtendedRightsPlugin | Embargo report |
| `museum:exhibition` | ahgMuseumPlugin | Exhibition management |
| `museum:getty-link` | ahgMuseumPlugin | Getty vocabulary linking |
| `museum:migrate` | ahgMuseumPlugin | Museum data migration |
| `ingest:commit` | ahgIngestPlugin | Commit ingest session |
| `ipsas:report` | ahgIPSASPlugin | IPSAS report |
| `nmmz:report` | ahgNMMZPlugin | NMMZ report |
| `metadata:export` | ahgMetadataExportPlugin | GLAM metadata export |
| `library:process-covers` | ahgLibraryPlugin | Process book covers |
| `portable:export` | ahgPortableExportPlugin | Portable export |
| `portable:import` | ahgPortableExportPlugin | Portable import |
| `portable:verify` | ahgPortableExportPlugin | Verify package |
| `portable:cleanup` | ahgPortableExportPlugin | Cleanup old packages |
| `api:webhook-process-retries` | ahgAPIPlugin | Process failed webhooks |

### 9.4 Queue Commands (`php bin/atom`)

| Command | Description |
|---------|-------------|
| `queue:work` | Start queue worker (MySQL-backed, `SELECT FOR UPDATE SKIP LOCKED`) |
| `queue:status` | Show queue statistics |
| `queue:failed` | List failed jobs |
| `queue:retry` | Retry a failed job |
| `queue:cleanup` | Purge old completed/failed jobs |

### 9.5 Legacy Job Commands (`php bin/atom`)

| Command | Description |
|---------|-------------|
| `jobs:list` | List background jobs |
| `jobs:clear` | Clear old jobs |
| `jobs:worker` | Legacy job worker |

---

## 10. Background Queue System

### 10.1 Queue Engine (atom-framework)

**Service:** `QueueService` (1291 lines)
**Backend:** MySQL with `SELECT FOR UPDATE SKIP LOCKED` for worker reservation

**Key Capabilities:**
- Job dispatch with priority, queue name, delay, max_attempts, backoff strategy
- Synchronous dispatch for immediate execution
- Chained sequential jobs
- Batch management (create, add, start, pause, cancel)
- Progress tracking (per-job and per-batch)
- Rate limiting per job group
- Exponential/linear backoff for retries
- Structured event logging

**Database Tables:**

| Table | Description |
|-------|-------------|
| `ahg_queue_job` | Job queue (payload, priority, attempts, scheduled_at, reserved_at) |
| `ahg_queue_batch` | Batch definitions and progress |
| `ahg_queue_failed` | Failed job storage for retry |
| `ahg_queue_log` | Structured event logging |
| `ahg_queue_rate_limit` | Per-group rate limiting |

**Worker Deployment:** systemd template `atom-queue-worker@.service` for per-queue instances

**Plugins Using Queue:** ahgIngestPlugin, ahgPortableExportPlugin (with nohup fallback), ahgAIPlugin

### 10.2 Legacy Gearman Jobs

**Export Jobs:**
```
arXmlExportSingleFileJob            - Single XML export
arActorCsvExportJob                 - Actor CSV bulk export
arActorXmlExportJob                 - Actor EAC-CPF export
arRepositoryCsvExportJob            - Repository CSV export
arGenerateReportJob                 - Scheduled report generation
arPhysicalObjectCsvHoldingsReportJob - Physical storage report
arValidateCsvJob                    - CSV import validation
```

**Processing Jobs:**
```
arUpdateEsIoDocumentsJob            - Elasticsearch reindex
arFindingAidJob                     - PDF finding aid generation
arTiffPdfMergeJob                   - Multi-TIFF to PDF conversion
```

---

## 11. Integration Points

### 11.1 Elasticsearch

- Powers search, autocomplete, and discovery
- Index sync via `arUpdateEsIoDocumentsJob` or `php bin/atom search:populate`
- Enhanced by ahgSearchPlugin and ahgDiscoveryPlugin

### 11.2 Apache Jena Fuseki

- RIC RiC-O triplestore for RiC (Records in Contexts) linked data and semantic search
- SPARQL queries via ahgRicExplorerPlugin
- ahgSearchPlugin semantic search mode

### 11.3 DataCite

- DOI minting and metadata sync via ahgDoiPlugin
- Supports test and production environments per repository

### 11.4 OAI-PMH

- Standard OAI-PMH via arOaiPlugin
- Extended with `oai_heritage` format via ahgFederationPlugin
- Peer harvesting for federated discovery

### 11.5 External AI Services

- spaCy (NER), Argos Translate, Tesseract OCR, ClamAV, Siegfried
- OpenCV/AWS/Azure (face detection)
- LLM providers (description suggestions)

---

## 12. Configuration

### 12.1 REST API Key Setup

1. Admin → Users → Edit User
2. Generate REST API Key
3. Use in header: `X-Api-Key: {key}` (v2) or `REST-API-Key: {key}` (legacy)

### 12.2 GraphQL Access

Uses ahgAPIPlugin API keys. Enable playground in development via plugin settings.

### 12.3 Report Builder Access

Admin → Report Builder (requires authenticated user with appropriate ACL permissions)

### 12.4 Export Permissions

Controlled via AtoM ACL:
- `read` - View records
- `export` - Export functionality (implied by read)
- `admin` - Bulk export, regenerate finding aids

### 12.5 Webhook Configuration

Admin → API → Webhooks. Configure target URL, events, and HMAC secret per webhook.

---

## 13. Outstanding / Parked Items

### 13.1 Parked (Low Priority)

| Item | Description | Status |
|------|-------------|--------|
| **RiC-O JSON-LD Export** | Structured data for search engines/RiC (Records in Contexts) linked data | Partially implemented (ahgMetadataExportPlugin Schema.org format) |
| **Wikidata/VIAF Linking** | Authority record enrichment | Not started |
| **Researcher Finding Aid** | PDF export from custom collection lists | Not started |
| **Public SPARQL Endpoint** | External query access to RIC RiC-O triplestore | Not started |
| **Mobile App API** | Extended REST API for mobile (sync endpoints exist) | Partial |

---

## Summary

| Category | Plugin(s) | Status | Endpoints/Commands |
|----------|-----------|--------|-------------------|
| REST API v2 | ahgAPIPlugin | Complete | 50+ endpoints |
| GraphQL API | ahgGraphQLPlugin | Complete | 3 endpoints |
| Webhooks | ahgAPIPlugin | Complete | 6 endpoints + CLI |
| OAI-PMH | arOaiPlugin + ahgFederationPlugin | Complete | Standard verbs + federation |
| DOI Integration | ahgDoiPlugin | Complete | 22 endpoints + 5 CLI |
| IIIF API | ahgIiifPlugin | Complete (stable) | 40+ endpoints (5 modules) |
| Discovery Search | ahgDiscoveryPlugin | Complete | 5 endpoints |
| Global Search | ahgSearchPlugin | Complete | 5 endpoints |
| Reports Dashboard | ahgReportsPlugin | Complete | 11 endpoints |
| Report Builder | ahgReportBuilderPlugin | Complete | 40+ endpoints (admin + API) |
| Usage Statistics | ahgStatisticsPlugin | Complete | 14 endpoints |
| Standard Export | Base AtoM | Complete | EAD, DC, CSV, MODS, SKOS |
| AHG Export UI | ahgExportPlugin | Complete | 10 endpoints |
| Metadata Export | ahgMetadataExportPlugin | Complete | 11 formats + RiC (Records in Contexts) linked data + CLI |
| Portable Export | ahgPortableExportPlugin | Complete | 16 endpoints + 4 CLI |
| Configurable Forms | ahgFormsPlugin | Complete | 19 endpoints + 3 CLI |
| Label Printing | ahgLabelPlugin | Complete | 1 endpoint |
| Framework Import/Export | atom-framework | Complete | 9 export + 15 import CLI |
| Queue Engine | atom-framework | Complete | 5 CLI commands |
| BI Reporting Views | atom-framework | Complete | 3 SQL views |
