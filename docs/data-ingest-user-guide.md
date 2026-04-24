# Data Ingestion Manager

## User Guide

OAIS-aligned multi-stage ingestion pipeline for batch import of archival records and digital objects. A 6-step wizard that sits between AtoM's basic CSV import and Archivematica's full OAIS pipeline: Configure, Upload, Map & Enrich, Validate, Preview, Commit.

**Scanner / watched-folder capture** uses this same engine through a different entry point. For continuous capture from scanning stations — where files arrive one at a time instead of in one big batch — see the [Scanner / Capture user guide](scanner-capture-user-guide.md). The scanner reuses the ingest session, derivatives, virus scan, OCR, and SIP/AIP/DIP packaging you configure here — you do not need to re-learn anything.

## Commit runner (delivered 2026-04-24)

The wizard's **Commit** step now runs a real commit: `IngestCommitRunner` walks every valid `ingest_row`, creates the information object (via `InformationObjectService::create()`), attaches any mapped digital object file (via `IngestService::ingestFile()`), and if the session has SIP/AIP/DIP flags set it invokes `OaisPackagerService` per IO. Progress is tracked in `ingest_job` and rendered on the commit page.

Invocation paths:

- **Web UI**: the "Start Commit" button on the commit page posts to `/ingest/{id}/commit` which triggers the runner synchronously.
- **CLI**: `php artisan ahg:ingest-commit <session_id>` — useful for long batches or scripted workflows.

For very large batches, the web commit path **automatically dispatches to the Laravel queue worker** when the session's valid-row count is at or above `heratio.ingest.queue_threshold` (default 500, configurable via `HERATIO_INGEST_QUEUE_THRESHOLD`). The commit view polls the seeded `ingest_job` row for progress — the UI behaves identically whether the run is sync or queued. If your deployment doesn't run a queue worker, set the threshold very high (or to 0 — which disables the queue path entirely) to force sync, and use `php artisan ahg:ingest-commit <session_id>` on the CLI for long-running batches.

---

## Overview
```
+-------------------------------------------------------------+
|                   INGESTION MANAGER                          |
|              ahgIngestPlugin v1.0.0                          |
+-------------------------------------------------------------+
|                                                              |
|  Step 1        Step 2        Step 3        Step 4            |
|  +--------+    +--------+    +--------+    +--------+        |
|  |Configure|--->| Upload |--->| Map &  |--->|Validate|       |
|  |        |    |        |    | Enrich |    |        |        |
|  +--------+    +--------+    +--------+    +--------+        |
|                                                |             |
|                              Step 6        Step 5            |
|                              +--------+    +--------+        |
|                              | Commit |<---| Preview|        |
|                              |& Report|    |& Approve|       |
|                              +--------+    +--------+        |
|                                                              |
|  +------------------------------------------------------+   |
|  |              SESSION DASHBOARD (/ingest)               |   |
|  |  View all sessions | Rollback | Download manifests    |   |
|  +------------------------------------------------------+   |
|                                                              |
+-------------------------------------------------------------+
```

---

## Key Features
```
+-------------------------------------------------------------+
|                   INGESTION CAPABILITIES                     |
+-------------------------------------------------------------+
|  [Wizard]   6-Step Pipeline  - Guided wizard from config     |
|                                to commit with rollback       |
|  [Upload]   Multi-Format     - CSV, ZIP (with DOs), EAD XML |
|  [Map]      Auto-Mapping     - Confidence-based field        |
|                                matching with saved profiles  |
|  [Check]    Validation       - Required fields, dates,       |
|                                hierarchy, duplicates         |
|  [Tree]     Preview          - Hierarchical tree view with   |
|                                expand/collapse + approval    |
|  [Package]  OAIS Packaging   - SIP, AIP, DIP generation     |
|  [Progress] Live Progress    - AJAX-polled progress bar      |
|  [Undo]     Rollback         - Undo entire committed batch   |
|  [Hash]     Checksums        - SHA-256 integrity hashing     |
|  [AI]       AI Processing    - NER, OCR, virus scan,         |
|                                summarize, spellcheck, more   |
+-------------------------------------------------------------+
```

---

## How to Access
```
  Main Menu
      |
      v
   Admin / Import ---------> AtoM CSV Import (basic)
      |
      v
   Ingestion Manager (/ingest)
      |
      +---> Session Dashboard (all sessions)
      |
      +---> [+ New Ingestion] ---> 6-Step Wizard
      |
      +---> Download CSV Templates
      |
      +---> View Completed Manifests
```

**Direct URL:** `/ingest`

**Dependencies:** ahgCorePlugin, ahgSecurityClearancePlugin

**Required Role:** Administrator or user with ingestion permissions

---

## Session Dashboard

When you open the Ingestion Manager, you see all ingestion sessions:

```
+-------------------------------------------------------------+
|  INGESTION MANAGER - SESSION DASHBOARD                       |
+-------------------------------------------------------------+
|  [+ New Ingestion]       [Download CSV Templates v]          |
+-------------------------------------------------------------+

+-------+--------------+----------+--------+--------+----------+
| ID    | Title        | Sector   | Status | Records| Actions  |
+-------+--------------+----------+--------+--------+----------+
| ING-5 | 2026 Photo   | archive  | commit | 1,240  | [Manifest]|
|       | Collection   |          | -ted   |        | [Rollback]|
+-------+--------------+----------+--------+--------+----------+
| ING-4 | Museum       | museum   | in     |   380  | [Resume] |
|       | Catalogue    |          | progr. |        | [Cancel] |
+-------+--------------+----------+--------+--------+----------+
| ING-3 | Library      | library  | commit | 2,500  | [Manifest]|
|       | Batch Import |          | -ted   |        | [Rollback]|
+-------+--------------+----------+--------+--------+----------+
| ING-2 | Gallery      | gallery  | rolled |   150  | [View]   |
|       | Artworks     |          | back   |        |          |
+-------+--------------+----------+--------+--------+----------+
| ING-1 | DAM Media    | dam      | cancel |   ---  | [View]   |
|       | Upload       |          | -led   |        |          |
+-------+--------------+----------+--------+--------+----------+
```

### Dashboard Actions

| Action | Description |
|--------|-------------|
| New Ingestion | Start a new 6-step wizard session |
| Resume | Continue an in-progress session at the last step |
| Cancel | Abort an in-progress session |
| Manifest | Download CSV with created AtoM record IDs |
| Rollback | Undo all records created by a committed session |
| Download CSV Templates | Get blank CSV templates per GLAM sector |

---

## Step 1: Configure

Set up the parameters for your ingestion session.

### Step 1.1: Open a New Ingestion

Click **[+ New Ingestion]** on the dashboard or navigate to `/ingest/configure`

### Step 1.2: Fill In Session Details

```
+-------------------------------------------------------------+
|  STEP 1 OF 6: CONFIGURE                                     |
|  [1 Configure] [2 Upload] [3 Map] [4 Validate] [5 Preview]  |
|  [6 Commit]                                                  |
+-------------------------------------------------------------+
|                                                              |
|  Session Title                                               |
|  +--------------------------------------------------------+ |
|  | 2026 Photo Collection Batch Import                      | |
|  +--------------------------------------------------------+ |
|                                                              |
|  GLAM Sector                                                 |
|  +--------------------------------------------------------+ |
|  | (o) Archive                                             | |
|  | ( ) Museum                                              | |
|  | ( ) Library                                             | |
|  | ( ) Gallery                                             | |
|  | ( ) DAM (Digital Asset Management)                      | |
|  +--------------------------------------------------------+ |
|                                                              |
|  Descriptive Standard                                        |
|  +--------------------------------------------------------+ |
|  | [ISAD(G)                                              v]| |
|  |  Options: ISAD(G), Dublin Core, Spectrum, CCO,         | |
|  |           RAD, DACS                                     | |
|  +--------------------------------------------------------+ |
|                                                              |
|  Repository                                                  |
|  +--------------------------------------------------------+ |
|  | [National Archives - NARSSA                           v]| |
|  +--------------------------------------------------------+ |
|                                                              |
+-------------------------------------------------------------+
```

### Step 1.3: Configure Parent Placement

```
+-------------------------------------------------------------+
|  PARENT PLACEMENT                                            |
+-------------------------------------------------------------+
|                                                              |
|  Where should imported records be placed?                    |
|  +--------------------------------------------------------+ |
|  | (o) Existing hierarchy  - attach to existing record     | |
|  |     Parent record: [Search or browse...             ]   | |
|  |                                                         | |
|  | ( ) New parent          - create a new parent record    | |
|  |     Parent title: [                                 ]   | |
|  |                                                         | |
|  | ( ) Top-level           - create as top-level fonds     | |
|  |                                                         | |
|  | ( ) CSV hierarchy       - use parentId/legacyId columns | |
|  |     to build hierarchy from CSV data                    | |
|  +--------------------------------------------------------+ |
|                                                              |
+-------------------------------------------------------------+
```

### Step 1.4: Configure Output Options

```
+-------------------------------------------------------------+
|  OUTPUT OPTIONS                                              |
+-------------------------------------------------------------+
|                                                              |
|  Record Creation                                             |
|  +--------------------------------------------------------+ |
|  | [X] Create AtoM records                                 | |
|  | [ ] Generate SIP package (Submission)                   | |
|  | [ ] Generate AIP package (Archival)                     | |
|  | [ ] Generate DIP package (Dissemination)                | |
|  +--------------------------------------------------------+ |
|                                                              |
|  Derivative Options                                          |
|  +--------------------------------------------------------+ |
|  | [X] Generate thumbnails                                 | |
|  | [X] Generate reference images                           | |
|  | [ ] Normalize to preservation format                    | |
|  +--------------------------------------------------------+ |
|                                                              |
|  Security Classification (via SecurityClearancePlugin)       |
|  +--------------------------------------------------------+ |
|  | [Unclassified                                         v]| |
|  |  Options: Unclassified, Restricted, Confidential,       | |
|  |           Secret, Top Secret                            | |
|  +--------------------------------------------------------+ |
|                                                              |
+-------------------------------------------------------------+
```

### Step 1.5: Configure AI Processing (Optional)

```
+-------------------------------------------------------------+
|  AI PROCESSING (requires ahgAIPlugin)                        |
+-------------------------------------------------------------+
|                                                              |
|  +--------------------------------------------------------+ |
|  | [ ] NER - Named Entity Recognition (persons, places)   | |
|  | [ ] OCR - Optical Character Recognition                 | |
|  | [ ] Virus Scan - Check uploaded files                   | |
|  | [ ] Summarize - Auto-generate scope & content           | |
|  | [ ] Spellcheck - Check spelling/grammar                 | |
|  | [ ] Translate - Translate metadata fields               | |
|  | [ ] Format Identification - Identify file formats       | |
|  | [ ] Face Detection - Detect faces in images             | |
|  +--------------------------------------------------------+ |
|                                                              |
|  Note: AI processing runs during the Commit step and may     |
|  increase processing time significantly for large batches.   |
|                                                              |
|                              [Cancel]  [Next: Upload >>]     |
+-------------------------------------------------------------+
```

---

## Step 2: Upload

Upload your source data files.

### Step 2.1: Choose Upload Method

```
+-------------------------------------------------------------+
|  STEP 2 OF 6: UPLOAD                                         |
|  [1 Configure] [2 Upload] [3 Map] [4 Validate] [5 Preview]  |
|  [6 Commit]                                                  |
+-------------------------------------------------------------+
|                                                              |
|  +--------------------------------------------------------+ |
|  |                                                         | |
|  |     +------------------------------------------+       | |
|  |     |                                          |       | |
|  |     |       Drag and drop files here           |       | |
|  |     |                                          |       | |
|  |     |    or click to browse your computer      |       | |
|  |     |                                          |       | |
|  |     |    Supported: CSV, ZIP, EAD XML          |       | |
|  |     |                                          |       | |
|  |     +------------------------------------------+       | |
|  |                                                         | |
|  |                   [Browse Files]                        | |
|  |                                                         | |
|  +--------------------------------------------------------+ |
|                                                              |
|  Or specify a server directory path (for large batches):     |
|  +--------------------------------------------------------+ |
|  | /uploads/imports/2026-batch-photos/                     | |
|  +--------------------------------------------------------+ |
|  [Scan Directory]                                            |
|                                                              |
+-------------------------------------------------------------+
```

### Step 2.2: File Auto-Detection

After upload, the system auto-detects file properties:

```
+-------------------------------------------------------------+
|  FILE ANALYSIS                                               |
+-------------------------------------------------------------+
|                                                              |
|  Uploaded File: photo_collection_2026.csv                    |
|                                                              |
|  +---------------------------+-----------------------------+ |
|  | Property                  | Detected Value              | |
|  +---------------------------+-----------------------------+ |
|  | File Type                 | CSV (Comma-Separated)       | |
|  | Delimiter                 | , (comma)                   | |
|  | Encoding                  | UTF-8                       | |
|  | Total Rows                | 1,240                       | |
|  | Header Row                | Yes (row 1)                 | |
|  | Columns                   | 18                          | |
|  +---------------------------+-----------------------------+ |
|                                                              |
+-------------------------------------------------------------+
```

### Step 2.3: Preview Data (First 10 Rows)

```
+-------------------------------------------------------------+
|  DATA PREVIEW (first 10 of 1,240 rows)                       |
+-------------------------------------------------------------+
|                                                              |
| +----+------------+--------------+--------+-------+-------+  |
| | #  | identifier | title        | date   | level | scope |  |
| +----+------------+--------------+--------+-------+-------+  |
| |  1 | PHO-0001   | Market Day   | 1965   | Item  | Black |  |
| |    |            | Photograph   |        |       | and.. |  |
| +----+------------+--------------+--------+-------+-------+  |
| |  2 | PHO-0002   | Town Hall    | 1966   | Item  | Color |  |
| |    |            | Opening      |        |       | pho.. |  |
| +----+------------+--------------+--------+-------+-------+  |
| |  3 | PHO-0003   | School       | 1967   | Item  | Phot..|  |
| |    |            | Portrait     |        |       |       |  |
| +----+------------+--------------+--------+-------+-------+  |
| | .. | ...        | ...          | ...    | ...   | ...   |  |
| +----+------------+--------------+--------+-------+-------+  |
|                                                              |
+-------------------------------------------------------------+
```

### Step 2.4: ZIP File Extraction (if applicable)

When a ZIP file is uploaded, the system extracts and displays the file tree:

```
+-------------------------------------------------------------+
|  ZIP EXTRACTION                                              |
+-------------------------------------------------------------+
|                                                              |
|  photo_collection.zip (245 MB) - Extracted successfully      |
|                                                              |
|  +--------------------------------------------------------+ |
|  | [v] photo_collection/                                   | |
|  |     [v] metadata/                                       | |
|  |         records.csv              (1,240 rows)           | |
|  |     [v] digital_objects/                                | |
|  |         [v] photographs/                                | |
|  |             PHO-0001.tif         (42 MB)                | |
|  |             PHO-0002.tif         (38 MB)                | |
|  |             PHO-0003.jpg         (12 MB)                | |
|  |             ... (1,237 more files)                      | |
|  +--------------------------------------------------------+ |
|                                                              |
|  Files found: 1,241 (1 CSV + 1,240 digital objects)          |
|                                                              |
|                        [<< Back]  [Next: Map & Enrich >>]    |
+-------------------------------------------------------------+
```

---

## Step 3: Map & Enrich

Map source columns to AtoM target fields and enrich with metadata.

### Step 3.1: Two-Column Mapping Interface

```
+-------------------------------------------------------------+
|  STEP 3 OF 6: MAP & ENRICH                                   |
|  [1 Configure] [2 Upload] [3 Map] [4 Validate] [5 Preview]  |
|  [6 Commit]                                                  |
+-------------------------------------------------------------+
|                                                              |
|  Mapping Profile: [-- Select Saved Profile --           v]   |
|  [Load Profile]  [Save Current Mapping]                      |
|                                                              |
|  SOURCE COLUMN          CONFIDENCE   TARGET AtoM FIELD       |
|  +--------------------+  +------+  +----------------------+  |
|  | identifier         |  | 100% |  | [identifier       v]|  |
|  |                    |  | [##] |  |                      |  |
|  +--------------------+  +------+  +----------------------+  |
|  | title              |  | 100% |  | [title            v]|  |
|  |                    |  | [##] |  |                      |  |
|  +--------------------+  +------+  +----------------------+  |
|  | date_created       |  |  85% |  | [eventDates       v]|  |
|  |                    |  | [# ] |  |                      |  |
|  +--------------------+  +------+  +----------------------+  |
|  | scope_content      |  | 100% |  | [scopeAndContent  v]|  |
|  |                    |  | [##] |  |                      |  |
|  +--------------------+  +------+  +----------------------+  |
|  | creator_name       |  |  70% |  | [eventActors      v]|  |
|  |                    |  | [#-] |  |                      |  |
|  +--------------------+  +------+  +----------------------+  |
|  | phys_desc          |  |  60% |  | [extentAndMedium  v]|  |
|  |                    |  | [#-] |  |                      |  |
|  +--------------------+  +------+  +----------------------+  |
|  | file_path          |  |   0% |  | [-- unmapped --   v]|  |
|  |                    |  | [  ] |  |                      |  |
|  +--------------------+  +------+  +----------------------+  |
|                                                              |
|  Confidence Legend:                                          |
|  [##] Green = Exact match   [#-] Yellow = Fuzzy match        |
|  [  ] Red = Unmapped (requires manual selection)             |
|                                                              |
+-------------------------------------------------------------+
```

### Step 3.2: Default Value Assignment

```
+-------------------------------------------------------------+
|  DEFAULT VALUES                                              |
+-------------------------------------------------------------+
|                                                              |
|  Assign default values for unmapped or empty fields:         |
|                                                              |
|  +----------------------------+---------------------------+  |
|  | Field                      | Default Value             |  |
|  +----------------------------+---------------------------+  |
|  | levelOfDescription         | [Item                  v] |  |
|  | publicationStatus          | [Draft                 v] |  |
|  | language                   | [English               v] |  |
|  | repository                 | [NARSSA                v] |  |
|  | culture                    | [en                     ] |  |
|  +----------------------------+---------------------------+  |
|                                                              |
+-------------------------------------------------------------+
```

### Step 3.3: Digital Object Matching Strategy

```
+-------------------------------------------------------------+
|  DIGITAL OBJECT MATCHING                                     |
+-------------------------------------------------------------+
|                                                              |
|  How should digital objects be matched to records?            |
|  +--------------------------------------------------------+ |
|  | (o) Filename match   - match DO filename to identifier  | |
|  |     e.g., PHO-0001.tif matches identifier PHO-0001     | |
|  |                                                         | |
|  | ( ) Legacy ID match  - match DO filename to legacyId    | |
|  |                                                         | |
|  | ( ) Title match      - match DO filename to title       | |
|  |                                                         | |
|  | ( ) CSV column        - use a specific CSV column       | |
|  |     Column: [digitalObjectPath                      v]  | |
|  +--------------------------------------------------------+ |
|                                                              |
+-------------------------------------------------------------+
```

### Step 3.4: Metadata Extraction Panel

```
+-------------------------------------------------------------+
|  EMBEDDED METADATA EXTRACTION                                |
+-------------------------------------------------------------+
|                                                              |
|  Extract metadata from digital objects:                       |
|  +--------------------------------------------------------+ |
|  | [X] EXIF data (camera, date taken, GPS)                 | |
|  | [X] IPTC data (caption, keywords, credit)               | |
|  | [X] XMP data  (Dublin Core, rights, description)        | |
|  +--------------------------------------------------------+ |
|                                                              |
|  Map extracted metadata to AtoM fields:                      |
|  +----------------------------+---------------------------+  |
|  | EXIF DateTimeOriginal      | [eventDates            v] |  |
|  | IPTC Caption               | [scopeAndContent       v] |  |
|  | IPTC Keywords              | [subjectAccessPoints   v] |  |
|  | XMP Creator                | [eventActors           v] |  |
|  | XMP Rights                 | [accessConditions      v] |  |
|  +----------------------------+---------------------------+  |
|                                                              |
|                        [<< Back]  [Next: Validate >>]        |
+-------------------------------------------------------------+
```

### Saved Mapping Profiles

Mapping profiles can be loaded from the ahgDataMigrationPlugin (if installed) or saved during the current session for reuse in future ingestions.

| Action | Description |
|--------|-------------|
| Load Profile | Apply a previously saved column-to-field mapping |
| Save Current Mapping | Store the current mapping for future sessions |
| Auto-Map | Re-run auto-detection to reset confidence scores |

---

## Step 4: Validate

Validate all rows before committing to the database.

### Step 4.1: Automatic Validation Runs

```
+-------------------------------------------------------------+
|  STEP 4 OF 6: VALIDATE                                       |
|  [1 Configure] [2 Upload] [3 Map] [4 Validate] [5 Preview]  |
|  [6 Commit]                                                  |
+-------------------------------------------------------------+
|                                                              |
|  Validation in progress...                                   |
|  [============================================>      ] 88%   |
|                                                              |
|  Checks Running:                                             |
|  [X] Required fields per ISAD(G)                             |
|  [X] Date format validation                                  |
|  [X] Hierarchy integrity (parentId references)               |
|  [X] Digital object file existence                           |
|  [X] MIME type verification                                  |
|  [X] SHA-256 checksum generation                             |
|  [>] Duplicate detection...                                  |
|  [ ] Summary report                                          |
|                                                              |
+-------------------------------------------------------------+
```

### Step 4.2: Validation Summary

```
+-------------------------------------------------------------+
|  VALIDATION RESULTS                                          |
+-------------------------------------------------------------+
|                                                              |
|  +----------+  +----------+  +----------+  +----------+     |
|  | Total    |  | Valid    |  | Warnings |  | Errors   |     |
|  | Rows     |  |          |  |          |  |          |     |
|  |  1,240   |  |  1,195   |  |    38    |  |     7    |     |
|  |          |  |  (96.4%) |  |  (3.1%)  |  |  (0.6%)  |     |
|  +----------+  +----------+  +----------+  +----------+     |
|                                                              |
+-------------------------------------------------------------+
```

### Step 4.3: Review Issues

```
+-------------------------------------------------------------+
|  VALIDATION ISSUES                                           |
+-------------------------------------------------------------+
|  Filter: [All] [Errors Only] [Warnings Only]                 |
+-------------------------------------------------------------+
|                                                              |
|  ERRORS (7)                                                  |
|  +----+------+-----------+---------------------------+-----+ |
|  | #  | Row  | Field     | Issue                     | Fix | |
|  +----+------+-----------+---------------------------+-----+ |
|  | 1  |   42 | title     | Required field is empty   | [E] | |
|  | 2  |  103 | eventDate | Invalid format: 13/2026   | [E] | |
|  | 3  |  205 | parentId  | Ref ID-999 not found      | [E] | |
|  | 4  |  340 | DO file   | PHO-0340.tif not in ZIP   | [E] | |
|  | 5  |  512 | title     | Required field is empty   | [E] | |
|  | 6  |  780 | eventDate | Invalid format: unknown   | [E] | |
|  | 7  |  901 | parentId  | Circular reference        | [E] | |
|  +----+------+-----------+---------------------------+-----+ |
|                                                              |
|  WARNINGS (38)                                               |
|  +----+------+-----------+---------------------------+-----+ |
|  | #  | Row  | Field     | Issue                     | Fix | |
|  +----+------+-----------+---------------------------+-----+ |
|  | 1  |   15 | checksum  | Duplicate of row 14       | [E] | |
|  | 2  |   88 | title     | Possible duplicate:       | [E] | |
|  |    |      |           | "Town Hall" matches row 2 |     | |
|  | .. |  ... | ...       | ...                       | ... | |
|  +----+------+-----------+---------------------------+-----+ |
|                                                              |
+-------------------------------------------------------------+
```

### Step 4.4: Inline Fix or Exclude Rows

Click **[E]** (Edit) on any row to fix inline, or exclude it from the import:

```
+-------------------------------------------------------------+
|  EDIT ROW 42                                                 |
+-------------------------------------------------------------+
|                                                              |
|  identifier:    [PHO-0042                              ]     |
|  title:         [Untitled Photograph                   ]     |
|  eventDates:    [1968                                  ]     |
|  levelOfDesc:   [Item                                v]     |
|                                                              |
|  [X] Include this row    [ ] Exclude from import             |
|                                                              |
|                     [Cancel]  [Save & Re-validate]           |
+-------------------------------------------------------------+
```

### Duplicate Detection Methods

| Method | Description |
|--------|-------------|
| Identifier | Exact match on identifier field |
| Legacy ID | Exact match on legacyId field |
| Title + Date | Records with same title AND date |
| Checksum | SHA-256 match on digital object file |

```
                        [<< Back]  [Next: Preview >>]
```

---

## Step 5: Preview & Approve

Review the complete import as a hierarchical tree before committing.

### Step 5.1: Hierarchical Tree Visualization

```
+-------------------------------------------------------------+
|  STEP 5 OF 6: PREVIEW & APPROVE                              |
|  [1 Configure] [2 Upload] [3 Map] [4 Validate] [5 Preview]  |
|  [6 Commit]                                                  |
+-------------------------------------------------------------+
|                                                              |
|  +---------------------------+-----------------------------+ |
|  |  HIERARCHY TREE           |  RECORD DETAIL              | |
|  +---------------------------+-----------------------------+ |
|  |                           |                             | |
|  |  [v] Photo Collection     |  Title: Market Day Photo    | |
|  |   |  (Fonds) [green]      |  Identifier: PHO-0001      | |
|  |   |                       |  Date: 1965                 | |
|  |   +--[v] Series A         |  Level: Item                | |
|  |   |   |  [green]          |  Scope: Black and white     | |
|  |   |   |                   |    photograph of the weekly  | |
|  |   |   +-- PHO-0001        |    market at Church Square   | |
|  |   |   |   [green]  <---   |                             | |
|  |   |   +-- PHO-0002        |  Digital Object:            | |
|  |   |   |   [green]         |  +------------------------+ | |
|  |   |   +-- PHO-0003        |  | +----+                 | | |
|  |   |       [amber]         |  | |    | PHO-0001.tif     | | |
|  |   |                       |  | |img | 42 MB            | | |
|  |   +--[v] Series B         |  | |    | 4000x3000 px     | | |
|  |       |  [green]          |  | +----+                 | | |
|  |       +-- PHO-0050        |  +------------------------+ | |
|  |       |   [green]         |                             | |
|  |       +-- PHO-0051        |  Status: Valid              | |
|  |       |   [red/excluded]  |                             | |
|  |       +-- ...             |                             | |
|  |                           |                             | |
|  +---------------------------+-----------------------------+ |
|                                                              |
|  Color Legend:                                               |
|  [green] = Valid    [amber] = Warning    [red] = Excluded    |
|                                                              |
+-------------------------------------------------------------+
```

### Step 5.2: SIP/DIP Package Preview (if enabled)

```
+-------------------------------------------------------------+
|  PACKAGE PREVIEW                                             |
+-------------------------------------------------------------+
|                                                              |
|  +----------------------------+---------------------------+  |
|  | Package                    | Estimated Size            |  |
|  +----------------------------+---------------------------+  |
|  | SIP (Submission)           | 2.4 GB                    |  |
|  |   Records: 1,233           |                           |  |
|  |   Digital Objects: 1,233   |                           |  |
|  |   Metadata XML: 1          |                           |  |
|  +----------------------------+---------------------------+  |
|  | DIP (Dissemination)        | 890 MB                    |  |
|  |   Access copies: 1,233     |                           |  |
|  |   Thumbnails: 1,233        |                           |  |
|  |   Finding aid: 1           |                           |  |
|  +----------------------------+---------------------------+  |
|                                                              |
+-------------------------------------------------------------+
```

### Step 5.3: Approval Actions

```
+-------------------------------------------------------------+
|  APPROVAL                                                    |
+-------------------------------------------------------------+
|                                                              |
|  Summary:                                                    |
|  - Records to create: 1,233 (7 excluded)                    |
|  - Digital objects: 1,232 (1 missing file excluded)          |
|  - Estimated time: ~15 minutes                               |
|                                                              |
|  +--------------------------------------------------+       |
|  |  [Approve All]              Import all 1,233     |       |
|  |                             valid records         |       |
|  +--------------------------------------------------+       |
|  |  [Approve with Exclusions]  Import valid only,   |       |
|  |                             skip 7 excluded       |       |
|  +--------------------------------------------------+       |
|  |  [Cancel]                   Return to dashboard   |       |
|  +--------------------------------------------------+       |
|                                                              |
|               [<< Back to Validate]                          |
+-------------------------------------------------------------+
```

---

## Step 6: Commit & Report

Execute the import and monitor progress in real time.

### Step 6.1: Live Progress Bar

```
+-------------------------------------------------------------+
|  STEP 6 OF 6: COMMIT                                         |
|  [1 Configure] [2 Upload] [3 Map] [4 Validate] [5 Preview]  |
|  [6 Commit]                                                  |
+-------------------------------------------------------------+
|                                                              |
|  Ingestion in progress... do not close this page.            |
|                                                              |
|  +--------------------------------------------------------+ |
|  | Stage: Uploading Digital Objects                        | |
|  | [==============================>               ] 62%    | |
|  | 765 / 1,233 records                                     | |
|  | Elapsed: 6m 23s | Estimated remaining: 3m 50s           | |
|  +--------------------------------------------------------+ |
|                                                              |
|  Stages:                                                     |
|  [X] Creating Records             (1,233 / 1,233)           |
|  [>] Uploading Digital Objects     (765 / 1,232)             |
|  [ ] Generating Derivatives       (0 / 1,232)               |
|  [ ] Building SIP Package                                    |
|  [ ] Building AIP Package                                    |
|  [ ] Building DIP Package                                    |
|  [ ] Indexing in Elasticsearch                               |
|                                                              |
|  Progress updates every 2 seconds (AJAX polling)             |
|                                                              |
+-------------------------------------------------------------+
```

### Step 6.2: Completion Report

```
+-------------------------------------------------------------+
|  INGESTION COMPLETE                                          |
+-------------------------------------------------------------+
|                                                              |
|  Session: 2026 Photo Collection Batch Import                 |
|  Status:  Committed successfully                             |
|  Duration: 14m 52s                                           |
|                                                              |
|  +----------------------------+---------------------------+  |
|  | Metric                     | Result                    |  |
|  +----------------------------+---------------------------+  |
|  | Records Created            | 1,233                     |  |
|  | Digital Objects Uploaded    | 1,232                     |  |
|  | Thumbnails Generated       | 1,232                     |  |
|  | Reference Images           | 1,232                     |  |
|  | SIP Package                | 1 (2.4 GB)                |  |
|  | DIP Package                | 1 (890 MB)                |  |
|  | Checksums Computed         | 1,232                     |  |
|  | Errors                     | 0                         |  |
|  | Rows Excluded              | 7                         |  |
|  +----------------------------+---------------------------+  |
|                                                              |
|  Actions:                                                    |
|  +--------------------------------------------------+       |
|  |  [Download Manifest]  CSV with AtoM IDs for all  |       |
|  |                       created records             |       |
|  +--------------------------------------------------+       |
|  |  [View Records]       Browse created records in   |       |
|  |                       AtoM                        |       |
|  +--------------------------------------------------+       |
|  |  [Rollback]           Undo all created records    |       |
|  |                       and digital objects          |       |
|  +--------------------------------------------------+       |
|  |  [New Ingestion]      Start another session       |       |
|  +--------------------------------------------------+       |
|                                                              |
+-------------------------------------------------------------+
```

### Manifest CSV Format

The downloadable manifest contains:

```
+-------+-----------+-----------+--------+------------------+-----+
| row   | identifier| atomId    | slug   | digitalObjectPath| stat|
+-------+-----------+-----------+--------+------------------+-----+
|     1 | PHO-0001  | 123456    | pho-.. | uploads/r/n/...  | ok  |
|     2 | PHO-0002  | 123457    | pho-.. | uploads/r/n/...  | ok  |
|     3 | PHO-0003  | 123458    | pho-.. | uploads/r/n/...  | ok  |
|   ... | ...       | ...       | ...    | ...              | ... |
+-------+-----------+-----------+--------+------------------+-----+
```

---

## Rollback

Rollback undoes an entire committed session, removing all created records and digital objects.

### How Rollback Works

```
+-------------------------------------------------------------+
|  ROLLBACK CONFIRMATION                                       |
+-------------------------------------------------------------+
|                                                              |
|  [!] WARNING: This action cannot be undone.                  |
|                                                              |
|  You are about to rollback session ING-5:                    |
|  "2026 Photo Collection Batch Import"                        |
|                                                              |
|  This will permanently delete:                               |
|  - 1,233 information object records                          |
|  - 1,232 digital object files and derivatives                |
|  - Associated access points and events                       |
|  - SIP/DIP packages                                          |
|                                                              |
|  Type "ROLLBACK" to confirm:                                 |
|  +--------------------------------------------------------+ |
|  | ROLLBACK                                                | |
|  +--------------------------------------------------------+ |
|                                                              |
|                              [Cancel]  [Execute Rollback]    |
+-------------------------------------------------------------+
```

After rollback, the session status changes to "rolled back" and the records are permanently removed from AtoM.

---

## CSV Templates

Download pre-built CSV templates for each GLAM sector from the dashboard:

### Available Templates

| Template | Sector | Standard | Columns |
|----------|--------|----------|---------|
| Archive - ISAD(G) | Archive | ISAD(G) | identifier, title, levelOfDescription, eventDates, eventTypes, eventActors, repository, scopeAndContent, extentAndMedium, ... |
| Archive - RAD | Archive | RAD | identifier, title, levelOfDescription, dates, nameAccessPoints, placeAccessPoints, ... |
| Archive - DACS | Archive | DACS | identifier, title, levelOfDescription, date, extent, scopeAndContent, ... |
| Museum - Spectrum | Museum | Spectrum | objectNumber, objectName, title, briefDescription, numberOfObjects, ... |
| Library - Dublin Core | Library | Dublin Core | identifier, title, creator, subject, description, publisher, date, type, format, ... |
| Gallery - CCO | Gallery | CCO | identifier, title, creator, date, workType, measurements, materials, ... |
| DAM - Dublin Core | DAM | Dublin Core | identifier, title, creator, date, format, rights, description, ... |

---

## Supported File Formats

### Upload Formats

| Format | Extension | Description |
|--------|-----------|-------------|
| CSV | .csv | Comma/tab/semicolon delimited data |
| ZIP | .zip | Archive containing CSV + digital objects |
| EAD XML | .xml | Encoded Archival Description finding aid |

### Digital Object Formats (within ZIP)

| Category | Formats |
|----------|---------|
| Images | TIFF, JPEG, PNG, GIF, BMP, SVG |
| Documents | PDF, DOCX, DOC, ODT, TXT |
| Audio | WAV, MP3, FLAC, OGG, AIFF |
| Video | MP4, MOV, AVI, MKV, WEBM |
| 3D | OBJ, GLB, GLTF, STL |

---

## Quick Reference

### Navigation Paths

| Page | URL | Description |
|------|-----|-------------|
| Dashboard | `/ingest` | All sessions overview |
| New Session | `/ingest/configure` | Start Step 1 |
| Upload | `/ingest/upload` | Step 2 (within session) |
| Map & Enrich | `/ingest/map` | Step 3 (within session) |
| Validate | `/ingest/validate` | Step 4 (within session) |
| Preview | `/ingest/preview` | Step 5 (within session) |
| Commit | `/ingest/commit` | Step 6 (within session) |

### Session Statuses

| Status | Meaning |
|--------|---------|
| configuring | Session created, Step 1 in progress |
| uploading | Files being uploaded, Step 2 |
| mapping | Field mapping, Step 3 |
| validating | Validation running, Step 4 |
| previewing | Awaiting approval, Step 5 |
| committing | Import executing, Step 6 |
| committed | Successfully completed |
| rolled back | Commit undone |
| cancelled | Session aborted by user |
| failed | Import failed with errors |

### Database Tables

| Table | Purpose |
|-------|---------|
| `ingest_session` | Session metadata, status, configuration |
| `ingest_file` | Uploaded files and extracted paths |
| `ingest_mapping` | Column-to-field mapping definitions |
| `ingest_validation` | Validation results per row |
| `ingest_row` | Individual row data and status |
| `ingest_job` | Background job tracking |

### Descriptive Standards by Sector

| Sector | Recommended Standard | Alternatives |
|--------|---------------------|--------------|
| Archive | ISAD(G) | RAD, DACS |
| Museum | Spectrum | CCO |
| Library | Dublin Core | - |
| Gallery | CCO | Dublin Core |
| DAM | Dublin Core | - |

---

## Troubleshooting

### Upload Fails

```
+-------------------------------------------------------------+
|  [!] TROUBLESHOOTING                                         |
+-------------------------------------------------------------+
|  Problem: File upload fails or times out                     |
|  Solution:                                                   |
|    1. Check file size against PHP upload_max_filesize        |
|       (default 2M - increase in php.ini)                     |
|    2. Check post_max_size in php.ini                         |
|    3. For large batches, use the server directory path       |
|       option instead of browser upload                       |
|    4. Verify file permissions on upload directory             |
+-------------------------------------------------------------+
```

### Mapping Not Auto-Detecting

```
+-------------------------------------------------------------+
|  [!] TROUBLESHOOTING                                         |
+-------------------------------------------------------------+
|  Problem: All columns show as unmapped (red)                 |
|  Solution:                                                   |
|    1. Ensure CSV headers match AtoM field names              |
|       (e.g., "title" not "Title" or "TITLE")                 |
|    2. Check the selected descriptive standard matches        |
|       your CSV structure                                     |
|    3. Try loading a saved mapping profile                    |
|    4. Manually map columns using the dropdown selectors      |
+-------------------------------------------------------------+
```

### Validation Errors on Hierarchy

```
+-------------------------------------------------------------+
|  [!] TROUBLESHOOTING                                         |
+-------------------------------------------------------------+
|  Problem: "parentId not found" or "circular reference"       |
|  Solution:                                                   |
|    1. Verify parentId values reference valid legacyId        |
|       values within the same CSV                             |
|    2. Check for typos in parentId column                     |
|    3. Ensure parent rows appear BEFORE child rows            |
|    4. Use the inline edit [E] button to correct references   |
|    5. Select "Top-level" placement if hierarchy is flat      |
+-------------------------------------------------------------+
```

### Commit Takes Too Long

```
+-------------------------------------------------------------+
|  [!] TROUBLESHOOTING                                         |
+-------------------------------------------------------------+
|  Problem: Commit stage seems stuck or very slow              |
|  Solution:                                                   |
|    1. Large batches (>5,000 rows) are normal to take         |
|       30+ minutes                                            |
|    2. Digital object upload is the slowest stage -            |
|       consider smaller batches for large files               |
|    3. AI processing (NER, OCR) adds significant time -       |
|       disable if not needed for this batch                   |
|    4. Check server disk space for derivative generation      |
|    5. Do not close the browser tab - progress is polled      |
|       via AJAX every 2 seconds                               |
+-------------------------------------------------------------+
```

### Rollback Not Available

```
+-------------------------------------------------------------+
|  [!] TROUBLESHOOTING                                         |
+-------------------------------------------------------------+
|  Problem: Rollback button is disabled or missing             |
|  Solution:                                                   |
|    1. Only committed sessions can be rolled back             |
|    2. Already rolled-back sessions cannot be rolled back     |
|       again                                                  |
|    3. Verify you have administrator privileges               |
|    4. Sessions older than the configured retention period    |
|       may lose rollback capability                           |
+-------------------------------------------------------------+
```

### Digital Objects Not Matching

```
+-------------------------------------------------------------+
|  [!] TROUBLESHOOTING                                         |
+-------------------------------------------------------------+
|  Problem: Digital objects not linked to records               |
|  Solution:                                                   |
|    1. Check the matching strategy in Step 3                  |
|       (filename, legacyId, title, CSV column)                |
|    2. For filename matching, ensure the file name            |
|       (without extension) matches the identifier             |
|    3. Verify digital objects are in the correct              |
|       directory within the ZIP                               |
|    4. Check for case sensitivity mismatches                  |
|       (PHO-0001.tif vs pho-0001.tif)                         |
+-------------------------------------------------------------+
```

---

## Need Help?

Contact your system administrator or visit the AHG documentation at:
https://github.com/ArchiveHeritageGroup/atom-extensions-catalog/tree/main/docs

---

*Part of the AtoM AHG Framework*
