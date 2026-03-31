# AtoM AHG Data Migration Tool
## Technical Documentation

**Version:** 1.0.0  
**Last Updated:** January 2026  
**Author:** The Archive and Heritage Group

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Database Schema](#2-database-schema)
3. [Component Details](#3-component-details)
4. [API Reference](#4-api-reference)
5. [File Parsers](#5-file-parsers)
6. [Gearman Job System](#6-gearman-job-system)
7. [Security Considerations](#7-security-considerations)
8. [Extension Points](#8-extension-points)
9. [Deployment](#9-deployment)
10. [Testing](#10-testing)

---

## 1. Architecture Overview

### 1.1 System Architecture
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              CLIENT LAYER                                    │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │   Browser   │  │   CLI       │  │   API       │  │   Cron      │        │
│  │   (Web UI)  │  │   (Symfony) │  │   (REST)    │  │   (Jobs)    │        │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘        │
└─────────┼────────────────┼────────────────┼────────────────┼────────────────┘
          │                │                │                │
          ▼                ▼                ▼                ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           APPLICATION LAYER                                  │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                    ahgDataMigrationPlugin                             │  │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐     │  │
│  │  │  Actions   │  │  Parsers   │  │  Services  │  │   Tasks    │     │  │
│  │  │            │  │            │  │            │  │            │     │  │
│  │  │ - upload   │  │ - CSV      │  │ - Mapping  │  │ - import   │     │  │
│  │  │ - map      │  │ - Excel    │  │ - Transform│  │ - export   │     │  │
│  │  │ - preview  │  │ - XML      │  │ - Import   │  │ - info     │     │  │
│  │  │ - import   │  │ - JSON     │  │ - Export   │  │            │     │  │
│  │  │ - queueJob │  │ - OPEX     │  │            │  │            │     │  │
│  │  │ - jobStatus│  │ - PAX      │  │            │  │            │     │  │
│  │  └────────────┘  └────────────┘  └────────────┘  └────────────┘     │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                      atom-framework (Laravel)                         │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                   │  │
│  │  │ Query       │  │ Repositories│  │ Services    │                   │  │
│  │  │ Builder     │  │             │  │             │                   │  │
│  │  └─────────────┘  └─────────────┘  └─────────────┘                   │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                      AtoM Core (Symfony 1.x)                          │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                   │  │
│  │  │ Propel ORM  │  │ Gearman     │  │ Elasticsearch│                  │  │
│  │  └─────────────┘  └─────────────┘  └─────────────┘                   │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
          │                │                │                │
          ▼                ▼                ▼                ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                             DATA LAYER                                       │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │   MySQL     │  │ Elasticsearch│  │ File System │  │  Gearman   │        │
│  │             │  │             │  │  (uploads)  │  │  Queue     │        │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘        │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 1.2 Directory Structure
```
/usr/share/nginx/archive/
├── atom-ahg-plugins/
│   └── ahgDataMigrationPlugin/
│       ├── config/
│       │   ├── ahgDataMigrationPluginConfiguration.class.php
│       │   └── routing.yml
│       ├── lib/
│       │   ├── Parsers/
│       │   │   ├── OpexParser.php
│       │   │   └── PaxParser.php
│       │   └── task/
│       │       ├── migrationImportTask.class.php
│       │       ├── preservicaImportTask.class.php
│       │       ├── preservicaExportTask.class.php
│       │       └── preservicaInfoTask.class.php
│       ├── modules/
│       │   └── dataMigration/
│       │       ├── actions/
│       │       │   ├── indexAction.class.php
│       │       │   ├── uploadAction.class.php
│       │       │   ├── mapAction.class.php
│       │       │   ├── previewAction.class.php
│       │       │   ├── importAction.class.php
│       │       │   ├── queueJobAction.class.php
│       │       │   ├── jobStatusAction.class.php
│       │       │   ├── jobProgressAction.class.php
│       │       │   ├── jobsAction.class.php
│       │       │   └── cancelJobAction.class.php
│       │       └── templates/
│       │           ├── indexSuccess.php
│       │           ├── mapSuccess.php
│       │           ├── previewSuccess.php
│       │           ├── jobStatusSuccess.php
│       │           └── jobsSuccess.php
│       └── data/
│           └── fixtures/
├── lib/
│   └── job/
│       └── arMigrationImportJob.class.php
└── uploads/
    └── migration/
        └── test_data/
```

### 1.3 Request Flow
```
┌──────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐
│  Upload  │────▶│  Parse   │────▶│   Map    │────▶│ Transform│
│  Action  │     │  File    │     │  Fields  │     │   Data   │
└──────────┘     └──────────┘     └──────────┘     └──────────┘
                                                         │
     ┌───────────────────────────────────────────────────┘
     │
     ▼
┌──────────┐     ┌──────────┐     ┌──────────┐
│ Preview  │────▶│  Import  │────▶│  Index   │
│  Data    │     │  Records │     │  Search  │
└──────────┘     └──────────┘     └──────────┘
```

---

## 2. Database Schema

### 2.1 Entity Relationship Diagram
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           DATA MIGRATION SCHEMA                              │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────┐       ┌─────────────────────────┐
│   atom_data_mapping     │       │   atom_migration_job    │
├─────────────────────────┤       ├─────────────────────────┤
│ PK id                   │       │ PK id                   │
│    name                 │◄──────│ FK mapping_id           │
│    description          │       │ FK job_id ──────────────┼──┐
│    target_type          │       │    name                 │  │
│    field_mappings (JSON)│       │    target_type          │  │
│    created_at           │       │    source_file          │  │
│    updated_at           │       │    source_format        │  │
└─────────────────────────┘       │    mapping_snapshot     │  │
                                  │    import_options (JSON)│  │
                                  │    status               │  │
┌─────────────────────────┐       │    total_records        │  │
│         job             │       │    processed_records    │  │
├─────────────────────────┤       │    imported_records     │  │
│ PK id                   │◄──────│    updated_records      │  │
│    name                 │       │    skipped_records      │  │
│    download_path        │       │    error_count          │  │
│    completed_at         │       │    error_log (JSON)     │  │
│ FK user_id              │       │    progress_message     │  │
│ FK object_id            │       │    started_at           │  │
│ FK status_id            │       │    completed_at         │  │
│    output               │       │ FK created_by           │  │
└─────────────────────────┘       │    created_at           │  │
         ▲                        └─────────────────────────┘  │
         │                                                      │
         └──────────────────────────────────────────────────────┘


┌─────────────────────────┐       ┌─────────────────────────┐
│  information_object     │       │        keymap           │
├─────────────────────────┤       ├─────────────────────────┤
│ PK id                   │◄──────│    target_id            │
│    identifier           │       │    target_name          │
│    level_of_desc_id     │       │    source_id            │
│ FK parent_id            │       │    source_name          │
│ FK repository_id        │       └─────────────────────────┘
│    source_culture       │
│    lft, rgt             │       Used for tracking imported
└─────────────────────────┘       records and updates
         │
         ▼
┌─────────────────────────┐
│ information_object_i18n │
├─────────────────────────┤
│ PK id                   │
│ PK culture              │
│    title                │
│    scope_and_content    │
│    extent_and_medium    │
│    ... (other i18n)     │
└─────────────────────────┘
```

### 2.2 Table Definitions

#### atom_data_mapping

Stores saved field mapping configurations.
```sql
CREATE TABLE atom_data_mapping (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    target_type VARCHAR(100) NOT NULL,
    field_mappings JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY idx_name (name),
    INDEX idx_target_type (target_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### field_mappings JSON Structure
```json
{
    "name": "Vernon CMS (Museum)",
    "target_type": "museum",
    "fields": [
        {
            "source_field": "sys_id",
            "atom_field": "legacyId",
            "constant_value": "",
            "concatenate": false,
            "include": true,
            "concat_constant": false,
            "concat_symbol": "|",
            "transform": null
        },
        {
            "source_field": "object_name",
            "atom_field": "title",
            "constant_value": "",
            "concatenate": false,
            "include": true,
            "concat_constant": false,
            "concat_symbol": "|",
            "transform": null
        }
    ]
}
```

#### atom_migration_job

Tracks background import jobs.
```sql
CREATE TABLE atom_migration_job (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT NULL COMMENT 'Links to AtoM job table',
    name VARCHAR(255) NULL,
    target_type VARCHAR(100) NOT NULL,
    source_file VARCHAR(500) NULL,
    source_format VARCHAR(50) NULL,
    mapping_id BIGINT UNSIGNED NULL,
    mapping_snapshot JSON NULL,
    import_options JSON NULL,
    status ENUM('pending','running','completed','failed','cancelled') DEFAULT 'pending',
    total_records INT DEFAULT 0,
    processed_records INT DEFAULT 0,
    imported_records INT DEFAULT 0,
    updated_records INT DEFAULT 0,
    skipped_records INT DEFAULT 0,
    error_count INT DEFAULT 0,
    error_log JSON NULL,
    progress_message VARCHAR(255) NULL,
    output_file VARCHAR(500) NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_status (status),
    INDEX idx_job_id (job_id),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (mapping_id) REFERENCES atom_data_mapping(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### import_options JSON Structure
```json
{
    "repository_id": 5,
    "parent_id": 12345,
    "culture": "en",
    "update_existing": true,
    "match_field": "legacyId",
    "image_path": "/path/to/images/",
    "first_row_header": 1,
    "sheet_index": 0,
    "delimiter": "auto"
}
```

---

## 3. Component Details

### 3.1 Actions

#### uploadAction.class.php

Handles file upload and initial parsing.
```php
class dataMigrationUploadAction extends sfAction
{
    public function execute($request)
    {
        // 1. Validate administrator access
        // 2. Handle file upload
        // 3. Detect file format
        // 4. Parse headers and sample rows
        // 5. Store in session
        // 6. Redirect to mapping
    }
    
    protected function parseFile($filepath, $ext, $options)
    {
        // Dispatch to appropriate parser based on extension
        // Returns: ['headers' => [], 'rows' => [], 'row_count' => int]
    }
}
```

#### mapAction.class.php

Displays field mapping interface.
```php
class dataMigrationMapAction extends sfAction
{
    public function execute($request)
    {
        // 1. Load session data
        // 2. Get AtoM field definitions for target type
        // 3. Load saved mapping if specified
        // 4. Auto-match fields
        // 5. Pass to template
    }
    
    protected function getAtomFields($targetType)
    {
        // Returns array of available AtoM fields for target type
    }
    
    protected function autoMatchFields($sourceFields, $atomFields)
    {
        // Attempts to match source fields to AtoM fields
    }
}
```

#### queueJobAction.class.php

Creates background job and queues to Gearman.
```php
class dataMigrationQueueJobAction extends sfAction
{
    public function execute($request)
    {
        // 1. Collect mapping from form
        // 2. Create atom_migration_job record
        // 3. Create AtoM job record
        // 4. Queue Gearman job
        // 5. Redirect to status page
    }
}
```

### 3.2 Gearman Job

#### arMigrationImportJob.class.php

Background worker for processing imports.
```php
class arMigrationImportJob extends arBaseJob
{
    protected $extraRequiredParameters = ['migrationJobId'];
    
    public function runJob($parameters)
    {
        // 1. Load migration job record
        // 2. Parse source file
        // 3. Load field mappings
        // 4. Process each row:
        //    a. Transform data
        //    b. Check for existing record
        //    c. Create or update
        //    d. Import digital object if specified
        //    e. Update progress
        // 5. Mark job complete
    }
    
    protected function updateMigrationJob($DB, $jobId, $data)
    {
        // Update progress in database
    }
    
    protected function transformRow($row, $headers, $fields)
    {
        // Apply field mappings to transform row data
    }
    
    protected function createRecord($DB, $record, $culture, $repositoryId, $parentId)
    {
        // Create new information_object and related records
    }
}
```

### 3.3 File Parsers

#### OpexParser.php
```php
namespace ahgDataMigrationPlugin\Parsers;

class OpexParser
{
    public function parse($filepath)
    {
        // 1. Load XML
        // 2. Register namespaces
        // 3. Extract Transfer info
        // 4. Extract Properties
        // 5. Extract Dublin Core elements
        // 6. Return array of records
    }
    
    protected function extractDublinCore($xml)
    {
        // Extract dc:* and dcterms:* elements
    }
}
```

#### PaxParser.php
```php
namespace ahgDataMigrationPlugin\Parsers;

class PaxParser
{
    public function parse($filepath)
    {
        // 1. Open ZIP archive
        // 2. Find metadata.xml or *.xip
        // 3. Parse XIP content
        // 4. Extract StructuralObjects
        // 5. Return array of records
    }
    
    protected function parseXipContent($content)
    {
        // Parse Preservica XIP format
    }
}
```

---

## 4. API Reference

### 4.1 Web Routes

| Route | Method | Action | Description |
|-------|--------|--------|-------------|
| `/dataMigration` | GET | index | Upload page |
| `/dataMigration/upload` | POST | upload | Handle file upload |
| `/dataMigration/map` | GET/POST | map | Field mapping page |
| `/dataMigration/preview` | POST | preview | Preview/import data |
| `/dataMigration/queue` | POST | queueJob | Queue background job |
| `/dataMigration/jobs` | GET | jobs | List all jobs |
| `/dataMigration/job/:id` | GET | jobStatus | Job status page |
| `/dataMigration/job/progress` | GET | jobProgress | AJAX progress endpoint |
| `/dataMigration/job/cancel` | POST | cancelJob | Cancel running job |
| `/dataMigration/mapping/save` | POST | saveMapping | Save field mapping |
| `/dataMigration/mapping/load/:id` | GET | loadMapping | Load saved mapping |
| `/dataMigration/mapping/delete/:id` | POST | deleteMapping | Delete mapping |

### 4.2 AJAX Endpoints

#### GET /dataMigration/job/progress?id={jobId}

Returns JSON with job progress:
```json
{
    "id": 42,
    "status": "running",
    "total_records": 150,
    "processed_records": 75,
    "imported_records": 72,
    "updated_records": 3,
    "skipped_records": 0,
    "error_count": 0,
    "progress_message": "Processing: 75/150 (50%)",
    "percent": 50,
    "started_at": "2026-01-16 10:30:05",
    "completed_at": null
}
```

#### POST /dataMigration/job/cancel?id={jobId}

Returns JSON:
```json
{
    "success": true
}
```

Or on error:
```json
{
    "success": false,
    "error": "Job already completed"
}
```

### 4.3 CLI Commands

#### migration:import
```bash
php symfony migration:import [source] [options]

Arguments:
  source                    Path to import file

Options:
  --mapping=ID|NAME         Mapping ID or name (required)
  --list-mappings           List available mappings
  --repository=ID           Repository ID
  --parent=ID               Parent information object ID
  --culture=CODE            Culture code (default: en)
  --update                  Update existing records
  --match-field=FIELD       Match field (legacyId|identifier)
  --output=MODE             Output mode (import|csv|preview)
  --output-file=PATH        Output file for CSV export
  --dry-run                 Simulate without changes
  --limit=N                 Limit rows to import
  --sheet=N                 Excel sheet index (0-based)
  --skip-header             First row is not header
  --delimiter=CHAR          CSV delimiter (auto|,|;|\t||)
```

---

## 5. File Parsers

### 5.1 Parser Interface

All parsers implement a common interface:
```php
interface ParserInterface
{
    /**
     * Parse file and return array of records.
     * 
     * @param string $filepath Path to file
     * @return array ['headers' => [], 'rows' => [], 'row_count' => int]
     */
    public function parse($filepath);
}
```

### 5.2 Supported Formats

| Format | Extension | Parser | Library |
|--------|-----------|--------|---------|
| CSV | .csv, .txt | Built-in | PHP fgetcsv |
| Excel | .xls, .xlsx | PhpSpreadsheet | PhpOffice |
| XML | .xml | Built-in | SimpleXML |
| JSON | .json | Built-in | json_decode |
| OPEX | .opex, .xml | OpexParser | SimpleXML |
| PAX | .pax, .zip | PaxParser | ZipArchive |

### 5.3 Adding New Parsers

To add support for a new format:

1. Create parser class in `lib/Parsers/`:
```php
namespace ahgDataMigrationPlugin\Parsers;

class MyFormatParser
{
    public function parse($filepath)
    {
        $headers = [];
        $rows = [];
        
        // Parse file...
        
        return [
            'headers' => $headers,
            'rows' => $rows,
            'row_count' => count($rows),
            'format' => 'myformat'
        ];
    }
}
```

2. Register in `uploadAction.class.php`:
```php
if ($ext === 'myformat') {
    return $this->parseMyFormat($filepath);
}
```

3. Add file extension to upload form accept list.

---

## 6. Gearman Job System

### 6.1 Architecture
```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Web Request   │────▶│  Gearman Server │────▶│  Job Worker     │
│   (queueJob)    │     │  (gearmand)     │     │  (jobs:worker)  │
└─────────────────┘     └─────────────────┘     └─────────────────┘
        │                       │                       │
        ▼                       ▼                       ▼
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│ atom_migration  │     │   Job Queue     │     │ Process Import  │
│ _job record     │     │   (in memory)   │     │ Update Progress │
└─────────────────┘     └─────────────────┘     └─────────────────┘
```

### 6.2 Job Lifecycle
```
┌──────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐
│ PENDING  │────▶│  QUEUED  │────▶│ RUNNING  │────▶│COMPLETED │
└──────────┘     └──────────┘     └──────────┘     └──────────┘
     │                                  │                │
     │                                  ▼                │
     │                           ┌──────────┐           │
     └──────────────────────────▶│  FAILED  │◀──────────┘
                                 └──────────┘
                                       │
                                       ▼
                                 ┌──────────┐
                                 │CANCELLED │
                                 └──────────┘
```

### 6.3 Progress Updates

The worker updates progress every 50 records:
```php
if ($processed % 50 === 0 || $processed === $totalRows) {
    $percent = round(($processed / $totalRows) * 100);
    $this->updateMigrationJob($DB, $jobId, [
        'processed_records' => $processed,
        'imported_records' => $stats['imported'],
        'progress_message' => "Processing: {$processed}/{$totalRows} ({$percent}%)"
    ]);
}
```

### 6.4 Cancellation

Jobs check for cancellation every 100 records:
```php
if ($i % 100 === 0) {
    $jobStatus = $DB::table('atom_migration_job')
        ->where('id', $jobId)
        ->value('status');
    
    if ($jobStatus === 'cancelled') {
        break;
    }
}
```

---

## 7. Security Considerations

### 7.1 Authentication

All actions require administrator privileges:
```php
if (!$this->context->user->isAdministrator()) {
    $this->forward('admin', 'secure');
}
```

### 7.2 File Upload Security

- Files are saved with random names to prevent path traversal
- File extensions are validated
- Upload directory is outside web root
- Maximum file size enforced by PHP configuration

### 7.3 SQL Injection Prevention

All database queries use Laravel Query Builder with parameterized queries:
```php
$DB::table('atom_migration_job')
    ->where('id', $jobId)  // Parameterized
    ->update($data);
```

### 7.4 XSS Prevention

All output is escaped in templates:
```php
<?php echo htmlspecialchars($job->name) ?>
```

---

## 8. Extension Points

### 8.1 Adding New Target Types

1. Add to target type list in `indexSuccess.php`
2. Add field definitions in `mapAction.class.php`
3. Add record creation logic in job worker

### 8.2 Adding New Field Transformations

1. Add transform option in `mapSuccess.php`
2. Implement transform logic in `transformRow()`:
```php
protected function applyTransform($value, $transform, $options)
{
    switch ($transform) {
        case 'my_transform':
            return myTransformFunction($value, $options);
        // ...
    }
}
```

### 8.3 Custom Import Logic

Override the job worker for custom import behavior:
```php
class MyCustomImportJob extends arMigrationImportJob
{
    protected function createRecord($DB, $record, $culture, $repositoryId, $parentId)
    {
        // Custom record creation logic
    }
}
```

---

## 9. Deployment

### 9.1 Requirements

- PHP 8.1+
- MySQL 8.0+
- Gearman Job Server
- PhpSpreadsheet (for Excel support)
- AtoM 2.8+
- atom-framework (Laravel Query Builder)

### 9.2 Installation
```bash
# 1. Plugin is part of atom-ahg-plugins
cd /usr/share/nginx/archive
git clone https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git

# 2. Create symlink
ln -sf /usr/share/nginx/archive/atom-ahg-plugins/ahgDataMigrationPlugin \
       /usr/share/nginx/archive/plugins/ahgDataMigrationPlugin

# 3. Enable plugin
php symfony extension:enable ahgDataMigrationPlugin

# 4. Run database migrations (if any)
mysql -u root archive < atom-ahg-plugins/ahgDataMigrationPlugin/data/install.sql

# 5. Clear cache
php symfony cc

# 6. Ensure Gearman workers are running
systemctl status gearman-job-server
ps aux | grep jobs:worker
```

### 9.3 Configuration

No additional configuration required. Plugin uses AtoM's existing:
- Database connection
- File upload settings
- Gearman configuration

### 9.4 Upgrading
```bash
cd /usr/share/nginx/archive/atom-ahg-plugins
git pull origin main
php symfony cc
```

---

## 10. Testing

### 10.1 Test Data

Test data files are provided in:
```
/usr/share/nginx/archive/uploads/migration/test_data/
```

| File | Records | Format | Description |
|------|---------|--------|-------------|
| archivesspace_resources.csv | 20 | CSV | Hierarchical archives |
| archivesspace_accessions.csv | 20 | CSV | Accession records |
| archivesspace_agents.csv | 20 | CSV | Authority records |
| vernon_museum.csv | 20 | CSV | Museum objects |
| psis_library.csv | 20 | CSV | Library items |
| preservica_opex.opex | 20 | OPEX | Government gazettes |
| wdb_archives.csv | 20 | CSV | Archives with hierarchy |

### 10.2 Manual Testing
```bash
# Test CLI import
php symfony migration:import --list-mappings

# Dry run
php symfony migration:import /path/test.csv --mapping=10 --dry-run

# Full import
php symfony migration:import /path/test.csv --mapping=10 --limit=5
```

### 10.3 Automated Testing
```bash
# Run plugin tests (if implemented)
php symfony test:unit ahgDataMigrationPlugin
```

---

## Appendix A: Field Mapping Reference

### A.1 AtoM Information Object Fields

| Field Name | Database Column | Type | Required |
|------------|-----------------|------|----------|
| identifier | identifier | string | No |
| title | title (i18n) | string | Yes |
| levelOfDescription | level_of_description_id | term | No |
| extentAndMedium | extent_and_medium (i18n) | text | No |
| repository | repository_id | FK | No |
| archivalHistory | archival_history (i18n) | text | No |
| scopeAndContent | scope_and_content (i18n) | text | No |
| arrangement | arrangement (i18n) | text | No |
| accessConditions | access_conditions (i18n) | text | No |
| reproductionConditions | reproduction_conditions (i18n) | text | No |
| language | language (i18n) | string | No |
| findingAids | finding_aids (i18n) | text | No |
| locationOfOriginals | location_of_originals (i18n) | text | No |
| locationOfCopies | location_of_copies (i18n) | text | No |
| generalNote | general_note (i18n) | text | No |
| legacyId | keymap.source_id | string | No |
| parentId | parent_id | FK | No |
| creators | Relation | Actor | No |
| eventDates | Event | Date | No |
| subjectAccessPoints | Relation | Term | No |
| placeAccessPoints | Relation | Term | No |
| nameAccessPoints | Relation | Term | No |
| digitalObjectPath | Digital Object | File | No |

---

*© 2026 The Archive and Heritage Group. All rights reserved.*
