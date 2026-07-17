> Heratio Help Center article. Category: Import/Export.

# Portable Export

## User Guide

Export your catalogue as a self-contained, portable HTML/JS application on CD, USB, or downloadable ZIP. The viewer opens in any modern browser with no server, installation, or internet connection required - a "mini Heratio" for offline use.

It is also a **preservation rescue format**: the static package is designed to keep opening even when the Heratio stack, its database, the NAS, power, or connectivity are gone. It is the last-mile, sovereignty-preserving copy that survives the server's death - and, since v1.154.360, it carries its own SHA-256 fixity + C2PA credentials so it stays verifiable offline (see *Verifying authenticity offline*). See **Preservation role** below for where it sits alongside OCFL/BagIt.

---

## Overview
```
+-------------------------------------------------------------+
|                   PORTABLE EXPORT                            |
|              Heratio - Portable Export                        |
+-------------------------------------------------------------+
|                                                              |
|  SERVER SIDE                                                 |
|  +-------------------------------------------------------+  |
|  |  Admin > AHG Settings > Portable Export                |  |
|  |  Built by a queue worker after the wizard submits.    |  |
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

## Preservation role - a rescue / dark-archive format

Portable export is not only an access convenience; it is a deliberate **rescue format**. Position it in preservation terms:

| Layer (OAIS) | Format | Purpose |
| --- | --- | --- |
| **Preservation / AIP** | **OCFL** (`ahg-ocfl`), **BagIt** | The authoritative, fixity-checked archival master - full-fidelity originals + PREMIS, built for long-term custody and audit. |
| **Access / DIP** | **Portable export** (this feature) | A human-usable, dependency-free *dissemination* copy that opens on any browser with zero stack - the artifact that survives when everything else is gone. |

Why it matters:

- **Survives the server's death.** No database, no PHP, no web server, no internet - double-click `index.html`. A collection remains readable on a USB stick, a burned disc, or a dark-archive drive years later.
- **Sovereignty-preserving.** A community, a small institution, or a repatriation partner can hold and open their own copy independently of any AHG-hosted service - aligned with the data-sovereignty work (local vocabulary mirroring, community protocols, DOCiD).
- **Self-verifying.** The package carries `SHA256SUMS` + `data/fixity.json` + any C2PA Content Credentials and a `verify.sh` / `verify.html`, so a recipient can prove offline that nothing was tampered with (see *Verifying authenticity offline*).
- **Complementary, not a replacement.** Keep your OCFL/BagIt AIPs for preservation custody; use portable export as the resilient access/rescue copy layered on top. The two answer different questions - "is the master intact and auditable?" (OCFL) vs "can a human still read this with nothing but a browser?" (portable export).

> Rule of thumb: **OCFL/BagIt is what you preserve; the portable package is what you hand someone so the collection can never be locked away behind a dead server.**

### Rebuilding with no Heratio present (standalone generator)

The in-app export builds the package while Heratio is running. For the ultimate rescue case - the whole Heratio stack is gone and only the preserved AIP survives - a **standalone generator** can rebuild an equivalent offline viewer from an **OCFL object** or a **flat CSV**, using nothing but **Python 3** (standard library only; no Heratio, no server, no network):

```sh
# from an OCFL object root (reads inventory.json, extracts the latest version):
python3 packages/ahg-portable-export/standalone/heratio-portable-gen.py --ocfl ./object --out ./bundle

# from a CSV export (+ optional folder of digital-object files):
python3 packages/ahg-portable-export/standalone/heratio-portable-gen.py --csv descriptions.csv --assets ./files --out ./bundle
```

It emits the same self-contained `index.html` (data inlined, opens by double-click) plus `SHA256SUMS` + `verify.sh`. This closes the reconstructability loop: a collection preserved as OCFL can be turned back into a browsable, searchable archive by anyone, indefinitely, with zero Heratio dependency. See `packages/ahg-portable-export/standalone/README.md`.

---

## Key Features
```
+-------------------------------------------------------------+
|                   CAPABILITIES                               |
+-------------------------------------------------------------+
|  [Browse]     Tree Navigation    - Hierarchical tree         |
|                                    mirroring Heratio hierarchy  |
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
|  [Where]      Destinations       - ZIP, streamed Download    |
|                                    (large), or Folder/drive  |
|  [Secure]     Role + Gates       - Only exports what you may |
|                                    see (ACL) + disclosure    |
|  [Retention]  Expiry Dates       - Data-driven retention;    |
|                                    delete from the admin page |
|  [Settings]   Admin Settings     - Configurable defaults at  |
|                                    Admin > AHG Settings      |
|  [Worker]     Queue Worker       - ahg:portable-export-worker|
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
      +---> Worker: php artisan ahg:portable-export-worker
      +---> (runs on the scheduler; --all-pending drains the queue)
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

### Destination - where the package goes

The wizard also asks **where to write the export** - this is the answer to "a quick download" vs. "a collection too big for a ZIP":

| Destination | What you get | Best for |
|---|---|---|
| **ZIP file** | A downloadable `.zip`, stored on the server and offered as a normal download. | Small-medium sets. |
| **Download (large)** | The bundle is staged uncompressed on the server and **streamed to your browser as a ZIP on demand** - no 4 GB ZIP limit, and no second full copy is written to the server disk. It lands in your browser's Downloads folder. | Large collections you want **on your own PC / laptop**. |
| **Folder / drive** | The uncompressed bundle is written **directly to a server-side directory or mounted drive** you specify (e.g. a USB / NAS path). No ZIP, no size cap. | Very large exports staged onto external storage. |

For **Folder / drive** you enter the target path - it must already exist and be writable by the server. The ZIP **size cap** (`portable_export_max_size_mb`) applies to **ZIP** exports only; the other two destinations are for exports that deliberately exceed it. In the Past Exports table, a Folder/drive export shows an **"On drive"** badge with its path instead of a download button.

### Security & permissions - you can only export what you may see

Every portable export is **fail-closed** and gated on two levels, so a package can never carry content the operator (or the public) shouldn't have.

**1. The operator's role (ACL) - you only export what *you* are permitted to see:**
- No **Read Master** grant → **master files are excluded**, even if you ticked "Masters".
- No **Read Reference** / **Read Thumbnail** grant → that derivative tier is excluded.
- No **View Draft** grant → **unpublished / draft records are withheld**.

**2. Public disclosure gates - regardless of who exports, records are withheld to honour:**
- **Publication status** - draft / unpublished records are excluded by default.
- **ICIP / TK cultural protocols** - culturally restricted records (and their descendant subtrees) are excluded.
- **ODRL access policies** - records under a "use" prohibition are excluded.
- **Community access protocols** - records tagged with a term carrying a restricted community protocol (TK/BC label, e.g. sacred/secret, restricted, gendered, seasonal, community-voice) are excluded. Counted as `protocol` in the summary. There is **no** operator override for this reason - it is unconditionally fail-closed.
- **PII redaction** - records carrying redaction regions never ship their original files.

Every package includes a **`data/disclosure-summary.json`** recording exactly what was withheld and why - counts by reason, plus `perm_masters` / `perm_references` / `perm_thumbnails` flags when a tier was dropped by your role, and `exported_by`. The admin list shows an **"N withheld"** badge on each export.

### Verifying authenticity offline (#1390)

Every package is **authenticity-carrying** - a recipient can confirm, with no network and no Heratio, that nothing was tampered with in transit:

- **`SHA256SUMS`** (bundle root) - a SHA-256 checksum for every exported file, in the standard `sha256sum` format.
- **`data/fixity.json`** - a richer per-file manifest (size, usage tier, and the checksum stored in Heratio for cross-reference).
- **`data/c2pa/`** - any **C2PA Content Credentials** (signed provenance manifests) held for the exported records travel with the package; on-disk `.c2pa.json` sidecars are copied next to their asset too.
- **`verify.sh`** - one-command check: `sh verify.sh` (uses `sha256sum` on Linux or `shasum` on macOS). Prints `OK` per file, or `FAILED` if a file was altered.
- **`verify.html`** - open in a browser to re-hash the files in-page (SubtleCrypto) and see a pass/fail table; serve the folder (or use `verify.sh`) if the browser blocks local file reads.

This closes the loop with Heratio's content-authenticity chain, so the static package on a USB stick or dark-archive drive remains self-verifying long after it leaves the system.

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

## Command Line (worker & queue)

Heratio does **not** create exports from the command line - exports are created through the **web wizard** (or the quick / clipboard exports), which queues a job in the `portable_export` table. A background **worker** then builds the package. It runs automatically on the scheduler and can also be invoked by hand.

### Running the worker
```bash
# Process the next pending export (FIFO)
sudo -u www-data php artisan ahg:portable-export-worker

# Process one specific queued export by its id
sudo -u www-data php artisan ahg:portable-export-worker --id=42

# Drain every pending export in one run
sudo -u www-data php artisan ahg:portable-export-worker --all-pending
```

The scheduler runs `ahg:portable-export-worker --all-pending` automatically (registered in the package's service provider), so a queued export is normally picked up within a minute - no manual step is needed. Run the command by hand only to process immediately or to reprocess a job. Run artisan as `www-data` so the generated files stay writable by the server.

### Worker options
```
+----------------+-----------------------------------------------------+
| Option         | Description                                         |
+----------------+-----------------------------------------------------+
| --id=N         | Process only the queued export with portable_export |
|                | .id = N (else the next pending one, FIFO).          |
| --all-pending  | Drain every pending export in this run.             |
+----------------+-----------------------------------------------------+
```

Everything about *what* an export contains - scope, mode, destination, which
derivative tiers, title, culture, branding - is chosen in the wizard and stored
on the queued row; the worker just builds what was requested (subject to the
security gates described below).

### Retention & cleanup
Completed exports carry an **`expires_at`** date, driven by the
`portable_export_retention_days` setting (default **30 days**). Any export can
be deleted immediately from the **Portable Export** admin page (trash icon),
which also removes its ZIP file. There is no separate cleanup command -
retention is data-driven; prune expired packages per your policy. (Note: a
**Folder / drive** export leaves its files on the target drive after the DB
record is deleted - that folder is your deliverable to manage.)

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

### What happens to corrections when they sync back (curator moderation)

Notes and sources sync straight in, but **metadata corrections / suggestions** do
**not** silently overwrite the catalogue - they land in a **curator review queue**
for a person to approve. Staff review them at **`/research/admin/metadata-suggestions`**
(Research admin sidebar -> *Metadata Suggestions*): each shows the target record, the
field, the suggested value and who submitted it. **Approve** applies the value to the
record when the field maps to a known descriptive field (title, scope and content,
arrangement, extent, access conditions, ...) and stamps who reviewed it; **Reject**
discards it. This keeps community/researcher contributions moderated - nothing changes
the catalogue without archivist sign-off (#1390 #4a).

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

Retention is **data-driven**: each completed export stores an `expires_at`, and the "Expires" column flags when it is eligible for removal. Delete exports as they expire from the **Portable Export** admin page (trash icon) - that removes the ZIP file and the database record together. There is no separate `portable:cleanup` command; if you want automatic pruning, add a small scheduled job that deletes rows whose `expires_at` has passed (and unlinks their `output_path`) per your policy.

> A **Folder / drive** export leaves its files on the target drive even after the database record is deleted - that folder is the deliverable you asked Heratio to write, so managing/removing it is up to you.

---

## Output Structure
```
  portable-export.zip
  |
  +-- README.txt              <- Start here: what this is, how to open it,
  |                              what was withheld, AHG attribution + copyright
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
|  TIP: Delete expired exports from the admin page as they     |
|  pass their expiry to reclaim disk space (retention-driven). |
+-------------------------------------------------------------+
|  TIP: Use the clipboard export for targeted deliveries -     |
|  add specific items to clipboard, then export as portable.   |
+-------------------------------------------------------------+
|  TIP: Configure default settings at Admin > AHG Settings >   |
|  Portable Export to avoid repeating options each time.        |
+-------------------------------------------------------------+
```
