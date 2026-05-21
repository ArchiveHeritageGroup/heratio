> Heratio Help Center article. Category: Admin & Settings.

# AHG Settings

## Overview

The AHG Settings hub is the centralised administration interface for all Heratio configuration. All settings pages use the standardised URL pattern `/admin/ahgSettings/{page}`.

---

## How to Access

Navigate to **Admin > AHG Settings** or go directly to `/admin/ahgSettings`.

The dashboard displays tiles for all available settings pages, grouped by function.

---

## Settings Pages — Complete Reference

### Core Settings (AtoM scope cards)

| Page | URL | Description |
|------|-----|-------------|
| Global | `/admin/settings/global` | Site title, base URL, search, identifiers, publication status |
| Default page elements | `/admin/settings/page-elements` | Toggle logo, title, description, language menu, carousel, map |
| Default templates | `/admin/settings/default-template` | Display templates for IO/actor/repo |
| User interface labels | `/admin/settings/interface-labels` | Customise labels throughout the UI |
| Visible elements | `/admin/settings/visible-elements` | Show/hide fields per descriptive standard |
| Languages | `/admin/settings/languages` | I18n language management |
| OAI repository | `/admin/settings/oai` | OAI-PMH harvesting settings |
| Federation | `/admin/settings/federation` | Federated search settings |

### AHG Plugin Settings (`/admin/ahgSettings/...`)

| Page | URL | Controls | Description |
|------|-----|----------|-------------|
| Accession Management | `/admin/ahgSettings/accession` | 7 | Numbering mask, priority, donor agreement, appraisal |
| AHG Central | `/admin/ahgSettings/ahgIntegration` | 5 | Cloud NER training, API URL, key, site ID |
| AI Condition Assessment | `/admin/ahgSettings/aiCondition` | 6 + API clients | Damage detection, confidence, overlays, auto-scan |
| AI Services | `/admin/ahgSettings/aiServices` | 28 | NER, summarisation, spell check, translation, Qdrant |
| Audit Trail | `/admin/ahgSettings/audit` | 9 | Enable logging, what to log, privacy masking |
| Authority Records | `/admin/ahgSettings/authority` | 13 | Wikidata, VIAF, ULAN, NER stubs, merge/dedup |
| Background Jobs | `/admin/ahgSettings/jobs` | 7 | Enable, concurrency, timeout, retry, cleanup |
| Carousel Settings | `/admin/ahgSettings/carousel` | 18 | Homepage collection, viewer type, appearance |
| Compliance | `/admin/ahgSettings/compliance` | Dynamic | Regulatory compliance settings |
| Condition Photos | `/admin/ahgSettings/photos` | 9 | Upload path, thumbnails, JPEG quality, EXIF |
| Cron Jobs | `/admin/ahgSettings/cronJobs` | Per-job | Toggle, schedule, run now, output view |
| Data Ingest | `/admin/ahgSettings/ingest` | 22 | AI processing defaults, output, service availability |
| Data Protection | `/admin/ahgSettings/dataProtection` | 7+ | POPIA/GDPR, consent, notification |
| E-Commerce | `/admin/ahgSettings/ecommerce` | 11 | Currency, VAT, PayFast, product pricing |
| Email Settings | `/admin/ahgSettings/email` | Dynamic | SMTP, templates, notifications, error alerts |
| Encryption | `/admin/ahgSettings/encryption` | 7 | Master toggle, derivatives, field categories |
| Face Detection | `/admin/ahgSettings/faces` | Dynamic | Detection backend, auto-match, blur |
| Features | catch-all group | 3 | 3D viewer, IIIF, bookings toggles |
| FTP / SFTP Upload | `/admin/ahgSettings/ftp` | 9 | Protocol, host, credentials, paths, passive mode |
| Fuseki / RIC | `/admin/ahgSettings/fuseki` | 12 | SPARQL endpoint, sync, integrity checks |
| ICIP Settings | `/admin/ahgSettings/icipSettings` | 9 | Cultural notices, consent, Local Contexts Hub |
| IIIF Viewer | `/admin/ahgSettings/iiif` | 6 | Viewer library, navigator, rotation, zoom |
| Integrity | `/admin/ahgSettings/integrity` | Dynamic | Fixity checking, baseline, alerts |
| Levels of Description | `/admin/ahgSettings/levels` | Per-sector | Assign levels to Archive/Museum/Library/Gallery/DAM |
| Library Settings | `/admin/ahgSettings/library` | 23+ | Loan rules, circulation, patrons, OPAC, holds |
| Media Player | `/admin/ahgSettings/media` | 6 | Player type, autoplay, controls, loop, volume |
| Metadata Extraction | `/admin/ahgSettings/metadata` | 31 | Extract on upload, file types, field mapping |
| Multi-Tenancy | `/admin/ahgSettings/multiTenant` | 4 | Enable, enforce filter, switcher, branding |
| Plugin Management | `/admin/ahgSettings/plugins` | Per-plugin | Enable/disable packages |
| Portable Export | `/admin/ahgSettings/portableExport` | Dynamic | Offline export configuration |
| Sector Numbering | `/admin/ahgSettings/sectorNumbering` | CRUD | Numbering schemes per sector |
| Security | `/admin/ahgSettings/security` | Dynamic | Lockout, password policy, session |
| Services Monitor | `/admin/ahgSettings/services` | Read-only | System services health dashboard |
| Spectrum / Collections | `/admin/ahgSettings/spectrum` | 4+ | Museum procedures, auto-movements |
| System Information | `/admin/ahgSettings/systemInfo` | Read-only | PHP, MySQL, disk, Elasticsearch status |
| Text-to-Speech | `/admin/ahgSettings/tts` | Dedicated | Read-aloud accessibility settings |
| Theme Configuration | `/admin/ahgSettings/themes` | Dedicated | Colours, branding, custom CSS |
| Voice & AI | `/admin/ahgSettings/voiceAi` | 5 | Voice commands, speech recognition, hover read |
| Webhooks | `/admin/ahgSettings/webhooks` | Dedicated | Event-based webhooks for integration |

### Standalone Admin Pages

| Page | URL | Description |
|------|-----|-------------|
| Heritage Accounting | `/heritage/admin` | Standards, rules, regions admin |
| Marketplace | `/admin/marketplace/settings` | Commission, fees, currencies, payouts |
| Media Processing | `/admin/media-processing` | Derivatives, thumbnails, batch regeneration |
| Order Management | `/admin/orders` | Customer order management |
| Privacy Compliance | `/admin/privacy/dashboard` | POPIA/GDPR DSARs, breaches, ROPA |
| Reading Room | `/research/rooms` | Researcher registration, room management |
| Semantic Search | `/semantic-search/admin` | Thesaurus, synonyms, query expansion |
| Watermark Settings | `/admin/acl/watermark-settings` | Image watermark configuration |

---

## Dropdown Manager

All enumerated values (statuses, types, grades) are managed via the Dropdown Manager at `/admin/settings/dropdown`.

**Never hardcode select options** — always use the `ahg_dropdown` table via the Dropdown Manager.

---

## Legacy URLs

All old settings URLs automatically redirect to the new canonical paths:

- `/admin/settings/ahg/{group}` redirects to `/admin/ahgSettings/{group}`
- `/admin/settings/ai-services` redirects to `/admin/ahgSettings/aiServices`
- `/admin/iiif-settings` redirects to `/admin/ahgSettings/carousel`
- `/admin/ecommerce-settings` redirects to `/admin/ahgSettings/ecommerce`
- `/sfPluginAdminPlugin/plugins` redirects to `/admin/ahgSettings/plugins`

---

## Troubleshooting

### Settings Not Saving
1. Check you have administrator privileges
2. Verify the `ahg_settings` table exists
3. Clear route cache: `php artisan route:clear`
4. Check PHP error logs

### Theme Changes Not Visible
1. Clear browser cache (Ctrl+Shift+R)
2. Theme CSS regenerates automatically on save
3. Check `/css/ahg-theme-dynamic.css` loads correctly

---

*Part of the Heratio AHG Framework*
