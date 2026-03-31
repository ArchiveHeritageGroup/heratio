# ahgIngestPlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Ingestion
**Dependencies:** atom-framework, ahgCorePlugin, ahgSecurityClearancePlugin
**Load Order:** 100

---

## Overview

Multi-format ingestion wizard plugin providing a six-step guided workflow for importing archival records into AtoM. Supports CSV, ZIP, EAD, and directory-based imports across all five GLAM/DAM sectors (archive, museum, library, gallery, DAM) with automatic column mapping, validation, hierarchy building, digital object matching, and background commit processing. Integrates with preservation packaging (SIP/AIP/DIP), security classification, and AI enrichment services.

---

## Architecture

```
+---------------------------------------------------------------------+
|                        ahgIngestPlugin                               |
+---------------------------------------------------------------------+
|                                                                      |
|  +---------------------------------------------------------------+  |
|  |                   Plugin Configuration                         |  |
|  |  ahgIngestPluginConfiguration.class.php                        |  |
|  |  - Route registration (17 routes)                              |  |
|  |  - Module initialization                                       |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                  Wizard Step Actions                           |  |
|  |  +----------+  +--------+  +-----+  +--------+  +--------+   |  |
|  |  | configure|  | upload |  | map |  |validate|  |preview |   |  |
|  |  +----------+  +--------+  +-----+  +--------+  +--------+   |  |
|  |  +--------+  +-----------+  +--------+  +-----------+         |  |
|  |  | commit |  | rollback  |  | cancel |  | templates |         |  |
|  |  +--------+  +-----------+  +--------+  +-----------+         |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    AJAX Endpoints                              |  |
|  |  searchParent | autoMap | extractMetadata | jobStatus |        |  |
|  |  previewTree                                                   |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Service Layer                               |  |
|  |  IngestService              IngestCommitService                |  |
|  |  - Session management       - Job execution                    |  |
|  |  - File processing          - Record creation                  |  |
|  |  - Column mapping           - Derivative generation            |  |
|  |  - Validation               - SIP/AIP/DIP packaging           |  |
|  |  - Hierarchy building       - Search index update              |  |
|  |  - Template generation      - Manifest generation              |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    CLI Task Layer                              |  |
|  |  ingestCommitTask.class.php                                    |  |
|  |  - Background job processing via nohup                         |  |
|  |  - sfContext bootstrap + AhgDb initialization                  |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Database Tables                             |  |
|  |  ingest_session | ingest_file | ingest_mapping                |  |
|  |  ingest_validation | ingest_row | ingest_job                  |  |
|  +---------------------------------------------------------------+  |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## File Structure

```
ahgIngestPlugin/
+-- config/
|   +-- ahgIngestPluginConfiguration.class.php
+-- database/
|   +-- install.sql
+-- extension.json
+-- lib/
|   +-- Services/
|   |   +-- IngestService.php
|   |   +-- IngestCommitService.php
|   +-- task/
|       +-- ingestCommitTask.class.php
+-- modules/
    +-- ingest/
        +-- actions/
        |   +-- actions.class.php
        +-- templates/
            +-- indexSuccess.php
            +-- configureSuccess.php
            +-- uploadSuccess.php
            +-- mapSuccess.php
            +-- validateSuccess.php
            +-- previewSuccess.php
            +-- commitSuccess.php
```

---

## Database Schema

### ERD Diagram

```
+-------------------------------------------+     +-------------------------------------------+
|             ingest_session                 |     |              ingest_file                   |
+-------------------------------------------+     +-------------------------------------------+
| PK id INT AUTO_INCREMENT                  |     | PK id INT AUTO_INCREMENT                  |
|    user_id INT                            |<-+  |    session_id INT  ──────────────────>FK   |
|    title VARCHAR(255)                     |  |  |    file_type ENUM(csv,zip,ead,directory)   |
|    sector ENUM(archive,museum,library,    |  |  |    original_name VARCHAR(255)              |
|           gallery,dam)                    |  |  |    stored_path VARCHAR(500)                |
|    standard ENUM(isadg,dc,spectrum,       |  |  |    file_size BIGINT                       |
|             cco,rad,dacs)                 |  |  |    mime_type VARCHAR(100)                  |
|    repository_id INT                      |  |  |    row_count INT                           |
|    parent_id INT                          |  |  |    delimiter VARCHAR(5)                    |
|    parent_placement ENUM(existing,new,    |  |  |    encoding VARCHAR(50)                    |
|                     top_level,            |  |  |    headers JSON                            |
|                     csv_hierarchy)        |  |  |    extracted_path VARCHAR(500)              |
|    new_parent_title VARCHAR(255)          |  |  +-------------------------------------------+
|    new_parent_level VARCHAR(100)          |  |
|    output_create_records TINYINT(1)       |  |  +-------------------------------------------+
|    output_generate_sip TINYINT(1)         |  |  |            ingest_mapping                  |
|    output_generate_aip TINYINT(1)         |  |  +-------------------------------------------+
|    output_generate_dip TINYINT(1)         |  |  | PK id INT AUTO_INCREMENT                  |
|    output_sip_path VARCHAR(500)           |  +--|    session_id INT  ──────────────────>FK   |
|    output_aip_path VARCHAR(500)           |  |  |    source_column VARCHAR(255)              |
|    output_dip_path VARCHAR(500)           |  |  |    target_field VARCHAR(255)               |
|    derivative_thumbnails TINYINT(1)       |  |  |    is_ignored TINYINT(1)                   |
|    derivative_reference TINYINT(1)        |  |  |    default_value TEXT                      |
|    derivative_normalize_format VARCHAR(20)|  |  |    transform VARCHAR(255)                  |
|    security_classification_id INT         |  |  |    sort_order INT                          |
|    process_ner TINYINT(1)                 |  |  +-------------------------------------------+
|    process_ocr TINYINT(1)                 |  |
|    process_virus_scan TINYINT(1)          |  |  +-------------------------------------------+
|    process_summarize TINYINT(1)           |  |  |          ingest_validation                 |
|    process_spellcheck TINYINT(1)          |  |  +-------------------------------------------+
|    process_translate TINYINT(1)           |  |  | PK id INT AUTO_INCREMENT                  |
|    process_translate_lang VARCHAR(10)     |  +--|    session_id INT  ──────────────────>FK   |
|    process_format_id TINYINT(1)           |  |  |    row_number INT                          |
|    process_face_detect TINYINT(1)         |  |  |    severity ENUM(error,warning,info)       |
|    status ENUM(configure,upload,map,      |  |  |    field_name VARCHAR(255)                 |
|           validate,preview,commit,        |  |  |    message TEXT                             |
|           completed,failed,cancelled)     |  |  |    is_excluded TINYINT(1)                  |
|    config JSON                            |  |  +-------------------------------------------+
|    created_at TIMESTAMP                   |  |
|    updated_at TIMESTAMP                   |  |  +-------------------------------------------+
+-------------------------------------------+  |  |              ingest_row                    |
             |                                  |  +-------------------------------------------+
             |                                  |  | PK id INT AUTO_INCREMENT                  |
             v                                  +--|    session_id INT  ──────────────────>FK   |
+-------------------------------------------+  |  |    row_number INT                          |
|              ingest_job                    |  |  |    legacy_id VARCHAR(255)                  |
+-------------------------------------------+  |  |    parent_id_ref VARCHAR(255)              |
| PK id INT AUTO_INCREMENT                  |  |  |    level_of_description VARCHAR(100)       |
|    session_id INT  ──────────────────>FK --+  |  |    title VARCHAR(500)                     |
|    status ENUM(queued,running,            |     |    data JSON                               |
|           completed,failed,cancelled)     |     |    enriched_data JSON                      |
|    total_rows INT                         |     |    digital_object_path VARCHAR(500)         |
|    processed_rows INT                     |     |    digital_object_matched TINYINT(1)        |
|    created_records INT                    |     |    metadata_extracted JSON                  |
|    created_dos INT                        |     |    checksum_sha256 VARCHAR(64)              |
|    sip_package_id INT                     |     |    is_valid TINYINT(1)                      |
|    aip_package_id INT                     |     |    is_excluded TINYINT(1)                   |
|    dip_package_id INT                     |     |    created_atom_id INT                      |
|    error_count INT                        |     |    created_do_id INT                        |
|    error_log JSON                         |     +-------------------------------------------+
|    manifest_path VARCHAR(500)             |
|    started_at DATETIME                    |
|    completed_at DATETIME                  |
+-------------------------------------------+
```

### SQL Schema - ingest_session

```sql
CREATE TABLE IF NOT EXISTS ingest_session (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    sector ENUM('archive', 'museum', 'library', 'gallery', 'dam') NOT NULL,
    standard ENUM('isadg', 'dc', 'spectrum', 'cco', 'rad', 'dacs') NOT NULL DEFAULT 'isadg',
    repository_id INT,
    parent_id INT,
    parent_placement ENUM('existing', 'new', 'top_level', 'csv_hierarchy') NOT NULL DEFAULT 'top_level',
    new_parent_title VARCHAR(255),
    new_parent_level VARCHAR(100),
    output_create_records TINYINT(1) DEFAULT 1,
    output_generate_sip TINYINT(1) DEFAULT 0,
    output_generate_aip TINYINT(1) DEFAULT 0,
    output_generate_dip TINYINT(1) DEFAULT 0,
    output_sip_path VARCHAR(500),
    output_aip_path VARCHAR(500),
    output_dip_path VARCHAR(500),
    derivative_thumbnails TINYINT(1) DEFAULT 1,
    derivative_reference TINYINT(1) DEFAULT 1,
    derivative_normalize_format VARCHAR(20),
    security_classification_id INT,
    process_ner TINYINT(1) DEFAULT 0,
    process_ocr TINYINT(1) DEFAULT 0,
    process_virus_scan TINYINT(1) DEFAULT 0,
    process_summarize TINYINT(1) DEFAULT 0,
    process_spellcheck TINYINT(1) DEFAULT 0,
    process_translate TINYINT(1) DEFAULT 0,
    process_translate_lang VARCHAR(10),
    process_format_id TINYINT(1) DEFAULT 0,
    process_face_detect TINYINT(1) DEFAULT 0,
    status ENUM('configure', 'upload', 'map', 'validate', 'preview', 'commit', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'configure',
    config JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_sector (sector)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### SQL Schema - ingest_file

```sql
CREATE TABLE IF NOT EXISTS ingest_file (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    file_type ENUM('csv', 'zip', 'ead', 'directory') NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NOT NULL,
    file_size BIGINT,
    mime_type VARCHAR(100),
    row_count INT,
    delimiter VARCHAR(5),
    encoding VARCHAR(50),
    headers JSON,
    extracted_path VARCHAR(500),

    INDEX idx_session_id (session_id),
    CONSTRAINT fk_ingest_file_session FOREIGN KEY (session_id) REFERENCES ingest_session(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### SQL Schema - ingest_mapping

```sql
CREATE TABLE IF NOT EXISTS ingest_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    source_column VARCHAR(255) NOT NULL,
    target_field VARCHAR(255),
    is_ignored TINYINT(1) DEFAULT 0,
    default_value TEXT,
    transform VARCHAR(255),
    sort_order INT DEFAULT 0,

    INDEX idx_session_id (session_id),
    CONSTRAINT fk_ingest_mapping_session FOREIGN KEY (session_id) REFERENCES ingest_session(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### SQL Schema - ingest_validation

```sql
CREATE TABLE IF NOT EXISTS ingest_validation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    row_number INT,
    severity ENUM('error', 'warning', 'info') NOT NULL DEFAULT 'error',
    field_name VARCHAR(255),
    message TEXT NOT NULL,
    is_excluded TINYINT(1) DEFAULT 0,

    INDEX idx_session_id (session_id),
    INDEX idx_severity (session_id, severity),
    CONSTRAINT fk_ingest_validation_session FOREIGN KEY (session_id) REFERENCES ingest_session(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### SQL Schema - ingest_row

```sql
CREATE TABLE IF NOT EXISTS ingest_row (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    row_number INT NOT NULL,
    legacy_id VARCHAR(255),
    parent_id_ref VARCHAR(255),
    level_of_description VARCHAR(100),
    title VARCHAR(500),
    data JSON,
    enriched_data JSON,
    digital_object_path VARCHAR(500),
    digital_object_matched TINYINT(1) DEFAULT 0,
    metadata_extracted JSON,
    checksum_sha256 VARCHAR(64),
    is_valid TINYINT(1) DEFAULT 1,
    is_excluded TINYINT(1) DEFAULT 0,
    created_atom_id INT,
    created_do_id INT,

    INDEX idx_session_id (session_id),
    INDEX idx_session_row (session_id, row_number),
    INDEX idx_legacy_id (session_id, legacy_id),
    CONSTRAINT fk_ingest_row_session FOREIGN KEY (session_id) REFERENCES ingest_session(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### SQL Schema - ingest_job

```sql
CREATE TABLE IF NOT EXISTS ingest_job (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    status ENUM('queued', 'running', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'queued',
    total_rows INT DEFAULT 0,
    processed_rows INT DEFAULT 0,
    created_records INT DEFAULT 0,
    created_dos INT DEFAULT 0,
    sip_package_id INT,
    aip_package_id INT,
    dip_package_id INT,
    error_count INT DEFAULT 0,
    error_log JSON,
    manifest_path VARCHAR(500),
    started_at DATETIME,
    completed_at DATETIME,

    INDEX idx_session_id (session_id),
    INDEX idx_status (status),
    CONSTRAINT fk_ingest_job_session FOREIGN KEY (session_id) REFERENCES ingest_session(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Routes

Registered in `ahgIngestPluginConfiguration.class.php`:

| Route Name | URL | Action |
|------------|-----|--------|
| ingest_index | /ingest | index |
| ingest_new | /ingest/new | configure |
| ingest_configure | /ingest/:id/configure | configure |
| ingest_upload | /ingest/:id/upload | upload |
| ingest_map | /ingest/:id/map | map |
| ingest_validate | /ingest/:id/validate | validate |
| ingest_preview | /ingest/:id/preview | preview |
| ingest_commit | /ingest/:id/commit | commit |
| ingest_ajax_search_parent | /ingest/ajax/search-parent | searchParent |
| ingest_ajax_auto_map | /ingest/ajax/auto-map | autoMap |
| ingest_ajax_extract_metadata | /ingest/ajax/extract-metadata | extractMetadata |
| ingest_ajax_job_status | /ingest/ajax/job-status | jobStatus |
| ingest_ajax_preview_tree | /ingest/ajax/preview-tree | previewTree |
| ingest_cancel | /ingest/:id/cancel | cancel |
| ingest_rollback | /ingest/:id/rollback | rollback |
| ingest_download_manifest | /ingest/:id/manifest | downloadManifest |
| ingest_download_template | /ingest/template/:sector | downloadTemplate |

---

## Service Layer

### IngestService

Primary service for session management, file processing, column mapping, validation, and hierarchy building.

```php
namespace AhgIngestPlugin\Services;

use Illuminate\Database\Capsule\Manager as DB;

class IngestService
{
    /**
     * Get target fields for a given descriptive standard
     */
    public static function getTargetFields(string $standard = 'isadg'): array;

    /**
     * Get required fields for a given descriptive standard
     */
    public static function getRequiredFields(string $standard = 'isadg'): array;

    /**
     * Create a new ingest session
     */
    public function createSession(int $userId, array $config): int;

    /**
     * Update session configuration
     */
    public function updateSession(int $id, array $data): void;

    /**
     * Retrieve a session by ID
     */
    public function getSession(int $id): ?object;

    /**
     * List sessions for a user, optionally filtered by status
     */
    public function getSessions(int $userId, ?string $status = null): array;

    /**
     * Advance session to the next wizard step
     */
    public function updateSessionStatus(int $id, string $status): void;

    /**
     * Process an uploaded file (CSV, ZIP, EAD, directory)
     */
    public function processUpload(int $sessionId, array $fileInfo): int;

    /**
     * Auto-detect CSV delimiter, encoding, and headers
     */
    public function detectCsvFormat(string $filePath): array;

    /**
     * Extract ZIP archive contents to a working directory
     */
    public function extractZip(int $fileId, string $extractTo): array;

    /**
     * Scan a directory and catalog its contents
     */
    public function scanDirectory(string $dirPath): array;

    /**
     * Get all files associated with a session
     */
    public function getFiles(int $sessionId): array;

    /**
     * Parse uploaded CSV/EAD into ingest_row records
     */
    public function parseRows(int $sessionId): int;

    /**
     * Auto-map CSV columns to target fields using fuzzy matching
     */
    public function autoMapColumns(int $sessionId, string $standard = 'isadg'): array;

    /**
     * Save user-defined column mappings
     */
    public function saveMappings(int $sessionId, array $mappings): void;

    /**
     * Retrieve current mappings for a session
     */
    public function getMappings(int $sessionId): array;

    /**
     * Load a saved mapping profile into the current session
     */
    public function loadMappingProfile(int $sessionId, int $mappingId): void;

    /**
     * List all saved mapping profiles from atom_data_mapping
     */
    public function getSavedMappingProfiles(): array;

    /**
     * Run AI enrichment on parsed rows (NER, summarize, spellcheck, translate)
     */
    public function enrichRows(int $sessionId): void;

    /**
     * Extract embedded metadata (EXIF/IPTC/XMP) from digital objects
     */
    public function extractFileMetadata(int $sessionId): void;

    /**
     * Match digital objects to rows by filename, legacy ID, or path
     */
    public function matchDigitalObjects(int $sessionId, string $strategy = 'filename'): int;

    /**
     * Run full validation on session (required fields, data types, hierarchy)
     */
    public function validateSession(int $sessionId): array;

    /**
     * Retrieve validation messages, optionally filtered by severity
     */
    public function getValidationErrors(int $sessionId, ?string $severity = null): array;

    /**
     * Mark a row as excluded from import
     */
    public function excludeRow(int $sessionId, int $rowNumber, bool $exclude = true): void;

    /**
     * Fix a field value on a specific row
     */
    public function fixRow(int $sessionId, int $rowNumber, string $field, $value): void;

    /**
     * Build hierarchical tree structure from parsed rows
     */
    public function buildHierarchyTree(int $sessionId): array;

    /**
     * Get a single row for preview display
     */
    public function getPreviewRow(int $sessionId, int $rowNumber): ?object;

    /**
     * Get total row count for a session
     */
    public function getRowCount(int $sessionId): int;

    /**
     * Generate a downloadable CSV template for a sector/standard
     */
    public function generateCsvTemplate(string $sector, string $standard = 'isadg'): string;

    /**
     * Delete a session and all related data (cascades via FK)
     */
    public function deleteSession(int $id): void;
}
```

### IngestCommitService

Background job execution service for committing ingested data to AtoM.

```php
namespace AhgIngestPlugin\Services;

use Illuminate\Database\Capsule\Manager as DB;

class IngestCommitService
{
    /**
     * Create a new job record and return its ID
     */
    public function startJob(int $sessionId): int;

    /**
     * Get job status by job ID
     */
    public function getJobStatus(int $jobId): ?object;

    /**
     * Get job by session ID
     */
    public function getJobBySession(int $sessionId): ?object;

    /**
     * Execute the full commit job (called by CLI task)
     */
    public function executeJob(int $jobId): void;

    /**
     * Process a single row: create information object + digital object
     * Returns [legacyId => atomId] mapping or null on failure
     */
    public function processRow(int $jobId, object $row, object $session, array $legacyToAtomId): ?array;

    /**
     * Generate thumbnails and reference images for created digital objects
     */
    public function generateDerivatives(int $jobId): void;

    /**
     * Build SIP (Submission Information Package)
     */
    public function buildSipPackage(int $jobId): ?int;

    /**
     * Build AIP (Archival Information Package)
     */
    public function buildAipPackage(int $jobId): ?int;

    /**
     * Build DIP (Dissemination Information Package)
     */
    public function buildDipPackage(int $jobId): ?int;

    /**
     * Update Elasticsearch index for all created records
     */
    public function updateSearchIndex(int $jobId): void;

    /**
     * Generate JSON manifest with import summary and file list
     */
    public function generateManifest(int $jobId): string;

    /**
     * Roll back a completed job: delete created records and digital objects
     * Returns number of records removed
     */
    public function rollback(int $jobId): int;
}
```

---

## CLI Task

### ingest:commit

Background task for processing ingest jobs. Extends `arBaseTask` and is launched via `nohup` from the commit action.

```bash
# Process a specific job
php symfony ingest:commit --job-id=123

# Create a new job from a session and process it
php symfony ingest:commit --session-id=456
```

**Execution flow:**
1. Bootstraps sfContext and initializes AhgDb (Laravel Query Builder)
2. Loads job record and associated session
3. Calls `IngestCommitService::executeJob()`
4. Updates job status on completion or failure

---

## Flows

### Wizard Step Flow

```
+-----------+    +---------+    +------+    +----------+    +---------+    +--------+
| Configure |--->| Upload  |--->| Map  |--->| Validate |--->| Preview |--->| Commit |
| (Step 1)  |    | (Step 2)|    |(St 3)|    | (Step 4) |    | (Step 5)|    |(Step 6)|
+-----------+    +---------+    +------+    +----------+    +---------+    +--------+
      |                |            |             |               |             |
      v                v            v             v               v             v
  Select sector    Upload CSV   Auto-map      Validate       Build tree    Launch job
  Select standard  Upload ZIP   User adjust   Show errors    Preview rows  Poll status
  Set parent       Upload EAD   Save profile  Exclude rows   Show mapped   Report card
  Set outputs      Scan dir     Load profile  Fix rows       fields
  Set processing                                             Show hierarchy
```

### Commit Flow (Step 6)

```
User                    Browser                   Server (Action)              CLI Task (Background)
  |                        |                           |                              |
  |-- Click "Start" ------>|                           |                              |
  |                        |-- POST /ingest/:id/commit |                              |
  |                        |                           |                              |
  |                        |                           |-- Create ingest_job           |
  |                        |                           |   (status=queued)             |
  |                        |                           |                              |
  |                        |                           |-- nohup php symfony           |
  |                        |                           |   ingest:commit               |
  |                        |                           |   --job-id=X &  ------------>|
  |                        |                           |                              |
  |                        |<-- 200 OK (job_id) -------|                              |
  |                        |                           |                              |
  |                        |                           |              |-- Bootstrap sfContext
  |                        |                           |              |-- Init AhgDb
  |                        |                           |              |-- executeJob()
  |                        |                           |              |   |
  |  Poll every 2s         |                           |              |   |-- For each row:
  |<-----------------------|-- GET /ajax/job-status -->|              |   |   |-- createInformationObject
  |                        |<-- {processed, total} ----|              |   |   |-- createDigitalObject
  |                        |                           |              |   |   |-- Run AI processing
  |                        |                           |              |   |
  |                        |                           |              |   |-- generateDerivatives()
  |                        |                           |              |   |-- buildSipPackage()
  |                        |                           |              |   |-- buildAipPackage()
  |                        |                           |              |   |-- buildDipPackage()
  |                        |                           |              |   |-- updateSearchIndex()
  |                        |                           |              |   |-- generateManifest()
  |                        |                           |              |   |
  |                        |                           |              |   |-- Update job status
  |                        |                           |              |       (completed/failed)
  |                        |                           |              |
  |  Final poll            |                           |              |
  |<-----------------------|-- GET /ajax/job-status -->|              |
  |                        |<-- {status: completed} ---|              |
  |                        |                           |              |
  |<-- Report Card --------|                           |              |
  |    (counts, links,     |                           |              |
  |     manifest download) |                           |              |
```

### Auto-Map Flow

```
Browser                          Server (AJAX)                    IngestService
  |                                   |                                |
  |-- POST /ajax/auto-map ----------->|                                |
  |   {session_id, standard}          |                                |
  |                                   |-- autoMapColumns() ----------->|
  |                                   |                                |
  |                                   |                   Parse CSV headers
  |                                   |                   Load target fields
  |                                   |                   Fuzzy match columns
  |                                   |                   Score confidence
  |                                   |                                |
  |                                   |<-- [{source, target, score}] --|
  |<-- JSON mapping suggestions ------|                                |
  |                                   |                                |
  |  User adjusts mappings            |                                |
  |                                   |                                |
  |-- POST /ingest/:id/map --------->|                                |
  |   {mappings: [...]}               |-- saveMappings() ------------>|
  |                                   |                                |
  |<-- Redirect to validate ---------|                                |
```

### Rollback Flow

```
User                    Browser                   Server
  |                        |                         |
  |-- Click "Rollback" --->|                         |
  |                        |-- POST /:id/rollback -->|
  |                        |                         |
  |                        |            Load job + created_atom_id list
  |                        |            Delete digital objects (files + DB)
  |                        |            Delete information objects
  |                        |            Remove from Elasticsearch
  |                        |            Update job status -> cancelled
  |                        |            Update session status -> cancelled
  |                        |                         |
  |                        |<-- Redirect to index ---|
  |<-- Session list -------|                         |
```

---

## Configuration (extension.json)

```json
{
    "name": "AHG Ingest",
    "machine_name": "ahgIngestPlugin",
    "version": "1.0.0",
    "description": "Multi-format ingestion wizard for GLAM/DAM records with CSV, ZIP, EAD, and directory import support",
    "author": "The Archive and Heritage Group",
    "license": "GPL-3.0",
    "requires": {
        "atom_framework": ">=1.0.0",
        "atom": ">=2.8",
        "php": ">=8.1"
    },
    "dependencies": ["ahgCorePlugin", "ahgSecurityClearancePlugin"],
    "category": "ingestion",
    "load_order": 100
}
```

---

## Integration Points

| Integration | Source | Purpose |
|-------------|--------|---------|
| EmbeddedMetadataParser | atom-framework | EXIF/IPTC/XMP extraction from digital objects |
| PreservationService | ahgPreservationPlugin | SIP/AIP/DIP package building |
| SecurityClearanceService | ahgSecurityClearancePlugin | Apply security classification to imported records |
| atom_data_mapping table | ahgDataMigrationPlugin | Saved mapping profiles for reuse across sessions |
| ahg_settings table | ahgSettingsPlugin | Default processing options (NER, OCR, derivatives) |
| Elasticsearch | Base AtoM | Search index update after commit |

---

## Dependencies

| Package | Usage |
|---------|-------|
| atom-framework | Laravel Query Builder, EmbeddedMetadataParser |
| ahgCorePlugin | AhgDb initialization, base service classes |
| ahgSecurityClearancePlugin | Security classification assignment |
| ahgPreservationPlugin | SIP/AIP/DIP package generation (optional) |
| ahgSettingsPlugin | Default processing configuration (optional) |
| ahgDataMigrationPlugin | Saved mapping profile reuse (optional) |

---

## Troubleshooting

### Session Stuck in "upload" Status

1. Check uploaded files exist on disk:
```sql
SELECT stored_path, file_size FROM ingest_file WHERE session_id = ?;
```

2. Verify PHP upload limits:
```bash
php -i | grep -E 'upload_max|post_max|memory_limit'
```

3. Check file permissions on upload directory

### Auto-Map Returns No Matches

1. Verify CSV headers were detected:
```sql
SELECT headers FROM ingest_file WHERE session_id = ?;
```

2. Check that the correct standard is selected for the sector

3. Ensure CSV is UTF-8 encoded (BOM-free)

### Job Stuck in "running" Status

1. Check if CLI task is still running:
```bash
ps aux | grep ingest:commit
```

2. Review error log:
```sql
SELECT status, error_count, error_log, started_at FROM ingest_job WHERE id = ?;
```

3. Check PHP error log:
```bash
tail -100 /var/log/php8.3-fpm.log
```

4. Manually update job status if process died:
```sql
UPDATE ingest_job SET status = 'failed', completed_at = NOW() WHERE id = ? AND status = 'running';
```

### Validation Errors Not Showing

1. Ensure validation was run:
```sql
SELECT COUNT(*) FROM ingest_validation WHERE session_id = ?;
```

2. Check severity filter:
```sql
SELECT severity, COUNT(*) FROM ingest_validation WHERE session_id = ? GROUP BY severity;
```

### Digital Objects Not Matching

1. Check file paths in CSV match actual files:
```sql
SELECT digital_object_path, digital_object_matched FROM ingest_row WHERE session_id = ? AND digital_object_path IS NOT NULL;
```

2. Verify ZIP extraction path:
```sql
SELECT extracted_path FROM ingest_file WHERE session_id = ? AND file_type = 'zip';
```

3. Try different matching strategy (filename vs legacy_id vs path)

### Rollback Incomplete

1. Check for orphaned records:
```sql
SELECT created_atom_id, created_do_id FROM ingest_row WHERE session_id = ? AND created_atom_id IS NOT NULL;
```

2. Manually remove remaining records if needed

3. Re-run search index population:
```bash
php symfony search:populate
```

### Cache Issues After Import

1. Clear all caches:
```bash
rm -rf cache/*
php symfony cc
sudo systemctl restart php8.3-fpm
```

2. Repopulate search index:
```bash
php symfony search:populate
```

---

*Part of the AtoM AHG Framework*
