> Heratio Help Center article. Category: User Manual.

# Heratio AHG Data Migration Tool
## User Manual

**Version:** 1.0.0  
**Last Updated:** January 2026  
**Author:** The Archive and Heritage Group

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Getting Started](#2-getting-started)
3. [Web Interface Guide](#3-web-interface-guide)
4. [Field Mapping](#4-field-mapping)
5. [Import Options](#5-import-options)
6. [Background Jobs](#6-background-jobs)
7. [Command Line Interface](#7-command-line-interface)
8. [Supported Formats](#8-supported-formats)
9. [Troubleshooting](#9-troubleshooting)
10. [Appendix: Field Reference](#10-appendix-field-reference)

---

## 1. Introduction

### 1.1 What is the Data Migration Tool?

The Heratio AHG Data Migration Tool enables archivists and data managers to import records from various source systems into Heratio (Access to Memory). It supports multiple file formats and provides flexible field mapping to transform data from any source structure to Heratio's archival description standards.

### 1.2 Key Features

- **Multiple Format Support**: CSV, Excel (XLS/XLSX), XML, JSON, Preservica OPEX/PAX
- **Flexible Field Mapping**: Map any source field to any Heratio field
- **Saved Mappings**: Save and reuse mapping configurations
- **Background Processing**: Queue large imports to run without timeout
- **Live Progress Tracking**: Monitor import progress in real-time
- **Hierarchy Support**: Import parent-child relationships
- **Digital Objects**: Link imported records to digital files
- **Preview Mode**: Test imports before committing to database

### 1.3 Workflow Overview
```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   UPLOAD    │───▶│    MAP      │───▶│   PREVIEW   │───▶│   IMPORT    │
│    FILE     │    │   FIELDS    │    │    DATA     │    │  TO ATOM    │
└─────────────┘    └─────────────┘    └─────────────┘    └─────────────┘
      │                  │                  │                  │
      ▼                  ▼                  ▼                  ▼
  CSV, Excel,      Configure         Verify data         Records
  XML, JSON,       source-to-        before import       created in
  OPEX, PAX        Heratio mapping                          database
```

---

## 2. Getting Started

### 2.1 Accessing the Tool

1. Log in to Heratio as an administrator
2. Navigate to: **Admin** → **Data Migration**
3. Or directly visit: `/dataMigration`

### 2.2 User Permissions

Only users with **Administrator** privileges can access the Data Migration Tool.

### 2.3 Preparing Your Data

Before importing, ensure your data:

- Has consistent column headers (for CSV/Excel)
- Contains required fields (at minimum: title)
- Uses consistent date formats
- Has clean, UTF-8 encoded text
- Includes unique identifiers for update operations

---

## 3. Web Interface Guide

### 3.1 Upload Page

The upload page is your starting point for all imports.
```
┌────────────────────────────────────────────────────────────┐
│  📤 Data Migration Tool                      [View Jobs]   │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  ┌──────────────────────────────────────────────────────┐ │
│  │                                                      │ │
│  │     📄 Drag & drop file here or browse              │ │
│  │                                                      │ │
│  │     Supported: CSV, Excel, XML, JSON, OPEX, PAX     │ │
│  │                                                      │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                            │
│  File Options:                                             │
│  ┌────────────────┐  ┌────────────────┐                   │
│  │ First Row is   │  │ Target Type    │                   │
│  │ Header [✓]     │  │ [Archives ▼]   │                   │
│  └────────────────┘  └────────────────┘                   │
│                                                            │
│  Saved Mapping (optional):                                 │
│  ┌────────────────────────────────────────────────────┐   │
│  │ [Select a saved mapping...                      ▼] │   │
│  └────────────────────────────────────────────────────┘   │
│                                                            │
│                                    [Upload & Continue →]   │
└────────────────────────────────────────────────────────────┘
```

#### Options Explained:

| Option | Description |
|--------|-------------|
| **First Row is Header** | Check if your file's first row contains column names |
| **Excel Sheet** | For Excel files with multiple sheets, select which to import |
| **Delimiter** | For CSV files: auto-detect, comma, semicolon, tab, or pipe |
| **Encoding** | Character encoding: auto-detect, UTF-8, ISO-8859-1, Windows-1252 |
| **Target Type** | What type of records to create: Archives, Library, Museum, etc. |
| **Saved Mapping** | Optionally pre-load a saved field mapping |

### 3.2 Field Mapping Page

After upload, you'll configure how source fields map to Heratio fields.
```
┌────────────────────────────────────────────────────────────────────────┐
│  ⇄ Field Mapping: my_export.csv                                        │
├────────────────────────────────────────────────────────────────────────┤
│  📄 File: 150 rows    🎯 Target: Archives    📋 Source: 25 columns     │
├────────────────────────────────────────────────────────────────────────┤
│  🔧 Mapping Controls                                                    │
│  [📂 Load Mapping] [💾 Save Mapping] [☑ Select All] [☐ Deselect All]   │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  Source Field    │ Heratio Field      │ Constant │ ⊕│ ⊕C│ ✓│ Transform   │
│  ─────────────────────────────────────────────────────────────────────│
│  id              │ [legacyId    ▼] │ [      ] │ ☐│ ☐ │ ☑│ [None    ▼] │
│  title           │ [title       ▼] │ [      ] │ ☐│ ☐ │ ☑│ [None    ▼] │
│  description     │ [scopeAndCo~ ▼] │ [      ] │ ☐│ ☐ │ ☑│ [None    ▼] │
│  date_created    │ [eventDates  ▼] │ [      ] │ ☐│ ☐ │ ☑│ [None    ▼] │
│  creator         │ [creators    ▼] │ [      ] │ ☐│ ☐ │ ☑│ [None    ▼] │
│  file_path       │ [digitalObj~ ▼] │ [      ] │ ☐│ ☐ │ ☑│ [Filename▼] │
│  ...             │                 │          │  │   │  │             │
│                                                                        │
├────────────────────────────────────────────────────────────────────────┤
│  [← Back]                    [▶ Preview / Import] [☁ Background Job]  │
└────────────────────────────────────────────────────────────────────────┘
```

#### Column Explanations:

| Column | Description |
|--------|-------------|
| **Source Field** | Column name from your uploaded file |
| **Heratio Field** | Target field in Heratio (dropdown selection) |
| **Constant** | Fixed value to add (e.g., repository name) |
| **⊕ (Concatenate)** | Append to existing value instead of replacing |
| **⊕C (Concat Constant)** | Prepend constant to source value |
| **✓ (Include)** | Whether to include this field in import |
| **Transform** | Data transformation (e.g., extract filename from path) |

### 3.3 Preview Page

Before importing, preview shows exactly what will be created.
```
┌────────────────────────────────────────────────────────────────────────┐
│  📋 Import Preview                                                      │
├────────────────────────────────────────────────────────────────────────┤
│  Mode: Preview Only    Records: 150    Target: Archives                │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  Record 1 of 150                                                       │
│  ┌──────────────────────────────────────────────────────────────────┐ │
│  │ Title:           Johannesburg City Council Records                │ │
│  │ Identifier:      JHB-CC-001                                       │ │
│  │ Level:           Collection                                       │ │
│  │ Date:            1886-1994                                        │ │
│  │ Extent:          15.5 linear meters                               │ │
│  │ Scope:           Administrative records of the city council       │ │
│  └──────────────────────────────────────────────────────────────────┘ │
│                                                                        │
│  [← Previous]  Record 1 / 150  [Next →]                               │
│                                                                        │
├────────────────────────────────────────────────────────────────────────┤
│  [← Back to Mapping]                              [✓ Confirm Import]   │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 4. Field Mapping

### 4.1 Auto-Matching

The system attempts to automatically match source fields to Heratio fields based on common names:

| Source Field Names | Auto-Matched To |
|-------------------|-----------------|
| title, name, heading | title |
| description, scope, content | scopeAndContent |
| date, dates, date_range | eventDates |
| creator, author | creators |
| id, legacy_id, source_id | legacyId |
| identifier, reference, ref_code | identifier |

### 4.2 Saving Mappings

To save a mapping for reuse:

1. Click **💾 Save Mapping**
2. Enter a descriptive name (e.g., "Vernon CMS Museum Export")
3. Click **Save**

To load a saved mapping:

1. Click **📂 Load Mapping**
2. Select from the list
3. Click **Load**

### 4.3 Field Transformations

Available transformations for data cleaning:

| Transform | Description | Example |
|-----------|-------------|---------|
| **None** | No transformation | `C:\Images\photo.jpg` → `C:\Images\photo.jpg` |
| **Filename Only** | Extract filename from path | `C:\Images\photo.jpg` → `photo.jpg` |
| **Replace Prefix** | Replace path prefix | `C:\Images\photo.jpg` → `/uploads/photo.jpg` |
| **Add Prefix** | Prepend text | `photo.jpg` → `/uploads/photo.jpg` |
| **Lowercase** | Convert to lowercase | `PHOTO.JPG` → `photo.jpg` |
| **Extension** | Change file extension | `photo.tif` → `photo.jpg` |

### 4.4 Concatenation

Use concatenation to combine multiple source fields into one Heratio field:

**Example:** Combine `first_name` and `last_name` into `creators`

1. Map `first_name` to `creators` (Include ✓)
2. Map `last_name` to `creators` (Include ✓, Concatenate ✓)
3. Set concatenation symbol to space ` `

Result: "John" + "Smith" → "John Smith"

---

## 5. Import Options

### 5.1 Output Modes

| Mode | Description |
|------|-------------|
| **Preview Only** | View transformed data without importing |
| **Import to Database** | Create records in Heratio |
| **Export to Heratio CSV** | Generate Heratio-compatible CSV file |
| **Export to EAD** | Generate EAD 2002 XML file |

### 5.2 Import Settings

When importing to database:

| Setting | Description |
|---------|-------------|
| **Repository** | Assign all records to a repository |
| **Parent Record** | Import as children of specified record |
| **Culture** | Language/culture code (en, af, fr, etc.) |
| **Update Existing** | Update records matching by Legacy ID |
| **Match Field** | Field to match existing records (legacyId or identifier) |

### 5.3 Digital Objects

To import digital objects (images, PDFs, etc.):

1. Map your file path column to `digitalObjectPath`
2. In Background Job options, specify **Digital Objects Path**
3. Files will be copied to Heratio's uploads directory

**Important:** The server must have access to the specified path.

---

## 6. Background Jobs

### 6.1 When to Use Background Jobs

Use background jobs for:

- Large files (1,000+ rows)
- Imports with digital objects
- When you need to close your browser
- To avoid browser timeout errors

### 6.2 Queuing a Background Job

1. Configure your field mapping
2. Click **☁ Background Job** button
3. Set import options in the modal:
   - Repository (optional)
   - Parent Record ID (optional)
   - Culture/Language
   - Update existing records checkbox
   - Digital Objects Path (if importing files)
4. Click **Queue Job**

### 6.3 Monitoring Job Progress
```
┌────────────────────────────────────────────────────────────────────────┐
│  ⚙ Migration Job #42                                      [All Jobs]   │
├────────────────────────────────────────────────────────────────────────┤
│  Status: [■■■■■■■■■■■■■■■□□□□□] 75%  Running                           │
│                                                                        │
│  File:    vernon_export.csv           Format: CSV                      │
│  Target:  Museum                      Mapping: Vernon CMS (Museum)     │
│  Created: 2026-01-16 10:30:00        Started: 2026-01-16 10:30:05     │
│                                                                        │
│  ┌──────────┬──────────┬──────────┬──────────┬──────────┐             │
│  │  Total   │ Processed│ Imported │ Updated  │  Errors  │             │
│  │   150    │   112    │   108    │    4     │    0     │             │
│  └──────────┴──────────┴──────────┴──────────┴──────────┘             │
│                                                                        │
│  Progress: Processing record 112 of 150...                             │
│                                                                        │
│                                                    [Cancel Job]        │
└────────────────────────────────────────────────────────────────────────┘
```

### 6.4 Job Statuses

| Status | Description |
|--------|-------------|
| **Pending** | Job queued, waiting for worker |
| **Running** | Currently processing |
| **Completed** | Successfully finished |
| **Failed** | Error occurred |
| **Cancelled** | Stopped by user |

### 6.5 Viewing All Jobs

Navigate to **Data Migration** → **View Jobs** or `/dataMigration/jobs`

---

## 7. Command Line Interface

### 7.1 Available Commands

The CLI importers ingest **CSV** files. Richer source formats (Excel, XML, JSON,
Preservica OPEX/PAX) and mapping-by-name are handled through the Data Migration
web UI at `/dataMigration`; convert or preview those there, then use the CLI for
scripted CSV loads.

```bash
# Import a CSV with a saved mapping profile (archives sector)
php artisan sector:archives-csv-import /path/to/file.csv --mapping=10

# Other sectors use the same options
php artisan sector:museum-csv-import /path/to/file.csv --mapping=10
php artisan sector:library-csv-import /path/to/file.csv --mapping=10
php artisan sector:gallery-csv-import /path/to/file.csv --mapping=10
php artisan sector:dam-csv-import /path/to/file.csv --mapping=10

# Validate only (preview without importing)
php artisan sector:archives-csv-import /path/to/file.csv --mapping=10 --validate-only

# Import with options
php artisan sector:archives-csv-import /path/to/file.csv --mapping=10 \
  --repository=my-repo-slug \
  --update=identifier \
  --update-mode=update \
  --culture=en

# Generic importer (no sector validation) - tag rows with a source name and reindex
php artisan ahg:csv-import /path/to/file.csv --source-name="Vernon CMS" --index

# Limit rows for testing
php artisan sector:archives-csv-import /path/to/file.csv --mapping=10 --limit=10
```

### 7.2 CLI Options Reference

**Sector importers** (`sector:archives-csv-import`, `sector:museum-csv-import`,
`sector:library-csv-import`, `sector:gallery-csv-import`, `sector:dam-csv-import`):

| Option | Description |
|--------|-------------|
| `--mapping` | Mapping profile ID to use |
| `--repository` | Target repository slug |
| `--update` | Match field for updates (identifier, legacyId; default legacyId) |
| `--update-mode` | Update mode: skip, update, merge (default skip) |
| `--culture` | Default culture for i18n fields (default: en) |
| `--validate-only` | Validate without importing |
| `--limit` | Maximum rows to process |
| `--skip` | Number of rows to skip (default: 0) |

**Generic importer** (`ahg:csv-import`):

| Option | Description |
|--------|-------------|
| `--source-name` | Source name identifier tagged onto imported rows |
| `--default-legacy-parent-id` | Default parent ID for orphan records |
| `--update` | Update strategy (match, overwrite, skip) |
| `--skip-matched` | Skip records that already exist |
| `--limit` | Maximum records to import |
| `--index` | Reindex after import |

---

## 8. Supported Formats

### 8.1 CSV (Comma-Separated Values)

- Standard CSV with headers
- Auto-detects delimiter (comma, semicolon, tab, pipe)
- Auto-detects encoding
- Handles quoted fields with embedded delimiters

### 8.2 Excel (XLS/XLSX)

- Microsoft Excel 97-2003 (.xls)
- Microsoft Excel 2007+ (.xlsx)
- Multiple sheet support
- Preserves data types

### 8.3 XML

- Generic XML with repeating record elements
- Dublin Core XML
- Custom XML structures

### 8.4 JSON

- Array of objects
- Nested structures with `records` or `data` wrapper
- UTF-8 encoded

### 8.5 Preservica OPEX

- Open Preservation Exchange format
- Dublin Core metadata extraction
- Transfer and properties parsing
- Identifier extraction

### 8.6 Preservica PAX

- Preservica Archive Exchange packages
- ZIP-based container format
- XIP metadata extraction
- Structural object hierarchy

### 8.7 Pre-configured Mappings

| Mapping | Target | Fields | Source System |
|---------|--------|--------|---------------|
| ArchivesSpace Resources | Archives | 12 | ArchivesSpace |
| ArchivesSpace Accessions | Accession | 12 | ArchivesSpace |
| ArchivesSpace Agents | Actor | 9 | ArchivesSpace |
| Preservica OPEX | Archives | 23 | Preservica |
| Preservica PAX/XIP | Archives | 18 | Preservica |
| Vernon CMS (Museum) | Museum | 20 | Vernon CMS |
| PSIS Full Import | Library | 84 | PSIS |
| WDB | Archives | 8 | WDB |

---

## 9. Troubleshooting

### 9.1 Common Issues

#### File Upload Fails

**Problem:** "Failed to save uploaded file"

**Solutions:**
- Check file size doesn't exceed PHP upload limit
- Ensure `/uploads/migration/` directory is writable
- Try a smaller file to test

#### Mapping Not Loading

**Problem:** Saved mapping doesn't apply correctly

**Solutions:**
- Ensure source file has same column names as when mapping was created
- Check mapping wasn't corrupted (delete and recreate)
- Verify column order matches if using position-based mapping

#### Import Timeout

**Problem:** Browser shows timeout error during import

**Solutions:**
- Use **Background Job** for large imports
- Split file into smaller chunks
- Use CLI import instead

#### Records Not Appearing

**Problem:** Import completed but records not visible

**Solutions:**
- Check records were created under correct parent
- Verify publication status
- Rebuild search index: `php artisan ahg:search-populate`
- Clear cache: `php artisan optimize:clear`

#### Character Encoding Issues

**Problem:** Special characters display incorrectly

**Solutions:**
- Ensure source file is UTF-8 encoded
- Try different encoding option during upload
- Convert file to UTF-8 before importing

### 9.2 Error Messages

| Error | Cause | Solution |
|-------|-------|----------|
| "No data found in file" | Empty file or wrong format | Check file contents and format |
| "Mapping not found" | Invalid mapping ID | Verify the mapping ID in the Data Migration UI (`/dataMigration`) |
| "Title is required" | Records missing title field | Map a field to `title` |
| "Parent not found" | Invalid parent ID | Verify parent record exists |
| "Permission denied" | File system permissions | Check directory permissions |

### 9.3 Getting Help

1. Check this documentation
2. Review error logs: `storage/logs/`
3. Contact support with:
   - Error message
   - Source file sample (anonymized)
   - Mapping configuration
   - Steps to reproduce

---

## 10. Appendix: Field Reference

### 10.1 Archives (ISAD-G) Fields

| Heratio Field | ISAD-G Element | Description |
|------------|----------------|-------------|
| identifier | Reference Code | Unique identifier/reference |
| title | Title | Name of the unit |
| levelOfDescription | Level | Fonds, Series, File, Item |
| extentAndMedium | Extent | Physical extent |
| repository | Repository | Holding institution |
| archivalHistory | Archival History | Custodial history |
| scopeAndContent | Scope and Content | Description of contents |
| arrangement | Arrangement | Organization system |
| accessConditions | Access Conditions | Access restrictions |
| reproductionConditions | Reproduction | Copying conditions |
| language | Language | Language of materials |
| findingAids | Finding Aids | Available finding aids |
| locationOfOriginals | Location of Originals | Where originals held |
| locationOfCopies | Location of Copies | Where copies held |
| relatedUnitsOfDescription | Related Units | Related materials |
| publicationNote | Publication Note | Publications about material |
| generalNote | Notes | General notes |
| creators | Name of Creator | Creating entity |
| eventDates | Date(s) | Date range of materials |
| eventTypes | Event Type | Type of event |
| eventActors | Event Actor | Related actors |
| subjectAccessPoints | Subject | Subject terms |
| placeAccessPoints | Place | Geographic terms |
| nameAccessPoints | Name | Personal/corporate names |
| genreAccessPoints | Genre | Form/genre terms |

### 10.2 Library (MARC) Fields

| Heratio Field | MARC Field | Description |
|------------|------------|-------------|
| title | 245 | Title statement |
| alternativeTitle | 246 | Varying form of title |
| edition | 250 | Edition statement |
| placeOfPublication | 260$a | Place of publication |
| publisher | 260$b | Publisher name |
| publicationDate | 260$c | Date of publication |
| extentAndMedium | 300 | Physical description |
| seriesTitle | 490 | Series statement |
| isbn | 020 | ISBN |
| issn | 022 | ISSN |
| callNumber | 050/090 | Call number |
| subjectAccessPoints | 6XX | Subject headings |
| nameAccessPoints | 7XX | Added entries |

### 10.3 Museum (Spectrum) Fields

| Heratio Field | Spectrum Unit | Description |
|------------|---------------|-------------|
| identifier | Object Number | Unique object number |
| title | Object Name | Name of object |
| objectType | Object Type | Classification |
| physicalDescription | Physical Description | Physical characteristics |
| materials | Material | Materials used |
| dimensions | Dimension | Size measurements |
| inscriptions | Inscription | Inscriptions/markings |
| maker | Production Person | Creator/maker |
| dateOfProduction | Production Date | When made |
| placeOfProduction | Production Place | Where made |
| creditLine | Credit Line | Acknowledgment |
| currentLocation | Current Location | Storage location |
| acquisitionMethod | Acquisition Method | How acquired |
| acquisitionDate | Acquisition Date | When acquired |
| provenance | Provenance | Ownership history |

---

## Quick Reference Card

### Web UI Workflow
```
Upload → Map Fields → Preview → Import
         ↓
    [Save Mapping]
         ↓
    [Background Job] → Monitor Progress
```

### CLI Quick Commands
```bash
# Validate import (no writes)
php artisan sector:archives-csv-import file.csv --mapping=10 --validate-only

# Full import
php artisan sector:archives-csv-import file.csv --mapping=10 --repository=my-repo-slug
```

### URLs
- Upload: `/dataMigration`
- Jobs: `/dataMigration/jobs`
- Job Status: `/dataMigration/job/{id}`

---

*© 2026 The Archive and Heritage Group. All rights reserved.*
