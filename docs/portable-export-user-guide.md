# Portable Export

## User Guide

Export your catalogue as a self-contained, portable HTML/JS application on CD, USB, or downloadable ZIP. The viewer opens in any modern browser with no server, installation, or internet connection required - a "mini Heratio" for offline use.

---

## Overview
```
+-------------------------------------------------------------+
|                   PORTABLE EXPORT                            |
|              ahgPortableExportPlugin v1.1.0                  |
+-------------------------------------------------------------+
|                                                              |
|  SERVER SIDE                                                 |
|  +-------------------------------------------------------+  |
|  |  Admin > AHG Settings > Portable Export                |  |
|  |  or: php symfony portable:export --scope=all --zip     |  |
|  +-------------------------------------------------------+  |
|       |                                                      |
|       v                                                      |
|  +-----------+  +-----------+  +-----------+  +---------+   |
|  | Extract   |->| Collect   |->| Build     |->| Package |   |
|  | Catalogue |  | Assets    |  | Search    |  | & ZIP   |   |
|  +-----------+  +-----------+  +-----------+  +---------+   |
|                                                    |         |
|  CLIENT SIDE (zero server)                         v         |
|  +-------------------------------------------------------+  |
|  |  portable-export.zip                                   |  |
|  |  +-------------------------------------------------+   |  |
|  |  | index.html  - Open in any browser               |   |  |
|  |  | Tree nav    - Fonds > Series > File > Item      |   |  |
|  |  | Search      - Instant full-text (FlexSearch)    |   |  |
|  |  | View        - Images, PDFs, all ISAD(G) fields  |   |  |
|  |  | Edit mode   - Add notes, import files           |   |  |
|  |  +-------------------------------------------------+   |  |
|  +-------------------------------------------------------+  |
|                                                              |
+-------------------------------------------------------------+
```

---

## Key Features
```
+-------------------------------------------------------------+
|                   CAPABILITIES                               |
+-------------------------------------------------------------+
|  [Browse]     Tree Navigation    - Hierarchical tree         |
|                                    mirroring AtoM hierarchy  |
|  [Search]     Full-Text Search   - FlexSearch-powered        |
|                                    instant client-side search|
|  [View]       Detail View        - All ISAD(G) fields,      |
|                                    access points, dates      |
|  [Images]     Digital Objects    - Inline image + PDF viewing|
|  [Scope]      Flexible Scope     - Entire catalogue, fonds, |
|                                    repository, or clipboard  |
|  [Edit]       Edit Mode          - Add notes, import files,  |
|                                    export researcher exchange|
|  [Brand]      Custom Branding    - Title, subtitle, footer   |
|  [Share]      Download Tokens    - Secure shareable links    |
|                                    with expiry & limits      |
|  [Clipboard]  Clipboard Export   - Export clipboard items as |
|                                    portable catalogue        |
|  [Quick]      Quick Export       - One-click export from any |
|                                    description page          |
|  [Wizard]     Step-by-Step UI    - 4-step guided wizard for  |
|                                    configuring exports       |
|  [Retention]  Auto-Cleanup       - Automatic deletion of     |
|                                    expired exports           |
|  [Settings]   Admin Settings     - Configurable defaults at  |
|                                    Admin > AHG Settings      |
|  [CLI]        Command Line       - Scriptable via CLI        |
|  [Offline]    Zero Server        - Works from any filesystem |
+-------------------------------------------------------------+
```

---

## How to Access

### Main Export Page
```
  Admin Menu
      |
      v
   AHG Settings (/ahgSettings/index)
      |
      v
   Portable Export tile
      |
      v
   /portable-export
      |
      +---> 4-step wizard for new exports
      |       |
      |       +---> Step 1: Scope (All / Fonds / Repository)
      |       +---> Step 2: Content (objects, mode)
      |       +---> Step 3: Configure (title, language, branding)
      |       +---> Step 4: Review & Generate
      |
      +---> Past Exports table
      |       |
      |       +---> Download completed exports
      |       +---> Generate share links
      |       +---> Delete old exports
      |       +---> View expiry dates
      |
      +---> CLI: php symfony portable:export
      +---> CLI: php symfony portable:cleanup
```

### Quick Export from Description Pages
```
  Any Information Object page
      |
      v
   Sidebar > Export section
      |
      v
   "Portable Viewer" link
      |
      +---> One click starts fonds-level export
      +---> Redirects to /portable-export when started
```

### Clipboard Export
```
  Clipboard > Export page
      |
      v
   "Portable Catalogue" button (next to Export/Cancel)
      |
      +---> Exports all clipboard items as portable viewer
      +---> Items + their descendants included
      +---> Redirects to /portable-export when started
```

---

## Creating an Export (Web UI - 4-Step Wizard)

### Step 1: Scope - What to Export
```
  +-----------------------------------------------------------+
  |  Step 1 of 4: Scope                                        |
  +-----------------------------------------------------------+
  |                                                             |
  |  What would you like to export?                            |
  |                                                             |
  |  (o) Entire Catalogue                                       |
  |  ( ) Specific Fonds  [ enter slug__________________ ]      |
  |  ( ) By Repository   [ select repository_________ v ]      |
  |                                                             |
  |                                   [ Next > ]               |
  +-----------------------------------------------------------+
```

### Step 2: Content - Digital Objects & Mode
```
  +-----------------------------------------------------------+
  |  Step 2 of 4: Content                                      |
  +-----------------------------------------------------------+
  |                                                             |
  |  Digital Objects:                                           |
  |  [x] Include digital objects                                |
  |                                                             |
  |  Image Derivatives:                                         |
  |  [x] Thumbnails   (small previews, ~150px)                 |
  |  [x] References   (medium display, ~480-800px)             |
  |  [ ] Masters      (original files - can be very large!)    |
  |                                                             |
  |  Viewer Mode:                                               |
  |  [x] Read Only    (browse + search only)                    |
  |  [ ] Editable     (adds notes + file import)                |
  |                                                             |
  |                        [ < Back ] [ Next > ]               |
  +-----------------------------------------------------------+
```

**About image derivatives:**
- **Thumbnails** - Small preview images (~150px wide) used in search results and tree navigation
- **References** - Medium-sized images (~480-800px) used for on-screen viewing in the detail panel
- **Masters** - Original full-resolution files as uploaded. These can be very large (10-50+ MB each for high-res scans/TIFFs). Only include if you need print-quality originals for offline use. Excluding masters is recommended for most use cases.

### Step 3: Configure - Title, Language, Branding
```
  +-----------------------------------------------------------+
  |  Step 3 of 4: Configure                                    |
  +-----------------------------------------------------------+
  |                                                             |
  |  Export Title:    [ Portable Catalogue              ]       |
  |  Language:        [ English         v ]                     |
  |                                                             |
  |  Branding (Optional):                                       |
  |    Viewer Title:  [ My Archive Collection            ]      |
  |    Subtitle:      [ Special Collections              ]      |
  |    Footer:        [ (c) 2026 My Institution          ]      |
  |                                                             |
  |                        [ < Back ] [ Next > ]               |
  +-----------------------------------------------------------+
```

### Step 4: Review & Generate
```
  +-----------------------------------------------------------+
  |  Step 4 of 4: Review & Generate                            |
  +-----------------------------------------------------------+
  |                                                             |
  |  +-------------------------------------------------------+ |
  |  | Setting              | Value                          | |
  |  +-------------------------------------------------------+ |
  |  | Scope                | Entire Catalogue               | |
  |  | Digital Objects      | Yes                            | |
  |  | Thumbnails           | Yes                            | |
  |  | References           | Yes                            | |
  |  | Masters              | No                             | |
  |  | Mode                 | Read Only                      | |
  |  | Title                | Portable Catalogue             | |
  |  | Language             | en                             | |
  |  +-------------------------------------------------------+ |
  |                                                             |
  |  [ < Back ] [ Start Export ]                                |
  +-----------------------------------------------------------+
```

### Progress
```
  +-----------------------------------------------------------+
  |  Export Progress                                            |
  +-----------------------------------------------------------+
  |                                                             |
  |  [=============>                     ] 42%                  |
  |                                                             |
  |  Collecting digital objects...                              |
  |                                                             |
  +-----------------------------------------------------------+
```
Progress stages:
- 0-40%: Extracting catalogue data from database
- 40-70%: Collecting digital object files
- 70-80%: Building search index
- 80-90%: Packaging viewer
- 90-100%: Creating ZIP archive

### Download
```
  +-----------------------------------------------------------+
  |  Export complete! 1,234 descriptions, 567 objects (45 MB)  |
  |  Expires: 2026-03-16                                       |
  |                                                             |
  |  [ Download ZIP ]   [ Share Link ]                          |
  +-----------------------------------------------------------+
```

---

## Quick Export from Description Pages

When viewing any information object (fonds, series, file, or item), the sidebar "Export" section includes a **Portable Viewer** link:

```
  Export
  +---------------------------------------+
  |  Dublin Core 1.1 XML                   |
  |  EAD 2002 XML                          |
  |  Portable Viewer  <-- click this       |
  +---------------------------------------+
```

Clicking starts a fonds-level export using default settings from Admin > AHG Settings > Portable Export. The export title is set to the description's title and you are redirected to `/portable-export` to monitor progress.

This button can be hidden via: Admin > AHG Settings > Portable Export > "Show export button on description pages".

---

## Clipboard Export

The clipboard export page (`/clipboard/export`) includes a **Portable Catalogue** button when the plugin is enabled:

```
  +-----------------------------------------------------------+
  |  Export options                                             |
  |  [type] [format] [include drafts] ...                      |
  |                                                             |
  |  [ Export ]  [ Portable Catalogue ]  [ Cancel ]            |
  +-----------------------------------------------------------+
```

This exports all items currently in the clipboard as a portable viewer. Each item and its descendants are included.

This button can be hidden via: Admin > AHG Settings > Portable Export > "Show export button on clipboard page".

---

## Creating an Export (CLI)

### Basic Commands
```bash
# Export entire catalogue as ZIP
php symfony portable:export --scope=all --zip --output=/tmp/catalogue.zip

# Export a specific fonds
php symfony portable:export --scope=fonds --slug=example-fonds

# Export by repository
php symfony portable:export --scope=repository --repository-id=5

# Export with edit mode enabled
php symfony portable:export --scope=all --mode=editable

# Metadata only (no digital objects)
php symfony portable:export --scope=all --no-objects

# Include master files (large!)
php symfony portable:export --scope=all --include-masters

# Custom title and language
php symfony portable:export --scope=all --title="My Collection" --culture=af
```

### CLI Options
```
+-------------------+--------------------------------------------------+
| Option            | Description                                      |
+-------------------+--------------------------------------------------+
| --scope           | all, fonds, repository, or custom                |
| --slug            | Fonds/description slug (scope=fonds)             |
| --repository-id   | Repository ID (scope=repository)                 |
| --mode            | read_only (default) or editable                  |
| --culture         | Language code: en, fr, af, pt (default: en)      |
| --title           | Export title (default: Portable Catalogue)        |
| --output          | Output path (directory or .zip)                  |
| --zip             | Create ZIP archive                               |
| --no-objects      | Skip digital objects (metadata only)             |
| --no-thumbnails   | Skip thumbnail images                            |
| --no-references   | Skip reference images                            |
| --include-masters | Include original master files                    |
| --export-id       | Process an existing export job by ID             |
+-------------------+--------------------------------------------------+
```

### Cleanup Command
```bash
# Delete expired exports
php symfony portable:cleanup

# Preview what would be deleted (no actual deletion)
php symfony portable:cleanup --dry-run

# Override retention period (delete exports older than N days)
php symfony portable:cleanup --older-than=7
```

---

## Admin Settings

Navigate to **Admin > AHG Settings > Portable Export** to configure defaults.

### Available Settings
```
+--------------------------------------+------------------+---------------------------+
| Setting                              | Default          | Description               |
+--------------------------------------+------------------+---------------------------+
| Enable Portable Export               | true             | Master on/off toggle      |
| Retention Period (days)              | 30               | Auto-delete after N days  |
| Max Export Size (MB)                 | 2048             | Size limit per export     |
| Default Mode                        | read_only        | Read Only or Editable     |
| Include Digital Objects              | true             | Include objects by default |
| Include Thumbnails                   | true             | Include thumbs by default |
| Include Reference Images            | true             | Include refs by default   |
| Include Master Files                | false            | Include originals         |
| Default Language                     | en               | Default export language   |
| Show on Description Pages           | true             | Portable Viewer sidebar   |
| Show on Clipboard Page              | true             | Portable Catalogue button |
+--------------------------------------+------------------+---------------------------+
```

These defaults are used when starting exports from description pages or the clipboard. The full wizard allows overriding any default per export.

---

## Using the Portable Viewer

### Opening the Viewer
```
  1. Extract the ZIP (or burn to CD/copy to USB)
  2. Open index.html in any modern browser
     - Chrome, Firefox, Edge, Safari all supported
     - No server or internet connection needed
     - Works from local filesystem (file:// protocol)
```

### Browse Mode
```
  +---------------------------+-------------------------------+
  |  Hierarchy                |  Description Detail           |
  |                           |                               |
  |  v Fonds A                |  Series 1                     |
  |    v Series 1             |  [Series] [REF-001]           |
  |      > File 1.1           |                               |
  |      > File 1.2           |  Scope and Content            |
  |    > Series 2             |  This series contains...      |
  |  > Fonds B                |                               |
  |                           |  Dates                        |
  |  [Expand All]             |  Creation: 1950-1960          |
  |  [Collapse All]           |                               |
  |                           |  Subject Access Points        |
  |                           |  [History] [Land reform]      |
  |                           |                               |
  |                           |  Sub-levels (3)               |
  |                           |  > File 1.1                   |
  |                           |  > File 1.2                   |
  |                           |  > File 1.3                   |
  +---------------------------+-------------------------------+
```

### Search Mode
```
  +-----------------------------------------------------------+
  |  [ Search descriptions...                    ] [Search]    |
  |                                                             |
  |  3 results for "land reform"                                |
  |                                                             |
  |  Land Reform Records                                        |
  |  [REF-042] [Series]                                         |
  |  ...correspondence relating to <mark>land reform</mark>... |
  |                                                             |
  |  Title Deeds Collection                                     |
  |  [REF-089] [File]                                           |
  |  ...documents pertaining to <mark>land reform</mark>...    |
  +-----------------------------------------------------------+
```

### Edit Mode (Editable Exports Only)
```
  +-----------------------------------------------------------+
  |  Edit Mode                                                  |
  |                                                             |
  |  Drag & drop files here or click to browse                  |
  |  Images, PDFs, and documents accepted                       |
  |                                                             |
  |  Imported Files (2)                                         |
  |  +-------------------------------------------------------+ |
  |  | [photo.jpg]  234 KB  Caption: [ Site overview     ]    | |
  |  | [notes.pdf]  89 KB   Caption: [ Field notes       ]    | |
  |  +-------------------------------------------------------+ |
  |                                                             |
  |  Notes Summary                                              |
  |  +-------------------------------------------------------+ |
  |  | Correspondence File A                                  | |
  |  | "Contains letters from 1952 relating to..."           | |
  |  +-------------------------------------------------------+ |
  |                                                             |
  |  [ Export Changes (researcher-exchange.json) ]              |
  +-----------------------------------------------------------+
```

When browsing descriptions in edit mode, each description has a
"Research Notes" textarea at the bottom where you can add observations.

The "Export Changes" button downloads a `researcher-exchange.json` file
that can be submitted to the archive for import via ahgResearcherPlugin.

---

## Sharing Exports

### Generate a Download Token
```
  Past Exports table > [Share Link] button

  +-----------------------------------+
  |  Share Download Link              |
  |                                    |
  |  Max Downloads: [ 5  ]            |
  |  Expires After: [ 168 ] hours     |
  |                                    |
  |  [ Generate Link ]                |
  |                                    |
  |  Share URL:                        |
  |  https://psis.theahg.co.za/       |
  |  portable-export/download?         |
  |  token=abc123...  [Copy]          |
  +-----------------------------------+
```

Tokens support:
- Maximum download count (or unlimited)
- Expiry time (default 7 days)
- No login required for token-based downloads

---

## Export Retention & Auto-Cleanup

Exports are automatically assigned an expiry date based on the retention period setting (default: 30 days). The "Expires" column in the Past Exports table shows when each export will be eligible for deletion.

### Automatic Cleanup

Run the cleanup command periodically (e.g., via cron) to delete expired exports:

```bash
# Add to crontab - runs daily at 2am
0 2 * * * cd /usr/share/nginx/archive && php symfony portable:cleanup >> /var/log/portable-cleanup.log 2>&1
```

The cleanup task:
1. Finds exports where `expires_at` has passed
2. Finds completed/failed exports older than the retention period
3. Deletes the ZIP file, output directory, and database records
4. Logs what was deleted

Use `--dry-run` to preview without deleting.

---

## Output Structure
```
  portable-export.zip
  |
  +-- index.html              <- Open this in a browser
  +-- assets/
  |   +-- css/viewer.css
  |   +-- js/app.js
  |   +-- js/search.js
  |   +-- js/tree.js
  |   +-- js/import.js         <- Edit mode only
  |   +-- lib/
  |       +-- bootstrap.bundle.min.js
  |       +-- bootstrap.min.css
  |       +-- bootstrap-icons.min.css
  |       +-- flexsearch.min.js
  |       +-- fonts/
  +-- data/
  |   +-- catalogue.json       <- All descriptions
  |   +-- search-index.json    <- Pre-built search index
  |   +-- taxonomies.json      <- Subjects, places, genres
  |   +-- config.json          <- Viewer settings
  |   +-- manifest.json        <- File checksums
  +-- objects/
      +-- thumb/               <- Thumbnail images
      +-- ref/                 <- Reference images
      +-- pdf/                 <- PDF access copies
      +-- master/              <- Master files (if included)
```

---

## Use Cases

### Delivering to Clients
```
  Archive creates export (scope=fonds for client's collection)
  -> Burns to CD or copies to USB
  -> Client opens index.html in browser
  -> Full offline access to their collection
```

### Exhibition Kiosk
```
  Export with scope=repository, read-only mode
  -> Load onto kiosk computer
  -> Visitors browse collection without internet
```

### Researcher Field Work
```
  Export with editable mode enabled
  -> Researcher takes USB to field location
  -> Browses catalogue, adds notes to descriptions
  -> Imports photos from field work
  -> Exports researcher-exchange.json
  -> Submits back to archive via ahgResearcherPlugin
```

### Disaster Recovery Copy
```
  Full catalogue export (scope=all, include masters)
  -> Store on external drive in secure location
  -> Complete offline reference copy of all holdings
```

### NARSSA Handover
```
  Export specific repository holdings
  -> Include all metadata and reference images
  -> Provide as self-contained package to NARSSA
```

### Clipboard-Based Delivery
```
  Researcher adds specific items to clipboard
  -> Clipboard > Export > Portable Catalogue
  -> Only selected items + descendants exported
  -> Lightweight, targeted delivery
```

---

## Tips
```
+-------------------------------------------------------------+
|  TIP: Large exports with master files can be very large.     |
|  Consider excluding masters for most use cases.              |
+-------------------------------------------------------------+
|  TIP: The CLI command is better for large exports - the      |
|  web UI launches the same process in the background.         |
+-------------------------------------------------------------+
|  TIP: Edit mode exports include import.js which adds ~5KB   |
|  to the package. Read-only mode is slightly smaller.         |
+-------------------------------------------------------------+
|  TIP: The viewer works on Chrome, Firefox, Edge, and Safari. |
|  Internet Explorer is NOT supported.                         |
+-------------------------------------------------------------+
|  TIP: Share links expire after 7 days by default.            |
|  Set a longer expiry for permanent sharing.                  |
+-------------------------------------------------------------+
|  TIP: Set up portable:cleanup as a daily cron job to         |
|  automatically remove expired exports and save disk space.   |
+-------------------------------------------------------------+
|  TIP: Use the clipboard export for targeted deliveries -     |
|  add specific items to clipboard, then export as portable.   |
+-------------------------------------------------------------+
|  TIP: Configure default settings at Admin > AHG Settings >   |
|  Portable Export to avoid repeating options each time.        |
+-------------------------------------------------------------+
```
