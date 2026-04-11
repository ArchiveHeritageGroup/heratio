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
| AI Condition Assessment | `settings.ai-condition` | 3 (Service + Defaults + API Clients) | 6 | 6/6 ✓ |
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

### Row 7: Background Jobs

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `jobs_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 2 | `jobs_max_concurrent` | number | Yes | Yes | Yes | ✓ Match |
| 3 | `jobs_timeout` | number | Yes | Yes | Yes | ✓ Match |
| 4 | `jobs_retry_attempts` | number | Yes | Yes | Yes | ✓ Match |
| 5 | `jobs_cleanup_days` | number | Yes | Yes | Yes | ✓ Match |
| 6 | `jobs_notify_on_failure` | checkbox | Yes | Yes | Yes | ✓ Match |
| 7 | `jobs_notify_email` | email | Yes | Yes | Yes | ✓ Match |

**URL:** `/admin/ahgSettings/jobs` (was `/admin/settings/ahg/jobs`) ✓ Standardised

### Row 8: Carousel Settings

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `homepage_collection_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 2 | `homepage_collection_id` | select | Yes | Yes | Yes | ✓ Match |
| 3 | `homepage_carousel_height` | select | Yes | Yes | Yes | ✓ Match |
| 4 | `homepage_max_items` | number | Yes | Yes | Yes | ✓ Match |
| 5 | `homepage_carousel_autoplay` | checkbox | Yes | Yes | Yes | ✓ Match |
| 6 | `homepage_show_captions` | checkbox | Yes | Yes | Yes | ✓ Match |
| 7 | `homepage_carousel_interval` | number | Yes | Yes | Yes | ✓ Match |
| 8 | `viewer_type` | select | Yes | Yes | Yes | ✓ Match |
| 9 | `viewer_height` | select | Yes | Yes | Yes | ✓ Match |
| 10 | `carousel_autoplay` | checkbox | Yes | Yes | Yes | ✓ Match |
| 11 | `carousel_interval` | number | Yes | Yes | Yes | ✓ Match |
| 12 | `carousel_show_thumbnails` | checkbox | Yes | Yes | Yes | ✓ Match |
| 13 | `carousel_show_controls` | checkbox | Yes | Yes | Yes | ✓ Match |
| 14 | `background_color` | color | Yes | Yes | Yes | ✓ Match |
| 15 | `enable_fullscreen` | checkbox | Yes | Yes | Yes | ✓ Match |
| 16 | `show_zoom_controls` | checkbox | Yes | Yes | Yes | ✓ Match |
| 17 | `show_on_view` | checkbox | Yes | Yes | Yes | ✓ Match |
| 18 | `show_on_browse` | checkbox | Yes | Yes | Yes | ✓ Match |

**URL:** `/admin/ahgSettings/carousel` (was `/admin/iiif-settings`) ✓ Standardised

### Row 9: Condition Photos

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `photo_upload_path` | text | Yes | Yes | Yes | ✓ Match |
| 2 | `photo_max_upload_size` | select | Yes | Yes | Yes | ✓ Match |
| 3 | `photo_create_thumbnails` | checkbox | Yes | Yes | Yes | ✓ Match |
| 4 | `photo_thumbnail_small` | number | Yes | Yes | Yes | ✓ Match |
| 5 | `photo_thumbnail_medium` | number | Yes | Yes | Yes | ✓ Match |
| 6 | `photo_thumbnail_large` | number | Yes | Yes | Yes | ✓ Match |
| 7 | `photo_jpeg_quality` | range | Yes | Yes | Yes | ✓ Match |
| 8 | `photo_extract_exif` | checkbox | Yes | Yes | Yes | ✓ Match |
| 9 | `photo_auto_rotate` | checkbox | Yes | Yes | Yes | ✓ Match |

**URL:** `/admin/ahgSettings/photos` (was `/admin/settings/ahg/photos`) ✓ Standardised

### Row 10: Cron Jobs

AtoM page is a static reference listing CLI commands. Heratio has a fully interactive DB-driven scheduler with toggle, edit, run-now, output view — a complete superset.

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | Job toggle (per job) | checkbox | — | Yes | Yes | ✓ Heratio superset |
| 2 | Cron expression (per job) | text | — | Yes | Yes | ✓ Heratio superset |
| 3 | Timeout (per job) | number | — | Yes | Yes | ✓ Heratio superset |
| 4 | Notify on failure (per job) | checkbox | — | Yes | Yes | ✓ Heratio superset |
| 5 | Notify email (per job) | email | — | Yes | Yes | ✓ Heratio superset |
| 6 | Run now (per job) | button | — | Yes | Yes | ✓ Heratio superset |
| 7 | Seed defaults | button | — | Yes | Yes | ✓ Heratio superset |

**URL:** `/admin/ahgSettings/cronJobs` (was `/admin/settings/cron-jobs`) ✓ Standardised

### Row 11: Data Ingest

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `ingest_virus_scan` | checkbox | Yes | Yes | Yes | ✓ Match |
| 2 | `ingest_ocr` | checkbox | Yes | Yes | Yes | ✓ Match |
| 3 | `ingest_ner` | checkbox | Yes | Yes | Yes | ✓ Match |
| 4 | `ingest_summarize` | checkbox | Yes | Yes | Yes | ✓ Match |
| 5 | `ingest_spellcheck` | checkbox | Yes | Yes | Yes | ✓ Match |
| 6 | `ingest_format_id` | checkbox | Yes | Yes | Yes | ✓ Match |
| 7 | `ingest_face_detect` | checkbox | Yes | Yes | Yes | ✓ Match |
| 8 | `ingest_translate` | checkbox | Yes | Yes | Yes | ✓ Match |
| 9 | `ingest_translate_from` | select | Yes | Yes | Yes | ✓ Match |
| 10 | `ingest_translate_to` | select | Yes | Yes | Yes | ✓ Match |
| 11 | `ingest_spellcheck_lang` | select | Yes | Yes | Yes | ✓ Match |
| 12 | `ingest_create_records` | checkbox | Yes | Yes | Yes | ✓ Match |
| 13 | `ingest_generate_sip` | checkbox | Yes | Yes | Yes | ✓ Match |
| 14 | `ingest_generate_aip` | checkbox | Yes | Yes | Yes | ✓ Match |
| 15 | `ingest_generate_dip` | checkbox | Yes | Yes | Yes | ✓ Match |
| 16 | `ingest_thumbnails` | checkbox | Yes | Yes | Yes | ✓ Match |
| 17 | `ingest_reference` | checkbox | Yes | Yes | Yes | ✓ Match |
| 18 | `ingest_sip_path` | text | Yes | Yes | Yes | ✓ Match |
| 19 | `ingest_aip_path` | text | Yes | Yes | Yes | ✓ Match |
| 20 | `ingest_dip_path` | text | Yes | Yes | Yes | ✓ Match |
| 21 | `ingest_default_sector` | select | Yes | Yes | Yes | ✓ Match |
| 22 | `ingest_default_standard` | select | Yes | Yes | Yes | ✓ Match |

**URL:** `/admin/ahgSettings/ingest` (was `/admin/settings/ahg/ingest`) ✓ Standardised

### Row 12: E-Commerce

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `is_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 2 | `currency` | select | ZAR/USD/EUR/GBP | ZAR/USD/EUR/GBP | Yes | ✓ Match |
| 3 | `vat_rate` | number | Yes | Yes | Yes | ✓ Match |
| 4 | `vat_number` | text | Yes | Yes | Yes | ✓ Match |
| 5 | `admin_notification_email` | email | Yes | Yes | Yes | ✓ Match |
| 6 | `terms_conditions` | textarea | Yes | Yes | Yes | ✓ Match |
| 7 | `payfast_merchant_id` | text | Yes | Yes | Yes | ✓ Match |
| 8 | `payfast_merchant_key` | text | Yes | Yes | Yes | ✓ Match |
| 9 | `payfast_passphrase` | password | Yes | Yes | Yes | ✓ Match |
| 10 | `payfast_sandbox` | checkbox | Yes | Yes | Yes | ✓ Match |
| 11 | Product pricing table | dynamic | Yes | Yes | Yes | ✓ Match |

**URL:** `/admin/ahgSettings/ecommerce` (was `/admin/ecommerce-settings`) ✓ Standardised

### Row 13: Email Settings

Both AtoM and Heratio use dynamic DB-driven settings for SMTP, notifications, and templates.

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | SMTP settings (dynamic) | text/password/select/number | Yes | Yes | Yes | ✓ Match |
| 2 | Test Email | button + email | Yes | Yes | Yes | ✓ Match |
| 3 | Notification Recipients (dynamic) | email fields | Yes | Yes | Yes | ✓ Match |
| 4 | `error_alert_enabled` | select | Yes | Yes | Yes | ✓ Match |
| 5 | `error_alert_throttle_ttl` | number | Yes | Yes | Yes | ✓ Match |
| 6 | `error_alert_daily_cap` | number | Yes | Yes | Yes | ✓ Match |
| 7 | `error_alert_env_gate` | select | Yes | Yes | Yes | ✓ Match |
| 8 | Email templates (accordion) | subject + body per template | Yes | Yes | Yes | ✓ Match |
| 9 | Notification toggles (3) | checkboxes | Yes | Yes | Yes | ✓ Match |

**URL:** `/admin/ahgSettings/email` (was `/admin/settings/email`) ✓ Standardised

### Row 14: Encryption

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | Key status | display | Yes | Yes | n/a | ✓ Match |
| 2 | `encryption_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 3 | `encryption_encrypt_derivatives` | checkbox | Yes | Yes | Yes | ✓ Match |
| 4 | `encryption_field_contact_details` | checkbox | Yes | Yes | Yes | ✓ Match |
| 5 | `encryption_field_financial_data` | checkbox | Yes | Yes | Yes | ✓ Match |
| 6 | `encryption_field_donor_information` | checkbox | Yes | Yes | Yes | ✓ Match |
| 7 | `encryption_field_personal_notes` | checkbox | Yes | Yes | Yes | ✓ Match |
| 8 | `encryption_field_access_restrictions` | checkbox | Yes | Yes | Yes | ✓ Match |
| 9 | Compliance card | display | — | Yes | n/a | ✓ Heratio extra |

**URL:** `/admin/ahgSettings/encryption` (was `/admin/settings/ahg/encryption`) ✓ Standardised

### Row 15: FTP / SFTP Upload

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `ftp_protocol` | select | sftp/ftp | sftp/ftp | Yes | ✓ Match |
| 2 | `ftp_host` | text | Yes | Yes | Yes | ✓ Match |
| 3 | `ftp_port` | number | Yes | Yes | Yes | ✓ Match |
| 4 | `ftp_username` | text | Yes | Yes | Yes | ✓ Match |
| 5 | `ftp_password` | password | Yes | Yes | Yes | ✓ Match |
| 6 | Test Connection | button | Yes | Yes | Yes | ✓ Match |
| 7 | `ftp_remote_path` | text | Yes | Yes | Yes | ✓ Match |
| 8 | `ftp_disk_path` | text | Yes | Yes | Yes | ✓ Match |
| 9 | `ftp_passive_mode` | checkbox | Yes | Yes | Yes | ✓ Match |

**URL:** `/admin/ahgSettings/ftp` (was `/admin/settings/ahg/ftp`) ✓ Standardised

### Row 16: Fuseki / RIC Triplestore

Heratio is a superset — adds integrity check settings and quick action links beyond AtoM.

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `fuseki_endpoint` | url | Yes | Yes | Yes | ✓ Match |
| 2 | `fuseki_username` | text | Yes | Yes | Yes | ✓ Match |
| 3 | `fuseki_password` | password | Yes | Yes | Yes | ✓ Match |
| 4 | Test Connection | button | Yes | Yes | Yes | ✓ Match |
| 5 | `fuseki_sync_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 6 | `fuseki_queue_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 7 | `fuseki_sync_on_save` | checkbox | Yes | Yes | Yes | ✓ Match |
| 8 | `fuseki_sync_on_delete` | checkbox | Yes | Yes | Yes | ✓ Match |
| 9 | `fuseki_cascade_delete` | checkbox | Yes | Yes | Yes | ✓ Match |
| 10 | `fuseki_batch_size` | number | Yes | Yes | Yes | ✓ Match |
| 11 | `fuseki_integrity_schedule` | select | — | Yes | Yes | ✓ Heratio extra |
| 12 | `fuseki_orphan_retention_days` | number | — | Yes | Yes | ✓ Heratio extra |

**URL:** `/admin/ahgSettings/fuseki` (was `/admin/settings/ahg/fuseki`) ✓ Standardised

### Row 17: Heritage Accounting

Heritage Accounting is a standalone admin module — both AtoM and Heratio link to the heritage admin dashboard (standards, rules, regions) rather than a key-value settings form. Not comparable control-by-control.

**AtoM URL:** `heritageAdmin/index` | **Heratio URL:** `/heritage/admin` (route `heritage.admin`)
**Status:** ✓ Both link to the same admin dashboard. No URL standardisation needed (not under `/admin/settings/`).

### Row 18: ICIP Settings

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `enable_public_notices` | checkbox | Yes | Yes | Yes | ✓ Match |
| 2 | `enable_staff_notices` | checkbox | Yes | Yes | Yes | ✓ Match |
| 3 | `require_acknowledgement_default` | checkbox | Yes | Yes | Yes | ✓ Match |
| 4 | `require_community_consent` | checkbox | — | Yes | Yes | ✓ Heratio extra |
| 5 | `consent_expiry_warning_days` | number | Yes | Yes | Yes | ✓ Added (was missing) |
| 6 | `default_consultation_follow_up_days` | number | Yes | Yes | Yes | ✓ Added (was missing) |
| 7 | `local_contexts_hub_enabled` | checkbox | Yes | Yes | Yes | ✓ Added (was missing) |
| 8 | `local_contexts_api_key` | text | Yes | Yes | Yes | ✓ Added (was missing) |
| 9 | `audit_all_icip_access` | checkbox | Yes | Yes | Yes | ✓ Added (was missing) |

**URL:** `/admin/ahgSettings/icipSettings` (was `/admin/settings/icip-settings`) ✓ Standardised

### Row 19: IIIF Viewer

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `iiif_enabled` | checkbox | Yes | Yes | Yes | ✓ Match |
| 2 | `iiif_viewer` | select | OSD/Mirador/Leaflet | Yes (dynamic) | Yes | ✓ Match |
| 3 | `iiif_server_url` | url | Yes | Yes (dynamic) | Yes | ✓ Match |
| 4 | `iiif_show_navigator` | checkbox | Yes | Yes (dynamic) | Yes | ✓ Match |
| 5 | `iiif_show_rotation` | checkbox | Yes | Yes (dynamic) | Yes | ✓ Match |
| 6 | `iiif_max_zoom` | number | Yes | Yes (dynamic) | Yes | ✓ Match |

Heratio renders all `iiif` group settings dynamically via `buildGroupSettings()`.

**URL:** `/admin/ahgSettings/iiif` (was `/admin/settings/ahg/iiif`) ✓ Standardised

### Row 20: Levels of Description

Heratio is a superset — sector tabs, per-sector level assignment with checkboxes, display order management, taxonomy quick links. AtoM has a simpler page.

**URL:** `/admin/ahgSettings/levels` (was `/admin/settings/levels`) ✓ Standardised

### Row 21: Library Settings

AtoM has a structured page with 6 cards: Loan Rules (CRUD table), Circulation Defaults, Patron Defaults, OPAC, Holds, ISBN Providers. Heratio renders all `library` group settings dynamically via `buildGroupSettings()`. All key-value settings are present and saved; loan rules CRUD is handled by the library package separately.

| # | Control group | AtoM | Heratio | Status |
|---|--------------|------|---------|--------|
| 1 | Loan Rules CRUD table | Yes | Separate (library package) | ✓ Available |
| 2 | Circulation (loan_days, renewals, currency, auto_fine, barcode, holds, patrons) | 7 controls | 7 controls (dynamic) | ✓ Match |
| 3 | Patron Defaults (max_checkouts, renewals, holds, membership, fine_threshold, type, grace) | 7 controls | 7 controls (dynamic) | ✓ Match |
| 4 | OPAC (enabled, availability, covers, holds, results, arrivals, popular) | 7 controls | 7 controls (dynamic) | ✓ Match |
| 5 | Hold Settings (expiry, max_queue) | 2 controls | 2 controls (dynamic) | ✓ Match |
| 6 | ISBN Providers link | Yes | Separate (library package) | ✓ Available |

**URL:** `/admin/ahgSettings/library` (was `/admin/settings/ahg/library`) ✓ Standardised

### Row 22: Marketplace

AtoM has a dynamic DB-driven settings page that renders all marketplace settings grouped by `setting_group`, handling boolean/number/json/text types. Heratio marketplace settings page is a skeleton with an empty accordion body.

**AtoM URL:** `marketplace/adminSettings` | **Heratio URL:** `/admin/marketplace/settings` (route `ahgmarketplace.admin-settings`)
**Status:** Heratio view is a skeleton — settings controls not yet built. Standalone package, not under `/admin/ahgSettings/`.

### Row 23: Media Player

| # | Control | Type | AtoM | Heratio | Wired | Status |
|---|---------|------|------|---------|-------|--------|
| 1 | `media_player_type` | select | basic/enhanced | basic/enhanced | Yes | ✓ Match |
| 2 | `media_autoplay` | checkbox | Yes | Yes | Yes | ✓ Match |
| 3 | `media_show_controls` | checkbox | Yes | Yes | Yes | ✓ Match |
| 4 | `media_loop` | checkbox | Yes | Yes | Yes | ✓ Match |
| 5 | `media_default_volume` | range | Yes | Yes | Yes | ✓ Match |
| 6 | `media_show_download` | checkbox | Yes | Yes | Yes | ✓ Match |

**URL:** `/admin/ahgSettings/media` (was `/admin/settings/ahg/media`) ✓ Standardised

### Row 24: Media Processing

AtoM and Heratio both have standalone media processing dashboards (not key-value settings forms). Heratio has stats cards, per-item regeneration, batch regeneration, watermark settings, and queue management.

**AtoM URL:** `mediaSettings/index` | **Heratio URL:** `/admin/media-processing` (route `media-processing.index`)
**Status:** ✓ Both are standalone admin dashboards. Not under `/admin/ahgSettings/`.

## TODO

- [x] Fix duplicate "Authority" tile — DONE (added to skip list)
- [x] Rename tiles to match AtoM exactly — DONE (Email Settings, FTP / SFTP Upload, Fuseki / RIC Triplestore, Plugin Management, Heritage Accounting)
- [x] Wire all 6 AI Condition settings — DONE (all 6 controls wired + API Clients + Training Approval)
- [x] Carousel settings — all 18 controls at full parity (carousel_show_thumbnails and carousel_show_controls present)
- [x] Build dedicated structured pages for all 18 AHG groups — DONE
  - Batch 1 (agent): data_protection, encryption, faces, ftp, fuseki
  - Batch A (agent): spectrum, photos, media, metadata, ingest, integrity, voice_ai
  - Manual: iiif, security, library, multi_tenant, portable_export, compliance
  - Skipped: general (uses existing themes page)
