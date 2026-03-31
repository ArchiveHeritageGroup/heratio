# Heratio — Administrator Manual

**For:** System Administrators, IT Staff, Compliance Officers
**Product:** Heratio Framework v2.8.2
**Date:** 16 March 2026
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## About This Manual

This manual covers system administration: settings, backup, security, user management, and infrastructure. For end-user workflows (browse, search, records), see the **User Manual**. For development, see the **Technical Manual**.

---

## 1. Admin Panel Overview

**How to get there:** Admin menu in the navbar (requires administrator role)

```
Admin Menu
├── Plugins                    — Enable/disable AHG plugins
├── Themes                     — Theme selection
├── Settings (base AtoM)       — Core AtoM settings
├── AHG Settings               — 21 sections, 200+ options
├── Users & Groups             — User accounts, roles, ACL
├── Menus                      — Navigation menu management
├── Static pages               — Static page content
├── Visible elements           — Show/hide interface elements
├── Backup & Restore           — Backup management
├── Queue                      — Background job queue
├── Audit Trail                — Activity logging
├── Reports                    — Reporting dashboard
└── Help Center                — Help articles
```

---

## 2. AHG Settings

**How to get there:** Admin > AHG Settings

The central configuration hub. Every option is documented below, organized by section.

### How Settings Work

- Settings are stored in the `ahg_settings` database table
- Each setting has a key, value, group, and type
- Changes take effect immediately (no restart needed)
- Some sections only appear when the related plugin is enabled

---

### 2.1 General — Theme Configuration

Controls the visual appearance of the site.

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Enable AHG Theme | Toggle | On | Master switch for AHG theme customizations. When off, falls back to base AtoM theme. |
| Custom Logo | Text path | (empty) | Path to a custom logo image relative to web root (e.g., `/uploads/logo.png`). Leave blank for default. |
| Primary Color | Color picker | #1a5f7a | Main brand color used in navbar, headings, and primary buttons. |
| Secondary Color | Color picker | #57837b | Accent color used for hover states and secondary elements. |
| Card Header Background | Color picker | #1a5f2a | Background color for all card headers throughout the site. |
| Card Header Text | Color picker | #ffffff | Text color in card headers. Must contrast with Card Header Background. |
| Button Background | Color picker | #1a5f2a | Primary button background color. |
| Button Text | Color picker | #ffffff | Primary button text color. |
| Link Color | Color picker | #1a5f2a | Color for all hyperlinks. |
| Sidebar Background | Color picker | #f8f9fa | Background color for the left sidebar on two-column pages. |
| Sidebar Text | Color picker | #333333 | Text color in the sidebar. |
| Footer Text | Text | (empty) | Custom text displayed in the site footer. Leave blank to hide footer. |
| Show Branding | Toggle | On | Display "Powered by Heratio" branding in footer. |
| Custom CSS | Textarea | (empty) | Additional CSS rules injected after the theme stylesheet. Use for institution-specific styling without modifying theme files. |

**Tips:**
- Use a colour contrast checker to ensure text colours meet WCAG AA (4.5:1 ratio)
- Custom CSS is injected with a CSP nonce — inline styles in the textarea are safe

---

### 2.2 Spectrum — Collections Management

Controls Spectrum 5.1 procedures (UK Collections Trust standard).

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Enable Spectrum | Toggle | On | Master switch. When off, Spectrum features are hidden from all menus and record pages. |
| Default Currency | Select | ZAR | Currency for valuation records. Options: ZAR (South African Rand), USD, EUR, GBP. |
| Valuation Reminder | Number (days) | 365 | System will flag items for re-valuation after this many days since last valuation. Range: 30–1825. |
| Default Loan Period | Number (days) | 90 | Default duration for new loan records. Range: 1–365. |
| Condition Check Interval | Number (days) | 180 | Recommended interval between condition checks. Items overdue for a check are flagged. Range: 30–730. |
| Auto-create Movements | Toggle | On | When an object's location is changed, automatically create a movement record in the audit trail. |
| Require Photos | Toggle | Off | When on, condition reports cannot be saved without at least one photo attached. |
| Email Notifications | Toggle | On | Send email notifications when tasks are assigned (e.g., condition check, valuation due). |

---

### 2.3 Media — Media Player

Controls the HTML5 audio/video player behaviour.

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Player Type | Select | enhanced | **basic:** minimal HTML5 controls. **enhanced:** waveform display, transcription panel, playback speed. |
| Auto-play | Toggle | Off | Auto-play media when the page loads. Note: most browsers block autoplay with sound. |
| Show Controls | Toggle | On | Display player controls. When off, media plays but user cannot pause/seek. |
| Loop Playback | Toggle | Off | Automatically restart media when it reaches the end. |
| Default Volume | Slider | 0.8 | Initial volume level. Range: 0 (muted) to 1.0 (full). |
| Show Download | Toggle | Off | Display a download button on the player. When off, users must use the record's export options. |

---

### 2.4 Photos — Condition Photo Upload

Controls how condition assessment photos are stored and processed.

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Upload Path | Text path | `{atom_root}/uploads/condition_photos` | Absolute filesystem path where condition photos are stored. |
| Max Upload Size | Select | 10 MB | Maximum file size per photo. Options: 5 MB, 10 MB, 20 MB, 50 MB. |
| Create Thumbnails | Toggle | On | Auto-generate thumbnails at three sizes when a photo is uploaded. |
| Thumbnail Small | Pixels | 150 | Maximum dimension for small thumbnails. Range: 50–300. |
| Thumbnail Medium | Pixels | 300 | Maximum dimension for medium thumbnails. Range: 100–600. |
| Thumbnail Large | Pixels | 600 | Maximum dimension for large thumbnails. Range: 300–1200. |
| JPEG Quality | Slider | 85 | Compression quality for generated thumbnails. Range: 60–100. Higher = larger files, better quality. |
| Extract EXIF | Toggle | On | Read camera information (date taken, camera model, GPS) from photo EXIF data. |
| Auto-rotate | Toggle | On | Automatically rotate photos based on EXIF orientation tag. Prevents sideways photos. |

---

### 2.5 Data Protection — Compliance

Controls privacy compliance features.

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Enable Module | Toggle | On | Master switch for data protection features. |
| Default Regulation | Select | POPIA | Default privacy regulation. Options: POPIA (South Africa), GDPR (EU), PAIA (South Africa — access), CCPA (California). |
| Notify Overdue | Toggle | On | Send email when a data subject request exceeds the response deadline. |
| Notification Email | Email | (empty) | Recipient for overdue request notifications. |
| POPIA Request Fee | Number (ZAR) | 50 | Standard fee charged for POPIA information requests. |
| Special Category Fee | Number (ZAR) | 140 | Fee for requests involving special categories of personal information. |
| Response Days | Number (days) | 30 | Deadline in days for responding to data subject requests. Range: 1–90. |

---

### 2.6 IIIF — Image Viewer

Controls the high-resolution image viewer.

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Enable IIIF | Toggle | On | Master switch. When off, images display as simple `<img>` tags. |
| Viewer Library | Select | OpenSeadragon | **OpenSeadragon:** lightweight, fast. **Mirador:** full-featured with annotations. **Leaflet:** map-style viewer. |
| IIIF Server URL | URL | (empty) | URL of an external IIIF Image API server. Leave blank to use the built-in Cantaloupe server. |
| Show Navigator | Toggle | On | Display a mini-map in the corner for orientation on large images. |
| Enable Rotation | Toggle | On | Show rotation controls in the viewer toolbar. |
| Max Zoom Level | Number | 10 | Maximum zoom level. Range: 1–20. Higher values allow deeper zoom on high-resolution images. |

---

### 2.7 Jobs — Background Processing

Controls the background job system.

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Enable Jobs | Toggle | On | Master switch for background job processing. When off, all tasks run synchronously. |
| Max Concurrent | Number | 2 | Maximum jobs running simultaneously. Range: 1–10. Higher values need more server resources. |
| Timeout | Seconds | 3600 | Maximum time a single job can run before being killed. Range: 60–86400 (1 min to 24 hours). |
| Retry Attempts | Number | 3 | How many times a failed job is retried. Range: 0–10. |
| Cleanup After | Days | 30 | Completed jobs are deleted from the database after this many days. Range: 1–365. |
| Notify on Failure | Toggle | On | Send email when a background job fails. |
| Notification Email | Email | (empty) | Recipient for job failure notifications. |

---

### 2.8 Fuseki — RiC Triplestore

Controls the Records in Contexts (RiC) RiC (Records in Contexts) linked data integration.

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| SPARQL Endpoint | URL | http://localhost:3030/ric | Full URL to the Apache Fuseki SPARQL endpoint. |
| Username | Text | admin | Fuseki authentication username. |
| Password | Password | (hidden) | Fuseki authentication password. Not displayed after saving. |
| Enable Auto Sync | Toggle | On | Master switch for all RiC synchronization. When off, no data flows to Fuseki. |
| Use Async Queue | Toggle | On | Queue sync operations for background processing instead of blocking the user. |
| Sync on Save | Toggle | On | Push record data to Fuseki whenever a record is created or updated. |
| Sync on Delete | Toggle | On | Remove record data from Fuseki when a record is deleted in AtoM. |
| Cascade Delete | Toggle | On | When deleting, also remove triples where the deleted record appears as an object (not just subject). |
| Batch Size | Number | 100 | Records per batch during bulk sync operations. Range: 10–1000. |
| Integrity Schedule | Select | weekly | How often to run integrity checks between AtoM and Fuseki. Options: daily, weekly, monthly, disabled. |
| Orphan Retention | Days | 30 | Orphaned triples (in Fuseki but not AtoM) are kept for this many days before cleanup. Range: 1–365. |

---

### 2.9 Metadata — Extraction Configuration

Controls automatic metadata extraction from uploaded files.

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Extract on Upload | Toggle | On | Automatically extract embedded metadata when digital objects are uploaded. |
| Auto-Populate | Toggle | On | Populate AtoM description fields with extracted metadata values. |
| Images | Toggle | On | Extract from image files (EXIF, IPTC, XMP). |
| PDF | Toggle | On | Extract from PDF files (author, title, keywords). |
| Office | Toggle | On | Extract from Office documents (Word, Excel — author, title). |
| Video | Toggle | On | Extract from video files (duration, dimensions, codec). |
| Audio | Toggle | On | Extract from audio files (duration, artist, album). |

**Field Mapping** — configurable per GLAM sector (ISAD, Museum, DAM). Each extracted metadata field can be mapped to a specific AtoM field or set to "none" to skip. Mappable fields: Title, Creator, Keywords, Description, Date Created, Copyright, Technical Data, GPS Location.

---

### 2.10 Faces — Face Detection

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Enable | Toggle | Off | **Experimental.** Enable face detection in digital objects. |
| Backend | Select | local | **local:** OpenCV (free, runs on server). **aws:** AWS Rekognition (cloud, paid). **azure:** Azure Face API (cloud, paid). |

---

### 2.11 Ingest — Data Ingest Defaults

Default settings for the 6-step data ingest wizard.

**AI Processing Toggles:**

| Setting | Default | Backend |
|---------|---------|---------|
| Virus Scan | On | ClamAV |
| OCR | Off | Tesseract |
| NER | Off | Python/spaCy |
| Auto-Summarize | Off | Python |
| Spell Check | Off | aspell |
| Format ID | Off | Siegfried/PRONOM |
| Face Detection | Off | OpenCV/AWS/Azure |
| Auto-Translate | Off | Argos Translate |

**Translation/Spellcheck:**

| Setting | Type | Default |
|---------|------|---------|
| Translate from | Select | English |
| Translate to | Select | Afrikaans |
| Spellcheck language | Select | en_ZA |

**Output Defaults:**

| Setting | Type | Default |
|---------|------|---------|
| Create AtoM records | Toggle | On |
| Generate SIP | Toggle | Off |
| Generate AIP | Toggle | Off |
| Generate DIP | Toggle | Off |
| Generate thumbnails | Toggle | On |
| Generate reference images | Toggle | On |
| SIP/AIP/DIP output paths | Text | (empty) |
| Default sector | Select | archive |
| Default standard | Select | ISAD(G) |

A **Service Availability** dashboard shows the status of each backend (installed/available/unavailable).

---

### 2.12 Portable Export

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Enable | Toggle | On | Allow creation of offline portable catalogues. |
| Retention | Days | 30 | Auto-delete generated exports after this many days. |
| Include Digital Objects | Toggle | On | Include original digital objects in export. |
| Include Thumbnails | Toggle | On | Include thumbnail images. |
| Include References | Toggle | On | Include reference-size images. |
| Include Masters | Toggle | Off | Include master files (large — significantly increases export size). |
| Default Mode | Select | read_only | **read_only:** browse-only viewer. **editable:** allows local editing. |
| Default Language | Select | en | Language for the portable viewer interface. |
| Max Size (MB) | Number | 2048 | Maximum export file size. Range: 100–10240. |
| Show on Description Pages | Toggle | On | Display "Portable Viewer" button on record export options. |
| Show on Clipboard | Toggle | On | Display "Portable Catalogue" option on clipboard page. |

---

### 2.13 Encryption

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Enable Encryption | Toggle | Off | Master switch. **Requires** encryption key at `/etc/atom/encryption.key` (permissions 0600). |
| Encrypt Derivatives | Toggle | On | Also encrypt thumbnail and reference images (not just masters). |
| Contact Details | Toggle | Off | Encrypt email, address, telephone fields. |
| Financial Data | Toggle | Off | Encrypt appraisal values in accession records. |
| Donor Information | Toggle | Off | Encrypt biographical/administrative history. |
| Personal Notes | Toggle | Off | Encrypt internal staff notes. |
| Access Restrictions | Toggle | Off | Encrypt rights notes and restriction details. |

**Algorithm:** XChaCha20-Poly1305 (libsodium, preferred) or AES-256-GCM (OpenSSL fallback).

---

### 2.14 Voice & AI

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Enable Voice | Toggle | On | Enable voice command system for all users. |
| Language | Select | en-US | Recognition language. 11 options: en-US, en-GB, af-ZA, zu-ZA, xh-ZA, st-ZA, fr-FR, pt-PT, es-ES, de-DE. |
| Confidence | Slider | 0.4 | Minimum confidence threshold. Lower = more lenient (may misrecognize). Higher = stricter. Range: 0.3–0.95. |
| Speech Rate | Slider | 1.0 | Text-to-speech playback speed. Range: 0.5 (slow) to 2.0 (fast). |
| Continuous Listen | Toggle | Off | Keep microphone active after each command (hands-free mode). |
| Floating Button | Toggle | On | Show floating microphone button on all pages (bottom-right). |
| Hover Read | Toggle | On | Read button/link text aloud when mouse hovers. |
| Hover Delay | Slider | 400ms | Delay before hover-read activates. Range: 100–1000ms. |
| LLM Provider | Select | hybrid | **local:** Ollama only. **cloud:** Anthropic Claude only. **hybrid:** try local, fall back to cloud. |
| Daily Cloud Limit | Number | 50 | Maximum cloud API calls per day. 0 = unlimited. |
| Local LLM URL | URL | http://localhost:11434 | Ollama API endpoint. |
| Local LLM Model | Text | llava:7b | Vision model name (must be pulled in Ollama). |
| Timeout | Seconds | 30 | LLM request timeout. Range: 5–300. |
| Cloud API Key | Password | (hidden) | Anthropic API key. Stored encrypted. |
| Cloud Model | Text | claude-sonnet-4-20250514 | Anthropic model ID for image description. |
| Audit AI Calls | Toggle | On | Log every AI image description request to the audit trail. |

---

### 2.15 Integrity — Verification

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Enable | Toggle | On | Master switch for integrity verification. |
| Auto Baselines | Toggle | On | Automatically generate checksum baselines for objects that don't have one. |
| Algorithm | Select | sha256 | **sha256:** faster, sufficient for most use. **sha512:** more secure, slower. |
| Batch Size | Number | 200 | Objects processed per verification run. 0 = unlimited. Range: 0–50000. |
| IO Throttle | Milliseconds | 10 | Pause between objects to reduce disk I/O impact. Range: 0–1000. |
| Max Runtime | Minutes | 120 | Maximum duration for a verification run. Range: 1–1440 (24 hours). |
| Max Memory | MB | 512 | Memory limit per run. Range: 64–4096. |
| Dead Letter | Number | 3 | Consecutive failures on an object before escalation alert. Range: 1–100. |
| Notify on Failure | Toggle | On | Email when a verification run fails. |
| Notify on Mismatch | Toggle | On | Email when a file's checksum doesn't match its baseline. |
| Alert Email | Email | (empty) | Notification recipient. |
| Webhook URL | URL | (empty) | POST notifications to Slack, Teams, or PagerDuty. |

---

### 2.16 Accession — Intake Settings

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Numbering Mask | Text | ACC-{YYYY}-{####} | Pattern for auto-generated accession numbers. `{YYYY}` = year, `{####}` = zero-padded sequence. |
| Default Priority | Select | normal | Default for new accessions. Options: low, normal, high, urgent. |
| Auto-Assign | Toggle | Off | Automatically assign new accessions to the creating archivist. |
| Require Donor Agreement | Toggle | Off | Block finalization until a donor agreement is attached. |
| Require Appraisal | Toggle | Off | Block finalization until appraisal is completed. |
| Allow Container Barcodes | Toggle | Off | Enable barcode scanning for accession containers. |
| Rights Inheritance | Toggle | Off | Automatically copy rights from the donor agreement to the accession. |

---

### 2.17 Authority — Authority Records

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Wikidata | Toggle | Off | Enable Wikidata entity linking and reconciliation. |
| VIAF | Toggle | Off | Virtual International Authority File linking. |
| Getty ULAN | Toggle | Off | Union List of Artist Names linking. |
| LCNAF | Toggle | Off | Library of Congress Name Authority File. |
| ISNI | Toggle | Off | International Standard Name Identifier. |
| Auto-Verify Wikidata | Toggle | Off | Automatically mark Wikidata identifiers as verified. |
| Auto-Recalculate Completeness | Toggle | On | Recalculate completeness scores when records are saved. |
| Hide Stubs from Public | Toggle | On | Stub-level authority records are hidden from public browse/search. |
| NER Auto-Create Stubs | Toggle | Off | Auto-create authority stubs from NER-extracted entities. |
| NER Confidence Threshold | Number | 0.85 | Minimum NER confidence to auto-create. Range: 0.0–1.0. |
| Require Approval for Merge | Toggle | Off | Merging authorities requires workflow approval. |
| Dedup Threshold | Number | 0.80 | Similarity score for duplicate detection. Range: 0.0–1.0. |
| Function Linking | Toggle | On | Enable ISDF actor-to-function structured linking. |

---

### 2.18 Security — Access Control

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Password Expiry | Days | 90 | Force password change after this many days. 0 = never expires. Range: 0–365. |
| Password History | Number | 5 | Remember this many previous passwords (user cannot reuse them). 0 = disabled. Range: 0–24. |
| Expiry Warning | Days | 14 | Show warning this many days before password expires. Range: 0–90. |
| Show Expiry Notification | Toggle | On | Display flash notification on login when password is expiring soon. |
| Force Password Change | Toggle | Off | When on, expired passwords redirect to the change password page. When off, user sees a warning but can continue. |
| Enable Lockout | Toggle | On | Lock accounts after too many failed login attempts. |
| Max Failed Attempts | Number | 5 | Number of consecutive failures before lockout. Range: 1–20. |
| Lockout Duration | Minutes | 15 | How long the account stays locked. Range: 1–1440 (24 hours). |
| Session Timeout | Minutes | 30 | Idle sessions expire after this many minutes. Range: 5–480 (8 hours). |
| Login Attempt Retention | Hours | 24 | Failed login attempt records are kept for this many hours. Range: 1–720 (30 days). |

**Security Status** — displays active protections: Session Fixation Prevention, CSRF Protection, Security Headers, HttpOnly Cookies, Bell-LaPadula MAC, SSRF Protection, XXE Protection.

---

### 2.19 Library — Circulation

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Loan Rules | Table | (per type) | Configurable rules per material type and patron type: loan days, renewal days, max renewals, fine per day, fine cap, grace period, loanable flag. |
| Default Loan Period | Days | 14 | Default when no specific rule applies. |
| Default Max Renewals | Number | 2 | Default renewal count. |
| Currency | Text | ZAR | Currency code for fines (3 characters). |
| Auto Fine | Toggle | (varies) | Automatically generate fine records for overdue items. |

---

### 2.20 FTP — File Transfer

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Protocol | Select | SFTP | **SFTP** (recommended, encrypted) or **FTP** (unencrypted). |
| Host | Text | (empty) | Server hostname or IP address. |
| Port | Number | 22 | Port number. 22 for SFTP, 21 for FTP. Range: 1–65535. |
| Username | Text | (empty) | Login username. |
| Password | Password | (hidden) | Login password. Leave blank to keep current value. |
| Remote Path | Text | /uploads | Base path as seen by the FTP/SFTP user. |
| Server Disk Path | Text | (empty) | Actual filesystem path where uploaded files are stored. |
| Passive Mode | Toggle | On | Use passive mode for FTP connections. Always on for SFTP. |

---

## 3. Backup & Restore

**How to get there:** Admin > Backup & Restore

### 3.1 Dashboard

```
┌────────────────────┬──────────────────────────────────┐
│  Database Info      │                                  │
│  Storage Info       │  BACKUP HISTORY TABLE             │
│  Quick Actions      │  Date | Type | Components | Size  │
│  ├─ DB Only        │  [Restore] [Download] [Delete]   │
│  ├─ Full Backup    │                                  │
│  └─ Incremental    │                                  │
│                    │                                  │
│  Schedules          │                                  │
│  ├─ [+] Add        │                                  │
│  ├─ Daily DB ✓     │                                  │
│  └─ Weekly Full ✓  │                                  │
│  Cron: 0 * * * *   │                                  │
└────────────────────┴──────────────────────────────────┘
```

### 3.2 Creating Backups

**Manual:** Click "Create Backup" and select components (database, uploads, plugins, framework).

**Quick Actions:**
- **Database Only** — fast, database only
- **Full Backup** — all components
- **Incremental** — only changes since last full backup

### 3.3 Scheduled Backups

Click **+** in the Schedules card:

| Field | Description |
|-------|-------------|
| Name | Descriptive label (e.g., "Daily DB Backup") |
| Frequency | Hourly, Daily, Weekly, Monthly |
| Time | When to run (24-hour, e.g., 02:00) |
| Day of Week | For weekly (Sunday–Saturday) |
| Day of Month | For monthly (1–28) |
| Retention | Days to keep old backups |
| Components | Database, Uploads, Plugins, Framework checkboxes |

**Required cron entry:**
```
0 * * * * cd /usr/share/nginx/archive && php symfony backup:run-scheduled >> /var/log/atom/backup-cron.log 2>&1
```

**Recommended strategy:**

| Schedule | Frequency | Components | Retention |
|----------|-----------|-----------|-----------|
| Daily DB | Daily 02:00 | Database only | 30 days |
| Weekly Full | Weekly Sunday 03:00 | All | 90 days |
| Monthly Archive | Monthly 1st 04:00 | All | 365 days |

### 3.4 Restoring

1. Click **Restore** on any backup in the history table
2. Select which components to restore
3. Confirm — system backs up current state first, then restores

### 3.5 Upload Restore

1. Click **Upload Backup**
2. Upload a `.tar.gz`, `.sql.gz`, or `.zip` file
3. System validates and detects components
4. Select what to restore and confirm

### 3.6 Settings

**Admin > Backup & Restore > Settings:**

| Setting | Default | Description |
|---------|---------|-------------|
| Backup Path | /var/backups/atom | Where backups are stored |
| Log Path | /var/log/atom/backup.log | Backup log file |
| Max Backups | 30 | Maximum backups to keep |
| Retention Days | 90 | Delete backups older than this |
| Notification Email | (empty) | Email for success/failure alerts |
| Notify on Success | Off | Email on successful backup |
| Notify on Failure | On | Email on failed backup |

### 3.7 CLI

```bash
php symfony backup:run-scheduled              # Run due schedules
php symfony backup:run-scheduled --dry-run    # Preview what would run
php symfony backup:run-scheduled --force      # Run all active schedules now
```

---

## 4. User Management

**How to get there:** Admin > Users & Groups

### 4.1 Users

- Create, edit, deactivate user accounts
- Assign to groups (editor, contributor, administrator, etc.)
- Set security clearance level (for classified records)
- View login history and audit trail

### 4.2 Groups & ACL

- Define permission groups with granular ACL rules
- Control access per module, per action, per repository
- Inherit permissions from parent groups

---

## 5. Security

### 5.1 Security Classification

Bell-LaPadula mandatory access control:

```
Top Secret ────── Only Top Secret clearance users
    │
  Secret ────── Secret or higher
    │
Confidential ── Confidential or higher
    │
 Restricted ─── Restricted or higher
    │
Unclassified ── Everyone
```

Assign classification to records and clearance to users. The system enforces "no read up, no write down."

### 5.2 Audit Trail

Every create, update, delete operation is logged with:
- Who (user)
- What (entity type, entity ID)
- When (timestamp)
- What changed (field-level diff)

**View:** Admin > Audit Trail

### 5.3 Error Log

**View:** Admin > AHG Settings > Error Log

Application errors logged in `ahg_error_log` table. Resolve errors to clear the log.

---

## 6. Queue Management

**How to get there:** Admin > Queue

Monitor and manage background jobs:

| Column | Description |
|--------|-------------|
| Job ID | Unique identifier |
| Type | Job type (ingest, export, AI, etc.) |
| Status | pending, running, completed, failed |
| Progress | Percentage or step indicator |
| Created | When the job was queued |
| Started | When processing began |
| Duration | Elapsed time |

**Actions:** Retry failed jobs, cancel pending jobs, view error details.

**CLI:**
```bash
php bin/atom queue:work              # Start processing
php bin/atom queue:status            # Show queue status
php bin/atom queue:retry --id=123    # Retry a failed job
php bin/atom queue:failed            # List failed jobs
php bin/atom queue:cleanup           # Remove old completed jobs
```

---

## 7. Reports & Statistics

### 7.1 Reports Dashboard

Pre-built reports on collections, users, compliance status.

### 7.2 Report Builder

Enterprise report builder with:
- Rich text editor (Quill.js)
- SQL query data sources
- Sections and templates
- Export to Word, PDF, XLSX, CSV
- Scheduling and sharing

### 7.3 Statistics

Usage statistics dashboard with:
- Record counts by type, level, repository
- User activity trends
- Digital object statistics
- Search query analytics

---

## 8. Infrastructure

### 8.1 Cache Management

```bash
# Clear all caches
rm -rf /usr/share/nginx/archive/cache/*
php symfony cc
sudo systemctl restart php8.3-fpm
```

### 8.2 Search Index

```bash
# Rebuild Elasticsearch index
php symfony search:populate

# Check index status
php symfony search:status
```

### 8.3 Plugin Management

```bash
php bin/atom extension:discover      # Find new plugins
php bin/atom extension:enable <name> # Enable a plugin
php bin/atom extension:disable <name># Disable a plugin
php bin/atom extension:list          # List all plugins
```

### 8.4 Services

```bash
sudo systemctl restart php8.3-fpm     # PHP
sudo systemctl restart cantaloupe      # IIIF image server
sudo systemctl restart elasticsearch   # Search
```

---

*Heratio Framework v2.8.2 — The Archive and Heritage Group (Pty) Ltd*
