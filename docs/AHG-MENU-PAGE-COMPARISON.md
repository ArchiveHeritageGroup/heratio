# AHG Plugins Menu — Page-by-Page Comparison (AtoM vs Heratio)

Generated: 2026-03-17

---

## Legend

| Symbol | Meaning |
|--------|---------|
| ✓ | Implemented in Heratio |
| ⚠ | Partially implemented |
| ✗ | Missing from Heratio |
| DEAD LINK | Menu item exists but no route/controller/view |

---

## 1. Settings

### 1.1 AHG Settings

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/ahgSettings` | `/admin/settings` | ✓ |
| Plugin-aware sections (30+ plugins) | ✓ | ✗ | ✗ |
| ~50 setting section tiles | ✓ | ⚠ Scopes + ahg_settings groups | ⚠ |
| Setting scopes (Global, Templates, Labels, etc.) | ✓ | ✓ | ✓ |
| AHG setting groups (Accession, AI, Email, etc.) | ✓ | ✓ | ✓ |
| Dedicated pages (CSV Validator, Themes, etc.) | ✓ | ✓ (partial list) | ⚠ |
| E-Commerce / Marketplace sections | ✓ | ✗ | ✗ |
| Semantic Search section | ✓ | ✗ | ✗ |
| Plugin Management | ✓ | ✗ | ✗ |

**AtoM source:** `atom-ahg-plugins/ahgSettingsPlugin/modules/ahgSettings/`
**Heratio:** `packages/ahg-settings/`

---

### 1.2 Dropdown Manager

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/dropdowns` | `/admin/dropdowns` | DEAD LINK |
| Index: section sidebar with badges | ✓ | ✗ | ✗ |
| Index: accordion taxonomy list | ✓ | ✗ | ✗ |
| Index: search/filter taxonomies | ✓ | ✗ | ✗ |
| Index: create/rename/move/delete taxonomy | ✓ | ✗ | ✗ |
| Edit: drag-to-reorder terms (Sortable.js) | ✓ | ✗ | ✗ |
| Edit: inline label/color editing | ✓ | ✗ | ✗ |
| Edit: default term radio button | ✓ | ✗ | ✗ |
| Edit: active/inactive toggle | ✓ | ✗ | ✗ |
| Edit: add term modal | ✓ | ✗ | ✗ |
| 20+ predefined section categories | ✓ | ✗ | ✗ |

**Status: NOT IMPLEMENTED — no route, controller, or views exist**

**AtoM source:** `atom-ahg-plugins/ahgSettingsPlugin/modules/ahgDropdown/`

---

## 2. Security

### 2.1 Clearances

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/userClearance` | `/admin/acl/clearances` | ✓ |
| List users + clearances table | ✓ | ✓ | ✓ |
| Clearance level badge with color | ✓ | ✓ | ✓ |
| Granted date | ✓ | ✓ | ✓ |
| Expiry date with warning colors (≤7d red, ≤30d warning) | ✓ | ⚠ (basic, red if expired) | ⚠ |
| 2FA status badge | ✓ | ✗ | ✗ |
| Renewal status ("Renewal Pending" / "Active") | ✓ | ✗ | ✗ |
| Grant new clearance modal (full form) | ✓ | ⚠ (simple card form) | ⚠ |
| Vetting reference / date / authority fields | ✓ | ✗ | ✗ |
| Notes field | ✓ | ✗ | ✗ |
| Detail page (single user clearance) | ✓ | ✗ | ✗ |
| Revoke clearance action | ✓ | ✗ | ✗ |
| Compartment access grants | ✓ | ✗ | ✗ |
| Clearance history/audit trail | ✓ | ✗ | ✗ |
| Renewal approval form | ✓ | ✗ | ✗ |

**~40% feature-complete**

**AtoM source:** `atom-ahg-plugins/ahgSecurityClearancePlugin/modules/securityClearance/`
**Heratio:** `packages/ahg-acl/`

---

## 3. Research

### 3.1 Dashboard

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/research` | `/research/admin` | ✓ |
| Conditional status alerts (guest/pending/expired/rejected/approved) | ✓ | ✓ | ✓ |
| Quick action buttons | ✓ | ✓ | ✓ |
| Knowledge platform tools cards | ✓ | ✓ | ✓ |
| Pending researchers list (admin view) | ✓ | ⚠ | ⚠ |
| Today's bookings at a glance | ✓ | ⚠ | ⚠ |

**Heratio:** `packages/ahg-research/`

---

### 3.2 Researchers

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/research/researchers` | `/research/researchers` | ✓ |
| Status filter | ✓ (dropdown) | ✓ (tabs with badges) | ✓ improved |
| Search box | ✓ | ✓ | ✓ |
| Table: Name, Email, Institution, Status, Date, Actions | ✓ | ✓ | ✓ |
| Inline approve button (pending) | ✗ | ✓ | ✓ enhanced |
| Expired status tab | ✗ | ✓ | ✓ enhanced |

**100%+ (Heratio has more features)**

---

### 3.3 Bookings

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/research/bookings` | `/research/bookings` | ✓ |
| Pending section with table | ✓ | ✓ | ✓ |
| Upcoming confirmed section | ✓ | ✓ | ✓ |
| Check-in status column | ✓ | ✗ | ✗ |
| Check-in / Check-out buttons | ✓ | ✗ | ✗ |
| "Today" row highlighting + badge | ✓ | ✗ | ✗ |
| Cancel button for pending | ✗ | ✓ | ✓ enhanced |

---

### 3.4 Rooms

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/readingRoom` | `/research/rooms` | ✓ |
| List display | ✓ (card grid) | ✓ (table) | ⚠ layout differs |
| Add room button | ✓ | ✓ | ✓ |
| Room form: all fields | ✓ | ✓ | ✓ |
| Section headers & grouped layout | ✓ | ✗ | ✗ |
| Helper text for booking policy | ✓ | ✗ | ✗ |
| IIIF collaboration rooms | ✓ | ✗ | ✗ |

---

## 4. Researcher Submissions

**ENTIRE SECTION MISSING FROM HERATIO**

No `ahg-researcher-manage` package exists.

### 4.1 Dashboard (`/researcher/dashboard`)

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| 6 stat cards (total, draft, pending, approved, published, returned+rejected) | ✓ | ✗ | DEAD LINK |
| Recent submissions table | ✓ | ✗ | DEAD LINK |
| Research integration (profile, projects, collections, notes) | ✓ | ✗ | DEAD LINK |
| New Submission / Import Exchange buttons | ✓ | ✗ | DEAD LINK |

### 4.2 Pending Review (`/researcher/pending`)

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Status filter buttons (8 statuses) | ✓ | ✗ | DEAD LINK |
| Submissions table (ID, Title, Researcher, Source, Items, Files, Status, Dates) | ✓ | ✗ | DEAD LINK |

### 4.3 Import Exchange (`/researcher/import`)

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| JSON file upload with preview | ✓ | ✗ | DEAD LINK |
| Target repository dropdown | ✓ | ✗ | DEAD LINK |
| Supported collection types info | ✓ | ✗ | DEAD LINK |
| Import result stats display | ✓ | ✗ | DEAD LINK |

**Additional AtoM pages not in menu:**
- View/Edit submission, Add/Edit/Delete item, Submit/Resubmit, Publish
- File upload/delete API, Autocomplete API
- Create from research collection

**DB tables needed:** `researcher_submission`, `researcher_submission_item`, `researcher_submission_file`, `researcher_submission_review`

**AtoM source:** `atom-ahg-plugins/ahgResearcherPlugin/modules/researcher/`

---

## 5. Access

### 5.1 Requests

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/accessRequests` | `/admin/acl/access-requests` | ✓ |
| Stats cards (Pending, Approved Today, Denied Today, This Month) | ✓ | ✗ | ✗ |
| Status filter tabs | ✓ | ✓ | ✓ |
| Requests table | ✓ | ✓ | ✓ |
| Urgency/priority badges | ✓ | ✓ | ✓ |
| Row highlighting by urgency | ✓ | ✗ | ✗ |
| Single request detail page | ✓ | ✗ | ✗ |
| Approve form with notes + expiration date | ✓ | ⚠ (notes only, in modal) | ⚠ |
| Deny form with reason | ✓ | ✓ (in modal) | ✓ |
| Activity/audit log for request | ✓ | ✗ | ✗ |
| User-facing: request clearance form | ✓ | ✗ | ✗ |
| User-facing: request object access | ✓ | ✗ | ✗ |
| User-facing: My Requests dashboard | ✓ | ✗ | ✗ |
| Request cancellation | ✓ | ✗ | ✗ |

**AtoM source:** `atom-ahg-plugins/ahgSecurityClearancePlugin/modules/accessRequest/`
**Heratio:** `packages/ahg-acl/`

### 5.2 Approvers

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/accessApprovers` | `/admin/accessApprovers` | DEAD LINK |
| Current approvers table (User, Clearance, Can Approve range, Email Notify) | ✓ | ✗ | ✗ |
| Remove approver button | ✓ | ✗ | ✗ |
| Add approver form (User, Min/Max Level, Email Notifications) | ✓ | ✗ | ✗ |

**NOT IMPLEMENTED**

---

## 6. Audit

### 6.1 Statistics

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/auditStatistics` | `/admin/audit` (browse only) | ⚠ |
| Time period selector (7/30/90 days) | ✓ | ✗ | ✗ |
| Summary cards (Total, Created, Updated, Deleted) | ✓ | ✗ | ✗ |
| Most active users table | ✓ | ✗ | ✗ |
| Recent failed actions table | ✓ | ✗ | ✗ |

**Heratio has browse/list only, no statistics dashboard**

**AtoM source:** `atom-ahg-plugins/ahgAuditTrailPlugin/modules/auditTrail/`
**Heratio:** `packages/ahg-audit-trail/`

### 6.2 Logs

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/auditLog` | `/admin/acl/audit-log` | ⚠ |
| Statistics cards (5) + Export CSV | ✓ | ✗ | ✗ |
| Filters (table, action, date range, search) | ✓ | ✗ | ✗ |
| Audit log table with changes preview | ✓ | ⚠ (simple table) | ⚠ |
| Detail view with before/after JSON comparison | ✓ | ✗ | ✗ |
| Pagination | ✓ | ⚠ (limit selector only) | ⚠ |

### 6.3 Settings

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/auditSettings` | — | DEAD LINK |
| Enable audit logging toggle | ✓ | ✗ | ✗ |
| Log types (views, searches, downloads, API, auth, classified) | ✓ | ✗ | ✗ |
| Privacy settings (mask data, anonymize IPs) | ✓ | ✗ | ✗ |

**NOT IMPLEMENTED**

### 6.4 Error Log

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/errorLog` | `/admin/errorLog` | ⚠ ORPHANED |
| Stats cards (Open, Resolved, Today, Unread) | ✓ | ✓ (in view) | ⚠ |
| Filters (status, level, search) | ✓ | ✓ (in view) | ⚠ |
| Error table with details | ✓ | ✓ (in view) | ⚠ |
| Resolve/Reopen/Delete actions | ✓ | ✓ (in view) | ⚠ |
| Collapsible stack trace | ✓ | ✓ (in view) | ⚠ |
| **Laravel controller + route** | — | ✗ **VIEW EXISTS BUT NO ROUTE** | ⚠ |

**View exists at `packages/ahg-settings/resources/views/errorLog.blade.php` but no controller or route wires it up.**

---

## 7. RiC

### 7.1 RiC Dashboard

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/ricDashboard/index` | — | DEAD LINK |
| Fuseki status card | ✓ | ✗ | ✗ |
| Queue count card | ✓ | ✗ | ✗ |
| Orphaned triples card | ✓ | ✗ | ✗ |
| Record activity chart (7-day) | ✓ | ✗ | ✗ |
| Operations by type (doughnut) | ✓ | ✗ | ✗ |
| Entity sync status table | ✓ | ✗ | ✗ |
| Recent operations table | ✓ | ✗ | ✗ |
| Quick actions (Sync, Integrity Check, Cleanup) | ✓ | ✗ | ✗ |
| Sub-pages: Queue, Orphans, Logs, Config | ✓ | ✗ | ✗ |

**NOT IMPLEMENTED — no package exists**

**AtoM source:** `atom-ahg-plugins/ahgRicExplorerPlugin/`
**DB tables:** `ric_sync_status`, `ric_sync_queue`, `ric_orphan_tracking`, `ric_sync_log`

---

## 8. Data Quality

### 8.1 Data Migration

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/dataMigration` | `/admin/data-migration` | ✓ |
| Dashboard with saved mappings | ✓ | ✓ | ✓ |
| Upload page | ✓ | ✓ | ✓ |
| Field mapping UI | ✓ | ✓ | ✓ |
| Preview page | ✓ | ✓ | ✓ |
| Execute migration | ✓ | ✓ | ✓ |
| Jobs list + job status | ✓ | ✓ | ✓ |
| Batch export | ✓ | ✓ | ✓ |
| Import results | ✓ | ✓ | ✓ |
| Multi-format support (CSV, Excel, XML, JSON, OPEX, PAX, ZIP) | ✓ | ⚠ (CSV/XML only) | ⚠ |
| Excel sheet detection | ✓ | ✗ | ✗ |
| Delimiter auto-detection | ✓ | ✗ | ✗ |
| Encoding selection | ✓ | ✗ | ✗ |
| 4-step wizard UI | ✓ | ⚠ (simplified) | ⚠ |
| Source format presets (Preservica, ArchivesSpace, EMu, etc.) | ✓ | ✗ | ✗ |
| EAD/CSV export actions | ✓ | ✗ | ✗ |

**AtoM source:** `atom-ahg-plugins/ahgDataMigrationPlugin/`
**Heratio:** `packages/ahg-data-migration/`

### 8.2 Duplicate Detection

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/dedupe` | — | DEAD LINK |
| Dashboard (6 stat cards) | ✓ | ✗ | ✗ |
| Browse duplicates with filters | ✓ | ✗ | ✗ |
| Side-by-side comparison view | ✓ | ✗ | ✗ |
| Merge records with field selection | ✓ | ✗ | ✗ |
| Dismiss false positives | ✓ | ✗ | ✗ |
| Detection rules management (CRUD) | ✓ | ✗ | ✗ |
| Scan management (per-repository) | ✓ | ✗ | ✗ |
| Reports (monthly stats, clusters, efficiency) | ✓ | ✗ | ✗ |
| API: realtime duplicate check | ✓ | ✗ | ✗ |

**NOT IMPLEMENTED — no package exists**

**AtoM source:** `atom-ahg-plugins/ahgDedupePlugin/`
**DB tables:** `ahg_duplicate_detection`, `ahg_duplicate_rule`, `ahg_dedupe_scan`

---

## 9. Data Entry

### 9.1 Form Templates

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/formTemplates` | — | DEAD LINK |

**NOT IMPLEMENTED — no dedicated plugin found in AtoM either. Likely a placeholder/future feature.**

---

## 10. DOI Management

**ENTIRE SECTION MISSING FROM HERATIO**

No `ahg-doi-manage` package exists.

### 10.1 DOI Dashboard

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/doi` | — | DEAD LINK |
| Statistics cards | ✓ | ✗ | ✗ |
| Recent DOIs table | ✓ | ✗ | ✗ |
| Quick links | ✓ | ✗ | ✗ |

### 10.2 Minting Queue

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/doi/queue` | — | DEAD LINK |
| Queue status summary | ✓ | ✗ | ✗ |
| Queue items table | ✓ | ✗ | ✗ |
| Retry failed items | ✓ | ✗ | ✗ |

**Additional AtoM pages (not in menu):**
- Browse DOIs, View single DOI, Mint (single), Batch Mint
- Configuration (DataCite credentials), Sync, Reports
- Export (CSV/JSON), Deactivate/Reactivate, Verify resolution
- API endpoints: mint, status

**AtoM source:** `atom-ahg-plugins/ahgDoiPlugin/` (24 actions, 11 templates)
**DB tables:** `ahg_doi`, `ahg_doi_queue`, `ahg_doi_log`

---

## 11. Heritage

**ENTIRE SECTION MISSING FROM HERATIO**

No `ahg-heritage-manage` package exists.

### 11.1 Admin

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/heritage/admin` | — | DEAD LINK |
| Admin dashboard with stats + sidebar | ✓ | ✗ | ✗ |
| Landing page config | ✓ | ✗ | ✗ |
| Feature toggles | ✓ | ✗ | ✗ |
| Branding/theming settings | ✓ | ✗ | ✗ |
| User management | ✓ | ✗ | ✗ |
| Hero carousel slides CRUD | ✓ | ✗ | ✗ |
| Featured collections CRUD | ✓ | ✗ | ✗ |
| Access requests | ✓ | ✗ | ✗ |
| Embargo management | ✓ | ✗ | ✗ |
| POPIA compliance flags | ✓ | ✗ | ✗ |

### 11.2 Analytics

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/heritage/analytics` | — | DEAD LINK |
| Analytics dashboard (7/30/90 day selector) | ✓ | ✗ | ✗ |
| Page views, searches, downloads, visitors stats | ✓ | ✗ | ✗ |
| Search performance (avg results, zero-result rate, CTR) | ✓ | ✗ | ✗ |
| Sub-pages: Search Insights, Content Analytics, Alerts | ✓ | ✗ | ✗ |

### 11.3 Custodian

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/heritage/custodian` | — | DEAD LINK |
| Custodian dashboard (batch stats, activity, top contributors) | ✓ | ✗ | ✗ |
| Single item custodian view | ✓ | ✗ | ✗ |
| Batch operations interface | ✓ | ✗ | ✗ |
| Audit trail/change history | ✓ | ✗ | ✗ |

**AtoM source:** `atom-ahg-plugins/ahgHeritagePlugin/`

---

## 12. Maintenance

### 12.1 Backup

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/backup` | `/admin/preservation/backup` | ⚠ |
| Dashboard: DB info, storage info, quick actions | ✓ | ✗ (replication targets only) | ⚠ |
| Create backup (manual/incremental) | ✓ | ✗ | ✗ |
| Upload backup | ✓ | ✗ | ✗ |
| Backup list with download/delete | ✓ | ✗ | ✗ |
| Schedule management (CRUD) | ✓ | ✗ | ✗ |
| Settings (paths, retention, components) | ✓ | ✗ | ✗ |

**Heratio has replication dashboard only (~20% parity)**

**AtoM source:** `atom-ahg-plugins/ahgBackupPlugin/`
**Heratio:** `packages/ahg-preservation/`

### 12.2 Restore

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/admin/restore` | — | DEAD LINK |
| Restore from local backup | ✓ | ✗ | ✗ |
| Restore from uploaded backup | ✓ | ✗ | ✗ |
| Component selection | ✓ | ✗ | ✗ |
| Progress tracking | ✓ | ✗ | ✗ |

**NOT IMPLEMENTED**

### 12.3 Jobs

| Feature | AtoM | Heratio | Status |
|---------|------|---------|--------|
| Route | `/jobs/browse` | `/admin/jobs` | ✓ |
| Stats cards (total, completed, error, running) | ⚠ (via pager) | ✓ (dedicated cards) | ✓ improved |
| Filter buttons (status) | ✓ (all/active/failed) | ✓ (all/completed/error/running) | ✓ |
| Jobs table | ✓ | ✓ | ✓ |
| Job detail view | ✓ | ✓ | ✓ |
| Auto-refresh toggle | ✓ | ✗ | ✗ |
| Export history CSV | ✓ | ✗ | ✗ |
| Clear inactive jobs | ✓ | ✗ | ✗ |

**~85% parity**

**Heratio:** `packages/ahg-jobs-manage/`

---

## Summary: Implementation Status by Section

| # | Section | Items | Implemented | Partial | Missing | Dead Links |
|---|---------|-------|-------------|---------|---------|------------|
| 1 | Settings | 2 | 1 | 0 | 1 | 1 (Dropdown Manager) |
| 2 | Security | 1 | 0 | 1 | 0 | 0 |
| 3 | Research | 4 | 2 | 2 | 0 | 0 |
| 4 | Researcher Submissions | 3 | 0 | 0 | 3 | 3 |
| 5 | Access | 2 | 0 | 1 | 1 | 1 (Approvers) |
| 6 | Audit | 4 | 0 | 2 | 2 | 1 (Settings) |
| 7 | RiC | 1 | 0 | 0 | 1 | 1 |
| 8 | Data Quality | 2 | 1 | 0 | 1 | 1 (Dedupe) |
| 9 | Data Entry | 1 | 0 | 0 | 1 | 1 |
| 10 | DOI Management | 2 | 0 | 0 | 2 | 2 |
| 11 | Heritage | 3 | 0 | 0 | 3 | 3 |
| 12 | Maintenance | 3 | 1 | 1 | 1 | 1 (Restore) |
| | **TOTAL** | **28** | **5** | **7** | **16** | **15** |

---

## New Packages Needed

| Package | For Section | Est. Actions | Est. Views |
|---------|------------|-------------|------------|
| `ahg-dropdown-manage` | Dropdown Manager | 8 | 2 |
| `ahg-researcher-manage` | Researcher Submissions | 14+ | 11+ |
| `ahg-ric` | RiC Dashboard | 10+ | 6+ |
| `ahg-dedupe` | Duplicate Detection | 12+ | 8+ |
| `ahg-doi-manage` | DOI Management | 18+ | 11+ |
| `ahg-heritage-manage` | Heritage (Admin/Analytics/Custodian) | 18+ | 15+ |
| `ahg-backup` | Backup & Restore | 15+ | 5+ |

## Existing Packages Needing Enhancement

| Package | Missing Features |
|---------|-----------------|
| `ahg-settings` | Plugin-aware sections, error log route/controller |
| `ahg-acl` | Clearance detail page, revoke, vetting fields, approvers page, request detail, user-facing pages |
| `ahg-research` | Bookings check-in/out, rooms IIIF support |
| `ahg-audit-trail` | Statistics dashboard, settings page |
| `ahg-data-migration` | Multi-format support, Excel sheets, encoding, source presets |
| `ahg-jobs-manage` | Auto-refresh, export CSV, clear inactive |
| `ahg-preservation` | Full backup/restore UI (currently replication only) |
