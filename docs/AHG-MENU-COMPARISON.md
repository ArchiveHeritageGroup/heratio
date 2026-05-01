# AHG Plugins Menu - AtoM vs Heratio Comparison

Generated: 2026-03-17
**Resolved: 2026-04-12 - menu at `packages/ahg-theme-b5/resources/views/partials/menus/ahg-admin-menu.blade.php` is fully compliant. All 13 missing items added, all 14 extras removed, all 5 badge counts wired to real DB queries. All 23 route names + 5 URL paths verified via `php artisan route:list`. See `docs/heratio-vs-psis-outstanding-plan.md` Group 1 for checklist.**

## Action Required - HISTORICAL

This section is preserved for audit trail. All items have been implemented.

- **ADD** all MISSING items to Heratio → DONE
- **REMOVE** all EXTRA items from Heratio → DONE
- Badge counts on Research/DOI items need real DB queries → DONE (pendingResearchers, pendingBookings, pendingReview, pendingDuplicates, pendingDoi - all wired at top of menu partial)

---

## Full Comparison Table

| # | Section | AtoM Item | AtoM URL | Heratio Item | Heratio URL | Status |
|---|---------|-----------|----------|-------------|-------------|--------|
| | **Settings** | | | | | |
| 1 | | AHG Settings | /admin/ahgSettings | Settings | /admin/settings | MATCH (rename to "AHG Settings") |
| 2 | | Dropdown Manager | /admin/dropdowns | Dropdown Manager | /admin/dropdowns | MATCH |
| | | | | | | |
| | **Security** | | | | | |
| 3 | | Clearances | /admin/userClearance | Clearances | route('acl.clearances') | MATCH |
| | | | | | | |
| | **Research** | | | | | |
| 4 | | Dashboard | /admin/research | Research Management | /research/admin | MATCH (rename to "Dashboard") |
| 5 | | Researchers (badge) | /research/researchers | - | - | **MISSING** - add with pending count badge |
| 6 | | Bookings (badge) | /research/bookings | - | - | **MISSING** - add with pending count badge |
| 7 | | Rooms | /admin/readingRoom | Reading Rooms | /research/rooms | MATCH (rename to "Rooms") |
| | | | | | | |
| | **Researcher Submissions** | | | **MISSING SECTION** | | |
| 8 | | Dashboard | /researcher/dashboard | - | - | **MISSING** |
| 9 | | Pending Review (badge) | /researcher/pending | - | - | **MISSING** - add with pending count badge |
| 10 | | Import Exchange | /researcher/import | - | - | **MISSING** |
| | | | | | | |
| | **Access** | | | | | |
| 11 | | Requests | /admin/accessRequests | - | - | **MISSING** (currently in Security section - move here) |
| 12 | | Approvers | /admin/accessApprovers | - | - | **MISSING** |
| | | | | | | |
| | **Audit** | | | | | |
| 13 | | Statistics | /admin/auditStatistics | Audit Log | route('audit.browse') | PARTIAL (rename to "Statistics") |
| 14 | | Logs | /admin/auditLog | Security Audit Log | route('acl.audit-log') | PARTIAL (rename to "Logs") |
| 15 | | Settings | /admin/auditSettings | - | - | **MISSING** |
| 16 | | Error Log | /admin/errorLog | - | - | **MISSING** |
| | | | | | | |
| | **RiC** | | | **MISSING SECTION** | | |
| 17 | | RiC Dashboard | /ricDashboard/index | - | - | **MISSING** |
| | | | | | | |
| | **Data Quality** | | | | | |
| 18 | | Data Migration | /admin/dataMigration | Data Migration | route('data-migration.index') | MATCH |
| 19 | | Duplicate Detection (badge) | /admin/dedupe | - | - | **MISSING** - add with pending count badge |
| | | | | | | |
| | **Data Entry** | | | **MISSING SECTION** | | |
| 20 | | Form Templates | /admin/formTemplates | - | - | **MISSING** |
| | | | | | | |
| | **DOI Management** | | | **MISSING SECTION** | | |
| 21 | | DOI Dashboard | /admin/doi | - | - | **MISSING** |
| 22 | | Minting Queue (badge) | /admin/doi/queue | - | - | **MISSING** - add with pending count badge |
| | | | | | | |
| | **Heritage** | | | **MISSING SECTION** | | |
| 23 | | Admin | /heritage/admin | - | - | **MISSING** |
| 24 | | Analytics | /heritage/analytics | - | - | **MISSING** |
| 25 | | Custodian | /heritage/custodian | - | - | **MISSING** |
| | | | | | | |
| | **Maintenance** | | | | | |
| 26 | | Backup | /admin/backup | - | - | **MISSING** |
| 27 | | Restore | /admin/restore | - | - | **MISSING** |
| 28 | | Jobs | /jobs/browse | Jobs | /jobs/browse | MATCH |

---

## Items to REMOVE from Heratio menu (EXTRA - not in AtoM)

These exist in Heratio but NOT in AtoM's AHG Plugins menu. They should be moved to appropriate locations or removed:

| # | Heratio Section | Heratio Item | Heratio URL | Action |
|---|----------------|-------------|-------------|--------|
| 1 | Settings | Favorites | route('favorites.browse') | REMOVE - already in Static Pages sidebar |
| 2 | GLAM / DAM | Browse by Sector | route('glam.browse') | REMOVE - already in Browse menu |
| 3 | GLAM / DAM | Reports | route('reports.dashboard') | REMOVE - move to Manage menu or keep as separate |
| 4 | Security | Classifications | route('acl.classifications') | REMOVE - only "Clearances" in AtoM |
| 5 | Security | ACL Groups | route('acl.groups') | REMOVE - this is in Admin > Groups |
| 6 | Security | Access Requests | route('acl.access-requests') | MOVE to Access section |
| 7 | Audit | Security Audit Log | route('acl.audit-log') | MERGE into Audit > Logs |
| 8 | Workflows | Workflow Dashboard | route('workflow.dashboard') | REMOVE section - AtoM doesn't have this here |
| 9 | Workflows | Workflow Admin | route('workflow.admin') | REMOVE |
| 10 | Workflows | Publish Gates | route('workflow.gates.admin') | REMOVE |
| 11 | Preservation | Preservation Dashboard | route('preservation.index') | REMOVE section - AtoM doesn't have this here |
| 12 | Loans | Loan Management | route('loan.index') | REMOVE section - AtoM doesn't have this here |
| 13 | E-Commerce | Orders | route('cart.admin.orders') | REMOVE section - AtoM doesn't have this here |
| 14 | E-Commerce | E-Commerce Settings | route('cart.admin.settings') | REMOVE |

**Note:** Items 8-14 (Workflows, Preservation, Loans, E-Commerce) are Heratio-specific features. They should be accessible elsewhere (e.g., Admin menu, Manage menu, or Settings) but NOT in the AHG Plugins dropdown which should match AtoM exactly.

---

## Badge Counts (DB queries needed)

| Item | Query |
|------|-------|
| Researchers | `SELECT COUNT(*) FROM research_researcher WHERE status='pending'` |
| Bookings | `SELECT COUNT(*) FROM research_booking WHERE status='pending'` |
| Pending Review | `SELECT COUNT(*) FROM researcher_submission WHERE status='pending'` |
| Duplicate Detection | `SELECT COUNT(*) FROM ahg_dedupe_queue WHERE status='pending'` |
| Minting Queue | `SELECT COUNT(*) FROM ahg_doi_queue WHERE status='pending'` |

---

## Implementation Notes

1. All sections in AtoM are **conditionally shown** based on enabled plugins (e.g., Research only shows if `ahgResearchPlugin` is enabled)
2. AtoM uses `$themeData['enabledPluginMap']` to check - Heratio should do the same
3. The menu icon is `fa-cubes` (plural) - matching AtoM
4. Menu items are compact (small padding) with section headers in small caps
5. Badge counts are queried in real-time from the database
