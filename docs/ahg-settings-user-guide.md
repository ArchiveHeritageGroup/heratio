# AHG Settings

## User Guide

Centralized administration interface for managing all AtoM configuration settings, plugins, and AHG extensions in one place.

---

## Overview
```
+-------------------------------------------------------------+
|                     AHG SETTINGS HUB                         |
+-------------------------------------------------------------+
|                                                              |
|  +----------+   +----------+   +----------+   +----------+  |
|  |  Global  |   |  Plugin  |   |  Theme   |   |   AI     |  |
|  | Settings |   | Manager  |   | Config   |   | Services |  |
|  +----+-----+   +----+-----+   +----+-----+   +----+-----+  |
|       |              |              |              |         |
|       v              v              v              v         |
|  +---------------------------------------------------+      |
|  |              UNIFIED SETTINGS DASHBOARD            |      |
|  +---------------------------------------------------+      |
|                                                              |
+-------------------------------------------------------------+
```

---

## Key Features
```
+-------------------------------------------------------------+
|                    SETTINGS CAPABILITIES                     |
+-------------------------------------------------------------+
|  [Cog] Global         - Core AtoM behavior and display       |
|  [Puzzle] Plugins     - Enable/disable extensions            |
|  [Palette] Theme      - Colors, branding, layout             |
|  [List] Dropdowns     - Controlled vocabularies/taxonomies   |
|  [Robot] AI Services  - NER, translation, summarization      |
|  [Key] API Keys       - Manage API access tokens             |
|  [Envelope] Email     - SMTP and notification settings       |
|  [Numbers] Numbering  - GLAM/DAM identifier schemes          |
|  [Cloud] Preservation - Backup replication targets           |
|  [Download] Export    - Export/import settings               |
+-------------------------------------------------------------+
```

---

## How to Access
```
  Main Menu
      |
      v
   Admin  ---------> Admin Settings
      |
      v
   AHG Settings ---------------+
      |                        |
      +---> Settings Overview  |
      |                        |
      +---> Core AtoM Settings |
      |                        |
      +---> AHG Plugin Settings
```

**Direct URL:** `/admin/ahg-settings`

---

## Settings Overview Dashboard

When you open AHG Settings, you see a card-based dashboard:

```
+-------------------------------------------------------------+
|  [Cog] AHG Plugin Settings                                  |
+-------------------------------------------------------------+

+-------------------+  +-------------------+  +-------------------+
|  [Envelope]       |  |  [Landmark]       |  |  [Cloud]          |
|  AI Services      |  |  Heritage         |  |  Preservation     |
|                   |  |  Platform         |  |  & Backup         |
|  NER, translate,  |  |  Access control,  |  |  Replication      |
|  summarize        |  |  analytics        |  |  targets          |
|                   |  |                   |  |                   |
|   [Configure]     |  |   [Admin]         |  |   [Configure]     |
+-------------------+  +-------------------+  +-------------------+

+-------------------+  +-------------------+  +-------------------+
|  [Photo-Video]    |  |  [Cog]            |  |  [Puzzle-Piece]   |
|  Digital Asset    |  |  Section          |  |  Plugin           |
|  Management       |  |  Settings         |  |  Management       |
|                   |  |                   |  |                   |
|  PDF merge,       |  |  Theme, metadata, |  |  Enable/disable   |
|  3D viewer        |  |  IIIF, jobs       |  |  extensions       |
|                   |  |                   |  |                   |
|   [Open Tools]    |  |   [Configure]     |  |   [Manage]        |
+-------------------+  +-------------------+  +-------------------+
```

---

## Global Settings

Configure core AtoM behavior from the Global Settings page:

### Step 1: Open Global Settings

Navigate to **Admin** > **AHG Settings** > **Global**

### Step 2: Configure Sections

Settings are organized into expandable accordion sections:

```
+-------------------------------------------------------------+
|  GLOBAL SETTINGS                                             |
+-------------------------------------------------------------+
|  [v] Version                                                 |
|      - AtoM version display                                  |
|      - Check for updates toggle                              |
+-------------------------------------------------------------+
|  [v] Search and Browse                                       |
|      - Hits per page                                         |
|      - Sort order (user/anonymous)                           |
|      - Default browse views                                  |
|      - Escape queries setting                                |
+-------------------------------------------------------------+
|  [v] Presentation                                            |
|      - Show tooltips                                         |
|      - Draft notification enabled                            |
+-------------------------------------------------------------+
|  [v] Multi-repository                                        |
|      - Enable multi-repository mode                          |
|      - Institutional scoping                                 |
+-------------------------------------------------------------+
|  [v] Permalinks                                              |
|      - Slug basis (title/identifier/reference code)          |
|      - Permissive slug creation                              |
+-------------------------------------------------------------+
|  [v] System                                                  |
|      - Audit log enabled                                     |
|      - Generate reports as public user                       |
|      - Cache XML on save                                     |
|      - Default publication status                            |
+-------------------------------------------------------------+
|  [v] Integrations                                            |
|      - Google Maps API key                                   |
|      - SWORD deposit directory                               |
+-------------------------------------------------------------+
```

### Step 3: Save Changes

Click the **Save** button at the bottom of the form.

---

## Section-Based Settings

Manage AHG plugin settings organized by category:

```
+-------------------------+-----------------------------------+
|  SETTINGS SECTIONS      |  SETTINGS FORM                    |
+-------------------------+-----------------------------------+
|                         |                                   |
|  [Active] General       |  Theme Configuration              |
|                         |  +--------------------------+     |
|  [ ] Multi-Tenancy      |  | Enable AHG Theme    [X] |     |
|                         |  +--------------------------+     |
|  [ ] Metadata           |  | Custom Logo Path        |     |
|                         |  | [/uploads/logo.png    ] |     |
|  [ ] IIIF Viewer        |  +--------------------------+     |
|                         |  | Primary Color           |     |
|  [ ] Spectrum           |  | [#] [#1a5f7a         ] |     |
|                         |  +--------------------------+     |
|  [ ] Data Protection    |  | Secondary Color         |     |
|                         |  | [#] [#57837b         ] |     |
|  [ ] Face Detection     |  +--------------------------+     |
|                         |                                   |
|  [ ] Media Player       |  Extended Color Options           |
|                         |  +--------------------------+     |
|  [ ] Condition Photos   |  | Card Header BG          |     |
|                         |  | Button Colors           |     |
|  [ ] Ingest             |  | Link Color              |     |
|                         |  | Sidebar Colors          |     |
|  [ ] Background Jobs    |  +--------------------------+     |
|                         |                                   |
|  [ ] Fuseki / RIC       |                                   |
|                         |                                   |
+-------------------------+-----------------------------------+
|  Quick Actions          |            [Save Settings]        |
|  [Export Settings]      |                                   |
|  [Import Settings]      |                                   |
|  [Reset to Defaults]    |                                   |
+-------------------------+-----------------------------------+
```

### Available Sections

| Section | Description | Settings |
|---------|-------------|----------|
| General | Theme and branding | Logo, colors, CSS |
| Multi-Tenancy | Repository isolation | Tenant filtering, branding |
| Metadata | Auto-extraction | EXIF, XMP, IPTC, thumbnails |
| IIIF Viewer | Image viewing | Navigator, rotation, fullscreen |
| Spectrum | Collections management | Auto-movements, photos |
| Data Protection | Privacy compliance | POPIA, GDPR settings |
| Face Detection | Facial recognition | Auto-match, blur settings |
| Media Player | Audio/video | Autoplay, waveform, transcription |
| Photos | Condition documentation | Thumbnails, EXIF, rotation |
| Ingest | Data ingestion defaults | AI processing, output, derivatives |
| Jobs | Background processing | Queue settings, notifications |
| Fuseki / RIC | Linked data | RDF sync, cascade deletes |

---

## Plugin Management

Enable or disable plugins from a central interface:

### Step 1: Open Plugin Manager

Navigate to **Admin** > **AHG Settings** > **Plugins**

### Step 2: View Plugins by Category

```
+-------------------------------------------------------------+
|  PLUGIN MANAGEMENT                                           |
+-------------------------------------------------------------+
|  Filter: [All] [Core] [Themes] [AHG] [Integrations] [Other] |
+-------------------------------------------------------------+

+---------------------+  +---------------------+  +---------------------+
| [Core]   [Enabled]  |  | [AHG]    [Enabled]  |  | [Theme]  [Enabled]  |
| sfWebBrowserPlugin  |  | ahgLibraryPlugin    |  | ahgThemeB5Plugin    |
|                     |  |                     |  |                     |
| Core framework      |  | Library cataloging  |  | Bootstrap 5 theme   |
| plugin              |  | and search          |  | (Locked)            |
|                     |  |                     |  |                     |
| [Locked]            |  | [Disable]           |  | [Locked]            |
+---------------------+  +---------------------+  +---------------------+
```

### Step 3: Enable or Disable

- Click **Enable** to activate a disabled plugin
- Click **Disable** to deactivate (if not locked)
- **Locked** plugins cannot be disabled (core requirements)

### Important Notes

- Cache is automatically cleared after changes
- Some plugins have dependencies that prevent disabling
- After changes, you may need to refresh the page

---

## Dropdown Management

Manage controlled vocabularies for plugin dropdowns (statuses, types, grades, etc.):

### Step 1: Open Dropdown Manager

Navigate to **Admin** > **Dropdowns** or `/admin/dropdowns`

### Step 2: View Taxonomies

```
+-------------------------------------------------------------+
|  DROPDOWN MANAGEMENT                                         |
+-------------------------------------------------------------+
|  Filter: [All Taxonomies v]                    [+ Add Term]  |
+-------------------------------------------------------------+

+-------------------------+-------+--------+--------+----------+
| Taxonomy                | Terms | Active | Colors | Actions  |
+-------------------------+-------+--------+--------+----------+
| Loan Status             |   8   |   8    |   Yes  | [Edit]   |
| Loan Type               |   5   |   5    |   No   | [Edit]   |
| Condition Grade         |   5   |   5    |   Yes  | [Edit]   |
| Embargo Type            |   4   |   4    |   Yes  | [Edit]   |
| Embargo Reason          |   9   |   9    |   No   | [Edit]   |
| Workflow Status         |   9   |   9    |   Yes  | [Edit]   |
| Equipment Type          |  12   |  12    |   No   | [Edit]   |
| Creator Role            |  12   |  12    |   No   | [Edit]   |
+-------------------------+-------+--------+--------+----------+
```

### Step 3: Edit Terms

Click **[Edit]** to manage terms within a taxonomy:

```
+-------------------------------------------------------------+
|  LOAN STATUS TERMS                                           |
+-------------------------------------------------------------+
|                                                              |
|  +-----+--------+------------------+-------+-------+-------+ |
|  | # | Code    | Label            | Color | Order | Active| |
|  +-----+--------+------------------+-------+-------+-------+ |
|  | 1 | draft   | Draft            |#9e9e9e|  10   | [X]   | |
|  | 2 | pending | Pending Approval |#ff9800|  20   | [X]   | |
|  | 3 | approved| Approved         |#8bc34a|  30   | [X]   | |
|  | 4 | active  | Active           |#4caf50|  40   | [X]   | |
|  | 5 | overdue | Overdue          |#e91e63|  60   | [X]   | |
|  +-----+--------+------------------+-------+-------+-------+ |
|                                                              |
|  [+ Add Term]                                                |
|                                                              |
+-------------------------------------------------------------+
```

### Available Taxonomies (35)

| Category | Taxonomies | Description |
|----------|------------|-------------|
| **Exhibition** | `exhibition_type`, `exhibition_status`, `exhibition_object_status` | Exhibition management |
| **Loans** | `loan_status`, `loan_type` | Loan tracking |
| **Workflow** | `workflow_status`, `rtp_status` | Process states |
| **Rights** | `rights_basis`, `copyright_status`, `act_type`, `restriction_type` | Rights management |
| **Embargo** | `embargo_type`, `embargo_reason`, `embargo_status` | Access restrictions |
| **Condition** | `condition_grade`, `damage_type`, `report_type`, `image_type` | Condition reporting |
| **Shipping** | `shipment_type`, `shipment_status`, `cost_type` | Courier management |
| **Research** | `id_type`, `organization_type`, `equipment_type`, `equipment_condition`, `workspace_privacy` | Reading room |
| **Library** | `creator_role` | Bibliographic roles |
| **Documents** | `document_type`, `reminder_type` | Agreement management |
| **Export** | `rdf_format` | Metadata export |
| **Agreements** | `agreement_status` | Donor agreements |
| **Links** | `link_status` | Getty/vocabulary links |

### Adding a New Term

```
+-------------------------------------------------------------+
|  ADD TERM TO: Loan Status                                    |
+-------------------------------------------------------------+
|                                                              |
|  Code:        [extended     ]  (lowercase, no spaces)        |
|  Label:       [Extended Loan]                                |
|  Color:       [#] [9c27b0   ]  (optional hex color)          |
|  Icon:        [fa-clock     ]  (optional FontAwesome class)  |
|  Sort Order:  [55           ]  (display position)            |
|  Default:     [ ] Set as default selection                   |
|                                                              |
|                              [Cancel]  [Add Term]            |
+-------------------------------------------------------------+
```

### Term Properties

| Property | Description |
|----------|-------------|
| Code | Unique identifier (stored in database) |
| Label | Display text shown to users |
| Color | Hex color for status badges |
| Icon | FontAwesome icon class |
| Sort Order | Display sequence (lower = first) |
| Is Default | Pre-selected in dropdowns |
| Is Active | Soft delete (inactive = hidden) |

---

## AI Services Settings

Configure AI-powered features for your archive:

```
+-------------------------------------------------------------+
|  AI SERVICES CONFIGURATION                                   |
+-------------------------------------------------------------+
|                                                              |
|  Service Status                                              |
|  +--------------------------------------------------------+ |
|  | NER (Named Entity Recognition)     [X] Enabled         | |
|  | Summarizer                         [X] Enabled         | |
|  | Spell Checker                      [ ] Disabled        | |
|  | Translation                        [X] Enabled         | |
|  +--------------------------------------------------------+ |
|                                                              |
|  API Configuration                                           |
|  +--------------------------------------------------------+ |
|  | API URL:     [http://192.168.0.112:5004/ai/v1        ] | |
|  | API Key:     [ahg_ai_demo_internal_2026              ] | |
|  | Timeout:     [60] seconds                              | |
|  +--------------------------------------------------------+ |
|                                                              |
|  NER Settings                                                |
|  +--------------------------------------------------------+ |
|  | Entity Types: [X] Person  [X] Org  [X] Place  [X] Date | |
|  | Auto-extract on upload: [ ]                            | |
|  +--------------------------------------------------------+ |
|                                                              |
|  Translation Settings                                        |
|  +--------------------------------------------------------+ |
|  | Source Language: [English    v]                        | |
|  | Target Language: [Afrikaans  v]                        | |
|  | Mode:            [Review v] (requires approval)        | |
|  | Sector:          [Archives  v]                         | |
|  +--------------------------------------------------------+ |
|                                                              |
+-------------------------------------------------------------+
```

### Supported Languages

Translation supports South African and international languages:
- English, Afrikaans, Zulu, Xhosa, Sotho, Tswana
- French, German, Spanish, Portuguese, Dutch
- Arabic, Russian, Chinese, and more

---

## API Key Management

Manage programmatic access to your AtoM instance:

### Step 1: Open API Keys

Navigate to **Admin** > **AHG Settings** > **API Keys**

### Step 2: Create New Key

```
+-------------------------------------------------------------+
|  CREATE API KEY                                              |
+-------------------------------------------------------------+
|  Name:        [Reporting Dashboard             ]            |
|  User:        [Johan Pieterse                  v]           |
|  Scopes:      [X] Read  [ ] Write  [ ] Delete               |
|  Rate Limit:  [1000] requests per hour                      |
|  Expires:     [2026-12-31                      ]            |
|                                                              |
|                                      [Create API Key]        |
+-------------------------------------------------------------+
```

### Step 3: Copy Key (One Time Only!)

```
+-------------------------------------------------------------+
|  [!] API KEY CREATED SUCCESSFULLY                            |
|                                                              |
|  Your new API key (copy now - shown only once):              |
|  +--------------------------------------------------------+ |
|  | ahg_7f8e9d0c1b2a3e4f5g6h7i8j9k0l1m2n3o4p5q6r7s8t9u0v  | |
|  +--------------------------------------------------------+ |
|                                            [Copy to Clipboard]|
+-------------------------------------------------------------+
```

### Manage Existing Keys

```
+-------+----------------+----------+-------------+------------+
| Name  | User           | Scopes   | Last Used   | Actions    |
+-------+----------------+----------+-------------+------------+
| ahg_7 | Johan Pieterse | read     | 2 hours ago | [Toggle]   |
|       |                |          |             | [Delete]   |
+-------+----------------+----------+-------------+------------+
| ahg_3 | System         | read,    | Yesterday   | [Toggle]   |
|       |                | write    |             | [Delete]   |
+-------+----------------+----------+-------------+------------+
```

---

## Email Settings

Configure SMTP and notification settings:

```
+-------------------------------------------------------------+
|  EMAIL CONFIGURATION                                         |
+-------------------------------------------------------------+
|                                                              |
|  SMTP Settings                                               |
|  +--------------------------------------------------------+ |
|  | Host:       [smtp.gmail.com                           ] | |
|  | Port:       [587                                      ] | |
|  | Security:   [TLS v]                                     | |
|  | Username:   [notifications@archive.org                ] | |
|  | Password:   [**********                               ] | |
|  +--------------------------------------------------------+ |
|                                                              |
|  From Address                                                |
|  +--------------------------------------------------------+ |
|  | Email:      [no-reply@archive.org                     ] | |
|  | Name:       [Archive Notifications                    ] | |
|  +--------------------------------------------------------+ |
|                                                              |
|  [Test Email]                               [Save Settings]  |
|                                                              |
+-------------------------------------------------------------+
```

### Test Email

Click **Test Email** to verify your SMTP configuration works correctly.

---

## Numbering Schemes

Configure automatic identifier generation for GLAM/DAM records:

### Step 1: Open Numbering Schemes

Navigate to **Admin** > **AHG Settings** > **Numbering Schemes**

### Step 2: View Available Schemes

```
+-------------------------------------------------------------+
|  NUMBERING SCHEMES                                           |
+-------------------------------------------------------------+
|  Filter: [All v] [Archive] [Library] [Museum] [Gallery] [DAM]|
+-------------------------------------------------------------+

+---------------------+---------------------------+-------------+
| Name                | Pattern                   | Actions     |
+---------------------+---------------------------+-------------+
| Archive Standard    | {REPO}/{FONDS}/{SEQ:4}    | [Default]   |
| [*] Default         |                           | [Reset]     |
+---------------------+---------------------------+-------------+
| Archive Year-Based  | {YEAR}/{SEQ:4}            | [Set Default]|
|                     |                           | [Reset]     |
+---------------------+---------------------------+-------------+
| Library Accession   | LIB{YEAR}{SEQ:5}          | [Default]   |
| [*] Default         |                           | [Reset]     |
+---------------------+---------------------------+-------------+
| Museum Object       | {YEAR}.{SEQ:4}            | [Default]   |
| [*] Default         |                           | [Reset]     |
+---------------------+---------------------------+-------------+
```

### Available Tokens

| Token | Description | Example |
|-------|-------------|---------|
| {SEQ:N} | Sequence with N digits | {SEQ:4} = 0001 |
| {YEAR} | Current year | 2026 |
| {REPO} | Repository code | NARSSA |
| {FONDS} | Fonds identifier | F001 |
| {DEPT} | Department code | ART |
| {TYPE} | Media type | IMG |

---

## Preservation & Backup

Configure replication targets for disaster recovery:

```
+-------------------------------------------------------------+
|  PRESERVATION & BACKUP                                       |
+-------------------------------------------------------------+
|                                                              |
|  Statistics                                                  |
|  +----------+----------+----------+----------+----------+   |
|  | Total    | Active   | Syncs    | Success  | Failed   |   |
|  | Targets  | Targets  | Total    | Rate     | Syncs    |   |
|  |    3     |    2     |   156    |   98%    |    3     |   |
|  +----------+----------+----------+----------+----------+   |
|                                                              |
|  Replication Targets                                         |
|  +-----------------------------------------------------+    |
|  | Name              | Type    | Status   | Last Sync  |    |
|  +-----------------------------------------------------+    |
|  | Local Backup      | local   | Active   | 2 hours    |    |
|  | Off-site SFTP     | sftp    | Active   | Yesterday  |    |
|  | AWS S3            | s3      | Inactive | 1 week     |    |
|  +-----------------------------------------------------+    |
|                                                              |
|  [Add Target]                                                |
|                                                              |
+-------------------------------------------------------------+
```

### Target Types

| Type | Use Case | Configuration |
|------|----------|---------------|
| Local | On-server backup | Path only |
| SFTP | Remote server | Host, port, user, path |
| Rsync | Efficient sync | Host, port, user, path |
| S3 | Cloud storage | Bucket, region |

---

## Export and Import Settings

### Export Settings

1. Navigate to **Section Settings**
2. Click **Export Settings** in Quick Actions
3. JSON file downloads with all settings

```json
{
  "exported_at": "2026-01-30 10:15:00",
  "version": "1.0",
  "settings": {
    "general": {
      "ahg_theme_enabled": "true",
      "ahg_primary_color": "#1a5f7a"
    },
    "metadata": {
      "meta_extract_on_upload": "true"
    }
  }
}
```

### Import Settings

1. Navigate to **Section Settings**
2. Click **Import Settings** in Quick Actions
3. Select previously exported JSON file
4. Review and confirm import

---

## Reset to Defaults

Reset a section to factory defaults:

1. Navigate to the section you want to reset
2. Click **Reset to Defaults** in Quick Actions
3. Confirm the action when prompted
4. Settings are restored to original values

**Warning:** This action cannot be undone!

---

## Quick Reference

### Navigation Paths

| Setting Area | Path |
|-------------|------|
| Overview | Admin > AHG Settings |
| Global | Admin > AHG Settings > Global |
| Sections | Admin > AHG Settings > Section |
| Plugins | Admin > AHG Settings > Plugins |
| Dropdowns | Admin > Dropdowns |
| AI Services | Admin > AHG Settings > AI Services |
| API Keys | Admin > AHG Settings > API Keys |
| Email | Admin > AHG Settings > Email |
| Ingest Defaults | Admin > AHG Settings > Section > Ingest |
| Numbering | Admin > AHG Settings > Numbering Schemes |
| Preservation | Admin > AHG Settings > Preservation |

### Keyboard Shortcuts

| Action | Shortcut |
|--------|----------|
| Save Settings | Ctrl+S (with focus in form) |
| Expand All Sections | Click accordion headers |

---

## Troubleshooting

### Settings Not Saving

```
+-------------------------------------------------------------+
|  [!] TROUBLESHOOTING                                         |
+-------------------------------------------------------------+
|  Problem: Settings changes don't persist                     |
|  Solution:                                                   |
|    1. Check you have administrator privileges                |
|    2. Verify database connection                             |
|    3. Clear Symfony cache: php symfony cc                    |
|    4. Check PHP error logs                                   |
+-------------------------------------------------------------+
```

### Plugin Won't Enable

- Check for missing dependencies
- Verify symlink exists in plugins directory
- Review PHP error log for missing classes

### Theme Changes Not Visible

1. Clear browser cache
2. Run `php symfony cc`
3. Regenerate CSS: saves automatically on theme settings save

---

## Need Help?

Contact your system administrator or visit the AHG documentation at:
https://github.com/ArchiveHeritageGroup/atom-extensions-catalog/tree/main/docs

---

*Part of the AtoM AHG Framework*
