-- Settings Help Articles — seed/update
-- Run: mysql -u root heratio < packages/ahg-settings/database/seed_help_settings.sql
--
-- Copyright (C) 2026 Johan Pieterse — Plain Sailing Information Systems
-- AGPL-3.0-or-later

-- ── Fix: Remove duplicate setting rows ──
-- Duplicates exist where both low-id (original) and high-id (905xxx) rows have the same name+scope.
-- Keep originals, remove duplicates.

-- federation_enabled: ids 213 (keep) + 905178 (duplicate)
DELETE FROM setting_i18n WHERE id = 905178;
DELETE FROM setting WHERE id = 905178;

-- oai_heritage_format_enabled: ids 212 (keep) + 905177 (duplicate)
DELETE FROM setting_i18n WHERE id = 905177;
DELETE FROM setting WHERE id = 905177;

-- ── 1. Update existing AHG Settings article (id=4) ──
UPDATE help_article
SET body_markdown = '# AHG Settings

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

*Part of the Heratio AHG Framework*',

body_html = '<h1 id="ahg-settings">AHG Settings</h1>
<h2 id="overview">Overview</h2>
<p>The AHG Settings hub is the centralised administration interface for all Heratio configuration. All settings pages use the standardised URL pattern <code>/admin/ahgSettings/{page}</code>.</p>
<hr>
<h2 id="how-to-access">How to Access</h2>
<p>Navigate to <strong>Admin &gt; AHG Settings</strong> or go directly to <code>/admin/ahgSettings</code>.</p>
<p>The dashboard displays tiles for all available settings pages, grouped by function.</p>
<hr>
<h2 id="settings-pages">Settings Pages — Complete Reference</h2>
<h3 id="core-settings">Core Settings (AtoM scope cards)</h3>
<div class="table-responsive"><table class="table table-sm table-bordered">
<thead><tr><th>Page</th><th>URL</th><th>Description</th></tr></thead>
<tbody>
<tr><td>Global</td><td><code>/admin/settings/global</code></td><td>Site title, base URL, search, identifiers, publication status</td></tr>
<tr><td>Default page elements</td><td><code>/admin/settings/page-elements</code></td><td>Toggle logo, title, description, language menu, carousel, map</td></tr>
<tr><td>Default templates</td><td><code>/admin/settings/default-template</code></td><td>Display templates for IO/actor/repo</td></tr>
<tr><td>User interface labels</td><td><code>/admin/settings/interface-labels</code></td><td>Customise labels throughout the UI</td></tr>
<tr><td>Visible elements</td><td><code>/admin/settings/visible-elements</code></td><td>Show/hide fields per descriptive standard</td></tr>
<tr><td>Languages</td><td><code>/admin/settings/languages</code></td><td>I18n language management</td></tr>
<tr><td>OAI repository</td><td><code>/admin/settings/oai</code></td><td>OAI-PMH harvesting settings</td></tr>
<tr><td>Federation</td><td><code>/admin/settings/federation</code></td><td>Federated search settings</td></tr>
</tbody></table></div>
<h3 id="ahg-plugin-settings">AHG Plugin Settings (<code>/admin/ahgSettings/...</code>)</h3>
<div class="table-responsive"><table class="table table-sm table-bordered">
<thead><tr><th>Page</th><th>URL</th><th>Controls</th><th>Description</th></tr></thead>
<tbody>
<tr><td>Accession Management</td><td><code>/admin/ahgSettings/accession</code></td><td>7</td><td>Numbering mask, priority, donor agreement, appraisal</td></tr>
<tr><td>AHG Central</td><td><code>/admin/ahgSettings/ahgIntegration</code></td><td>5</td><td>Cloud NER training, API URL, key, site ID</td></tr>
<tr><td>AI Condition Assessment</td><td><code>/admin/ahgSettings/aiCondition</code></td><td>6 + API clients</td><td>Damage detection, confidence, overlays, auto-scan</td></tr>
<tr><td>AI Services</td><td><code>/admin/ahgSettings/aiServices</code></td><td>28</td><td>NER, summarisation, spell check, translation, Qdrant</td></tr>
<tr><td>Audit Trail</td><td><code>/admin/ahgSettings/audit</code></td><td>9</td><td>Enable logging, what to log, privacy masking</td></tr>
<tr><td>Authority Records</td><td><code>/admin/ahgSettings/authority</code></td><td>13</td><td>Wikidata, VIAF, ULAN, NER stubs, merge/dedup</td></tr>
<tr><td>Background Jobs</td><td><code>/admin/ahgSettings/jobs</code></td><td>7</td><td>Enable, concurrency, timeout, retry, cleanup</td></tr>
<tr><td>Carousel Settings</td><td><code>/admin/ahgSettings/carousel</code></td><td>18</td><td>Homepage collection, viewer type, appearance</td></tr>
<tr><td>Condition Photos</td><td><code>/admin/ahgSettings/photos</code></td><td>9</td><td>Upload path, thumbnails, JPEG quality, EXIF</td></tr>
<tr><td>Cron Jobs</td><td><code>/admin/ahgSettings/cronJobs</code></td><td>Per-job</td><td>Toggle, schedule, run now, output view</td></tr>
<tr><td>Data Ingest</td><td><code>/admin/ahgSettings/ingest</code></td><td>22</td><td>AI processing defaults, output, service availability</td></tr>
<tr><td>Data Protection</td><td><code>/admin/ahgSettings/dataProtection</code></td><td>7+</td><td>POPIA/GDPR, consent, notification</td></tr>
<tr><td>E-Commerce</td><td><code>/admin/ahgSettings/ecommerce</code></td><td>11</td><td>Currency, VAT, PayFast, product pricing</td></tr>
<tr><td>Email Settings</td><td><code>/admin/ahgSettings/email</code></td><td>Dynamic</td><td>SMTP, templates, notifications, error alerts</td></tr>
<tr><td>Encryption</td><td><code>/admin/ahgSettings/encryption</code></td><td>7</td><td>Master toggle, derivatives, field categories</td></tr>
<tr><td>Face Detection</td><td><code>/admin/ahgSettings/faces</code></td><td>Dynamic</td><td>Detection backend, auto-match, blur</td></tr>
<tr><td>FTP / SFTP Upload</td><td><code>/admin/ahgSettings/ftp</code></td><td>9</td><td>Protocol, host, credentials, paths, passive mode</td></tr>
<tr><td>Fuseki / RIC</td><td><code>/admin/ahgSettings/fuseki</code></td><td>12</td><td>SPARQL endpoint, sync, integrity checks</td></tr>
<tr><td>ICIP Settings</td><td><code>/admin/ahgSettings/icipSettings</code></td><td>9</td><td>Cultural notices, consent, Local Contexts Hub</td></tr>
<tr><td>IIIF Viewer</td><td><code>/admin/ahgSettings/iiif</code></td><td>6</td><td>Viewer library, navigator, rotation, zoom</td></tr>
<tr><td>Levels of Description</td><td><code>/admin/ahgSettings/levels</code></td><td>Per-sector</td><td>Assign levels to Archive/Museum/Library/Gallery/DAM</td></tr>
<tr><td>Library Settings</td><td><code>/admin/ahgSettings/library</code></td><td>23+</td><td>Loan rules, circulation, patrons, OPAC, holds</td></tr>
<tr><td>Media Player</td><td><code>/admin/ahgSettings/media</code></td><td>6</td><td>Player type, autoplay, controls, loop, volume</td></tr>
<tr><td>Metadata Extraction</td><td><code>/admin/ahgSettings/metadata</code></td><td>31</td><td>Extract on upload, file types, field mapping</td></tr>
<tr><td>Multi-Tenancy</td><td><code>/admin/ahgSettings/multiTenant</code></td><td>4</td><td>Enable, enforce filter, switcher, branding</td></tr>
<tr><td>Plugin Management</td><td><code>/admin/ahgSettings/plugins</code></td><td>Per-plugin</td><td>Enable/disable packages</td></tr>
<tr><td>Sector Numbering</td><td><code>/admin/ahgSettings/sectorNumbering</code></td><td>CRUD</td><td>Numbering schemes per sector</td></tr>
<tr><td>Security</td><td><code>/admin/ahgSettings/security</code></td><td>Dynamic</td><td>Lockout, password policy, session</td></tr>
<tr><td>Services Monitor</td><td><code>/admin/ahgSettings/services</code></td><td>Read-only</td><td>System services health dashboard</td></tr>
<tr><td>Spectrum / Collections</td><td><code>/admin/ahgSettings/spectrum</code></td><td>4+</td><td>Museum procedures, auto-movements</td></tr>
<tr><td>System Information</td><td><code>/admin/ahgSettings/systemInfo</code></td><td>Read-only</td><td>PHP, MySQL, disk, Elasticsearch status</td></tr>
<tr><td>Text-to-Speech</td><td><code>/admin/ahgSettings/tts</code></td><td>Dedicated</td><td>Read-aloud accessibility settings</td></tr>
<tr><td>Theme Configuration</td><td><code>/admin/ahgSettings/themes</code></td><td>Dedicated</td><td>Colours, branding, custom CSS</td></tr>
<tr><td>Voice &amp; AI</td><td><code>/admin/ahgSettings/voiceAi</code></td><td>5</td><td>Voice commands, speech recognition, hover read</td></tr>
<tr><td>Webhooks</td><td><code>/admin/ahgSettings/webhooks</code></td><td>Dedicated</td><td>Event-based webhooks for integration</td></tr>
</tbody></table></div>
<h3 id="standalone-admin">Standalone Admin Pages</h3>
<div class="table-responsive"><table class="table table-sm table-bordered">
<thead><tr><th>Page</th><th>URL</th><th>Description</th></tr></thead>
<tbody>
<tr><td>Heritage Accounting</td><td><code>/heritage/admin</code></td><td>Standards, rules, regions admin</td></tr>
<tr><td>Marketplace</td><td><code>/admin/marketplace/settings</code></td><td>Commission, fees, currencies, payouts</td></tr>
<tr><td>Media Processing</td><td><code>/admin/media-processing</code></td><td>Derivatives, thumbnails, batch regeneration</td></tr>
<tr><td>Order Management</td><td><code>/admin/orders</code></td><td>Customer order management</td></tr>
<tr><td>Privacy Compliance</td><td><code>/admin/privacy/dashboard</code></td><td>POPIA/GDPR DSARs, breaches, ROPA</td></tr>
<tr><td>Reading Room</td><td><code>/research/rooms</code></td><td>Researcher registration, room management</td></tr>
<tr><td>Semantic Search</td><td><code>/semantic-search/admin</code></td><td>Thesaurus, synonyms, query expansion</td></tr>
<tr><td>Watermark Settings</td><td><code>/admin/acl/watermark-settings</code></td><td>Image watermark configuration</td></tr>
</tbody></table></div>
<hr>
<h2 id="dropdown-manager">Dropdown Manager</h2>
<p>All enumerated values (statuses, types, grades) are managed via the Dropdown Manager at <code>/admin/settings/dropdown</code>.</p>
<p><strong>Never hardcode select options</strong> — always use the <code>ahg_dropdown</code> table via the Dropdown Manager.</p>
<hr>
<h2 id="legacy-urls">Legacy URLs</h2>
<p>All old settings URLs automatically redirect to the new canonical paths:</p>
<ul>
<li><code>/admin/settings/ahg/{group}</code> redirects to <code>/admin/ahgSettings/{group}</code></li>
<li><code>/admin/settings/ai-services</code> redirects to <code>/admin/ahgSettings/aiServices</code></li>
<li><code>/admin/iiif-settings</code> redirects to <code>/admin/ahgSettings/carousel</code></li>
<li><code>/admin/ecommerce-settings</code> redirects to <code>/admin/ahgSettings/ecommerce</code></li>
<li><code>/sfPluginAdminPlugin/plugins</code> redirects to <code>/admin/ahgSettings/plugins</code></li>
</ul>
<hr>
<h2 id="troubleshooting">Troubleshooting</h2>
<h3>Settings Not Saving</h3>
<ol>
<li>Check you have administrator privileges</li>
<li>Verify the <code>ahg_settings</code> table exists</li>
<li>Clear route cache: <code>php artisan route:clear</code></li>
<li>Check PHP error logs</li>
</ol>
<h3>Theme Changes Not Visible</h3>
<ol>
<li>Clear browser cache (Ctrl+Shift+R)</li>
<li>Theme CSS regenerates automatically on save</li>
<li>Check <code>/css/ahg-theme-dynamic.css</code> loads correctly</li>
</ol>
<hr>
<p><em>Part of the Heratio AHG Framework</em></p>',

body_text = 'AHG Settings. Overview. The AHG Settings hub is the centralised administration interface for all Heratio configuration. All settings pages use the standardised URL pattern /admin/ahgSettings/{page}. How to Access. Navigate to Admin > AHG Settings. Settings Pages Complete Reference. Core Settings: Global, Default page elements, Default templates, User interface labels, Visible elements, Languages, OAI repository, Federation. AHG Plugin Settings: Accession Management, AHG Central, AI Condition Assessment, AI Services, Audit Trail, Authority Records, Background Jobs, Carousel Settings, Condition Photos, Cron Jobs, Data Ingest, Data Protection, E-Commerce, Email Settings, Encryption, Face Detection, FTP/SFTP Upload, Fuseki/RIC, ICIP Settings, IIIF Viewer, Levels of Description, Library Settings, Media Player, Metadata Extraction, Multi-Tenancy, Plugin Management, Sector Numbering, Security, Services Monitor, Spectrum/Collections, System Information, Text-to-Speech, Theme Configuration, Voice AI, Webhooks. Standalone Admin Pages: Heritage Accounting, Marketplace, Media Processing, Order Management, Privacy Compliance, Reading Room, Semantic Search, Watermark Settings. Dropdown Manager. Legacy URLs.',

tags = 'settings,admin,configuration,ahgSettings,plugins,theme,email,security,encryption,IIIF,carousel,AI,NER,translation',
updated_at = NOW()

WHERE id = 4;

-- Update sections for search
DELETE FROM help_section WHERE article_id = 4;
INSERT INTO help_section (article_id, heading, anchor, level, body_text, sort_order) VALUES
(4, 'Overview', 'overview', 2, 'Centralised administration interface for all Heratio configuration settings.', 10),
(4, 'How to Access', 'how-to-access', 2, 'Navigate to Admin > AHG Settings or /admin/ahgSettings.', 20),
(4, 'Settings Pages — Complete Reference', 'settings-pages', 2, 'Complete list of all settings pages with URLs and control counts.', 30),
(4, 'Core Settings', 'core-settings', 3, 'Global, Default page elements, Default templates, User interface labels, Visible elements, Languages, OAI repository, Federation.', 31),
(4, 'AHG Plugin Settings', 'ahg-plugin-settings', 3, 'All /admin/ahgSettings/ pages: Accession, AI Services, Audit, Authority, Carousel, E-Commerce, Email, Encryption, and more.', 32),
(4, 'Standalone Admin Pages', 'standalone-admin', 3, 'Heritage Accounting, Marketplace, Media Processing, Orders, Privacy, Reading Room, Semantic Search, Watermark.', 33),
(4, 'Dropdown Manager', 'dropdown-manager', 2, 'All enumerated values managed via /admin/settings/dropdown.', 40),
(4, 'Legacy URLs', 'legacy-urls', 2, 'Old settings URLs automatically redirect to new canonical /admin/ahgSettings/ paths.', 50),
(4, 'Troubleshooting', 'troubleshooting', 2, 'Settings not saving, theme changes not visible.', 60);
