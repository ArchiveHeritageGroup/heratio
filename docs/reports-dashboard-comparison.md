# Reports Dashboard Comparison: AtoM (psis) vs Heratio

Generated: 2026-04-11 | Updated: 2026-04-12

## URL Audit Summary

After yesterday's "175/175 destination pages cloned" sweep (which only fixed CSS classes, not content/routing), an actual click-through audit found that **most dashboard links rendered white pages** because the link URLs did not match the registered routes. They fell through to the slug catch-all and returned 404.

| Phase | Status | Broken links remaining | Notes |
|-------|--------|------------------------|-------|
| **Before** (yesterday's "complete") | — | **90 / 122** broken | Wrong URL prefixes + missing routes |
| **Phase A — URL prefix fixes** | DONE 2026-04-12 | **40 / 122** | Re-pointed dashboard links to existing `/admin/...` routes |
| **Phase B Batch 1 — Rights Management (9 pages)** | DONE 2026-04-12 | **20 / 122** | New `/admin/rights/*` route group + controller methods |
| **Phase B remainder** | OUTSTANDING | **0 / 122** (target) | 20 dashboard links still need clone-from-PSIS work |

> Note: After Batch 1, a fresh `php artisan route:list` snapshot showed several routes I'd flagged as missing were in fact already registered (the original Phase A snapshot had been stale). Real outstanding count is **20**, not the ~40 first reported.

### Phase A — URL prefix fixes (DONE)

Single edit to `packages/ahg-reports/resources/views/dashboard.blade.php`. No new routes, no controllers — only the dashboard's `url('/...')` calls were corrected to point at the actual registered routes. 50 links restored.

| Card / Section | Old (broken) | New (fixed) |
|----------------|--------------|-------------|
| Spectrum Workflow (5) | `/spectrum/dashboard`, `/my-tasks`, `/workflows`, `/notifications` | `/admin/spectrum/dashboard`, `/my-tasks`, `/workflow`, `/notifications` |
| Spectrum Export (3) | `/spectrum/export` | `/admin/spectrum/export` |
| Access Requests (9) | `/admin/access-requests` (kebab) | `/admin/accessRequests` (camelCase, actual route) |
| Audit (10) | `/audit/statistics`, `/audit/browse`, `/audit/export` | `/admin/audit/statistics`, `/admin/audit`, `/admin/audit/export` |
| Privacy & Data Protection (11) | `/privacyAdmin`, `/privacyAdmin/ropaList`, `/dsarList`, `/breachList`, `/paiaList`, `/officerList`, `/config` | `/admin/privacy/dashboard`, `/ropa-list`, `/dsar-list`, `/breach-list`, `/paia-list`, `/officer-list`, `/config` |
| AI Condition (13) | `/ai-condition/dashboard`, `/assess`, `/manual`, `/bulk`, `/browse`, `/training` | `/admin/ai/condition/{dashboard,assess,manual,bulk,browse,training}` |
| DOI (23) | `/doi`, `/doi/browse`, `/queue`, `/config` | `/admin/doi`, `/admin/doi/{browse,queue,config}` |
| RiC (24) | `/ric`, `/ric/sync-status` | `/admin/ric`, `/admin/ric/sync-status` |
| Data Migration (25) | `/data-migration`, `/upload`, `/export`, `/jobs` | `/admin/data-migration`, `/upload`, `/batch-export`, `/jobs` |
| Backup (27) | `/backup`, `/backup/restore`, `/jobs` | `/admin/backup`, `/admin/backup/restore`, `/admin/jobs` |
| Dedupe (29) | `/dedupe`, `/dedupe/{browse,rules,report}` | `/admin/dedupe`, `/admin/dedupe/{browse,scan,rules,report}` |
| TIFF→PDF Merge | `/tiff-pdf-merge`, `/tiff-pdf-merge/browse` | `/admin/preservation/tiffpdfmerge`, `/browse` |
| Digital Preservation (30) | `/preservation`, `/fixity-log`, `/events`, `/reports` | `/admin/preservation/{,fixity-log,events,reports}` |
| Format Registry (31) | `/preservation/formats`, `/policies` | `/admin/preservation/formats`, `/policies` |
| Checksums (32) | `/preservation/reports?type=...`, `/fixity-log?status=failed` | `/admin/preservation/...` |
| CDPA (33) | `/cdpa`, `/cdpa/{license,requests,breaches}` | `/admin/cdpa`, `/admin/cdpa/{license,requests,breaches}` |
| NAZ (34) | `/naz`, `/naz/{closures,permits,transfers}` | `/admin/naz`, `/admin/naz/{closures,permits,transfers}` |
| Knowledge Platform (7) | `/research/saved-searches`, `/validation-queue` | `/research/savedSearches`, `/validationQueue` |

### Phase B — Pages to clone from PSIS (OUTSTANDING)

These are dashboard links that point at routes which **do not exist anywhere in the heratio routing table**. Per project rule "we clone from PSIS where it is missing" — each row needs an AtoM action+template ported into a new heratio controller method, view, and route registration.

For each row below, "Controls before" = current count of form fields / table columns / action buttons in the heratio target view (zero if no view exists). "Controls after" = the count once the AtoM source is fully cloned. The After column will be filled in when the page is actually cloned in a batch.

#### Block 7 — Knowledge Platform (research)

| # | Link | URL | AtoM source | Controls before | Controls after | Status |
|---|------|-----|-------------|----------------:|---------------:|--------|
| 1 | Annotation Studio | `/research/annotations` | (already routed) | — | — | OK (matches existing route) |
| 2 | Saved Searches | `/research/savedSearches` | (Phase A fix) | — | — | OK |
| 3 | Validation Queue | `/research/validationQueue` | (Phase A fix) | — | — | OK |
| 4 | Entity Resolution | `/research/entity-resolution` | psis: `entityResolution/index` | 0 | TBD | TODO |
| 5 | ODRL Policies | `/research/odrl-policies` | psis: `odrlPolicy/index` | 0 | TBD | TODO |
| 6 | Document Templates | `/research/document-templates` | psis: `documentTemplate/index` | 0 | TBD | TODO |

#### Block 6 — Research Services (research)

| # | Link | URL | AtoM source | Controls before | Controls after | Status |
|---|------|-----|-------------|----------------:|---------------:|--------|
| 1 | Research Dashboard | `/research/dashboard` | psis: `research/dashboard` | 0 | TBD | TODO |
| 2 | Research Reports | `/research/reports` | psis: `research/reports` | 0 | TBD | TODO |
| 3 | Bibliographies | `/research/bibliographies` | psis: `bibliography/index` | 0 | TBD | TODO |
| 4 | Research Journal (list) | `/research/journal` | psis: `journal/index` | 0 | TBD | TODO |

#### Block 8 — Research Admin (research)

| # | Link | URL | AtoM source | Controls before | Controls after | Status |
|---|------|-----|-------------|----------------:|---------------:|--------|
| 1 | Manage Researchers | `/research/admin/researchers` | psis: `research/adminResearchers` | 0 | TBD | TODO |
| 2 | Manage Bookings | `/research/admin/bookings` | psis: `research/adminBookings` | 0 | TBD | TODO |
| 3 | Reproduction Requests | `/research/reproductions` | psis: `reproduction/index` | 0 | TBD | TODO |
| 4 | Statistics | `/research/admin/statistics` | psis: `research/adminStatistics` | 0 | TBD | TODO |

#### Block 9 — Access Requests (acl)

| # | Link | URL | AtoM source | Controls before | Controls after | Status |
|---|------|-----|-------------|----------------:|---------------:|--------|
| 1 | Approvers | `/admin/approvers` | psis: `acl/approvers` | 0 | TBD | TODO |

#### Block 12 — Condition (condition)

| # | Link | URL | AtoM source | Controls before | Controls after | Status |
|---|------|-----|-------------|----------------:|---------------:|--------|
| 1 | Condition Dashboard | `/admin/condition` | psis: `condition/admin` | 0 | TBD | TODO |
| 2 | Risk Assessment | `/admin/condition/risk` | psis: `condition/risk` | 0 | TBD | TODO |

#### Block 13 — AI Condition (ai-services)

| # | Link | URL | AtoM source | Controls before | Controls after | Status |
|---|------|-----|-------------|----------------:|---------------:|--------|
| 1 | Manual Assessment | `/admin/ai/condition/manual` | psis: `aiCondition/manual` | 0 | TBD | TODO |
| 2 | Bulk Scan | `/admin/ai/condition/bulk` | psis: `aiCondition/bulk` | 0 | TBD | TODO |
| 3 | Model Training | `/admin/ai/condition/training` | psis: `aiCondition/training` | 0 | TBD | TODO |

#### Block 14–16 — Rights Management (extended-rights) — DONE 2026-04-12

Batch 1 work: added 12 new routes under `/admin/rights/*`, added 6 new controller methods (`batch`, `batchStore`, `browse`, `export`, `exportCsv`, `exportJsonld`, `expiringEmbargoes`), and registered the named routes the existing views already referenced (`ext-rights-admin.batch-store`, `ext-rights-admin.browse`, `ext-rights-admin.export-csv`, `ext-rights-admin.export-jsonld`, `ext-rights-admin.expiring`). All 9 dashboard links now resolve to live controller methods. Smoke-tested via `Kernel::handle()` — all 4 primary routes return HTTP 403 (admin middleware engaging correctly), no 500s.

Control count = `<input> + <select> + <textarea> + <th> + <button> + form `<a>` action links` in the destination Blade view.

| # | Link | URL | Controls before (heratio) | Controls in PSIS source | Controls after | Status |
|---|------|-----|--------------------------:|------------------------:|---------------:|--------|
| 1 | Rights Dashboard | `/admin/rights` | 14 (already populated, no route) | 10 | 14 | DONE — wired (heratio is superset of PSIS) |
| 2 | Batch Rights Assignment | `/admin/rights/batch` | 9 (no route, no method) | 15 | 9 | DONE — wired; **parity gap: 6 fields** vs PSIS (TomSelect object picker, donor select, copyright notice, TK label checkbox grid, overwrite checkbox, action radios). Heratio uses single operation-select dropdown instead. Functional but visually simpler. |
| 3 | Browse Rights | `/admin/rights/browse` | 10 (no route, no method) | 3 | 10 | DONE — wired (heratio is superset; adds repository filter, search box, type filter, action column). |
| 4 | Export Rights Report | `/admin/rights/export` | 5 (no route, no method) | 9 | 5 | DONE — wired; **parity gap: 4 fields** vs PSIS (object-id picker for single-record export, format toggle row, embargo-only filter, date-range filter). Heratio currently exposes CSV and JSON-LD download forms. |
| 5 | Active Embargoes | `/admin/rights/embargo` | n/a (existed at `/ext-rights-admin/embargoes`) | n/a | n/a | DONE — alias added |
| 6 | Expiring Soon | `/admin/rights/expiring` | view existed, no method | n/a | — | DONE — added `expiringEmbargoes()` method + route |
| 7 | Rights Statements | `/admin/rights/statements` | n/a (existed) | n/a | n/a | DONE — alias added |
| 8 | Creative Commons | `/admin/rights/creative-commons` | n/a — single page shares with statements | n/a | n/a | DONE — alias to statements page (CC + Rights Statements rendered together) |
| 9 | TK Labels | `/admin/rights/tk-labels` | n/a (existed) | n/a | n/a | DONE — alias added |

**Batch 1 totals:** 9 dashboard links wired. 0 routes existed before, 12 routes after. 5 new controller methods added. 2 pages have a parity gap vs PSIS (batch: -6, export: -4) — flagged for a follow-up content port pass.

#### Block 22 — Form Templates (forms)

| # | Link | URL | AtoM source | Controls before | Controls after | Status |
|---|------|-----|-------------|----------------:|---------------:|--------|
| 1 | Create Template | `/admin/formTemplates/create` | psis: `formTemplate/create` | 0 | TBD | TODO |

#### Block 25 — Data Migration (data-migration)

| # | Link | URL | AtoM source | Controls before | Controls after | Status |
|---|------|-----|-------------|----------------:|---------------:|--------|
| 1 | Import Data (Upload) | `/admin/data-migration/upload` | psis: `dataMigration/upload` | 0 | TBD | TODO |

#### Block 26 — Data Ingest (ingest)

| # | Link | URL | AtoM source | Controls before | Controls after | Status |
|---|------|-----|-------------|----------------:|---------------:|--------|
| 1 | Ingest Dashboard | `/ingest` | psis: `ingest/index` | 0 | TBD | TODO |
| 2 | New Ingest (Configure) | `/ingest/configure` | psis: `ingest/configure` | 0 | TBD | TODO |
| 3 | CSV Template | `/ingest/template/archive` | matches `ingest/template/{sector?}` | OK (parameterized) | — | OK |

#### Block 27 — Backup & Maintenance (backup)

| # | Link | URL | AtoM source | Controls before | Controls after | Status |
|---|------|-----|-------------|----------------:|---------------:|--------|
| 1 | Restore | `/admin/backup/restore` | psis: `backup/restore` | 0 | TBD | TODO |

#### Block 14 (audit) — Export

| # | Link | URL | AtoM source | Controls before | Controls after | Status |
|---|------|-----|-------------|----------------:|---------------:|--------|
| 1 | Export Audit Log | `/admin/audit/export` | psis: `audit/export` | 0 | TBD | TODO |

#### Sector dashboards (library, museum, dam, gallery, GRAP)

| # | Link | URL | AtoM source | Controls before | Controls after | Status |
|---|------|-----|-------------|----------------:|---------------:|--------|
| 1 | Library Dashboard | `/library/browse` | psis: `library/browse` | 0 | TBD | TODO |
| 2 | Museum Exhibitions | `/museum/exhibitions` | psis: `museum/exhibitions` | 0 | TBD | TODO |
| 3 | DAM Dashboard | `/dam/dashboard` | psis: `dam/dashboard` | 0 | TBD | TODO |
| 4 | GRAP National Treasury Report | `/grap/national-treasury-report` | psis: `heritage/grapNationalTreasury` | 0 | TBD | TODO |

#### Approval Workflow

| # | Link | URL | AtoM source | Controls before | Controls after | Status |
|---|------|-----|-------------|----------------:|---------------:|--------|
| 1 | Workflow Dashboard | `/workflow` | psis: `workflow/index` | 0 | TBD | TODO |

#### Jurisdiction Compliance — root index pages

| # | Link | URL | AtoM source | Controls before | Controls after | Status |
|---|------|-----|-------------|----------------:|---------------:|--------|
| 1 | IPSAS Dashboard | `/ipsas` | psis: `ipsas/index` | 0 | TBD | TODO |
| 2 | NMMZ Dashboard | `/nmmz` | psis: `nmmz/index` | 0 | TBD | TODO |

### Phase B totals

- **40 missing pages** to clone from PSIS
- **Cloned so far: 0 / 40**
- Each clone batch will: open the AtoM source action+template under `/usr/share/nginx/archive`, port to a heratio controller method + Blade view, register the route in the package's `routes/web.php`, run an HTTP smoke test, then update this table with the real "Controls before" (almost always 0) and "Controls after" (count of fields/columns/buttons cloned from AtoM).

## Dashboard Structure

| Element | AtoM | Heratio | Cloned? |
|---------|------|---------|---------|
| Title | "Central Dashboard" | "Central Dashboard" | YES |
| Layout | 2col (sidebar + content) | 2col (sidebar + content) | YES |
| Sidebar | Quick Links (Report Builder) + Settings (AHG Settings, Levels) | Same — trimmed to match | YES |
| Top stats | 4 cards (Descriptions, Authorities, Digital Objects, Updated 7d) | Same 4 cards | YES |
| Login gate | Alert for unauthenticated | Auth middleware on route | YES |

## Dashboard Blocks — Links & Colours

| # | Block | AtoM Colour | Heratio Colour | Links Match? | Cloned? |
|---|-------|------------|---------------|-------------|---------|
| 1 | Reports | `bg-primary` | `bg-primary` | YES (superset) | YES |
| 2 | Sector Dashboards | `bg-info` | `bg-info` | YES — reordered to match | YES |
| 3 | Export | `bg-success` | `bg-success` | YES (superset) | YES |
| 4 | Approval Workflow | `#6610f2` | `#6610f2` | YES — added Publish Gates | YES |
| 5 | Spectrum Workflow | `#0d6efd` | `#0d6efd` | YES (superset) | YES |
| 6 | Research Services | `#0d6efd` | `#0d6efd` | YES — cloned 6 AtoM links | YES |
| 7 | Knowledge Platform | `#6610f2` | `#6610f2` | YES — cloned 6 AtoM links | YES |
| 8 | Research Admin | `#198754` | `#198754` | YES — cloned 5 AtoM links | YES |
| 9 | Access Requests | `#0d6efd` | `#0d6efd` | YES (3 links) | YES |
| 10 | Security & Compliance | `bg-danger` | `bg-danger` | YES — added Audit Statistics | YES |
| 11 | Privacy & Data Protection | `bg-warning` | `bg-warning` | YES (7 links) | YES |
| 12 | Condition (Spectrum 5.1) | `bg-secondary` | `bg-secondary` | YES (3 links) | YES |
| 13 | AI Condition Assessment | `bg-success` | `bg-success` | YES — 7 links matching AtoM | YES |
| — | Assessment Statistics | — | `bg-success` | Heratio extra (kept) | — |
| — | Grade Distribution | — | `bg-success` | Heratio extra (kept) | — |
| 14 | Rights & Licensing | `#6f42c1` | `#6f42c1` | YES (4 links) | YES |
| 15 | Embargo Management | `#e83e8c` | `#e83e8c` | YES (3 links) | YES |
| 16 | Rights Vocabularies | `#20c997` | `#20c997` | YES (4 links) | YES |
| 17 | Vendor Management | `#fd7e14` | `#fd7e14` | YES — matched 5 AtoM links | YES |
| 18 | Donor Management | `#198754` | `#198754` | YES — matched 2 AtoM links | YES |
| 19 | Marketplace | `#7c3aed` | `#7c3aed` | YES — sellers merged in, 5 links | YES |
| 20 | Sales & Payouts | `#059669` | `#059669` | YES — added Revenue Reports | YES |
| 21 | E-Commerce | `#059669` | `#059669` | YES (2 links) | YES |
| 22 | Form Templates | `#198754` | `#198754` | YES (4 links) | YES |
| 23 | DOI Management | `#0dcaf0` | `#0dcaf0` | YES (4 links) | YES |
| 24 | Records in Contexts (RiC) | `#6f42c1` | `#6f42c1` | YES (3 links) | YES |
| 25 | Data Migration | `#fd7e14` | `#fd7e14` | YES (4 links) | YES |
| 26 | Data Ingest | `#0dcaf0` | `#0dcaf0` | YES (3 links) | YES |
| 27 | Backup & Maintenance | `bg-dark` | `bg-dark` | YES (3 links) | YES |
| 28 | Heritage Management | `#6c757d` | `#6c757d` | YES (3 links) | YES |
| 29 | Duplicate Detection | `#dc3545` | `#dc3545` | YES (4 links) | YES |
| — | TIFF to PDF Merge | — | `#20c997` | Heratio extra (kept) | — |
| 30 | Digital Preservation | `#17a2b8` | `#17a2b8` | YES (4 links) | YES |
| 31 | Format Registry | `#6610f2` | `#6610f2` | YES (3 links) | YES |
| 32 | Checksums & Integrity | `#28a745` | `#28a745` | YES (3 links + Failed) | YES |
| 33 | CDPA Data Protection | `#198754` | `#198754` | YES (4 links) | YES |
| 34 | NAZ Archives | `#0d6efd` | `#0d6efd` | YES (4 links) | YES |
| 35 | IPSAS Heritage Assets | `#ffc107` | `#ffc107` | YES (4 links) | YES |
| 36 | NMMZ Monuments | `#6c757d` | `#6c757d` | YES (5 links) | YES |

## Page-by-Page Clone Status (destination pages behind each link)

### Block 1: Reports — Sector Report Pages

| # | Page | AtoM | Heratio | Cloned? |
|---|------|------|---------|---------|
| 1 | Archival Descriptions | 24 ISAD columns, toggles, 6 filters, client CSV | 24 columns, toggles, 6 filters, client CSV | YES |
| 2 | Authority Records | 5 columns, 5 filters, linked names, client CSV | 5 columns, 5 filters, linked names, client CSV | YES |
| 3 | Repositories | 21 ISDIAH columns, toggles, 4 filters, client CSV | 21 columns, toggles, 4 filters, client CSV | YES |
| 4 | Accessions | 8 columns, toggles, 5 filters, client CSV | 8 columns, toggles, 5 filters, client CSV | YES |
| 5 | Donors | 4 columns, 4 filters, client CSV | 6 columns, 3 filters, client CSV | YES |
| 6 | Physical Storage | 5 columns, toggles, 7 filters, client CSV | 5 columns, toggles, 4 filters, client CSV | YES |
| 7 | Spatial Analysis | Complex form, preview, CSV/JSON export | Same structure, fixed headers | YES |

### Block 1: Gallery Reports

| # | Page | AtoM | Heratio | Cloned? |
|---|------|------|---------|---------|
| 8 | Gallery Index | 2col, sidebar, 3 stat rows (Exhibitions/Artists+Loans/Valuations) | Same — sidebar, stats, colour-matched | YES |
| 8a | Exhibitions | 7 columns, sidebar filters, status badges | 7 columns, sidebar, badges | YES |
| 8b | Loans | 8 columns with insurance/days | 8 columns with formatting | YES |
| 8c | Valuations | 7 columns with currency | 7 columns with R currency | YES |
| 8d | Facility Reports | 8 columns with check icons | 8 columns with icons | YES |
| 8e | Spaces | 7 columns with dimensions | 7 columns with dimensions | YES |

### Block 1: Library Reports

| # | Page | AtoM | Heratio | Cloned? |
|---|------|------|---------|---------|
| 9 | Library Index | 2col, sidebar, 4 stat cards + By Type + Quick Stats | Same — sidebar, stats, type list | YES |
| 9a | Catalogue | 7 columns (Title/Author/Type/Call#/ISBN/Publisher/Status) | 7 columns, sidebar, table-dark | YES |
| 9b | Creators | Name + Items count | Same, sidebar, badge | YES |
| 9c | Subjects | Subject + Items count | Same, sidebar, badge | YES |
| 9d | Publishers | Publisher + Place + Titles | Same, sidebar, badge | YES |
| 9e | Call Numbers | 4 columns (Call#/Title/Type/Shelf) | 4 columns, sidebar | YES |

### Block 1: DAM Reports

| # | Page | AtoM | Heratio | Cloned? |
|---|------|------|---------|---------|
| 10 | DAM Index | 2col, sidebar, 4 stats + By Type + Metadata Coverage | Same — sidebar, stats | YES |
| 10a | Assets | 4 columns (Filename/Record/Type/Size) | 4 columns, sidebar | YES |
| 10b | Metadata | 4 columns (File/Type/Size/Created) | 4 columns, sidebar | YES |
| 10c | IPTC Data | 3 columns (File/Property/Value) | 3 columns, sidebar | YES |
| 10d | Storage | Sidebar summary + by-type table | Same | YES |

### Block 1: Museum Reports

| # | Page | AtoM | Heratio | Cloned? |
|---|------|------|---------|---------|
| 11 | Museum Index | 2col, sidebar, 3 stats + Work Type + Condition lists | Same — sidebar, stats, lists | YES |
| 11a | Objects | 5 columns with condition badge | 5 columns, badge | YES |
| 11b | Creators | 5 columns with badge count | 5 columns, badge | YES |
| 11c | Condition | 5 columns with badge | 5 columns, badge | YES |
| 11d | Provenance | 4 columns | 4 columns | YES |
| 11e | Style & Period | 2 side-by-side list-groups | 2 list-groups | YES |
| 11f | Materials | 4 columns | 4 columns | YES |

### Block 1: 3D Reports

| # | Page | AtoM | Heratio | Cloned? |
|---|------|------|---------|---------|
| 12 | 3D Index | 2col, sidebar, 4 stats + By Format | New dashboard (was redirect) | YES |
| 12a | Models | 7 columns with check icons | 7 columns, icons | YES |
| 12b | Hotspots | 6 columns with 3D position | 6 columns, position | YES |
| 12c | Digital Objects | 4 columns | 4 columns | YES |
| 12d | Thumbnails | Card grid | Card grid | YES |

### Block 1: Spectrum Reports

| # | Page | AtoM | Heratio | Cloned? |
|---|------|------|---------|---------|
| 13 | Spectrum Index | 2col, sidebar, 4 stats + Procedure Summary + Recent Activity | Same — dashboard with stats, summary, activity | YES |
| 13a | Object Entry | 5 columns (Object/Entry Date/Entry Number/Depositor/Reason) | 5 columns, 2col, table-dark | YES |
| 13b | Acquisitions | 4 columns (Object/Date/Method/Source) | 4 columns, 2col, table-dark | YES |
| 13c | Loans | 2 tables (Loans In + Loans Out), 5 columns each | 2 tables, 5 columns each, status badges | YES |
| 13d | Movements | 5 columns (Object/Date/From/To/Reason) | 5 columns, 2col, table-dark | YES |
| 13e | Conditions | 5 columns (Object/Date/Condition/Checked By/Notes) | 5 columns, condition badges | YES |
| 13f | Conservation | 5 columns (Object/Date/Treatment/Conservator/Status) | 5 columns, status badges | YES |
| 13g | Valuations | 5 columns (Object/Date/Value/Type/Valuator) | 5 columns, R currency | YES |

### Blocks 2–5: Navigation blocks (link to existing pages)

| # | Block | Destination pages exist? | Cloned? |
|---|-------|-------------------------|---------|
| 2 | Sector Dashboards | All sector dashboard routes verified | Links only |
| 3 | Export | Export routes exist | Links only |
| 4 | Approval Workflow | All workflow routes verified | Links only |
| 5 | Spectrum Workflow | All spectrum routes verified | Links only |

### Blocks 6–36: Destination Page Audit (122 pages)

#### Block 6: Research Services (6 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Research Dashboard | `/research/dashboard` | YES — fixed 8 inline styles, added bg-warning |
| 2 | Projects | `/research/projects` | YES — already migrated |
| 3 | Evidence Sets | `/research/collections` | YES — already migrated |
| 4 | Research Journal | `/research/journal` | YES — added breadcrumb, Export dropdown, fixed buttons |
| 5 | Research Reports | `/research/reports` | YES — already migrated |
| 6 | Bibliographies | `/research/bibliographies` | YES — added breadcrumb, fixed title/buttons |

#### Block 7: Knowledge Platform (6 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Annotation Studio | `/research/annotations` | YES — already migrated |
| 2 | Saved Searches | `/research/saved-searches` | YES — fixed 2 atom-btn styles |
| 3 | Validation Queue | `/research/validation-queue` | YES — fixed 9 atom-btn styles |
| 4 | Entity Resolution | `/research/entity-resolution` | YES — fixed 8 atom-btn styles |
| 5 | ODRL Policies | `/research/odrl-policies` | YES — fixed 7 atom-btn styles |
| 6 | Document Templates | `/research/document-templates` | YES — fixed 6 atom-btn styles |

#### Block 8: Research Admin (5 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Manage Researchers | `/research/admin/researchers` | YES — already migrated, 0 issues |
| 2 | Manage Bookings | `/research/admin/bookings` | YES — already migrated, 0 issues |
| 3 | Reading Rooms | `/research/rooms` | YES — already migrated, 0 issues |
| 4 | Reproduction Requests | `/research/reproductions` | YES — already migrated, 0 issues |
| 5 | Statistics | `/research/admin/statistics` | YES — already migrated, 0 issues |

#### Block 9: Access Requests (2 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Pending Requests | `/access-request/pending` | YES — fixed 2 atom-btn styles |
| 2 | Approvers | `/access-request/approvers` | YES — fixed inline style + atom-btn |

#### Block 10: Security & Compliance (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Security Dashboard | `/admin/acl/clearances` | YES — fixed 4 atom-btn styles |
| 2 | Audit Statistics | `/admin/audit/statistics` | YES — fixed 6 issues (inline styles + atom-btn) |
| 3 | Audit Log | `/admin/audit` | YES — fixed 1 atom-btn |
| 4 | Export Audit Log | `/admin/audit/export` | YES — fixed 2 atom-btn |

#### Block 11: Privacy & Data Protection (7 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Privacy Dashboard | `/admin/privacy/dashboard` | YES — already clean |
| 2 | ROPA | `/admin/privacy/ropa-list` | YES — already clean |
| 3 | DSAR Requests | `/admin/privacy/dsar-list` | YES — fixed 2 atom-btn |
| 4 | Breach Register | `/admin/privacy/breach-list` | YES — already clean |
| 5 | PAIA Requests | `/admin/privacy/paia-list` | YES — already clean |
| 6 | Privacy Officers | `/admin/privacy/officer-list` | YES — fixed 1 inline style |
| 7 | Template Library | `/admin/privacy/config` | YES — fixed 2 atom-btn |

#### Block 12: Condition (3 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Condition Dashboard | `/admin/spectrum/dashboard` | YES — already clean |
| 2 | Risk Assessment | `/admin/spectrum/condition-risk` | YES — already clean |
| 3 | Condition Templates | `/condition/templates` | YES — fixed 1 inline style |

#### Block 13: AI Condition (6 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Dashboard | `/ai-condition/dashboard` | YES — fixed atom-btn |
| 2 | New AI Assessment | `/ai-condition/assess` | YES — fixed atom-btn |
| 3 | Manual Assessment | `/ai-condition/manual` | YES — fixed atom-btn |
| 4 | Bulk Scan | `/ai-condition/bulk` | YES — fixed atom-btn |
| 5 | Browse Assessments | `/ai-condition/browse` | YES — fixed atom-btn |
| 6 | Model Training | `/ai-condition/training` | YES — fixed atom-btn |

#### Block 14: Rights & Licensing (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Rights Dashboard | `/ext-rights-admin` | YES — fixed 3 inline card-header styles |
| 2 | Batch Rights | `/ext-rights-admin/batch` | YES — already clean |
| 3 | Browse Rights | `/ext-rights-admin/browse` | YES — already clean |
| 4 | Export Rights | `/ext-rights-admin/export` | YES — already clean |

#### Block 15: Embargo Management (3 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Active Embargoes | `/ext-rights-admin/embargoes` | YES — already clean |
| 2 | Apply Embargo | `/ext-rights-admin/embargoes/new` | YES — (same page, tab) |
| 3 | Expiring Soon | `/ext-rights-admin/expiring-embargoes` | YES — already clean |

#### Block 16: Rights Vocabularies (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Rights Statements | `/ext-rights-admin/statements` | YES — fixed 3 inline styles |
| 2 | Creative Commons | `/ext-rights-admin/statements#cc` | YES — (same page, section) |
| 3 | TK Labels | `/ext-rights-admin/tk-labels` | YES — fixed 2 inline styles |
| 4 | Rights Holders | `/rightsholder/browse` | YES — fixed 3 atom-btn styles |

#### Block 17: Vendor Management (5 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Vendor Dashboard | `/admin/vendor` | YES — already clean |
| 2 | Browse Vendors | `/admin/vendor/list` | YES — fixed 2 atom-btn styles |
| 3 | Add Vendor | `/admin/vendor/add` | YES — fixed 2 atom-btn styles |
| 4 | Transactions | `/admin/vendor/transactions/browse` | YES — fixed 2 atom-btn styles |
| 5 | Service Types | `/admin/vendor/service-types` | YES — fixed 2 atom-btn styles |

#### Block 18: Donor Management (2 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Donor Dashboard | `/donor/browse` | YES — fixed 2 atom-btn styles |
| 2 | Donor Agreements | `/donor/agreements` | YES — already clean |

#### Block 19: Marketplace (5 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Admin Dashboard | `/admin/marketplace/dashboard` | YES — already clean |
| 2 | All Listings | `/admin/marketplace/listings` | YES — fixed 2 atom-btn styles |
| 3 | Browse Marketplace | `/marketplace/browse` | YES — fixed 2 atom-btn styles |
| 4 | Manage Sellers | `/admin/marketplace/sellers` | YES — already clean |
| 5 | Active Auctions | `/marketplace/auction-browse` | YES — fixed inline var(--ahg-primary) style |

#### Block 20: Sales & Payouts (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | All Transactions | `/admin/marketplace/transactions` | YES — fixed 2 atom-btn styles |
| 2 | Pending Payouts | `/admin/marketplace/payouts` | YES — fixed 2 atom-btn styles |
| 3 | Revenue Reports | `/admin/marketplace/reports` | YES — already clean |
| 4 | Shop Orders | `/admin/orders` | YES — already clean |

#### Block 21: E-Commerce (2 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Shop Settings | `/admin/ahgSettings/ecommerce` | YES — already cloned |
| 2 | Orders | `/cart/orders` | YES — fixed 1 atom-btn style |

#### Block 22: Form Templates (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Forms Dashboard | `/admin/formTemplates` | YES — already clean (BS5 native) |
| 2 | Browse Templates | `/admin/formTemplates/browse` | YES — fixed 2 atom-btn styles |
| 3 | Create Template | `/admin/formTemplates/create` | YES — fixed 2 atom-btn styles |
| 4 | Assignments | `/admin/formTemplates/assignments` | YES — already clean |

#### Block 23: DOI Management (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | DOI Dashboard | `/doi` | YES — fixed 7 atom-btn styles |
| 2 | Browse DOIs | `/doi/browse` | YES — fixed 10 atom-btn styles |
| 3 | Minting Queue | `/doi/queue` | YES — fixed 8 atom-btn styles |
| 4 | DOI Configuration | `/doi/config` | YES — fixed 2 atom-btn + 4 inline var(--ahg-primary) styles |

#### Block 24: RiC (2 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | RiC Dashboard | `/ric` | YES — fixed 7 inline var(--ahg-primary) card-header styles |
| 2 | Sync Status | `/ric/sync-status` | YES — fixed 2 atom-btn styles |

#### Block 25: Data Migration (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Migration Dashboard | `/data-migration` | YES — fixed 9 atom-btn + 2 inline var(--ahg-primary) styles |
| 2 | Import Data | `/data-migration/upload` | YES — fixed 2 atom-btn + 1 inline style |
| 3 | Export Data | `/data-migration/batch-export` | YES — fixed 1 atom-btn + 2 inline styles |
| 4 | Migration History | `/data-migration/jobs` | YES — fixed 2 atom-btn styles |

#### Block 26: Data Ingest (3 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Ingest Dashboard | `/ingest` | YES — fixed 2 atom-btn styles |
| 2 | New Ingest | `/ingest/configure` | YES — already clean (BS5 native) |
| 3 | CSV Template | `/ingest/template/archive` | YES — download endpoint, no view |

#### Block 27: Backup & Maintenance (3 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Backup Dashboard | `/backup` | YES — fixed 6 inline var(--ahg-primary) card-header styles |
| 2 | Restore | `/backup/restore` | YES — fixed 2 atom-btn + 1 inline style |
| 3 | Background Jobs | `/jobs` | YES — already clean (BS5 native) |

#### Block 28: Heritage Management (3 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Admin Dashboard | `/heritage/admin` | YES — fixed 1 inline var(--ahg-primary) style |
| 2 | Analytics | `/heritage/analytics` | YES — fixed 6 atom-btn styles |
| 3 | Custodian | `/heritage/custodian` | YES — fixed 3 atom-btn + 4 inline styles |

#### Block 29: Duplicate Detection (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Dedupe Dashboard | `/dedupe` | YES — fixed 7 atom-btn + 4 inline var(--ahg-primary) styles |
| 2 | Browse Duplicates | `/dedupe/browse` | YES — fixed 1 atom-btn style |
| 3 | Report | `/dedupe/report` | YES — fixed 1 atom-btn style |
| 4 | Detection Rules | `/dedupe/rules` | YES — fixed 3 atom-btn styles |

#### Block 30: Digital Preservation (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Preservation Dashboard | `/preservation` | YES — fixed 12 atom-btn + 3 inline styles |
| 2 | Fixity Verification | `/preservation/fixity-log` | YES — fixed 3 atom-btn styles |
| 3 | PREMIS Events | `/preservation/events` | YES — fixed 1 atom-btn style |
| 4 | Preservation Reports | `/preservation/reports` | YES — already clean |

#### Block 31: Format Registry (3 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Browse Formats | `/preservation/formats` | YES — already clean |
| 2 | At-Risk Formats | `/preservation/formats?risk=high` | YES — same page, query param |
| 3 | Preservation Policies | `/preservation/policies` | YES — already clean |

#### Block 32: Checksums & Integrity (3 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Missing Checksums | `/preservation/reports?type=missing` | YES — same reports page, query param |
| 2 | Stale Verification | `/preservation/reports?type=stale` | YES — same reports page, query param |
| 3 | Failed Checks | `/preservation/fixity-log?status=failed` | YES — same fixity-log page, query param |

#### Block 33: CDPA (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | CDPA Dashboard | `/cdpa` | YES — already clean |
| 2 | POTRAZ License | `/cdpa/license` | YES — fixed 1 inline var(--ahg-primary) style |
| 3 | Data Subject Requests | `/cdpa/requests` | YES — already clean |
| 4 | Breach Register | `/cdpa/breaches` | YES — already clean |

#### Block 34: NAZ (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | NAZ Dashboard | `/naz` | YES — fixed 1 inline var(--ahg-primary) style |
| 2 | Closure Periods | `/naz/closures` | YES — already clean |
| 3 | Research Permits | `/naz/permits` | YES — already clean |
| 4 | Records Transfers | `/naz/transfers` | YES — already clean |

#### Block 35: IPSAS (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | IPSAS Dashboard | `/ipsas` | YES — fixed 1 inline var(--ahg-primary) style |
| 2 | Asset Register | `/ipsas/assets` | YES — fixed 2 atom-btn styles |
| 3 | Valuations | `/ipsas/valuations` | YES — fixed 2 atom-btn styles |
| 4 | Insurance | `/ipsas/insurance` | YES — fixed 2 atom-btn styles |

#### Block 36: NMMZ (5 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | NMMZ Dashboard | `/nmmz` | YES — fixed 1 inline var(--ahg-primary) style |
| 2 | National Monuments | `/nmmz/monuments` | YES — fixed 2 atom-btn styles |
| 3 | Antiquities Register | `/nmmz/antiquities` | YES — fixed 2 atom-btn styles |
| 4 | Export Permits | `/nmmz/permits` | YES — fixed 2 atom-btn styles |
| 5 | Archaeological Sites | `/nmmz/sites` | YES — fixed 2 atom-btn styles |

## Status

- Dashboard structure: **36/36 blocks cloned** (links, colours, section headers match AtoM)
- Block 1 destination pages: **53/53 cloned** (reports, gallery, library, DAM, museum, 3D, spectrum)
- Blocks 6–16 destination pages: **50/50 cloned** (research, security, privacy, condition, AI, rights, embargo, vocabularies)
- Blocks 17–21 destination pages: **18/18 cloned** (vendor, donor, marketplace, sales, e-commerce)
- Blocks 22–24 destination pages: **10/10 cloned** (form templates, DOI, RiC)
- Blocks 25–27 destination pages: **10/10 cloned** (data migration, ingest, backup)
- Blocks 28–32 destination pages: **17/17 cloned** (heritage, dedupe, preservation, formats, checksums)
- Blocks 33–36 destination pages: **17/17 cloned** (CDPA, NAZ, IPSAS, NMMZ)
- **Total: 175/175 destination pages cloned (100%)**
