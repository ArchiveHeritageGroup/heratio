# Heratio — User Manual

**For:** Archivists, Librarians, Museum Curators, Gallery Managers, Researchers
**Product:** Heratio Framework v2.8.2
**Date:** 16 March 2026
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## About This Manual

This manual covers day-to-day use of Heratio for managing archival, library, museum, gallery, and digital asset collections. For system administration (settings, backup, security), see the **Admin Manual**. For development and technical architecture, see the **Technical Manual**.

---

## 1. Getting Started

### 1.1 Logging In

1. Navigate to your institution's AtoM URL
2. Click **Log in** (top-right)
3. Enter your email and password
4. Click **Log in**

If your password has expired, you will be prompted to change it.

### 1.2 The Interface

```
┌─────────────────────────────────────────────────────────────┐
│ [Logo]  Browse ▼  [Search Box]  Add ▼  Manage ▼  [User ▼]  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│                    PAGE CONTENT                              │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                    [Footer]                                  │
└─────────────────────────────────────────────────────────────┘
```

| Menu | What It Contains |
|------|-----------------|
| **Browse** | Archival descriptions, Authority records, Institutions, Functions, Subjects, Places, Digital objects |
| **Search box** | Free text search with options gear (advanced search, semantic search, synonym expansion) |
| **Add** | Create new records (descriptions, authorities, accessions, institutions, terms) |
| **Manage** | Accessions, Donors, Rights holders, Physical storage, Jobs |
| **User menu** | Profile, Language, Clipboard, Log out |

### 1.3 The Homepage

The homepage displays:

- **Carousel** — featured collections with images
- **Browse by GLAM type** — clickable cards for Archive, Library, Museum, Gallery, DAM
- **Browse by creator** — top creators with item counts
- **Recent additions** — latest records added

---

## 2. Browsing Records

### 2.1 GLAM Browse

**How to get there:** Browse > Archival descriptions

```
┌────────────────────┬──────────────────────────────────────┐
│   FILTER SIDEBAR   │         RESULTS                      │
│                    │                                      │
│  GLAM Type         │  View: [Card] [Grid] [Table] [Full]  │
│  ├─ All            │  Sort: Date | Title | Identifier      │
│  ├─ Archive (150)  │  Per page: 10 | 30 | 50 | 100        │
│  ├─ Museum (45)    │                                      │
│  ├─ Library (30)   │  ┌────────────────────────────┐      │
│  └─ Gallery (12)   │  │ [Thumbnail]                │      │
│                    │  │ Title                      │      │
│  Creator           │  │ Identifier · Level         │      │
│  Subject           │  │ Description excerpt...     │      │
│  Place             │  │ [Archive] [View] [Clip]    │      │
│  Genre             │  └────────────────────────────┘      │
│  Level             │                                      │
│  Media Type        │  Page 1 of 5  [< Prev] [Next >]      │
│  Repository        │                                      │
└────────────────────┴──────────────────────────────────────┘
```

**View modes:**

| Mode | Best For |
|------|----------|
| **Card** (default) | General browsing — shows thumbnail, title, description excerpt |
| **Grid** | Visual browsing — compact thumbnail grid |
| **Table** | Data review — sortable columns, resizable headers |
| **Full width** | Detailed review — large images with full metadata |

**Filtering:**
- Click a facet value to filter (e.g., click "Archive" under GLAM Type)
- Click again to remove the filter
- Multiple filters combine with AND
- Each facet shows the count of matching records
- Click the facet header to collapse/expand

### 2.2 Searching

**Quick search:** Type in the search box and press Enter.

**Advanced search:** Click the gear icon > "Advanced search"

| Field | Description |
|-------|-------------|
| Any field | Searches all text fields |
| Title | Title field only |
| Identifier / Reference code | Identifier fields |
| Creator | Name access points |
| Subject | Subject access points |
| Place | Place access points |
| Date range | Start and end dates |
| Level of description | Fonds, series, file, item, etc. |
| Repository | Dropdown of institutions |
| Digital object | Has / doesn't have digital object |
| Media type | Image, video, audio, text, application |
| GLAM type | Archive, library, museum, gallery, DAM |

Combine fields with **AND**, **OR**, or **NOT**.

**Semantic search:** Click gear > "Semantic search" or toggle "Expand search with synonyms"
- Expands your query with related terms automatically
- "photographs" also finds "photos", "images", "pictures"

**Discovery search:** Click gear > "Semantic search" modal
- Natural language queries: "What documents relate to land ownership in the 1950s?"
- Three-strategy matching: direct, expanded, contextual

### 2.3 Clipboard

The clipboard lets you collect records for batch actions:

1. Click the paperclip icon on any record
2. Open clipboard from the navbar
3. Actions: Export CSV, Print labels, Portable export, Remove

---

## 3. Working with Records

### 3.1 Viewing a Record

Click any record title to view it. The record page shows:

- **Title and identification area** — reference code, title, dates, level
- **Digital object** — image viewer, video/audio player, PDF viewer, or 3D viewer
- **Description fields** — organized by ISAD(G)/DACS/DC/MODS/RAD sections
- **Access points** — linked subjects, places, names, genres
- **Action bar** — Edit, Delete, Move, Copy, Export, Print label
- **Children** — sub-records in a hierarchical tree

### 3.2 Creating a Record

**How to get there:** Add > Archival description

1. **Select standard** — ISAD(G), DACS, Dublin Core, MODS, or RAD
2. **Fill in fields** — at minimum, Title is required
3. **Add access points** — click "Add new" under Subjects, Places, Names
4. **Set parent** — place in hierarchy (optional)
5. **Attach digital object** — Upload tab or Link tab
6. **Set publication status** — Draft (internal) or Published (public)
7. **Save**

### 3.3 Editing a Record

1. Navigate to the record
2. Click **Edit** in the action bar
3. Fields are organized in collapsible accordion sections
4. Make changes
5. Click **Save** or **Cancel**

### 3.4 Uploading Digital Objects

On the record edit page, scroll to "Digital object":

| File Type | What Happens |
|-----------|-------------|
| **Images** (JPEG, PNG, TIFF, GIF, WebP) | Thumbnail + reference copy auto-generated; IIIF viewer for high-res |
| **PDF** | Rendered in IIIF viewer; OCR available for text extraction |
| **Video** (MP4, OGV, WebM) | HTML5 player with optional transcription |
| **Audio** (MP3, WAV, OGG) | HTML5 player with waveform display |
| **3D Models** (GLB, GLTF) | Google Model Viewer with AR support |

### 3.5 Custom Fields

Your administrator may have defined custom fields for your records. These appear as an additional panel on view and edit pages. Custom fields can be:

- **Text** — short text input
- **Textarea** — multi-line text
- **Date** — date picker
- **Number** — numeric input
- **Boolean** — yes/no toggle
- **Dropdown** — predefined choices
- **URL** — clickable link

---

## 4. GLAM Sectors

### 4.1 Archive

Standard archival descriptions following ISAD(G):

```
┌─ Identity Area ──────────────────────────┐
│  Reference code    Title    Date(s)       │
│  Level of description    Extent           │
├─ Context Area ───────────────────────────┤
│  Creator    Repository    Archival history │
│  Immediate source of acquisition          │
├─ Content Area ───────────────────────────┤
│  Scope and content    Appraisal           │
│  Accruals    System of arrangement        │
├─ Access Area ────────────────────────────┤
│  Conditions of access/reproduction        │
│  Language    Finding aids                 │
├─ Allied Materials ───────────────────────┤
│  Related units    Publication note        │
├─ Notes ──────────────────────────────────┤
│  Archivist's note    Rules/conventions    │
└──────────────────────────────────────────┘
```

Other supported standards: **DACS**, **Dublin Core**, **MODS**, **RAD** — each with their own field sets accessible via the standard selector.

### 4.2 Library

MARC-inspired cataloguing with integrated library system:

- **Cataloguing** — bibliographic records
- **ISBN Lookup** — type an ISBN, click lookup, metadata auto-populates
- **Circulation** — issue books, process returns, manage renewals
- **Fines** — automatic overdue fines with configurable grace periods
- **Patron management** — registered borrowers with loan history
- **Cover images** — automatic retrieval from Open Library

### 4.3 Museum

CCO (Cataloguing Cultural Objects) standard:

- **Object identification** — name, classification, materials, techniques, dimensions
- **Getty AAT** — linked vocabulary from the Art & Architecture Thesaurus
- **Condition assessment** — scoring with photo documentation
- **Spectrum 5.1** — UK Collections Trust procedures for acquisition, loans, movement, condition
- **Exhibition linking** — connect objects to exhibitions

### 4.4 Gallery

Exhibition and artwork management:

- **Artist records** — biographical data with exhibition history
- **Exhibitions** — planning, layout, timeline, media, loans
- **Artwork tracking** — provenance, valuation, insurance
- **VRA Core** — Visual Resources Association metadata

### 4.5 Digital Asset Management (DAM)

Photo and media management:

- **IPTC metadata** — automatic extraction from images (caption, keywords, creator, location)
- **Watermarking** — configurable watermarks applied to downloads
- **Batch operations** — bulk metadata editing across selections

---

## 5. Entity Management

### 5.1 Authority Records (Actors)

**How to get there:** Browse > Authority records

ISAAR(CPF) compliant records for persons, corporate bodies, and families:

- **Identity** — authorized form of name, type (person/corporate/family), dates
- **Description** — history, places, functions, mandates
- **Relationships** — links to other authorities, resources, functions
- **Control** — authority record identifier, maintenance dates

### 5.2 Donors

**How to get there:** Manage > Donors

- Contact information (name, address, phone, email)
- Donation history
- Linked donor agreements
- Related accessions

### 5.3 Accessions

**How to get there:** Manage > Accessions

Track incoming transfers:

```
┌─ Accession Record ──────────────────────┐
│  Accession number: ACC-2026-0001        │
│  Title: Pieterse Family Papers          │
│  Date: 2026-03-15                       │
│  Donor: Johan Pieterse                  │
│  Priority: Normal                       │
│  Status: In progress                    │
│                                         │
│  Extent: 3 boxes, 150 items            │
│  Processing notes: ...                  │
│  Appraisal: ...                        │
│  Donor agreement: [Attached]            │
└─────────────────────────────────────────┘
```

### 5.4 Repositories (Archival Institutions)

**How to get there:** Browse > Archival institutions

ISDIAH compliant institution descriptions with contact details, holdings, services.

### 5.5 Physical Storage

**How to get there:** Manage > Physical storage

Track physical locations:
- **Buildings** > **Rooms** > **Shelves/Cabinets** > **Containers/Boxes**
- Link any record to a storage location
- Track movements between locations

### 5.6 Terms & Taxonomies

**How to get there:** Browse > Subjects, Browse > Places

Manage controlled vocabularies:
- Add/edit/delete terms
- Organize in hierarchies (broader/narrower terms)
- Merge duplicate terms

---

## 6. Digital Object Viewers

### 6.1 Image Viewer (IIIF)

High-resolution viewing via OpenSeadragon or Mirador:

| Control | Action |
|---------|--------|
| Scroll wheel | Zoom in/out |
| Click + drag | Pan the image |
| Rotation buttons | Rotate 90 degrees |
| Full screen button | Expand to full screen |
| Mini-map (bottom-right) | Navigate overview |
| Home button | Reset to original view |

### 6.2 3D Model Viewer

For GLB/GLTF 3D models:

| Control | Action |
|---------|--------|
| Click + drag | Rotate the model |
| Scroll wheel | Zoom in/out |
| "View in AR" button | Augmented reality (mobile) |
| Hotspot markers | Click for annotations |

### 6.3 Media Player (Audio/Video)

HTML5 player with:
- Play/pause, seek bar, volume control
- Playback speed adjustment
- Synchronized captions (VTT/SRT)
- Waveform visualization (audio)

### 6.4 PDF Viewer

Embedded PDF viewing:
- Scroll through pages
- Zoom controls
- Text search (if OCR'd)
- Download original

---

## 7. AI Tools

### 7.1 Voice Commands

Click the **microphone button** (navbar or floating bottom-right) and speak:

| Say This | What Happens |
|----------|-------------|
| "browse" | Opens the browse page |
| "search for [term]" | Searches for the term |
| "go to admin" | Opens admin panel |
| "read title" | Reads the record title aloud |
| "read description" | Reads scope and content aloud |
| "read metadata" | Reads all populated fields |
| "describe image" | AI generates image description |
| "read PDF" | Reads PDF transcript aloud |
| "start dictating" | Dictate into a text field |
| "stop dictating" | End dictation |
| "help" | Shows all available commands |
| "disable voice" | Turns off voice until re-enabled |

**Right-click** the mic button to type a command instead of speaking.

**Keyboard shortcuts:**
- **Ctrl+Shift+V** — toggle voice on/off
- **Ctrl+Shift+H** — show voice help

### 7.2 AI Features on Records

When viewing a record, you may see AI action buttons:

| Button | What It Does |
|--------|-------------|
| **NER Extract** | Finds persons, places, organizations, dates in the description text |
| **Translate** | Translates metadata to another language (offline) |
| **Summarize** | Generates a summary of the description |
| **Spellcheck** | Checks spelling and grammar |
| **AI Suggest** | Generates a full description using LLM |

These features process text locally — no data is sent to external services (unless cloud LLM is configured by your administrator).

---

## 8. Import & Export

### 8.1 Data Ingest (6-Step Wizard)

**How to get there:** Import > Data Ingest

```
  Step 1        Step 2       Step 3        Step 4       Step 5       Step 6
┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐
│Configure│─>│ Upload  │─>│  Map &  │─>│Validate │─>│ Preview │─>│ Commit  │
│         │  │         │  │ Enrich  │  │         │  │         │  │         │
└─────────┘  └─────────┘  └─────────┘  └─────────┘  └─────────┘  └─────────┘
```

**Step 1 — Configure:** Choose sector (archive/library/museum/gallery/DAM), standard, repository, parent record, and which AI processing to apply.

**Step 2 — Upload:** Upload a CSV file, ZIP archive, EAD XML, or point to a server directory.

**Step 3 — Map & Enrich:** Map your CSV columns to AtoM fields. Use auto-map for common column names. Save mapping profiles for reuse.

**Step 4 — Validate:** System checks for required fields, invalid dates, hierarchy issues, duplicates. Fix or exclude problem rows.

**Step 5 — Preview:** Review the hierarchical tree of records that will be created. Approve or exclude individual records.

**Step 6 — Commit:** Records are created in the background. A progress bar shows status. Download a completion report when done.

### 8.2 CSV Import

**How to get there:** Import > CSV

Quick import for:
- Archival descriptions
- Authority records
- Accessions
- Repository records

### 8.3 Export

From any browse results or record view:

| Format | How |
|--------|-----|
| **CSV** | Browse > CSV button, or record > Export > CSV |
| **EAD XML** | Record > Export > EAD |
| **Dublin Core XML** | Record > Export > DC |
| **Portable catalogue** | Clipboard > Portable Catalogue (standalone HTML for USB/CD) |
| **Labels** | Record > Print Label (with barcode) |

---

## 9. Research & Public Access

### 9.1 Research Portal

**How to get there:** Research menu

- **Register as researcher** — complete registration form
- **Book a reading room** — select date, time, and materials
- **Request access** — submit access requests for restricted materials
- **Workspace** — view your active requests and bookings

### 9.2 Access Requests

Submit requests for restricted materials. Requests go through a triage workflow:
1. Submit request with justification
2. Archivist reviews and approves/denies
3. Materials prepared for access
4. Researcher notified

### 9.3 Cart & Favorites

- **Cart** (cart icon) — collect items for reproduction requests
- **Favorites** (heart icon) — bookmark records for later reference

---

## 10. Compliance

### 10.1 Security Classification

Records may have security classifications:

| Level | Who Can See |
|-------|------------|
| Unclassified | Everyone |
| Restricted | Users with Restricted clearance or higher |
| Confidential | Users with Confidential clearance or higher |
| Secret | Users with Secret clearance or higher |
| Top Secret | Users with Top Secret clearance only |

Your clearance level is set by your administrator. You will only see records at or below your clearance level.

### 10.2 Embargo

Some records may be embargoed (time-locked). Embargoed records show a notice with the release date.

### 10.3 Privacy

If you handle records containing personal information, be aware of privacy compliance requirements (POPIA, GDPR, etc.). Contact your administrator for guidance.

---

## Appendix: Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| Ctrl+Shift+V | Toggle voice commands |
| Ctrl+Shift+H | Open voice help |
| Tab | Next interactive element |
| Shift+Tab | Previous element |
| Escape | Close modal/dropdown |
| Enter | Activate focused element |

---

*Heratio Framework v2.8.2 — The Archive and Heritage Group (Pty) Ltd*
