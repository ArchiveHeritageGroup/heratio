# Reports Dashboard Comparison: AtoM (psis) vs Heratio

Generated: 2026-04-11 | Updated: 2026-04-11

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
| 1 | Condition Dashboard | `/admin/condition` | NO |
| 2 | Risk Assessment | `/admin/condition/risk` | NO |
| 3 | Condition Templates | `/condition/templates` | NO |

#### Block 13: AI Condition (6 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Dashboard | `/ai-condition/dashboard` | NO |
| 2 | New AI Assessment | `/ai-condition/assess` | NO |
| 3 | Manual Assessment | `/ai-condition/manual` | NO |
| 4 | Bulk Scan | `/ai-condition/bulk` | NO |
| 5 | Browse Assessments | `/ai-condition/browse` | NO |
| 6 | Model Training | `/ai-condition/training` | NO |

#### Block 14: Rights & Licensing (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Rights Dashboard | `/admin/rights` | NO |
| 2 | Batch Rights | `/admin/rights/batch` | NO |
| 3 | Browse Rights | `/admin/rights/browse` | NO |
| 4 | Export Rights | `/admin/rights/export` | NO |

#### Block 15: Embargo Management (3 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Active Embargoes | `/admin/rights/embargo` | NO |
| 2 | Apply Embargo | `/admin/rights/batch?type=embargo` | NO |
| 3 | Expiring Soon | `/admin/rights/expiring` | NO |

#### Block 16: Rights Vocabularies (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Rights Statements | `/admin/rights/statements` | NO |
| 2 | Creative Commons | `/admin/rights/creative-commons` | NO |
| 3 | TK Labels | `/admin/rights/tk-labels` | NO |
| 4 | Rights Holders | `/rightsholder/browse` | NO |

#### Block 17: Vendor Management (5 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Vendor Dashboard | `/admin/vendor` | NO |
| 2 | Browse Vendors | `/admin/vendor/list` | NO |
| 3 | Add Vendor | `/admin/vendor/add` | NO |
| 4 | Transactions | `/admin/vendor/transactions/browse` | NO |
| 5 | Service Types | `/admin/vendor/service-types` | NO |

#### Block 18: Donor Management (2 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Donor Dashboard | `/donor/browse` | NO |
| 2 | Donor Agreements | `/donor/agreements` | NO |

#### Block 19: Marketplace (5 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Admin Dashboard | `/admin/marketplace/dashboard` | NO |
| 2 | All Listings | `/admin/marketplace/listings` | NO |
| 3 | Browse Marketplace | `/marketplace/browse` | NO |
| 4 | Manage Sellers | `/admin/marketplace/sellers` | NO |
| 5 | Active Auctions | `/marketplace/auction-browse` | NO |

#### Block 20: Sales & Payouts (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | All Transactions | `/admin/marketplace/transactions` | NO |
| 2 | Pending Payouts | `/admin/marketplace/payouts` | NO |
| 3 | Revenue Reports | `/admin/marketplace/reports` | NO |
| 4 | Shop Orders | `/admin/orders` | NO |

#### Block 21: E-Commerce (2 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Shop Settings | `/admin/ahgSettings/ecommerce` | NO |
| 2 | Orders | `/admin/orders` | NO |

#### Block 22: Form Templates (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Forms Dashboard | `/admin/formTemplates` | NO |
| 2 | Browse Templates | `/admin/formTemplates/browse` | NO |
| 3 | Create Template | `/admin/formTemplates/create` | NO |
| 4 | Assignments | `/admin/formTemplates/assignments` | NO |

#### Block 23: DOI Management (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | DOI Dashboard | `/doi` | NO |
| 2 | Browse DOIs | `/doi/browse` | NO |
| 3 | Minting Queue | `/doi/queue` | NO |
| 4 | Batch Mint | `/doi/config` | NO |

#### Block 24: RiC (2 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | RiC Dashboard | `/ric` | NO |
| 2 | Sync Status | `/ric/sync-status` | NO |

#### Block 25: Data Migration (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Migration Dashboard | `/data-migration` | NO |
| 2 | Import Data | `/data-migration/upload` | NO |
| 3 | Export Data | `/data-migration/batch-export` | NO |
| 4 | Migration History | `/data-migration/jobs` | NO |

#### Block 26: Data Ingest (3 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Ingest Dashboard | `/ingest` | NO |
| 2 | New Ingest | `/ingest/configure` | NO |
| 3 | CSV Template | `/ingest/template/archive` | NO |

#### Block 27: Backup & Maintenance (3 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Backup Dashboard | `/backup` | NO |
| 2 | Restore | `/backup/restore` | NO |
| 3 | Background Jobs | `/jobs` | NO |

#### Block 28: Heritage Management (3 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Admin Dashboard | `/heritage/admin` | NO |
| 2 | Analytics | `/heritage/analytics` | NO |
| 3 | Custodian | `/heritage/custodian` | NO |

#### Block 29: Duplicate Detection (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Dedupe Dashboard | `/dedupe` | NO |
| 2 | Browse Duplicates | `/dedupe/browse` | NO |
| 3 | Run Scan | `/dedupe/report` | NO |
| 4 | Detection Rules | `/dedupe/rules` | NO |

#### Block 30: Digital Preservation (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Preservation Dashboard | `/preservation` | NO |
| 2 | Fixity Verification | `/preservation/fixity-log` | NO |
| 3 | PREMIS Events | `/preservation/events` | NO |
| 4 | Preservation Reports | `/preservation/reports` | NO |

#### Block 31: Format Registry (3 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Browse Formats | `/preservation/formats` | NO |
| 2 | At-Risk Formats | `/preservation/formats?risk=high` | NO |
| 3 | Preservation Policies | `/preservation/policies` | NO |

#### Block 32: Checksums & Integrity (3 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | Missing Checksums | `/preservation/reports?type=missing` | NO |
| 2 | Stale Verification | `/preservation/reports?type=stale` | NO |
| 3 | Failed Checks | `/preservation/fixity-log?status=failed` | NO |

#### Block 33: CDPA (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | CDPA Dashboard | `/cdpa` | NO |
| 2 | POTRAZ License | `/cdpa/license` | NO |
| 3 | Data Subject Requests | `/cdpa/requests` | NO |
| 4 | Breach Register | `/cdpa/breaches` | NO |

#### Block 34: NAZ (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | NAZ Dashboard | `/naz` | NO |
| 2 | Closure Periods | `/naz/closures` | NO |
| 3 | Research Permits | `/naz/permits` | NO |
| 4 | Records Transfers | `/naz/transfers` | NO |

#### Block 35: IPSAS (4 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | IPSAS Dashboard | `/ipsas` | NO |
| 2 | Asset Register | `/ipsas/assets` | NO |
| 3 | Valuations | `/ipsas/valuations` | NO |
| 4 | Insurance | `/ipsas/insurance` | NO |

#### Block 36: NMMZ (5 pages)
| # | Page | URL | Cloned? |
|---|------|-----|---------|
| 1 | NMMZ Dashboard | `/nmmz` | NO |
| 2 | National Monuments | `/nmmz/monuments` | NO |
| 3 | Antiquities Register | `/nmmz/antiquities` | NO |
| 4 | Export Permits | `/nmmz/permits` | NO |
| 5 | Archaeological Sites | `/nmmz/sites` | NO |

## Status

- Dashboard structure: **36/36 blocks cloned** (links, colours, section headers match AtoM)
- Block 1 destination pages: **53/53 cloned** (reports, gallery, library, DAM, museum, 3D, spectrum)
- Blocks 6–36 destination pages: **0/122 cloned** (pages exist but not audited against AtoM)
- **Total: 53/175 destination pages cloned (30%)**
