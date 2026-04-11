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
| AI Condition Assessment | `settings.ahg.ai-condition` | 3 (Service + Defaults + API Clients) | 6 | 6/6 ✓ |
| Audit Trail | `settings.ahg.audit` | 3 (General + What to Log + Privacy) | 9 | 9/9 ✓ |
| Authority Records | `settings.authority` | 5 (External Sources + Completeness + NER + Merge + ISDF) | 13 | 13/13 ✓ |
| Background Jobs | `settings.ahg.jobs` | 2 (Job Settings + Queue Status) | 7 | 7/7 ✓ |
| Carousel Settings | `iiif.settings` | 5 (Homepage + Viewer + Carousel + Appearance + Display) | 19 | 16/18 (2 need carousel viewer mode) |

## Control-by-Control Comparison (AtoM psis vs Heratio)

### Row 1: Accession Management

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `accession_numbering_mask` | text | `ACC-{YYYY}-{####}` | `ACC-{YYYY}-{####}` | Yes | ✓ Match |
| 2 | `accession_default_priority` | select | `low/normal/high/urgent` | `low/normal/high/urgent` | Yes | ✓ Fixed (was Title Case) |
| 3 | `accession_auto_assign_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 4 | `accession_require_donor_agreement` | checkbox | Yes | Yes | Yes | ✓ Match |
| 5 | `accession_require_appraisal` | checkbox | Yes | Yes | Yes | ✓ Match |
| 6 | `accession_allow_container_barcodes` | checkbox | Yes | Yes | Yes | ✓ Match |
| 7 | `accession_rights_inheritance_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |

**URL:** `/admin/ahgSettings/accession` (was `/admin/settings/ahg/accession`) ✓ Standardised

### Row 2: AHG Central

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `ahg_central_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 2 | `ahg_central_api_url` | url | Yes | Yes | Yes | ✓ Match |
| 3 | `ahg_central_api_key` | password | Yes | Yes | Yes | ✓ Match |
| 4 | `ahg_central_site_id` | text | Yes | Yes | Yes | ✓ Added (was missing) |
| 5 | Env vars legacy card | display | Yes | Yes | n/a | ✓ Added (was missing) |

**URL:** `/admin/ahgSettings/ahgIntegration` (was `/admin/settings/ahg-integration`) ✓ Standardised

### Row 3: AI Condition Assessment

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `ai_condition_service_url` | url | Yes | Yes | Yes | ✓ Match |
| 2 | `ai_condition_api_key` | text | Yes | Yes | Yes | ✓ Match |
| 3 | `ai_condition_min_confidence` | number | Yes | Yes | Yes | ✓ Match |
| 4 | `ai_condition_overlay_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 5 | `ai_condition_auto_scan` | checkbox | Yes | Yes | Yes | ✓ Match |
| 6 | `ai_condition_notify_grade` | select | Yes | Yes | Yes | ✓ Match |
| 7 | API Clients CRUD | table+modal | Yes | Yes | Yes | ✓ Match |
| 8 | Training Data Approval | table+modal | Yes | Yes | Yes | ✓ Match |

**URL:** `/admin/ahgSettings/aiCondition` (was `/admin/settings/ahg/ai_condition`) ✓ Standardised

### Row 4: AI Services

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `processing_mode` | radio | hybrid/job | hybrid/job | Yes | ✓ Match |
| 2 | `api_url` | text | Yes | Yes | Yes | ✓ Match |
| 3 | `api_key` | password | Yes | Yes | Yes | ✓ Match |
| 4 | `api_timeout` | number | Yes | Yes | Yes | ✓ Match |
| 5 | `ner_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 6 | `auto_extract_on_upload` | checkbox | Yes | Yes | Yes | ✓ Match |
| 7 | Entity types (4 checkboxes) | checkboxes | Yes | Yes | Yes | ✓ Match |
| 8 | `summarizer_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 9 | `summary_field` | select | Yes | Yes | Yes | ✓ Match |
| 10 | `summarizer_min_length` | number | Yes | Yes | Yes | ✓ Match |
| 11 | `summarizer_max_length` | number | Yes | Yes | Yes | ✓ Match |
| 12 | `spellcheck_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 13 | `spellcheck_language` | select | Yes | Yes | Yes | ✓ Match |
| 14 | Spellcheck fields | checkboxes | Yes | Yes | Yes | ✓ Match |
| 15 | `translation_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 16 | `mt_endpoint` | text | Yes | Yes | Yes | ✓ Match |
| 17 | `translation_source_lang` | select | Yes | Yes | Yes | ✓ Match |
| 18 | `translation_target_lang` | select | Yes | Yes | Yes | ✓ Match |
| 19 | `translation_save_culture` | checkbox | Yes | Yes | Yes | ✓ Match |
| 20 | `translation_sector` | select | Yes | Yes | Yes | ✓ Match |
| 21 | Fields to translate | checkbox+select table | Yes | Yes | Yes | ✓ Match |
| 22 | `translation_mode` | radio | review/auto | review/auto | Yes | ✓ Match |
| 23 | `translation_overwrite` | checkbox | Yes | Yes | Yes | ✓ Match |
| 24 | `mt_timeout` | number | Yes | Yes | Yes | ✓ Match |
| 25 | `qdrant_url` | text | Yes | Yes | Yes | ✓ Match |
| 26 | `qdrant_collection` | text | Yes | Yes | Yes | ✓ Match |
| 27 | `qdrant_model` | select | Yes | Yes | Yes | ✓ Match |
| 28 | `qdrant_min_score` | number | Yes | Yes | Yes | ✓ Match |

**URL:** `/admin/ahgSettings/aiServices` (was `/admin/settings/ai-services`) ✓ Standardised

### Row 5: Audit Trail

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `audit_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 2 | `audit_views` | checkbox | Yes | Yes | Yes | ✓ Match |
| 3 | `audit_searches` | checkbox | Yes | Yes | Yes | ✓ Match |
| 4 | `audit_downloads` | checkbox | Yes | Yes | Yes | ✓ Match |
| 5 | `audit_api_requests` | checkbox | Yes | Yes | Yes | ✓ Match |
| 6 | `audit_authentication` | checkbox | Yes | Yes | Yes | ✓ Match |
| 7 | `audit_sensitive_access` | checkbox | Yes | Yes | Yes | ✓ Match |
| 8 | `audit_mask_sensitive` | checkbox | Yes | Yes | Yes | ✓ Match |
| 9 | `audit_ip_anonymize` | checkbox | Yes | Yes | Yes | ✓ Match |

**URL:** `/admin/ahgSettings/audit` (was `/admin/settings/ahg/audit`) ✓ Standardised

### Row 6: Authority Records

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `authority_wikidata_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 2 | `authority_viaf_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 3 | `authority_ulan_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 4 | `authority_lcnaf_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 5 | `authority_isni_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 6 | `authority_auto_verify_wikidata` | checkbox | Yes | Yes | Yes | ✓ Match |
| 7 | `authority_completeness_auto_recalc` | checkbox | Yes | Yes | Yes | ✓ Match |
| 8 | `authority_hide_stubs_from_public` | checkbox | Yes | Yes | Yes | ✓ Match |
| 9 | `authority_ner_auto_stub_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 10 | `authority_ner_auto_stub_threshold` | number | Yes | Yes | Yes | ✓ Match |
| 11 | `authority_merge_require_approval` | checkbox | Yes | Yes | Yes | ✓ Match |
| 12 | `authority_dedup_threshold` | number | Yes | Yes | Yes | ✓ Match |
| 13 | `authority_function_linking_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |

**URL:** `/admin/ahgSettings/authority` (was `/admin/settings/authority`) ✓ Standardised

## TODO

- [x] Fix duplicate "Authority" tile — DONE (added to skip list)
- [x] Rename tiles to match AtoM exactly — DONE (Email Settings, FTP / SFTP Upload, Fuseki / RIC Triplestore, Plugin Management, Heritage Accounting)
- [x] Wire all 6 AI Condition settings — DONE (all 6 controls wired + API Clients + Training Approval)
- [ ] Wire `carousel_show_thumbnails` and `carousel_show_controls` when Bootstrap carousel viewer mode is added to record pages
- [x] Build dedicated structured pages for all 18 AHG groups — DONE
  - Batch 1 (agent): data_protection, encryption, faces, ftp, fuseki
  - Batch A (agent): spectrum, photos, media, metadata, ingest, integrity, voice_ai
  - Manual: iiif, security, library, multi_tenant, portable_export, compliance
  - Skipped: general (uses existing themes page)
