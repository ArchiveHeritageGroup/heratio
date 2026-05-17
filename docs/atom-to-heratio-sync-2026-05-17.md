# AtoM → Heratio sync plan (2026-05-16 to 2026-05-17)

**Purpose:** bring the Heratio (Laravel) instance to 100% parity with the AtoM
(Symfony 1.4) instance after the two-day burst of feature + compliance work
that shipped on PSIS on 16–17 May 2026.

The AtoM side is now ahead of Heratio for two large bodies of work:

1. **Research module v3.1.0** — 13 features ported from the Heratio Laravel
   spec into AtoM. Heratio already has the spec at
   `docs/research-enhancements-roadmap.md`; this section is mostly *closing
   parity gaps* discovered during the AtoM port back to Heratio.
2. **Records-management compliance closure** — retention schedules, disposal
   workflow, NARSSA transfer manifest, compliance dashboard report
   templates, post-ingest hook chain. Most of this is new for Heratio too.

This document is the worklist for bringing Heratio to the same state.

---

## 1. Researcher enhancements parity (AtoM v3.1.0 → Heratio v1.59+)

The AtoM port lives in `atom-ahg-plugins/ahgResearchPlugin/v3.1.0`. The
Heratio side has the spec but not all 13 features built. Items confirmed
**ahead** on AtoM:

| # | Feature | AtoM file paths | Heratio status | Action |
|---|---|---|---|---|
| 1 | Studio — 8 output types | `lib/Services/ResearchStudioService.php` (8 prompt templates, xlsx via PhpSpreadsheet, audio TTS) | Spec only | Port to `packages/ahg-research/src/Services/ResearchStudioService.php` |
| 2 | Citation popovers | `web/js/citation-popover.js` | Source-of-truth on Heratio side already | None |
| 3 | Notebooks + promote-to-project | `lib/Services/NotebookService.php` + `promoteToProject()` transactional flow | Partial | Mirror the idempotent `promoteToProject()` semantics |
| 4 | Cross-fonds queries | `lib/Services/CrossFondsQueryService.php` — fans out across `lft`/`rgt` ranges on OpenSearch | Partial — uses ES directly | Verify parity, ensure `research_cross_fonds_query` table exists |
| 5 | Analytics dashboard | `lib/Services/ResearchAnalyticsService.php` — 8 KPIs + top-N lists | Built | Verify the 4 new activity_types are logged: `ai_studio`, `search_cross_fonds`, `notebook_item_added`, `cite_export` |
| 6 | Live collaboration (polling) | `lib/Services/CollaborationRealtimeService.php` — 3s tick, 90s stale | Partial | Add `research_collaboration_session` + `research_collaboration_presence` tables; port the polling-mode Eloquent observers |
| 7 | Per-record citation export (RIS/BibTeX/EndNote/APA/MLA/Chicago) | `lib/Services/CitationService.php` | Built | Verify the 6 formats match byte-for-byte (NB: AtoM joins `actor_i18n` for repository name — Heratio uses `repository` table directly) |
| 8 | ORCID Works push/pull | `lib/Services/OrcidService.php` extended — `linkResearcher`, `pullWorks`, `pushWork`, `buildWorkXml`, AES-256-CBC tokens | Built | Verify token-encryption key derivation matches (AtoM uses `sf_app_secret`; Heratio uses `APP_KEY`) |
| 9 | Mobile/PWA shell | `manifest.webmanifest` + `sw.js` + `modules/research/templates/mobileHomeSuccess.php` | Built | Verify the `/research/mobile` route + service worker register flow |
| 10 | Offline mode + sync | `lib/Services/OfflineSyncService.php` + `research_offline_sync_log` table | Built | Mirror — Heratio side ships the Laravel-native equivalent |
| 11 | ResearcherView JSON | `/research/researcher-view/:id` endpoint | Heratio-side has full GraphQL (webonyx) | Verify the GraphQL `researcherView` query returns the same shape |

### Database tables to verify on Heratio

Each of these exists on AtoM as of v3.1.0; check that Heratio has equivalents
(naming may differ — Laravel migrations may use snake_case singular):

```
research_studio_artefact
research_notebook
research_notebook_item
research_cross_fonds_query
research_collaboration_session
research_collaboration_presence
research_orcid_link
research_offline_sync_log
```

Heratio's annotation table (`research_annotation` or equivalent) already has
`project_id` + `visibility` columns on the AtoM side; the spec assumed these
were Heratio-only, but AtoM was actually ahead. Verify.

### Routes added on AtoM (21 — Heratio routes should map 1:1)

```
GET  /research/studio/:projectId
POST /research/studio/:projectId/generate
GET  /research/studio/:projectId/artefact/:artefactId
GET  /research/studio/:projectId/artefact/:artefactId/download
POST /research/studio/:projectId/artefact/:artefactId/delete
GET  /research/notebooks
GET  /research/notebooks/:id
POST /research/notebooks/:id/delete
POST /research/notebooks/:id/promote
GET  /research/cross-fonds-query
GET  /research/analytics
GET  /research/projects/:projectId/realtime/panel
POST /research/projects/:projectId/realtime/join
POST /research/projects/:projectId/realtime/poll
POST /research/projects/:projectId/realtime/comment
POST /research/projects/:projectId/realtime/comment/:commentId/resolve
GET  /research/orcid/works
GET  /research/researcher-view/:researcherId
GET  /research/mobile
POST /research/sync/offline
GET  /research/cite/:slug/export/:format     ← (ris|bibtex|endnote|apa|mla|chicago)
```

### Templates added on AtoM (9)

`studioSuccess.php`, `studioShowSuccess.php`, `notebooksSuccess.php`,
`notebookShowSuccess.php`, `crossFondsQuerySuccess.php`,
`analyticsSuccess.php`, `collabPanelSuccess.php`, `orcidWorksSuccess.php`,
`mobileHomeSuccess.php`. The Heratio side uses Blade — port the structure,
not the syntax.

---

## 2. Records-management compliance closure (NEW for Heratio)

This is the bigger lift — most of these are brand-new on AtoM (released
2026-05-17) and have no Heratio counterpart yet.

### 2.1 Retention schedule + disposal workflow

**New plugin / package on Heratio:** `ahg-extended-rights` v2.0 (the AtoM
plugin equivalent is `ahgExtendedRightsPlugin` v1.3.0).

| Table | Purpose |
|---|---|
| `retention_schedule` | One row per File-Plan record series. Code, title, active years, dormant years, trigger event, disposal action, required signoff flags, legal basis |
| `retention_assignment` | One row per (information_object, schedule). Calculated disposal-due date |
| `disposal_action` | One row per disposal decision. Multi-stage status: `proposed → officer_signed → legal_signed → executive_signed → approved → executed` (plus `rejected`, `deferred`) |

**Services to port:**

- `RetentionScheduleService` — CRUD on schedules, `assign(io_id, schedule_id, trigger_date)`, `dueRecords(lookAheadDays)`
- `DisposalWorkflowService` — `propose`, `officerSign`, `legalSign`, `executiveSign`, `execute`, `reject`, `defer`. Auto-advances to `approved` when all required signoffs present. Writes to `ahg_audit_log` on every state change.

**Seed data:** 6 example schedules (`COMM-001` to `LEG-001` — already in
the migration SQL). Operators replace these with their organisation's File
Plan codes.

**File:** `atom-ahg-plugins/ahgExtendedRightsPlugin/database/migrations/2026_05_17_retention_schedule_disposal_workflow.sql`

### 2.2 NARSSA transfer manifest

**Brand new plugin on AtoM:** `ahgNARSSAPlugin` v0.1.0. Heratio needs
equivalent.

**Tables:**

```
narssa_transfer        (transfer batch — reference, title, item count, bytes,
                         package_path, package_sha256, status, transmitted_at,
                         accepted_at, narssa_receipt_reference)
narssa_transfer_item   (one row per information_object in a transfer)
```

**Service:** `TransferPackageService::build(io_ids, user_id, title)` and
`buildFromApprovedDisposals(user_id)`. Produces a `.tar.gz` containing
`manifest.csv` + `transfer.xml` (METS wrapper) + `items/<ref>/{description.xml
(EAD2002), digital_objects/, checksums.sha256}`.

**CLI:** `php symfony narssa:transfer-package [--io-ids=N,N --user-id=N --title=...]`

**Standards used:** METS (Library of Congress) + EAD2002 (SAA / LoC) + SHA-256.

**Heratio side:** `php artisan narssa:transfer-package` in
`packages/ahg-narssa/` (new). Same XML output bytes-for-bytes if at all
possible — the package gets verified by SHA-256 on the NARSSA end.

### 2.3 Records Management Compliance dashboard report templates

5 new `report_template` rows seeded by
`ahgReportBuilderPlugin/database/migrations/2026_05_17_records_management_compliance_templates.sql`:

| Template name | Surfaces |
|---|---|
| Records Management Compliance: Audit Summary | volume by action, top 20 users, disposal-workflow audit chain |
| Records Management Compliance: Access Logs & User Activity | active users 90d, share-link accesses, failed access attempts |
| Records Management Compliance: Metadata Integrity | versioned-record coverage, most-edited records, restore events |
| Records Management Compliance: Retention Status & Lifecycle | records assigned to schedule, due in 12 months, disposal pipeline, approved transfers awaiting NARSSA package |
| Records Management Compliance: Consolidated Quarterly Dashboard | 8 KPI tiles + 30-day audit-volume line chart |

Heratio's report builder uses the same `report_template.structure` JSON
shape (Heratio is the source-of-truth for the builder), so on the
Laravel side this is *just an Eloquent seeder* — drop the same JSON in.
Watch the SQL queries: they reference `ahg_audit_log`,
`information_object_share_token`, `information_object_share_access`,
`information_object_version`, `actor_version`, `retention_assignment`,
`retention_schedule`, `disposal_action`, `narssa_transfer`. Adjust to
Heratio's table names if they differ.

### 2.4 ShareLink v0.2 — bookmarkable issue form

Heratio currently ships the Bootstrap-modal-injected "Share this record"
button (via `ViewLinkInjector`). AtoM v0.2 adds a complementary full-page
issue form at `/shareLink/issue?information_object_id=N`.

**Files added on AtoM:**

- `modules/shareLink/templates/newSuccess.php` — GET form
- `modules/shareLink/templates/issueSuccess.php` — POST success with copy-to-clipboard
- `executeIssue` in `modules/shareLink/actions/actions.class.php` —
  content-negotiates (Accept: application/json → existing JSON, otherwise
  HTML)

**Heratio:** add an equivalent Blade pair (`new.blade.php` +
`issue.blade.php`) and content-negotiate in the controller. The modal
keeps working unchanged.

---

## 3. Post-ingest compliance hooks (SP NO-PUSH on AtoM)

This is on the AtoM side as `PostIngestHookService` + the CLI task
`php symfony sharepoint:post-ingest-hooks --job-id=N`. **It is LOCAL on
AtoM per the SP NO-PUSH policy** and is mirrored to
`/opt/ahg-sp-integration/post-ingest-hooks/` on the PSIS host.

On Heratio (which is on the same NO-PUSH policy for SharePoint), the
equivalent should also stay local — at
`/usr/share/nginx/heratio/app/Domain/SharePoint/Services/PostIngestHookService.php`
(if/when it's built). Five hooks fire per ingested IO:

| Hook | Purpose |
|---|---|
| sp_xref columns | Writes `sp_item_id`, `sp_drive_id`, `sp_etag`, `sp_retention_label`, `sp_web_url`, `sp_ingested_at` onto `information_object` — preserves the cross-reference outside `ingest_file.sidecar_json` |
| v1 version baseline | Calls `VersionWriter::write()` with `change_summary='Initial baseline from SharePoint ingest'` — no need to wait for `version:backfill` |
| Label → classification | Maps M365 retention label → `security_classification.id` via `ahg_settings.sharepoint.label_classification_map` JSON |
| OAIS AIP package | Writes `uploads/aip/<io_id>/{objects/, metadata/premis.json, manifest.json, checksum.sha256}` |
| PII scan | Best-effort `PiiScanService::scanInformationObject($id)` when `ahgPrivacyPlugin` is installed |

**Schema migration (LOCAL only):**

```sql
ALTER TABLE information_object
  ADD COLUMN sp_item_id VARCHAR(255) NULL,
  ADD COLUMN sp_drive_id VARCHAR(255) NULL,
  ADD COLUMN sp_etag VARCHAR(128) NULL,
  ADD COLUMN sp_retention_label VARCHAR(255) NULL,
  ADD COLUMN sp_web_url VARCHAR(1000) NULL,
  ADD COLUMN sp_ingested_at DATETIME NULL,
  ADD INDEX idx_sp_item (sp_item_id),
  ADD INDEX idx_sp_drive (sp_drive_id);
```

Apply directly on Heratio's MySQL; do NOT commit to migration files until
the NO-PUSH policy is lifted.

---

## 4. Verification — regression sweep

AtoM ships `php symfony ahg-vc:regression` — 80 assertions across F1
(Time-Limited Share Links — 34/34), F2 (Version Control — 22/22), F3
(Federated Search — 24/24). It asserts:

- DB tables exist with the right columns
- Service classes load
- Routes registered
- Service-layer round-trip works end-to-end against the live DB

Heratio should ship the Pest/PHPUnit equivalent. Without it, claiming
"34/22/24 regression assertions pass" is only true on Heratio (per
`packages/ahg-share-link/tests/`, `packages/ahg-version-control/tests/`,
`packages/ahg-federation/tests/`) and **must not be conflated with AtoM**
in bid documentation.

---

## 5. File / function inventory — what to read from the AtoM repo

Cloned/synced to Heratio from the AtoM repo at the indicated paths:

```
atom-ahg-plugins/ahgResearchPlugin/lib/Services/ResearchStudioService.php       ~600 lines
atom-ahg-plugins/ahgResearchPlugin/lib/Services/NotebookService.php             ~200 lines
atom-ahg-plugins/ahgResearchPlugin/lib/Services/CrossFondsQueryService.php      ~230 lines
atom-ahg-plugins/ahgResearchPlugin/lib/Services/ResearchAnalyticsService.php    ~260 lines
atom-ahg-plugins/ahgResearchPlugin/lib/Services/CollaborationRealtimeService.php ~200 lines
atom-ahg-plugins/ahgResearchPlugin/lib/Services/CitationService.php             ~300 lines
atom-ahg-plugins/ahgResearchPlugin/lib/Services/OfflineSyncService.php          ~130 lines
atom-ahg-plugins/ahgResearchPlugin/lib/Services/OrcidService.php (extended)     ~900 lines
atom-ahg-plugins/ahgExtendedRightsPlugin/lib/Services/RetentionScheduleService.php ~200 lines
atom-ahg-plugins/ahgExtendedRightsPlugin/lib/Services/DisposalWorkflowService.php ~270 lines
atom-ahg-plugins/ahgNARSSAPlugin/lib/Services/TransferPackageService.php       ~330 lines
atom-ahg-plugins/ahgNARSSAPlugin/lib/task/narssaTransferPackageTask.class.php  ~60 lines
```

All of the above except `OrcidService.php` are new files. `OrcidService` is
an in-place extension — diff between v0.1 and v3.1 (Phase 2.4) is the new
methods (`linkResearcher`, `pullWorks`, `pushWork`, `buildWorkXml`,
`encryptToken`, `decryptToken`) plus the `research_orcid_link` table writes.

---

## 6. Open items / known divergences

1. **F3 federation connectors** — AtoM has `OaiPmhConnector` in git;
   `AtomElasticsearchConnector` + `SharePointGraphConnector` are LOCAL
   only. Heratio has all 4 connectors. F3 dispatch on AtoM is wired
   through a LOCAL modification to `FederatedSearchService.php`. When
   the SP NO-PUSH lifts, sync from Heratio (source-of-truth) back to
   AtoM and commit.

2. **`information_object.sp_*` columns** — added directly to PSIS DB via
   ALTER TABLE (LOCAL only). Heratio's `information_objects` table needs
   the same columns or an equivalent `Eloquent` cast. Apply via direct
   `php artisan tinker` ALTER until the NO-PUSH lifts.

3. **Mobile manifest + sw.js path** — AtoM serves these at the web root
   (`/manifest.webmanifest`, `/sw.js`). Heratio's `public/` ships them
   the same way; verify they exist post-deploy.

4. **GCIS-specific phrasing removed from generic docs (2026-05-17)** —
   the bid plan stays in `atom-extensions-catalog/docs/AtoM_Heratio_GCIS_RFB-001_*.md`
   (specific). All other plugin documentation is now generic and refers
   to "applicable records-management frameworks" instead of GCIS clause
   numbers. Mirror this when porting plugin docs to Heratio.

5. **Retention seed-data file-plan codes** — renamed from `GCIS-*` to
   generic prefixes (`COMM-*`, `CORP-*`, `HR-*`, `LEG-*`). Heratio
   migrations should use the same generic codes.

6. **Report template category renamed** — `gcis_compliance` →
   `records_management_compliance`. Heratio seeders should use the new
   category name from the start.

---

## 7. Recommended Heratio worklist order

Sequence the port to minimise integration risk:

1. **Day 1** — Apply schema migrations: `retention_schedule`,
   `retention_assignment`, `disposal_action`, `narssa_transfer`,
   `narssa_transfer_item`, plus seed schedules. No code changes yet.
2. **Day 1** — Port `RetentionScheduleService` + `DisposalWorkflowService`
   (Laravel-flavoured, but methods + assertions are 1:1 with AtoM). Pest
   regression suite.
3. **Day 2** — Port `TransferPackageService` + `ahg:transfer-package`
   Artisan command. Verify package SHA-256 byte-equivalent to AtoM
   output on the same record IDs.
4. **Day 2** — Seed the 5 records-management-compliance `report_template`
   rows (the JSON is portable as-is).
5. **Day 3** — Port the v0.2 ShareLink bookmarkable form (small —
   2 Blade views + content-negotiation in the controller).
6. **Day 3+ (deferred — NO-PUSH)** — Port the SP `PostIngestHookService`
   to `/usr/share/nginx/heratio/app/Domain/SharePoint/Services/` LOCAL.
   Apply the `information_object.sp_*` columns to Heratio's DB directly.

Total: ~3 working days to bring Heratio to 100% parity with AtoM as of
2026-05-17.

---

*Sync document drafted 2026-05-17. Source-of-truth for the worklist:
`atom-ahg-plugins/` at commit v3.34.0 + LOCAL extras at
`/opt/ahg-sp-integration/`.*
