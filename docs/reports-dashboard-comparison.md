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
| 6 | Research Services | `#0d6efd` | `#0d6efd` | Different links | NO |
| 7 | Knowledge Platform | `#6610f2` | `#6610f2` | Different links | NO |
| 8 | Research Admin | `#198754` | `#198754` | Different links | NO |
| 9 | Access Requests | `#0d6efd` | `#0d6efd` | YES (superset) | NO |
| 10 | Security & Compliance | `bg-danger` | `bg-danger` | YES (superset) | NO |
| 11 | Privacy & Data Protection | `bg-warning` | `bg-warning` | YES | NO |
| 12 | Condition (Spectrum 5.1) | `bg-secondary` | `bg-secondary` | YES | NO |
| 13 | AI Condition Assessment | `bg-success` | `bg-success` | Heratio superset | NO |
| — | Assessment Statistics | — | `bg-success` | Heratio extra | — |
| — | Grade Distribution | — | `bg-success` | Heratio extra | — |
| 14 | Rights & Licensing | `#6f42c1` | `#6f42c1` | YES (superset) | NO |
| 15 | Embargo Management | `#e83e8c` | `#e83e8c` | YES (superset) | NO |
| 16 | Rights Vocabularies | `#20c997` | `#20c997` | YES (superset) | NO |
| 17 | Vendor Management | `#fd7e14` | `#fd7e14` | YES (superset) | NO |
| 18 | Donor Management | `#198754` | `#198754` | YES (superset) | NO |
| 19 | Marketplace | `#7c3aed` | `#7c3aed` | Partial | NO |
| — | Sellers & Stores | — | `#2563eb` | Heratio extra | — |
| 20 | Sales & Payouts | `#059669` | `#059669` | Partial | NO |
| 21 | E-Commerce | `#059669` | `#059669` | YES | NO |
| 22 | Form Templates | `#198754` | `#198754` | YES (superset) | NO |
| 23 | DOI Management | `#0dcaf0` | `#0dcaf0` | YES (superset) | NO |
| 24 | Records in Contexts (RiC) | `#6f42c1` | `#6f42c1` | YES | NO |
| 25 | Data Migration | `#fd7e14` | `#fd7e14` | YES | NO |
| 26 | Data Ingest | `#0dcaf0` | `#0dcaf0` | YES | NO |
| 27 | Backup & Maintenance | `bg-dark` | `bg-dark` | YES | NO |
| 28 | Heritage Management | `#6c757d` | `#6c757d` | YES | NO |
| 29 | Duplicate Detection | `#dc3545` | `#dc3545` | YES | NO |
| — | TIFF to PDF Merge | — | `#20c997` | Heratio extra | — |
| 30 | Digital Preservation | `#17a2b8` | `#17a2b8` | YES | NO |
| 31 | Format Registry | `#6610f2` | `#6610f2` | YES | NO |
| 32 | Checksums & Integrity | `#28a745` | `#28a745` | Partial (missing Failed) | NO |
| 33 | CDPA Data Protection | `#198754` | `#198754` | YES (superset) | NO |
| 34 | NAZ Archives | `#0d6efd` | `#0d6efd` | YES (superset) | NO |
| 35 | IPSAS Heritage Assets | `#ffc107` | `#ffc107` | YES (superset) | NO |
| 36 | NMMZ Monuments | `#6c757d` | `#6c757d` | YES (superset) | NO |

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

### Blocks 6–36: Destination pages NOT YET audited

| # | Block | Status |
|---|-------|--------|
| 6 | Research Services | Links exist, pages not audited |
| 7 | Knowledge Platform | Links exist, pages not audited |
| 8 | Research Admin | Links exist, pages not audited |
| 9–36 | Remaining blocks | Links exist, destination pages not audited |

## Last Cloned: Block 1 complete including Spectrum (53 pages). Next: Block 6+ destination pages.
