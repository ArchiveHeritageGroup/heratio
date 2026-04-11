# Settings Tile Comparison: AtoM (psis) vs Heratio

Generated: 2026-04-11

## Summary

| | Count |
|---|---|
| AtoM tiles | 40 |
| Heratio tiles | 59 |
| Matching | 40/40 (100% AtoM coverage) |
| Heratio extras | 19 |
| Name mismatches | 5 (shorter/different labels) |
| Bugs | 1 (duplicate "Authority" tile) |

## Full comparison

| # | AtoM (psis) | Heratio | Status |
|---|---|---|---|
| 1 | Accession Management | Accession Management | ✓ |
| 2 | AHG Central | AHG Central | ✓ |
| 3 | AI Condition Assessment | AI Condition Assessment | ✓ |
| 4 | AI Services | AI Services | ✓ |
| 5 | Audit Trail | Audit Trail | ✓ |
| 6 | Authority Records | Authority Records | ✓ |
| 7 | Background Jobs | Background Jobs | ✓ |
| 8 | Carousel Settings | Carousel Settings | ✓ |
| 9 | Condition Photos | Condition Photos | ✓ |
| 10 | Cron Jobs | Cron Jobs | ✓ |
| 11 | Data Ingest | Data Ingest | ✓ |
| 12 | E-Commerce | E-Commerce | ✓ |
| 13 | Email Settings | Email | ✓ name shorter |
| 14 | Encryption | Encryption | ✓ |
| 15 | FTP / SFTP Upload | FTP / SFTP | ✓ name shorter |
| 16 | Fuseki / RIC Triplestore | Fuseki / RIC | ✓ name shorter |
| 17 | Heritage Accounting | Heritage Platform | ✓ different name |
| 18 | ICIP Settings | ICIP Settings | ✓ |
| 19 | IIIF Viewer | IIIF Viewer | ✓ |
| 20 | Levels of Description | Levels of Description | ✓ |
| 21 | Library Settings | Library Settings | ✓ |
| 22 | Marketplace | Marketplace | ✓ |
| 23 | Media Player | Media Player | ✓ |
| 24 | Media Processing | Media Processing | ✓ |
| 25 | Metadata Extraction | Metadata Extraction | ✓ |
| 26 | Multi-Tenancy | Multi-Tenancy | ✓ |
| 27 | Order Management | Order Management | ✓ |
| 28 | Plugin Management | Plugins | ✓ name shorter |
| 29 | Privacy Compliance | Privacy Compliance | ✓ |
| 30 | Reading Room | Reading Room | ✓ |
| 31 | Sector Numbering | Sector Numbering | ✓ |
| 32 | Semantic Search | Semantic Search | ✓ |
| 33 | Services Monitor | Services Monitor | ✓ |
| 34 | Spectrum / Collections | Spectrum / Collections | ✓ |
| 35 | System Information | System Information | ✓ |
| 36 | Text-to-Speech | Text-to-Speech | ✓ |
| 37 | Theme Configuration | Theme Configuration | ✓ |
| 38 | Voice & AI | Voice & AI | ✓ |
| 39 | Watermark Settings | Watermark Settings | ✓ |
| 40 | Webhooks | Webhooks | ✓ |

## Heratio extras (19 tiles not in AtoM)

| # | Tile | Source | Notes |
|---|---|---|---|
| 1 | Authority | AHG group `authority` | **BUG: duplicate** — same as "Authority Records" dedicated tile. Remove from AHG group loop. |
| 2 | Compliance | AHG group | Heratio regulatory compliance settings |
| 3 | Data Protection | AHG group | POPIA / GDPR data handling |
| 4 | Default page elements | AtoM standard setting | Promoted to tile (AtoM has it in sidebar only) |
| 5 | Default templates | AtoM scope card | Display templates for IO/actor/repo |
| 6 | Error Log | Heratio standalone | Application error log viewer |
| 7 | Face Detection | AHG group | Face detection/recognition settings |
| 8 | Features | AHG group | Feature toggles (3D, IIIF, bookings) |
| 9 | Federation | AtoM scope card | Federated search settings |
| 10 | Global settings | AtoM scope card | Site title, base URL, search options |
| 11 | Integrity | AHG group | Fixity checking (generic AHG group) |
| 12 | Integrity Assurance | Dedicated tile | Fixity dashboard (links to integrity.index) |
| 13 | Languages | AtoM scope card | I18n language management |
| 14 | OAI repository | AtoM scope card | OAI-PMH harvesting settings |
| 15 | Plugins | Heratio standalone | Package/plugin management |
| 16 | Portable Export | AHG group | Offline export configuration |
| 17 | Preservation & Backup | Dedicated tile | Backup replication targets |
| 18 | Security | AHG group | Lockout and password policies |
| 19 | User interface labels | AtoM scope card | UI label customisation |
| 20 | Visible elements | AtoM scope card | Per-standard field visibility |

## Wiring status of dedicated settings pages

| Page | Route | Cards | Settings | Wired |
|---|---|---|---|---|
| Accession Management | `settings.ahg.accession` | 2 (Intake Queue + Containers & Rights) | 7 | 7/7 ✓ |
| AI Condition Assessment | `settings.ahg.ai-condition` | 3 (Service + Defaults + API Clients) | 6 | 1/6 (5 TODO — API not built) |
| Audit Trail | `settings.ahg.audit` | 3 (General + What to Log + Privacy) | 9 | 9/9 ✓ |
| Authority Records | `settings.authority` | 5 (External Sources + Completeness + NER + Merge + ISDF) | 13 | 13/13 ✓ |
| Background Jobs | `settings.ahg.jobs` | 2 (Job Settings + Queue Status) | 7 | 7/7 ✓ |
| Carousel Settings | `iiif.settings` | 5 (Homepage + Viewer + Carousel + Appearance + Display) | 19 | 16/18 (2 need carousel viewer mode) |

## TODO

- [x] Fix duplicate "Authority" tile — DONE (added to skip list)
- [x] Rename tiles to match AtoM exactly — DONE (Email Settings, FTP / SFTP Upload, Fuseki / RIC Triplestore, Plugin Management, Heritage Accounting)
- [ ] Wire remaining 5 AI Condition settings when the condition API is built (ai_condition_service_url, ai_condition_api_key, ai_condition_min_confidence, ai_condition_overlay_enabled, ai_condition_notify_grade)
- [ ] Wire `carousel_show_thumbnails` and `carousel_show_controls` when Bootstrap carousel viewer mode is added to record pages
- [x] Build dedicated structured pages for all 18 AHG groups — DONE
  - Batch 1 (agent): data_protection, encryption, faces, ftp, fuseki
  - Batch A (agent): spectrum, photos, media, metadata, ingest, integrity, voice_ai
  - Manual: iiif, security, library, multi_tenant, portable_export, compliance
  - Skipped: general (uses existing themes page)
