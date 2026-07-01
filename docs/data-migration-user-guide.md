> Heratio Help Center article. Category: Import/Export.

# Heratio AHG Framework - Data Migration Tool

## User Guide

**Last Updated:** 2026-07-01

---

## Table of Contents

1. [Overview](#1-overview)
2. [Accessing the Tool](#2-accessing-the-tool)
3. [Supported Source Systems](#3-supported-source-systems)
4. [Web Interface Workflow](#4-web-interface-workflow)
5. [Field Mapping](#5-field-mapping)
6. [Data Validation](#6-data-validation)
7. [Batch Export](#7-batch-export)
8. [Sector-Specific Import](#8-sector-specific-import)
9. [Sample CSV Files](#9-sample-csv-files)
10. [Preservica Import/Export](#10-preservica-importexport)
11. [Background Jobs](#11-background-jobs)
12. [CLI Commands](#12-cli-commands)
13. [Background Jobs (Queue Worker)](#13-background-jobs-queue-worker)
14. [Troubleshooting](#14-troubleshooting)

---

## 1. Overview

The Data Migration Tool enables importing records from various archival and collection management systems into Heratio. It supports:

- **CSV and Excel files** from multiple source systems
- **XML formats** (Preservica OPEX, EAD)
- **Preservica packages** (PAX/XIP with digital objects)
- **Sector-specific mappings** (Archives, Museum, Library, Gallery, DAM)
- **Background processing** for large datasets via the Laravel queue worker
- **Field transformation** and validation
- **Rights import** (PREMIS, SecurityDescriptor, dc:rights, MODS, EAD)
- **Provenance/history import** from OPEX

---

## 2. Accessing the Tool

### Web Interface

Navigate to: `https://[your-domain]/dataMigration`

Or: **Admin → Import/Export → Data Migration**

### Required Permissions

- Administrator access
- Or `import` permission in user group

---

## 3. Supported Source Systems

### Collection Management Systems

| System | Formats | Target Sector |
|--------|---------|---------------|
| **ArchivesSpace** | CSV/JSON | Archives, Accessions, Agents, Repositories |
| **Vernon CMS** | CSV/Excel | Museum |
| **PastPerfect** | CSV | Museum |
| **CollectiveAccess** | CSV | Multi-sector |
| **Filemaker Pro** | CSV | Any |
| **WDB** | CSV | Archives |
| **PSIS** | Excel (83 fields) | Library |

### Preservation Systems

| System | Formats | Features |
|--------|---------|----------|
| **Preservica** | OPEX XML | Metadata, rights, provenance import/export |
| **Preservica** | PAX/XIP (ZIP) | Metadata + digital objects |

### Standard Formats

| Format | Use Case |
|--------|----------|
| CSV | Universal import |
| Excel (.xlsx, .xls) | Spreadsheet data |
| XML | EAD, Dublin Core |

---

## 4. Web Interface Workflow

### Step 1: Upload File

1. Go to `/dataMigration`
2. Click **"Choose File"** or drag-and-drop
3. Supported: `.csv`, `.xlsx`, `.xls`, `.xml`, `.opex`, `.pax`, `.zip`
4. Click **"Upload"**

The system auto-detects:
- File format (CSV, Excel, XML)
- Source system (based on headers/structure)
- Sector type (Archives, Museum, Library, etc.)

### Step 2: Select or Create Mapping

**Use Existing Mapping:**
1. Select from dropdown (e.g., "Vernon CMS (Museum)")
2. Click **"Load Mapping"**

**Create New Mapping:**
1. Click **"New Mapping"**
2. Enter name (e.g., "My Museum Import")
3. Select target sector
4. Click **"Create"**

### Step 3: Map Fields

The mapping interface shows:
- **Left column**: Your source fields (from uploaded file)
- **Right column**: Heratio target fields

For each source field:
1. Click the dropdown
2. Select matching Heratio field
3. Optionally set **transformation** rules

**Field Transformations:**
- `trim` - Remove whitespace
- `uppercase` / `lowercase` - Case conversion
- `date:Y-m-d` - Date formatting
- `prepend:/uploads/` - Add prefix to paths
- `split:|` - Split multi-value fields

### Step 4: Preview

1. Click **"Preview"**
2. Review first 10-20 records
3. Check field mappings are correct
4. Verify hierarchy (parent-child relationships)

### Step 5: Import

**Option A: Export to Heratio CSV**
1. Click **"Export Heratio CSV"**
2. Download the transformed CSV
3. Use Heratio's built-in CSV Import (Admin → Import → CSV)

**Option B: Direct Import (Large Files)**
1. Click **"Background Job"**
2. Job queued to the background queue
3. Monitor progress at `/dataMigration/jobs`

---

## 5. Field Mapping

### Core Heratio Fields

| Heratio Field | Description | Required |
|------------|-------------|----------|
| `legacyId` | Unique ID from source system | Yes |
| `parentId` | Parent's legacyId for hierarchy | No |
| `title` | Record title | Yes |
| `identifier` | Reference code | No |
| `scopeAndContent` | Description/scope | No |
| `levelOfDescription` | Fonds/Series/File/Item | Yes |
| `repository` | Repository name or ID | No |
| `culture` | Language code (en, af, etc.) | No |

### Digital Object Fields

| Field | Description |
|-------|-------------|
| `digitalObjectPath` | Path to file (relative or absolute) |
| `digitalObjectURI` | External URL |
| `digitalObjectChecksum` | MD5/SHA256 for verification |

### Multi-Value Fields

Use pipe `|` separator for multiple values:
```
subjectAccessPoints: History|World War II|Military
placeAccessPoints: South Africa|Johannesburg
nameAccessPoints: Jan Smuts|Louis Botha
```

### Hierarchy Example
```csv
legacyId,parentId,title,levelOfDescription
F001,,Municipal Archives,Fonds
S001,F001,Council Minutes,Series
F001-001,S001,Minutes 1950-1960,File
F001-001-001,F001-001,Meeting 1950-01-15,Item
```

---

## 6. Data Validation

The validation framework helps you identify and fix data quality issues before importing.

### Validation Types

| Validation | Description |
|------------|-------------|
| **Schema** | Required fields, data types, patterns, max lengths |
| **Referential** | Parent-child relationships, circular reference detection |
| **Duplicates** | Duplicate detection in file and against existing database records |
| **Sector-Specific** | Standards-based rules for each GLAM sector |

### Validation-Only Mode

Test your data without importing:

**Web Interface:**
1. Upload your file
2. Map fields as usual
3. Click **"Validate Only"** instead of Import
4. Review errors/warnings by row and column

**CLI:**
```bash
php artisan sector:archives-csv-import /path/to/file.csv --validate-only
php artisan sector:museum-csv-import /path/to/file.csv --validate-only --mapping=10
```

### Understanding Validation Results

Results show errors by row and column:

| Severity | Icon | Action Required |
|----------|------|-----------------|
| **Error** | Red | Must fix before import |
| **Warning** | Yellow | Review recommended |
| **Info** | Blue | Informational only |

**Example output:**
```
Row 3, Column 'identifier': Required field is empty
Row 5, Column 'levelOfDescription': Invalid value 'folder' - must be one of: fonds, series, file, item
Row 8, Column 'parentId': Parent record '999' not found in file or database
```

### Sector-Specific Validation Rules

Each sector has specialized validation:

**Archives (ISAD-G):**
- Level of description must be valid (fonds, series, file, item, etc.)
- Parent hierarchy must follow ISAD-G rules
- Required: identifier, title, levelOfDescription

**Museum (Spectrum):**
- Object number format validation
- Acquisition date must be valid date
- Required: objectNumber, objectName

**Library (MARC/RDA):**
- ISBN-10 and ISBN-13 checksum validation
- ISSN validation
- Required: identifier, title

**Gallery (CCO):**
- Work type must be from controlled vocabulary
- Creator format validation (Name; Role)
- Required: objectNumber, title

**DAM (Dublin Core/IPTC):**
- DC type must be valid (Image, Audio, Video, Document, etc.)
- MIME type format validation
- GPS coordinate range validation
- Required: identifier, title

### Live Validation Preview

While mapping fields, click **"Preview Validation"** to see validation results for the first 20 rows without running a full import.

### Duplicate Detection Strategies

Configure how duplicates are detected:

| Strategy | Matches On |
|----------|------------|
| **Identifier** | identifier field |
| **Legacy ID** | legacyId field |
| **Title + Date** | Combination of title and date |
| **Composite** | Multiple configurable fields |

---

## 7. Batch Export

Export existing Heratio records to sector-specific CSV formats for backup, reporting, or migration to other systems.

### Accessing Batch Export

Navigate to: `https://[your-domain]/dataMigration/batchExport`

Or from the Data Migration page, click the **"Batch Export"** button in the header.

### Export Formats

| Format | Standard | Best For |
|--------|----------|----------|
| **Archives** | ISAD(G) | Archival fonds, series, files, items |
| **Museum** | Spectrum 5.1 | Museum objects with acquisition, location data |
| **Library** | MARC/RDA | Bibliographic records with ISBN, call numbers |
| **Gallery** | CCO/VRA | Artworks and visual resources |
| **Digital Assets** | Dublin Core/IPTC | Digital files with technical metadata |

### Filter Options

You can narrow down which records to export:

| Filter | Description |
|--------|-------------|
| **Repository** | Export only records from a specific repository |
| **Level of Description** | Filter by fonds, series, file, item, etc. (multi-select) |
| **Parent Slug** | Export children of a specific record |
| **Include Descendants** | Include all levels below the parent (not just direct children) |

### Export Workflow

1. Select the **Sector Format** for your CSV columns
2. Optionally set **filters** to narrow the export
3. Click **"Export CSV"**

**For small exports (<500 records):**
- CSV downloads immediately

**For large exports (>500 records):**
- Export is queued as a background job
- Check progress at `/dataMigration/jobs`
- Download file when complete

### Example Use Cases

**Backup a collection:**
1. Select "Archives (ISAD-G)" format
2. Enter the fonds slug in "Parent Record Slug"
3. Check "Include all descendants"
4. Export

**Export museum objects for reporting:**
1. Select "Museum (Spectrum 5.1)" format
2. Select your repository
3. Select "Item" level of description
4. Export

**Migrate records to another system:**
1. Choose the format closest to your target system
2. Export all records or filter by repository
3. Use the CSV in your target system's import tool

---

## 8. Sector-Specific Import

Import directly using sector-specific CLI commands with validation built-in.

### Archives Import (ISAD-G)

```bash
php artisan sector:archives-csv-import /path/to/archives.csv \
    --repository=my-archive \
    --update=identifier \
    --validate-only  # Remove to perform actual import
```

### Museum Import (Spectrum)

```bash
php artisan sector:museum-csv-import /path/to/museum.csv \
    --repository=my-museum \
    --update=identifier
```

### Library Import (MARC/RDA)

```bash
php artisan sector:library-csv-import /path/to/library.csv \
    --repository=my-library \
    --update=identifier
```

### Gallery Import (CCO)

```bash
php artisan sector:gallery-csv-import /path/to/gallery.csv \
    --repository=my-gallery \
    --update=identifier
```

### DAM Import (Dublin Core/IPTC)

```bash
php artisan sector:dam-csv-import /path/to/dam.csv \
    --repository=my-repository \
    --update=identifier
```

### Common Options

| Option | Description |
|--------|-------------|
| `--validate-only` | Validate without importing |
| `--mapping=ID` | Use specific mapping profile |
| `--repository=SLUG` | Target repository slug |
| `--update=FIELD` | Match field for updates (skip if exists) |

---

## 9. Sample CSV Files

The plugin includes sample CSV files demonstrating correct format for each sector.

### Available Samples

Downloadable from the Data Migration page (**Admin > Data Migration > Download templates**).

| File | Sector | Records | Description |
|------|--------|---------|-------------|
| `archives_sample.csv` | Archives | 5 | Hierarchical ISAD-G records with parent-child relationships |
| `museum_sample.csv` | Museum | 5 | Spectrum objects with materials, techniques, locations |
| `library_sample.csv` | Library | 5 | MARC/RDA records with ISBN, call numbers, subjects |
| `gallery_sample.csv` | Gallery | 5 | CCO artworks with provenance, credit lines |
| `dam_sample.csv` | DAM | 5 | Dublin Core assets with technical metadata |

### Archives Sample Structure

```csv
legacyId,parentId,identifier,title,levelOfDescription,repository,...
1,,F001,Smith Family Papers,Fonds,Main Archive,...
2,1,F001/S1,Correspondence,Series,Main Archive,...
3,2,F001/S1/F1,Personal Letters,File,Main Archive,...
```

Key features:
- `legacyId` and `parentId` establish hierarchy
- Parent records must appear before children
- Level of description follows ISAD-G

### Museum Sample Structure

```csv
objectNumber,objectName,title,materials,techniques,dimensions,productionDate,...
OBJ-001,Painting,Landscape with River,Canvas|Oil paint,Oil painting,60 x 80 cm,1935,...
```

Key features:
- Multi-value fields use pipe `|` separator
- Spectrum-compliant field names
- Acquisition and location tracking

### Using Samples for Testing

1. Copy sample to test location:
   ```bash
   cp data/samples/museum_sample.csv /tmp/test-import.csv
   ```

2. Test validation:
   ```bash
   php artisan sector:museum-csv-import /tmp/test-import.csv --validate-only
   ```

3. Import if validation passes:
   ```bash
   php artisan sector:museum-csv-import /tmp/test-import.csv --repository=test
   ```

---

## 10. Preservica Import/Export

### OPEX Import

OPEX (Open Preservation Exchange) is Preservica's XML metadata format.

**Web Interface:**
1. Upload `.opex` or `.xml` file
2. Select "Preservica OPEX" mapping
3. Map fields or use defaults
4. Preview and import

**How to run it:** Preservica OPEX import is handled through the Data Migration interface (**Admin > Data Migration > Preservica Import**, `/admin/data-migration/preservica/import`). Uploading a package queues a background `preservica_import` job; track it under **Data Migration > Jobs**.

**OPEX Rights Extraction:**
The importer automatically extracts rights from:
- `SecurityDescriptor` elements
- `dc:rights` Dublin Core
- `dcterms:license` 
- MODS `<accessCondition>`
- EAD `<userestrict>` and `<accessrestrict>`

**Provenance Import:**
OPEX `<opex:History>` elements are imported to `provenance_event` table.

### PAX/XIP Import

PAX packages contain metadata (XIP XML) plus content files.

**Web Interface:**
1. Upload `.pax` or `.zip` file
2. Select "Preservica PAX/XIP" mapping
3. Digital objects extracted automatically
4. Preview and import

**How to run it:** PAX/XIP import uses the same Data Migration interface - upload the `.pax`/`.zip` and select the **Preservica PAX/XIP** mapping. Digital objects are extracted automatically and the work runs as a queued job.

### Preservica Export

Export Heratio records to Preservica format through the Data Migration interface (**Admin > Data Migration > Preservica Export**, `/admin/data-migration/preservica/export`). Choose the record (optionally with its hierarchy) and the target format (OPEX or XIP/PAX); a background `preservica_export` job produces the package, downloadable from **Data Migration > Jobs** when complete.

---

## 11. Background Jobs

For large imports (1000+ records), use background processing:

### Starting a Background Job

1. Complete field mapping
2. Click **"Background Job"** instead of direct import
3. Job queued to the background queue

### Monitoring Jobs

Navigate to: `/dataMigration/jobs`

| Status | Description |
|--------|-------------|
| `queued` | Waiting for worker |
| `running` | Currently processing |
| `completed` | Finished successfully |
| `failed` | Error occurred |

### Job Details

Click any job to see:
- Records processed / total
- Errors encountered
- Processing time
- Download results

---

## 12. CLI Commands

CSV imports run through the sector-specific commands. Each takes a positional CSV filename plus options. Mapping profiles are created and listed in the Data Migration UI (**Admin > Data Migration > Mapping**); pass a profile's ID with `--mapping=`.

### Sector Import Commands

```bash
# Archives (ISAD-G)
php artisan sector:archives-csv-import /path/to/file.csv \
    --repository=SLUG --mapping=ID --update=identifier --validate-only

# Museum (Spectrum)
php artisan sector:museum-csv-import /path/to/file.csv \
    --repository=SLUG --mapping=ID --update=identifier --validate-only

# Library (MARC/RDA)
php artisan sector:library-csv-import /path/to/file.csv \
    --repository=SLUG --mapping=ID --update=identifier --validate-only

# Gallery (CCO)
php artisan sector:gallery-csv-import /path/to/file.csv \
    --repository=SLUG --mapping=ID --update=identifier --validate-only

# DAM (Dublin Core)
php artisan sector:dam-csv-import /path/to/file.csv \
    --repository=SLUG --mapping=ID --update=identifier --validate-only
```

**Common options** (all sector commands): `--validate-only`, `--mapping=ID`, `--repository=SLUG`, `--update=identifier|legacyId` (match field), `--update-mode=skip|update|merge`, `--culture=en`, `--limit=N`, `--skip=N`. Remove `--validate-only` to perform the actual import.

A simpler generic importer is also available: `php artisan ahg:csv-import /path/to/file.csv --source-name=NAME` (options: `--update=match|overwrite|skip`, `--skip-matched`, `--limit=N`, `--index`).

### Preservica (OPEX / PAX / XIP)

Preservica import and export are UI-driven, not CLI - use **Admin > Data Migration > Preservica Import / Export** (`/admin/data-migration/preservica/import`, `/admin/data-migration/preservica/export`). Each queues a background job tracked under **Data Migration > Jobs**.

---

## 13. Background Jobs (Queue Worker)

Large imports and exports run as background jobs on Heratio's Laravel queue. A queue worker must be running to process them.

### Running the Worker

**Development (manual):**
```bash
cd /usr/share/nginx/heratio
php artisan queue:work
```

**Production:** run the worker as a managed process (systemd service or Supervisor) so it restarts automatically. A typical invocation:
```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

### Verify

```bash
# Confirm a worker process is running
ps aux | grep 'queue:work'

# Inspect queued / failed jobs
php artisan queue:failed
```

Migration jobs and their status are also visible in the UI under **Admin > Data Migration > Jobs** (`/admin/data-migration/jobs`).

---

## 14. Troubleshooting

### File Upload Fails

**Problem:** File too large  
**Solution:** Increase PHP limits in `/etc/php/8.3/fpm/php.ini`:
```ini
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
```

### Mapping Not Found

**Problem:** Source columns not detected  
**Solution:** Ensure CSV has headers in first row, UTF-8 encoding

### Hierarchy Not Working

**Problem:** Parent-child relationships broken  
**Solution:** 
- Ensure `legacyId` is unique
- Ensure `parentId` matches a valid `legacyId`
- Parents must appear before children in file

### Digital Objects Not Importing

**Problem:** Files not attaching to records  
**Solution:**
- Check `digitalObjectPath` is correct
- Verify files exist at specified path
- Use absolute paths or paths relative to Heratio root

### Background Job Stuck

**Problem:** Job shows "running" but no progress  
**Solution:**
```bash
# Check that a queue worker is running
ps aux | grep 'queue:work'

# (Re)start the worker, or restart its systemd/Supervisor service
php artisan queue:work
```

### OPEX Rights Not Importing

**Problem:** Rights not appearing on records  
**Solution:**
- Verify OPEX contains `<SecurityDescriptor>` or `<dc:rights>`
- Check `ahg_rights_statement` table for imported rights
- Ensure the Rights module is enabled

---

## Quick Reference

| Task | Web UI | CLI |
|------|--------|-----|
| Import Archives | `/dataMigration` → Upload | `php artisan sector:archives-csv-import file.csv` |
| Import Museum | `/dataMigration` → Upload | `php artisan sector:museum-csv-import file.csv` |
| Import Library | `/dataMigration` → Upload | `php artisan sector:library-csv-import file.csv` |
| Import Gallery | `/dataMigration` → Upload | `php artisan sector:gallery-csv-import file.csv` |
| Import DAM | `/dataMigration` → Upload | `php artisan sector:dam-csv-import file.csv` |
| **Validate Only** | Map page → Validate | `php artisan sector:*-csv-import file.csv --validate-only` |
| Generic CSV import | Admin → Import | `php artisan ahg:csv-import file.csv --source-name=NAME` |
| Import OPEX / PAX | `/admin/data-migration/preservica/import` | N/A (UI-driven, queued job) |
| Export OPEX / PAX | `/admin/data-migration/preservica/export` | N/A (UI-driven, queued job) |
| **Batch Export** | `/dataMigration/batchExport` | N/A |
| **Export/Import Mapping** | Map page → Export / Import | N/A |
| View Jobs | `/dataMigration/jobs` | N/A |
| List / manage Mappings | `/admin/data-migration/mapping` | N/A |

---

## Version History

| Version | Changes |
|---------|---------|
| 1.4.0 | Universal validation framework, sector-specific validators (ISAD-G, Spectrum, MARC/RDA, CCO, Dublin Core), sector CLI import tasks, validation-only mode, sample CSV files, mapping profile export/import |
| 1.3.0 | Added Batch Export UI, Library/Gallery/DAM default mappings |
| 1.2.0 | Added Preservica OPEX/PAX support, rights import, provenance import, background jobs |
| 1.1.0 | Added sector-specific CSV exporters |
| 1.0.0 | Initial release with field mapping UI |

---

**Need Help?**

- Check `/dataMigration/jobs` for import status
- Review error logs: `/log/qubit.log`
- Contact: support@theahg.co.za
