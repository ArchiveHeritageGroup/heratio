# Migration Tools

## User Guide

Import data from external systems including Vernon CMS, ArchivesSpace, DB/TextWorks, and custom sources with sector-based field mapping.

---

## Overview
```
+---------------------------------------------------------------------+
|                        MIGRATION WORKFLOW                            |
+---------------------------------------------------------------------+
|                                                                     |
|   SOURCE                     MAPPING                  DESTINATION   |
|   SYSTEM                     ENGINE                   SECTOR        |
|      |                          |                         |         |
|      v                          v                         v         |
|  +---------+     +----------------------------+    +------------+  |
|  | Vernon  |     |                            |    | Archives   |  |
|  | CMS     | --> |   Field Mapping Designer   | -->| Museum     |  |
|  +---------+     |                            |    | Library    |  |
|  | Archives|     |  - Auto-detection          |    | Gallery    |  |
|  | Space   | --> |  - Transformation rules    | -->| DAM        |  |
|  +---------+     |  - Validation              |    +------------+  |
|  | DB/Text |     |  - Preview                 |         |         |
|  | Works   | --> |  - Batch processing        |         v         |
|  +---------+     +----------------------------+    +-----------+   |
|  | Custom  |                                       | AtoM      |   |
|  | CSV/XML | ------------------------------------->| Records   |   |
|  +---------+                                       +-----------+   |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Supported Source Systems
```
+---------------------------------------------------------------------+
|                     SUPPORTED SOURCES                                |
+---------------------------------------------------------------------+
|                                                                     |
|  Vernon CMS         Museum/gallery collections management          |
|  ArchivesSpace      Archival management system                     |
|  DB/TextWorks       Database/text management                       |
|  PastPerfect        Museum collection software                     |
|  CollectiveAccess   Open-source collections management             |
|  EAD Files          Encoded Archival Description (XML)             |
|  Custom CSV/XML     Any structured data format                     |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Destination Sectors
```
+---------------------------------------------------------------------+
|                    DESTINATION SECTORS                               |
+---------------------------------------------------------------------+
|  Sector     | Standard    | Plugin Required  | Description          |
+-------------+-------------+------------------+----------------------+
|  Archives   | ISAD(G)     | None (core)      | Archival descriptions|
|  Museum     | SPECTRUM    | ahgMuseumPlugin  | Museum objects       |
|  Library    | MARC/RDA    | ahgLibraryPlugin | Bibliographic records|
|  Gallery    | CCO/VRA     | ahgGalleryPlugin | Art & visual works   |
|  DAM        | Dublin Core | ahgDAMPlugin     | Digital assets       |
+---------------------------------------------------------------------+
```

---

## How to Access
```
  Main Menu
      |
      v
   Admin
      |
      v
   AHG Settings
      |
      v
   Migration Tools ----------------------------------------+
      |                                                    |
      +---> New Migration Job    (start import)            |
      |                                                    |
      +---> Migration Templates  (reusable mappings)       |
      |                                                    |
      +---> Job History          (view past imports)       |
```

---

## Starting a Migration

### Step 1: Create New Job

Go to **Admin** > **AHG Settings** > **Migration Tools** > **New Migration**

### Step 2: Upload Source File

```
+---------------------------------------------------------------------+
|                     UPLOAD SOURCE FILE                               |
+---------------------------------------------------------------------+
|                                                                     |
|   Supported Formats:                                                |
|   - CSV (comma-separated values)                                    |
|   - TSV (tab-separated values)                                      |
|   - XML (generic XML structure)                                     |
|   - EAD (Encoded Archival Description)                              |
|                                                                     |
|   +-------------------------------------------+                     |
|   |  Drop file here or click to browse        |                     |
|   |  Maximum file size: 100 MB                |                     |
|   +-------------------------------------------+                     |
|                                                                     |
|   File Encoding: [UTF-8            v]                               |
|   CSV Delimiter: [Auto-detect      v]                               |
|                                                                     |
+---------------------------------------------------------------------+
```

### Step 3: Auto-Detection

The system automatically detects:
- Source system (Vernon, ArchivesSpace, etc.)
- File format and encoding
- Column headers / field names

```
+---------------------------------------------------------------------+
|                     SOURCE DETECTION                                 |
+---------------------------------------------------------------------+
|                                                                     |
|   Detected Format:   CSV                                            |
|   Detected Source:   Vernon CMS                                     |
|   Confidence:        90%                                            |
|   Total Records:     1,247                                          |
|                                                                     |
|   Detected Headers:                                                 |
|   - Object Number                                                   |
|   - Primary Maker                                                   |
|   - Object Name                                                     |
|   - Date Made                                                       |
|   - Materials                                                       |
|   - ... (15 more fields)                                            |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Field Mapping Designer

### Step 4: Select Destination Sector

Choose where records will be imported:

```
+---------------------------------------------------------------------+
|                   SELECT DESTINATION                                 |
+---------------------------------------------------------------------+
|                                                                     |
|   ( ) Archives    - ISAD(G) archival descriptions                   |
|   (*) Museum      - SPECTRUM object records                         |
|   ( ) Library     - MARC/RDA bibliographic records                  |
|   ( ) Gallery     - CCO/VRA art records                             |
|   ( ) DAM         - Dublin Core digital assets                      |
|                                                                     |
|   Repository: [Select repository...         v]                      |
|   Parent:     [Select parent record...      v] (optional)           |
|                                                                     |
+---------------------------------------------------------------------+
```

### Step 5: Map Fields

The system suggests automatic mappings based on field name similarity:

```
+---------------------------------------------------------------------+
|                    FIELD MAPPING                                     |
+---------------------------------------------------------------------+
| Source Field       | Target Field          | Transform    | Status  |
+--------------------+-----------------------+--------------+---------+
| Object Number      | identifier            | -            | Auto    |
| Object Name        | title                 | -            | Auto    |
| Primary Maker      | creators              | createActor  | Auto    |
| Date Made          | eventDates            | parseDate    | Auto    |
| Materials          | materials             | splitMulti   | Auto    |
| Description        | scopeAndContent       | -            | Auto    |
| Current Location   | physicalStorage       | -            | Auto    |
| Condition          | condition             | mapTaxonomy  | Auto    |
| -                  | levelOfDescription    | -            | Default |
+--------------------+-----------------------+--------------+---------+
|                                                                     |
|   [Add Mapping]  [Clear All]  [Load Template]  [Save Template]      |
|                                                                     |
+---------------------------------------------------------------------+
```

### Mapping Options

For each field mapping, you can configure:

```
+---------------------------------------------------------------------+
|                    FIELD MAPPING OPTIONS                             |
+---------------------------------------------------------------------+
|                                                                     |
|   Source Field:   [Primary Maker      v]                            |
|                                                                     |
|   Target Field:   [creators           v]                            |
|                                                                     |
|   Transformation: [createActor        v]                            |
|                   - parseDate                                       |
|                   - splitMultiValue                                 |
|                   - mapLevel                                        |
|                   - createActor                                     |
|                   - trim                                            |
|                   - uppercase                                       |
|                   - (more...)                                       |
|                                                                     |
|   Options:                                                          |
|   Actor Type: [person     v]                                        |
|                                                                     |
|                                    [Apply]  [Cancel]                |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Transformations

Available data transformations:

```
+---------------------------------------------------------------------+
|                    TRANSFORMATION TYPES                              |
+---------------------------------------------------------------------+
|                                                                     |
|  DATE TRANSFORMATIONS                                               |
|  - parseDate           Auto-detect date format                      |
|  - parseDateRange      Extract start/end dates                      |
|  - parseVernonDate     Handle circa, decade dates                   |
|  - formatDate          Convert to specific format                   |
|                                                                     |
|  MAPPING TRANSFORMATIONS                                            |
|  - mapLevel            Map to level of description                  |
|  - mapTaxonomy         Map to existing taxonomy term                |
|  - mapBoolean          Convert yes/no to true/false                 |
|  - mapEntityType       Map to person/corporate                      |
|                                                                     |
|  MULTI-VALUE TRANSFORMATIONS                                        |
|  - splitMultiValue     Split by delimiter (|, ;, etc.)              |
|  - joinValues          Combine multiple values                      |
|  - firstValue          Take only first value                        |
|                                                                     |
|  ACTOR TRANSFORMATIONS                                              |
|  - createActor         Create person actor                          |
|  - createCorporateBody Create corporate body                        |
|  - createFamily        Create family actor                          |
|                                                                     |
|  TEXT TRANSFORMATIONS                                               |
|  - trim                Remove leading/trailing spaces               |
|  - uppercase           Convert to UPPERCASE                         |
|  - lowercase           Convert to lowercase                         |
|  - titlecase           Convert to Title Case                        |
|  - stripHtml           Remove HTML tags                             |
|  - normalizeWhitespace Collapse multiple spaces                     |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Default Values

Set default values for fields not in your source:

```
+---------------------------------------------------------------------+
|                    DEFAULT VALUES                                    |
+---------------------------------------------------------------------+
|                                                                     |
|   Field                    Default Value                            |
|   +------------------------+-----------------------------------+    |
|   | levelOfDescription     | Item                              |    |
|   | language               | en                                |    |
|   | accessConditions       | Open access                       |    |
|   | repository             | Main Repository                   |    |
|   +------------------------+-----------------------------------+    |
|                                                                     |
|   [Add Default]                                                     |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Validation and Preview

### Step 6: Validate Data

Before importing, the system validates all records:

```
+---------------------------------------------------------------------+
|                    VALIDATION RESULTS                                |
+---------------------------------------------------------------------+
|                                                                     |
|   Total Records:     1,247                                          |
|   Valid:             1,198  (96%)    [GREEN BAR]                    |
|   Warnings:             42  (3%)     [YELLOW BAR]                   |
|   Errors:                7  (1%)     [RED BAR]                      |
|                                                                     |
|   VALIDATION ISSUES:                                                |
|   +-----------------------------------------------------------+    |
|   | Row | Field      | Issue                        | Status  |    |
|   +-----+------------+------------------------------+---------+    |
|   |  23 | identifier | Duplicate value              | Warning |    |
|   | 145 | title      | Missing required field       | Error   |    |
|   | 267 | eventDates | Invalid date format          | Warning |    |
|   | 891 | creators   | Empty after transformation   | Warning |    |
|   +-----------------------------------------------------------+    |
|                                                                     |
|   [View Details]  [Export Errors]  [Fix and Re-validate]           |
|                                                                     |
+---------------------------------------------------------------------+
```

### Step 7: Preview Records

View how records will appear after import:

```
+---------------------------------------------------------------------+
|                    RECORD PREVIEW                                    |
+---------------------------------------------------------------------+
|   Showing record 1 of 1,247                    [< Prev] [Next >]    |
|                                                                     |
|   ORIGINAL DATA                    MAPPED DATA                      |
|   +------------------------+       +---------------------------+    |
|   | Object Number: M2024-1 |  -->  | identifier: M2024-1       |    |
|   | Object Name: Vase      |  -->  | title: Vase               |    |
|   | Primary Maker: J Smith |  -->  | creators:                 |    |
|   |                        |       |   - name: J Smith         |    |
|   |                        |       |   - type: person          |    |
|   | Date Made: c.1920      |  -->  | eventDates:               |    |
|   |                        |       |   - description: c.1920   |    |
|   |                        |       |   - startDate: 1915-01-01 |    |
|   |                        |       |   - endDate: 1925-12-31   |    |
|   | Materials: Ceramic;    |  -->  | materials:                |    |
|   |   glaze                |       |   - Ceramic               |    |
|   |                        |       |   - glaze                 |    |
|   +------------------------+       +---------------------------+    |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Output Options

### Step 8: Choose Output Mode

```
+---------------------------------------------------------------------+
|                    OUTPUT OPTIONS                                    |
+---------------------------------------------------------------------+
|                                                                     |
|   Output Mode:                                                      |
|   (*) Direct Import    - Import records directly into AtoM          |
|   ( ) Export CSV       - Export mapped data for review              |
|   ( ) Both             - Import and create export file              |
|                                                                     |
|   Import Options:                                                   |
|   [x] Match existing records by identifier                          |
|   [ ] Update existing records if found                              |
|   [x] Create new actors automatically                               |
|   [x] Create new taxonomy terms automatically                       |
|   [ ] Skip records with errors                                      |
|                                                                     |
|   Batch Size: [100   ] records per batch                            |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Running the Import

### Step 9: Execute Import

```
+---------------------------------------------------------------------+
|                    IMPORT PROGRESS                                   |
+---------------------------------------------------------------------+
|                                                                     |
|   Status: Importing...                                              |
|                                                                     |
|   [====================--------------------] 50%                    |
|                                                                     |
|   Processed:    623 / 1,247                                         |
|   Created:      598                                                 |
|   Updated:       18                                                 |
|   Skipped:        7                                                 |
|   Errors:         0                                                 |
|                                                                     |
|   Estimated time remaining: 3 minutes                               |
|                                                                     |
|   [Pause]  [Cancel]                                                 |
|                                                                     |
+---------------------------------------------------------------------+
```

### Step 10: View Results

```
+---------------------------------------------------------------------+
|                    IMPORT COMPLETE                                   |
+---------------------------------------------------------------------+
|                                                                     |
|   Migration Job: Vernon Museum Import                               |
|   Status:        Completed                                          |
|   Duration:      6 minutes 42 seconds                               |
|                                                                     |
|   RESULTS:                                                          |
|   +-----------------------------------+                             |
|   | Total Processed    | 1,247       |                             |
|   | Records Created    | 1,215       |                             |
|   | Records Updated    |    25       |                             |
|   | Records Skipped    |     7       |                             |
|   | Errors             |     0       |                             |
|   +-----------------------------------+                             |
|                                                                     |
|   [View Created Records]  [Download Log]  [View Errors]             |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Migration Templates

Save and reuse field mapping configurations:

### Saving a Template

```
+---------------------------------------------------------------------+
|                    SAVE TEMPLATE                                     |
+---------------------------------------------------------------------+
|                                                                     |
|   Template Name: [Vernon to Museum Objects              ]           |
|   Description:   [Standard mapping for Vernon CMS exports]          |
|                                                                     |
|   Source System: Vernon CMS                                         |
|   Destination:   Museum                                             |
|                                                                     |
|   Field Mappings:    15 mappings                                    |
|   Transformations:   8 transformations                              |
|   Default Values:    3 defaults                                     |
|                                                                     |
|                                         [Save Template]             |
|                                                                     |
+---------------------------------------------------------------------+
```

### Using a Template

```
+---------------------------------------------------------------------+
|                    SELECT TEMPLATE                                   |
+---------------------------------------------------------------------+
|                                                                     |
|   Available Templates:                                              |
|   +---------------------------------------------------------------+ |
|   | Name                      | Source       | Destination | Used  | |
|   +---------------------------+--------------+-------------+-------+ |
|   | Vernon to Museum Objects  | Vernon CMS   | Museum      |  12   | |
|   | ArchivesSpace to Archives | ArchivesSpace| Archives    |   8   | |
|   | EAD Import Standard       | EAD          | Archives    |  25   | |
|   | Library CSV Import        | Custom       | Library     |   4   | |
|   +---------------------------+--------------+-------------+-------+ |
|                                                                     |
|   [Use Selected]  [Edit]  [Delete]  [Export]  [Import]              |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## EAD Import

Special support for Encoded Archival Description files:

```
+---------------------------------------------------------------------+
|                    EAD IMPORT                                        |
+---------------------------------------------------------------------+
|                                                                     |
|   EAD Version:    EAD 2002                                          |
|   Collection:     Smith Family Papers                               |
|   Finding Aid ID: ead-2024-001                                      |
|                                                                     |
|   Hierarchy Detected:                                               |
|   +-- Fonds: Smith Family Papers (1)                                |
|       +-- Series: Correspondence (15 items)                         |
|       +-- Series: Photographs (42 items)                            |
|       +-- Series: Financial Records (28 items)                      |
|           +-- File: Bank Statements (12 items)                      |
|           +-- File: Receipts (16 items)                             |
|                                                                     |
|   Total Components: 87                                              |
|                                                                     |
|   EAD Fields Mapped:                                                |
|   - unittitle    --> title                                          |
|   - unitid       --> identifier                                     |
|   - unitdate     --> eventDates                                     |
|   - scopecontent --> scopeAndContent                                |
|   - origination  --> creators                                       |
|   - (12 more fields)                                                |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Job History

View and manage past migrations:

```
+---------------------------------------------------------------------+
|                    MIGRATION HISTORY                                 |
+---------------------------------------------------------------------+
|                                                                     |
| Date       | Name                    | Records | Status    | Action |
+------------+-------------------------+---------+-----------+--------+
| 2026-01-30 | Vernon Museum Import    | 1,247   | Completed | View   |
| 2026-01-28 | EAD Finding Aid Import  |    87   | Completed | View   |
| 2026-01-25 | Library Books CSV       |   342   | Completed | View   |
| 2026-01-20 | ArchivesSpace Export    | 2,156   | Failed    | View   |
| 2026-01-15 | Digital Assets Import   |   890   | Completed | View   |
+------------+-------------------------+---------+-----------+--------+
|                                                                     |
|   Filter by: [Status: All   v]  [Source: All    v]  [Search...]     |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Rollback

If needed, you can review what was imported and identify records for potential manual cleanup:

```
+---------------------------------------------------------------------+
|                    IMPORT LOG DETAILS                                |
+---------------------------------------------------------------------+
|                                                                     |
|   Job: Vernon Museum Import                                         |
|   Date: 2026-01-30 14:32                                            |
|                                                                     |
|   Records Created: 1,215                                            |
|                                                                     |
|   Export list of created records to:                                |
|   (*) CSV file with identifiers and slugs                           |
|   ( ) Text file with links                                          |
|                                                                     |
|   [Export Record List]                                              |
|                                                                     |
|   Note: Manual deletion required through AtoM interface             |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Tips for Successful Migration
```
+--------------------------------+----------------------------------+
|  DO                            |  DON'T                           |
+--------------------------------+----------------------------------+
|  Preview sample records first  |  Import without validation       |
|  Save templates for reuse      |  Recreate mappings each time     |
|  Validate before importing     |  Skip the preview step           |
|  Start with small test batch   |  Import full dataset first       |
|  Document your mappings        |  Rely on memory                  |
|  Check encoding before upload  |  Assume UTF-8 encoding           |
|  Back up before large imports  |  Import without backup           |
+--------------------------------+----------------------------------+
```

---

## Common Field Mappings

### Vernon CMS to Museum
```
Vernon Field          -->  AtoM Field
---------------------------------------------
Object Number         -->  identifier
Object Name           -->  title
Primary Maker         -->  creators
Date Made             -->  eventDates
Materials             -->  materials
Description           -->  scopeAndContent
Current Location      -->  physicalStorage
Condition             -->  condition
Image Reference       -->  digitalObject.path
```

### ArchivesSpace to Archives
```
ArchivesSpace Field   -->  AtoM Field
---------------------------------------------
component_id          -->  identifier
title                 -->  title
date_expression       -->  eventDates.description
begin_date            -->  eventDates.startDate
end_date              -->  eventDates.endDate
scope_content         -->  scopeAndContent
level                 -->  levelOfDescription
```

---

## Need Help?

Contact your system administrator if you experience issues with data migration.

---

*Part of the AtoM AHG Framework*
