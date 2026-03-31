# ahgPreservationPlugin - Technical Documentation

## Overview

The ahgPreservationPlugin provides comprehensive digital preservation capabilities for AtoM, implementing OAIS (Open Archival Information System) and PREMIS (Preservation Metadata Implementation Strategies) standards. The plugin includes PRONOM-based format identification (using Siegfried), virus scanning, format conversion, backup verification, and replication features.

**Version:** 1.5.0
**Category:** Preservation
**Dependencies:** atom >= 2.8.0, PHP >= 8.1

**Optional Dependencies:**
- Siegfried (PRONOM format identification)
- ClamAV (virus scanning)
- ImageMagick (image conversion)
- FFmpeg (audio/video conversion)
- LibreOffice (document conversion)
- Ghostscript (PDF processing)

---

## Architecture

### Component Diagram

```
+-------------------------------------------------------------------------+
|                        ahgPreservationPlugin                             |
+-------------------------------------------------------------------------+
|                                                                          |
|  +-------------------------------------------------------------------+  |
|  |                      PRESENTATION LAYER                            |  |
|  +-------------------------------------------------------------------+  |
|  |  +----------+ +----------+ +----------+ +----------+ +----------+ |  |
|  |  |Dashboard | |Identific | |VirusScan | |Conversion| | Backup   | |  |
|  |  | Template | | Template | | Template | | Template | | Template | |  |
|  |  +----------+ +----------+ +----------+ +----------+ +----------+ |  |
|  +-------------------------------------------------------------------+  |
|                                    |                                     |
|                                    v                                     |
|  +-------------------------------------------------------------------+  |
|  |                      CONTROLLER LAYER                              |  |
|  +-------------------------------------------------------------------+  |
|  |                    actions.class.php                               |  |
|  |  +------------+ +------------+ +------------+ +------------+       |  |
|  |  |executeIndex| |executeVirus| |executeConv | |executeBackup|      |  |
|  |  +------------+ +------------+ +------------+ +------------+       |  |
|  +-------------------------------------------------------------------+  |
|                                    |                                     |
|                                    v                                     |
|  +-------------------------------------------------------------------+  |
|  |                       SERVICE LAYER                                |  |
|  +-------------------------------------------------------------------+  |
|  |                   PreservationService.php                          |  |
|  |  +---------------------------------------------------------------+ |  |
|  |  | Checksum Operations    | Fixity Verification    |             | |  |
|  |  | generateChecksums()    | verifyFixity()         |             | |  |
|  |  | getChecksums()         | runBatchFixityCheck()  |             | |  |
|  |  +---------------------------------------------------------------+ |  |
|  |  | Format Identification  | Virus Scanning         |             | |  |
|  |  | identifyFormat()       | scanForVirus()         |             | |  |
|  |  | runBatchIdentification | runBatchVirusScan()    |             | |  |
|  |  | isSiegfriedAvailable() | isClamAvAvailable()    |             | |  |
|  |  +---------------------------------------------------------------+ |  |
|  |  | Format Conversion      | Backup & Replication   |             | |  |
|  |  | convertFormat()        | verifyBackup()         |             | |  |
|  |  | getConversionTools()   | verifyAllBackups()     |             | |  |
|  |  | selectConversionTool() | getStatistics()        |             | |  |
|  |  +---------------------------------------------------------------+ |  |
|  +-------------------------------------------------------------------+  |
|                                    |                                     |
|                                    v                                     |
|  +-------------------------------------------------------------------+  |
|  |                         CLI TASKS                                  |  |
|  +-------------------------------------------------------------------+  |
|  | preservationIdentifyTask    | preservationFixityTask              |  |
|  | preservationVirusScanTask   | preservationConvertTask             |  |
|  | preservationVerifyBackupTask| preservationReplicateTask           |  |
|  +-------------------------------------------------------------------+  |
|                                    |                                     |
|                                    v                                     |
|  +-------------------------------------------------------------------+  |
|  |                       DATA LAYER                                   |  |
|  +-------------------------------------------------------------------+  |
|  |         Illuminate\Database\Capsule\Manager (Laravel QB)          |  |
|  +-------------------------------------------------------------------+  |
|                                                                          |
+-------------------------------------------------------------------------+
```

---

## Database Schema

### Entity Relationship Diagram

```
+-------------------------------------------------------------------------+
|                    PRESERVATION DATABASE SCHEMA                          |
+-------------------------------------------------------------------------+

+----------------------+          +----------------------+
|    digital_object    |          |  information_object  |
|   (AtoM Core Table)  |          |   (AtoM Core Table)  |
+----------------------+          +----------------------+
| PK id                |          | PK id                |
|    name              |          |    ...               |
|    path              |          +----------------------+
|    byte_size         |                    ^
|    object_id --------+--------------------|
+----------------------+
         |
         | 1:N
         v
+----------------------+     +----------------------+
| preservation_checksum|     |preservation_fixity_  |
+----------------------+     |       check          |
| PK id                |     +----------------------+
| FK digital_object_id |<----| PK id                |
|    algorithm         |     | FK digital_object_id |
|    checksum_value    |     | FK checksum_id       |
|    file_size         |     |    algorithm         |
|    generated_at      |     |    expected_value    |
|    verified_at       |     |    actual_value      |
|    verification_     |     |    status            |
|      status          |     |    error_message     |
|    created_at        |     |    checked_at        |
|    updated_at        |     |    checked_by        |
+----------------------+     |    duration_ms       |
                             |    created_at        |
                             +----------------------+

+----------------------+     +----------------------+
| preservation_virus_  |     |preservation_format_  |
|        scan          |     |     conversion       |
+----------------------+     +----------------------+
| PK id                |     | PK id                |
| FK digital_object_id |     | FK digital_object_id |
|    scan_engine       |     |    source_format     |
|    engine_version    |     |    source_mime_type  |
|    signature_version |     |    target_format     |
|    status            |     |    target_mime_type  |
|    threat_name       |     |    conversion_tool   |
|    file_path         |     |    tool_version      |
|    file_size         |     |    status            |
|    scanned_at        |     |    source_path       |
|    scanned_by        |     |    source_size       |
|    duration_ms       |     |    source_checksum   |
|    error_message     |     |    output_path       |
|    quarantined       |     |    output_size       |
|    quarantine_path   |     |    output_checksum   |
|    created_at        |     |    conversion_options|
+----------------------+     |    started_at        |
                             |    completed_at      |
                             |    duration_ms       |
                             |    error_message     |
                             |    created_by        |
                             |    created_at        |
                             +----------------------+

+----------------------+     +----------------------+
|preservation_backup_  |     |preservation_replica- |
|    verification      |     |    tion_target       |
+----------------------+     +----------------------+
| PK id                |     | PK id                |
|    backup_type       |     |    name              |
|    backup_path       |     |    target_type       |
|    backup_size       |     |    connection_config |
|    original_checksum |     |    description       |
|    verified_checksum |     |    is_active         |
|    status            |     |    last_sync_at      |
|    verification_     |     |    created_at        |
|      method          |     |    updated_at        |
|    files_checked     |     +----------------------+
|    files_valid       |              |
|    files_invalid     |              | 1:N
|    files_missing     |              v
|    verified_at       |     +----------------------+
|    verified_by       |     |preservation_replica- |
|    duration_ms       |     |      tion_log        |
|    error_message     |     +----------------------+
|    details (JSON)    |     | PK id                |
|    created_at        |     | FK target_id         |
+----------------------+     | FK digital_object_id |
                             |    operation         |
+----------------------+     |    file_path         |
|  preservation_event  |     |    file_size         |
+----------------------+     |    checksum          |
| PK id                |     |    status            |
| FK digital_object_id |     |    error_message     |
| FK information_      |     |    started_at        |
|      object_id       |     |    completed_at      |
|    event_type        |     |    duration_ms       |
|    event_datetime    |     |    created_at        |
|    event_detail      |     +----------------------+
|    event_outcome     |
|    event_outcome_    |
|      detail          |
|    linking_agent_    |
|      type            |
|    linking_agent_    |
|      value           |
|    created_at        |
+----------------------+

+----------------------+     +----------------------+
| preservation_format  |     |preservation_object_  |
+----------------------+     |      format          |
| PK id                |     +----------------------+
|    puid              |<----| PK id                |
|    mime_type         |     | FK digital_object_id |
|    format_name       |     | FK format_id         |
|    format_version    |     |    puid              |
|    extension         |     |    mime_type         |
|    risk_level        |     |    format_name       |
|    risk_notes        |     |    format_version    |
|    preservation_     |     |    identification_   |
|      action          |     |      tool            |
| FK migration_        |     |    identification_   |
|      target_id       |     |      date            |
|    is_preservation_  |     |    confidence        |
|      format          |     |    basis             |
|    created_at        |     |    warning           |
|    updated_at        |     |    created_at        |
+----------------------+     +----------------------+
```

---

## Database Tables

### preservation_virus_scan

Records virus scan results for digital objects.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT PK | Auto-increment primary key |
| `digital_object_id` | INT FK | Reference to digital_object |
| `scan_engine` | VARCHAR(50) | Scanner used (e.g., 'clamav') |
| `engine_version` | VARCHAR(50) | Scanner version |
| `signature_version` | VARCHAR(50) | Virus signature version |
| `status` | ENUM | clean, infected, error, skipped |
| `threat_name` | VARCHAR(255) | Name of detected threat |
| `file_path` | VARCHAR(1024) | Path to scanned file |
| `file_size` | BIGINT UNSIGNED | File size in bytes |
| `scanned_at` | DATETIME | When scan was performed |
| `scanned_by` | VARCHAR(100) | User or 'system'/'cron' |
| `duration_ms` | INT UNSIGNED | Scan duration in milliseconds |
| `error_message` | TEXT | Error details if failed |
| `quarantined` | TINYINT(1) | Whether file was quarantined |
| `quarantine_path` | VARCHAR(1024) | Path in quarantine |

### preservation_format_conversion

Records format conversion operations.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT PK | Auto-increment primary key |
| `digital_object_id` | INT FK | Reference to digital_object |
| `source_format` | VARCHAR(50) | Original file extension |
| `source_mime_type` | VARCHAR(100) | Original MIME type |
| `target_format` | VARCHAR(50) | Target file extension |
| `target_mime_type` | VARCHAR(100) | Target MIME type |
| `conversion_tool` | VARCHAR(100) | Tool used (imagemagick, ffmpeg, etc.) |
| `tool_version` | VARCHAR(50) | Tool version |
| `status` | ENUM | pending, processing, completed, failed |
| `source_path` | VARCHAR(1024) | Path to source file |
| `source_size` | BIGINT UNSIGNED | Source file size |
| `source_checksum` | VARCHAR(128) | Source file SHA-256 |
| `output_path` | VARCHAR(1024) | Path to converted file |
| `output_size` | BIGINT UNSIGNED | Converted file size |
| `output_checksum` | VARCHAR(128) | Converted file SHA-256 |
| `conversion_options` | JSON | Options used for conversion |
| `started_at` | DATETIME | Conversion start time |
| `completed_at` | DATETIME | Conversion completion time |
| `duration_ms` | INT UNSIGNED | Conversion duration |
| `error_message` | TEXT | Error details if failed |
| `created_by` | VARCHAR(100) | User who initiated |

### preservation_backup_verification

Records backup verification results.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT PK | Auto-increment primary key |
| `backup_type` | ENUM | database, files, full |
| `backup_path` | VARCHAR(1024) | Path to backup |
| `backup_size` | BIGINT UNSIGNED | Backup size in bytes |
| `original_checksum` | VARCHAR(128) | Expected checksum |
| `verified_checksum` | VARCHAR(128) | Calculated checksum |
| `status` | ENUM | valid, invalid, corrupted, missing, warning, error |
| `verification_method` | VARCHAR(50) | Method used (sha256, etc.) |
| `files_checked` | INT UNSIGNED | Number of files verified |
| `files_valid` | INT UNSIGNED | Files that passed |
| `files_invalid` | INT UNSIGNED | Files that failed |
| `files_missing` | INT UNSIGNED | Missing files |
| `verified_at` | DATETIME | When verification ran |
| `verified_by` | VARCHAR(100) | User or 'system' |
| `duration_ms` | INT UNSIGNED | Verification duration |
| `error_message` | TEXT | Error details |
| `details` | JSON | Additional verification details |

### preservation_replication_target

Defines backup replication destinations.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT PK | Auto-increment primary key |
| `name` | VARCHAR(255) | Target name |
| `target_type` | ENUM | local, sftp, rsync, s3 |
| `connection_config` | JSON | Connection parameters |
| `description` | TEXT | Target description |
| `is_active` | TINYINT(1) | Whether target is enabled |
| `last_sync_at` | DATETIME | Last successful sync |
| `created_at` | DATETIME | Record creation time |
| `updated_at` | DATETIME | Last update time |

**Connection Config JSON Structure:**

Local:
```json
{
    "path": "/var/backups/atom"
}
```

SFTP/Rsync:
```json
{
    "host": "backup.example.com",
    "port": 22,
    "path": "/backups/atom",
    "user": "backup"
}
```

S3:
```json
{
    "bucket": "my-bucket",
    "region": "us-east-1"
}
```

### preservation_replication_log

Records replication operations.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT PK | Auto-increment primary key |
| `target_id` | BIGINT FK | Reference to replication_target |
| `digital_object_id` | INT FK | Reference to digital_object (nullable) |
| `operation` | ENUM | upload, verify, delete |
| `file_path` | VARCHAR(1024) | Source file path |
| `file_size` | BIGINT UNSIGNED | File size |
| `checksum` | VARCHAR(128) | File checksum |
| `status` | ENUM | started, completed, failed |
| `error_message` | TEXT | Error details |
| `started_at` | DATETIME | Operation start |
| `completed_at` | DATETIME | Operation end |
| `duration_ms` | INT UNSIGNED | Operation duration |

### preservation_package

Main table for OAIS packages (SIP/AIP/DIP).

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT PK | Auto-increment primary key |
| `uuid` | CHAR(36) UK | Unique package identifier |
| `name` | VARCHAR(255) | Package name |
| `description` | TEXT | Package description |
| `package_type` | ENUM | sip, aip, dip |
| `status` | ENUM | draft, building, complete, validated, exported, error |
| `package_format` | ENUM | bagit, zip, tar, directory |
| `bagit_version` | VARCHAR(10) | BagIt version (default 1.0) |
| `object_count` | INT UNSIGNED | Number of objects in package |
| `total_size` | BIGINT UNSIGNED | Total package size in bytes |
| `manifest_algorithm` | VARCHAR(20) | Checksum algorithm (sha256) |
| `package_checksum` | VARCHAR(128) | Overall package checksum |
| `source_path` | VARCHAR(1024) | Path to built package directory |
| `export_path` | VARCHAR(1024) | Path to exported archive file |
| `originator` | VARCHAR(255) | Creating organization |
| `submission_agreement` | VARCHAR(255) | Reference to agreement |
| `retention_period` | VARCHAR(100) | Retention policy |
| `parent_package_id` | BIGINT FK | Parent package (for conversions) |
| `information_object_id` | INT FK | Linked archival description |
| `created_by` | VARCHAR(100) | Creator user |
| `created_at` | DATETIME | Creation timestamp |
| `built_at` | DATETIME | When package was built |
| `validated_at` | DATETIME | When package was validated |
| `exported_at` | DATETIME | When package was exported |
| `metadata` | JSON | Additional metadata |

### preservation_package_object

Links digital objects to packages.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT PK | Auto-increment primary key |
| `package_id` | BIGINT FK | Reference to preservation_package |
| `digital_object_id` | INT FK | Reference to digital_object |
| `relative_path` | VARCHAR(1024) | Path within package (e.g., data/file.pdf) |
| `file_name` | VARCHAR(255) | File name |
| `file_size` | BIGINT UNSIGNED | File size in bytes |
| `checksum_algorithm` | VARCHAR(20) | Checksum algorithm used |
| `checksum_value` | VARCHAR(128) | File checksum |
| `mime_type` | VARCHAR(100) | MIME type |
| `puid` | VARCHAR(50) | PRONOM identifier |
| `object_role` | ENUM | payload, metadata, manifest, tagfile |
| `sequence` | INT UNSIGNED | Order in package |
| `added_at` | DATETIME | When object was added |

### preservation_package_event

Records package lifecycle events.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT PK | Auto-increment primary key |
| `package_id` | BIGINT FK | Reference to preservation_package |
| `event_type` | ENUM | creation, modification, building, validation, export, import, transfer, deletion, error |
| `event_datetime` | DATETIME | When event occurred |
| `event_detail` | TEXT | Event description |
| `event_outcome` | ENUM | success, failure, warning |
| `event_outcome_detail` | TEXT | Additional outcome details |
| `agent_type` | VARCHAR(50) | Agent type (software, human) |
| `agent_value` | VARCHAR(255) | Agent identifier |
| `created_by` | VARCHAR(100) | User who triggered event |

---

## Service Layer API

### PreservationService

Main service class providing all preservation operations.

#### Format Identification (Siegfried/PRONOM)

```php
/**
 * Check if Siegfried is available
 *
 * @return bool
 */
public function isSiegfriedAvailable(): bool

/**
 * Get Siegfried version information
 *
 * @return array|null Version info including version and signature_date
 */
public function getSiegfriedVersion(): ?array

/**
 * Identify format using Siegfried (PRONOM)
 *
 * @param int  $digitalObjectId
 * @param bool $updateRegistry   Add format to registry if new
 * @return array Results with puid, format_name, mime_type, confidence, basis, warning
 */
public function identifyFormat(int $digitalObjectId, bool $updateRegistry = true): array

/**
 * Re-identify an already identified object
 *
 * @param int $digitalObjectId
 * @return array Results with updated identification
 */
public function reidentifyFormat(int $digitalObjectId): array

/**
 * Batch identification for multiple objects
 *
 * @param int  $limit            Maximum objects to identify
 * @param bool $unidentifiedOnly Only identify objects without existing identification
 * @param bool $updateRegistry   Add new formats to registry
 * @return array Summary with identified, failed, skipped counts
 */
public function runBatchIdentification(int $limit = 100, bool $unidentifiedOnly = true, bool $updateRegistry = true): array

/**
 * Get identification statistics
 *
 * @return array Stats including total_objects, identified, unidentified, by_confidence, top_formats
 */
public function getIdentificationStatistics(): array

/**
 * Get identification history
 *
 * @param int         $limit      Maximum records
 * @param string|null $confidence Filter by confidence level
 * @return array Identification records
 */
public function getIdentificationLog(int $limit = 100, ?string $confidence = null): array
```

#### Virus Scanning

```php
/**
 * Check if ClamAV is available
 *
 * @return bool
 */
public function isClamAvAvailable(): bool

/**
 * Get ClamAV version information
 *
 * @return array|null Version info or null if not installed
 */
public function getClamAvVersion(): ?array

/**
 * Scan a digital object for viruses
 *
 * @param int    $digitalObjectId
 * @param bool   $quarantine      Move infected files to quarantine
 * @param string $scannedBy       User or system identifier
 * @return array Scan results with status, threat_name, quarantined, etc.
 */
public function scanForVirus(int $digitalObjectId, bool $quarantine = true, string $scannedBy = 'system'): array

/**
 * Batch virus scan for multiple objects
 *
 * @param int    $limit     Maximum objects to scan
 * @param bool   $newOnly   Only scan unscanned objects
 * @param string $scannedBy Identifier
 * @return array Summary with clean, infected, errors counts
 */
public function runBatchVirusScan(int $limit = 100, bool $newOnly = true, string $scannedBy = 'cron'): array

/**
 * Get virus scan history
 *
 * @param int         $limit  Maximum records
 * @param string|null $status Filter by status
 * @return array Scan records
 */
public function getVirusScanLog(int $limit = 100, ?string $status = null): array
```

#### Format Conversion

```php
/**
 * Get available conversion tools
 *
 * @return array Tool information (imagemagick, ffmpeg, ghostscript, libreoffice)
 */
public function getConversionTools(): array

/**
 * Convert a digital object to a different format
 *
 * @param int    $digitalObjectId
 * @param string $targetFormat     Target format (tiff, pdf, mp4, etc.)
 * @param array  $options          Conversion options (quality, compress, etc.)
 * @param string $createdBy        User identifier
 * @return array Result with success, output_path, output_size, etc.
 */
public function convertFormat(int $digitalObjectId, string $targetFormat, array $options = [], string $createdBy = 'system'): array

/**
 * Get conversion history
 *
 * @param int         $limit  Maximum records
 * @param string|null $status Filter by status
 * @return array Conversion records
 */
public function getConversionLog(int $limit = 100, ?string $status = null): array
```

#### Backup Verification

```php
/**
 * Verify backup integrity
 *
 * @param string      $backupPath       Path to backup file or directory
 * @param string      $backupType       Type: database, files, full
 * @param string|null $expectedChecksum Expected checksum if known
 * @param string      $verifiedBy       User identifier
 * @return array Results with status, files_checked, files_valid, etc.
 */
public function verifyBackup(string $backupPath, string $backupType = 'full', ?string $expectedChecksum = null, string $verifiedBy = 'system'): array

/**
 * Verify all backups in a directory
 *
 * @param string|null $backupDir   Backup directory (default: uploads/backups)
 * @param string      $verifiedBy  Identifier
 * @return array Summary with total, valid, invalid counts
 */
public function verifyAllBackups(string $backupDir = null, string $verifiedBy = 'cron'): array

/**
 * Get backup verification history
 *
 * @param int         $limit  Maximum records
 * @param string|null $status Filter by status
 * @return array Verification records
 */
public function getBackupVerificationLog(int $limit = 100, ?string $status = null): array
```

#### Extended Statistics

```php
/**
 * Get extended statistics including new features
 *
 * @return array Statistics including virus_scans_30d, conversions_30d, etc.
 */
public function getExtendedStatistics(): array
```

---

## CLI Tasks

### preservationSchedulerTask

Runs scheduled preservation workflows based on their configured schedules.

**Location:** `lib/task/preservationSchedulerTask.class.php`

**Namespace:** `preservation:scheduler`

**Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--status` | - | Show scheduler status and statistics |
| `--list` | - | List all configured schedules |
| `--run-id=N` | - | Run a specific schedule by ID |
| `--dry-run` | - | Show what would be run without executing |

**Usage:**
```bash
# Run all due workflows (intended for cron)
php symfony preservation:scheduler

# Show scheduler status
php symfony preservation:scheduler --status

# List all schedules
php symfony preservation:scheduler --list

# Run specific schedule
php symfony preservation:scheduler --run-id=1

# Preview without running
php symfony preservation:scheduler --dry-run
```

**Cron Setup:**
```bash
# Run scheduler every minute
* * * * * cd /usr/share/nginx/archive && php symfony preservation:scheduler >> /var/log/atom/scheduler.log 2>&1
```

---

### preservationIdentifyTask

Identifies file formats using Siegfried (PRONOM-based identification).

**Location:** `lib/task/preservationIdentifyTask.class.php`

**Namespace:** `preservation:identify`

**Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--status` | - | Show Siegfried status and statistics |
| `--dry-run` | - | Preview without identifying |
| `--limit=N` | 100 | Maximum objects to identify |
| `--object-id=N` | - | Identify specific object |
| `--all` | false | Identify all objects (including already identified) |
| `--reidentify` | false | Force re-identification of already identified objects |

**Usage:**
```bash
php symfony preservation:identify --status
php symfony preservation:identify --dry-run
php symfony preservation:identify --limit=500
php symfony preservation:identify --object-id=123
php symfony preservation:identify --all --limit=1000
php symfony preservation:identify --object-id=123 --reidentify
```

**Output includes:**
- PRONOM Unique Identifier (PUID)
- Format name and version
- MIME type
- Confidence level (certain, high, medium, low)
- Identification basis (signature, extension, container, byte match)
- Warnings (if any)

---

### preservationVirusScanTask

Scans digital objects for viruses using ClamAV.

**Location:** `lib/task/preservationVirusScanTask.class.php`

**Namespace:** `preservation:virus-scan`

**Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--status` | - | Show ClamAV status and statistics |
| `--dry-run` | - | Preview without scanning |
| `--limit=N` | 100 | Maximum objects to scan |
| `--object-id=N` | - | Scan specific object |
| `--all` | false | Scan all objects (including previously scanned) |
| `--no-quarantine` | false | Don't quarantine infected files |

**Usage:**
```bash
php symfony preservation:virus-scan --status
php symfony preservation:virus-scan --dry-run
php symfony preservation:virus-scan --limit=200
php symfony preservation:virus-scan --object-id=123
php symfony preservation:virus-scan --all --limit=500
```

### preservationConvertTask

Converts digital objects to preservation-safe formats.

**Location:** `lib/task/preservationConvertTask.class.php`

**Namespace:** `preservation:convert`

**Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--status` | - | Show conversion tools and statistics |
| `--dry-run` | - | Preview without converting |
| `--limit=N` | 10 | Maximum objects to convert |
| `--object-id=N` | - | Convert specific object |
| `--format=X` | - | Target format (tiff, pdf, wav, etc.) |
| `--mime-type=X` | - | Filter by source MIME type |
| `--quality=N` | 95 | Conversion quality (1-100) |

**Usage:**
```bash
php symfony preservation:convert --status
php symfony preservation:convert --dry-run
php symfony preservation:convert --limit=50
php symfony preservation:convert --object-id=123 --format=tiff
php symfony preservation:convert --mime-type=image/jpeg --format=tiff --limit=100
```

**Supported Conversions:**

| Source | Target | Tool |
|--------|--------|------|
| image/jpeg, image/png, image/bmp, image/gif | TIFF | ImageMagick |
| audio/mpeg, audio/ogg | WAV | FFmpeg |
| video/* | MKV | FFmpeg |
| application/msword, application/vnd.ms-excel, application/vnd.ms-powerpoint | PDF | LibreOffice |
| application/vnd.openxmlformats-* | PDF | LibreOffice |
| application/pdf | PDF/A | Ghostscript |

### preservationVerifyBackupTask

Verifies backup file integrity.

**Location:** `lib/task/preservationVerifyBackupTask.class.php`

**Namespace:** `preservation:verify-backup`

**Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--path=X` | - | Path to specific backup file |
| `--backup-dir=X` | uploads/backups | Backup directory |
| `--checksum=X` | - | Expected checksum |
| `--all` | false | Verify all backups in directory |

**Usage:**
```bash
php symfony preservation:verify-backup --path=/backups/backup.tar.gz
php symfony preservation:verify-backup --all --backup-dir=/backups
php symfony preservation:verify-backup --path=/backups/db.sql.gz --checksum=abc123...
```

### preservationReplicateTask

Replicates files to backup targets.

**Location:** `lib/task/preservationReplicateTask.class.php`

**Namespace:** `preservation:replicate`

**Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--status` | - | Show targets and statistics |
| `--dry-run` | - | Preview without replicating |
| `--limit=N` | 100 | Maximum objects to replicate |
| `--target-id=N` | - | Replicate to specific target |
| `--force` | false | Re-sync already synced files |

**Usage:**
```bash
php symfony preservation:replicate --status
php symfony preservation:replicate --dry-run
php symfony preservation:replicate --limit=500
php symfony preservation:replicate --target-id=1 --limit=100
php symfony preservation:replicate --force --limit=50
```

### preservationFixityTask

Runs fixity verification checks with optional self-healing auto-repair.

**Location:** `lib/task/preservationFixityTask.class.php`

**Namespace:** `preservation:fixity`

**Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--status` | - | Show statistics |
| `--repair-stats` | - | Show self-healing repair statistics |
| `--dry-run` | - | Preview without verifying |
| `--limit=N` | 100 | Maximum objects to check |
| `--stale-days=N` | 30 | Check objects not verified in N days |
| `--all` | false | Check all regardless of age |
| `--object-id=N` | - | Check specific object |
| `--auto-repair` | false | Enable self-healing auto-repair from backups |
| `--failed-only` | false | Only show failed checks in status |

**Self-Healing Auto-Repair:**

When `--auto-repair` is enabled, the system will automatically:
1. Detect failed fixity checks (checksum mismatch or missing files)
2. Search configured replication targets for valid backup copies
3. Validate backup integrity against stored checksums
4. Restore corrupted files from verified backups
5. Log all repair events as PREMIS preservation events

**Supported Backup Targets:**
- Local file system paths
- Rsync targets
- SFTP servers
- Amazon S3 buckets (requires AWS SDK)
- Azure Blob Storage (requires SDK)
- Google Cloud Storage (requires SDK)

**Usage:**
```bash
php symfony preservation:fixity --status
php symfony preservation:fixity --repair-stats
php symfony preservation:fixity --limit=500
php symfony preservation:fixity --object-id=123
php symfony preservation:fixity --auto-repair
php symfony preservation:fixity --stale-days=7 --auto-repair
php symfony preservation:fixity --object-id=123 --auto-repair
```

---

### preservationPronomSyncTask

Synchronizes format registry from UK National Archives PRONOM database.

**Location:** `lib/task/preservationPronomSyncTask.class.php`

**Namespace:** `preservation:pronom-sync`

**Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--status` | - | Show PRONOM sync status |
| `--puid=X` | - | Sync specific PUID (e.g., fmt/18) |
| `--lookup=X` | - | Look up PUID without syncing |
| `--unregistered` | - | Sync only unregistered PUIDs |
| `--common` | - | Sync common archival format PUIDs |
| `--all` | - | Sync all known PUIDs |

**PRONOM Data Retrieved:**
- Official format names and versions
- MIME types and file extensions
- Binary signature availability
- Format risk information
- Preservation recommendations

**Usage:**
```bash
php symfony preservation:pronom-sync --status
php symfony preservation:pronom-sync --puid=fmt/18
php symfony preservation:pronom-sync --lookup=fmt/43
php symfony preservation:pronom-sync --unregistered
php symfony preservation:pronom-sync --common
php symfony preservation:pronom-sync --all
```

---

### preservationPackageTask

Manages OAIS packages (SIP/AIP/DIP) using BagIt format.

**Location:** `lib/task/preservationPackageTask.class.php`

**Namespace:** `preservation:package`

**Actions:**

| Action | Description |
|--------|-------------|
| `list` | List all packages |
| `create` | Create a new package |
| `show` | Show package details |
| `add-objects` | Add digital objects to package |
| `build` | Build the BagIt package |
| `validate` | Validate package checksums |
| `export` | Export to ZIP/TAR format |
| `convert` | Convert SIP to AIP or AIP to DIP |

**Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--id=N` | - | Package ID |
| `--uuid=X` | - | Package UUID |
| `--type=X` | - | Package type (sip, aip, dip) |
| `--status=X` | - | Filter by status |
| `--name=X` | - | Package name |
| `--description=X` | - | Package description |
| `--format=X` | zip | Export format (zip, tar, tar.gz) |
| `--output=X` | - | Output path |
| `--objects=X` | - | Comma-separated object IDs |
| `--query=X` | - | Object query (e.g., "mime_type:application/pdf") |
| `--originator=X` | - | Creating organization |
| `--limit=N` | 20 | Limit for list operations |

**Usage:**
```bash
# List packages
php symfony preservation:package list
php symfony preservation:package list --type=sip --status=draft

# Create package
php symfony preservation:package create --type=sip --name="My Collection SIP"

# Show package details
php symfony preservation:package show --id=1

# Add objects
php symfony preservation:package add-objects --id=1 --objects=100,101,102
php symfony preservation:package add-objects --id=1 --query="mime_type:application/pdf"

# Build package
php symfony preservation:package build --id=1

# Validate
php symfony preservation:package validate --id=1

# Export
php symfony preservation:package export --id=1 --format=zip

# Convert SIP to AIP
php symfony preservation:package convert --id=1 --type=aip
```

---

## Process Flows

### Virus Scan Flow

```
+-------------------------------------------------------------------------+
|                         VIRUS SCAN SEQUENCE                              |
+-------------------------------------------------------------------------+

    +----------+     +----------------+     +--------------+
    |  Client  |     |PreservationSvc |     |    ClamAV    |
    +----+-----+     +-------+--------+     +------+-------+
         |                   |                     |
         | scanForVirus      |                     |
         | (objectId)        |                     |
         |------------------>|                     |
         |                   |                     |
         |                   | isClamAvAvailable() |
         |                   |-------------------->|
         |                   |<--------------------|
         |                   |                     |
         |                   | Get file path       |
         |                   | Verify file exists  |
         |                   |                     |
         |                   | clamscan/clamdscan  |
         |                   |-------------------->|
         |                   |<--------------------|
         |                   |    (return code)    |
         |                   |                     |
         |                   | Parse results       |
         |                   | 0=clean, 1=infected |
         |                   |                     |
         |                   | If infected:        |
         |                   |   Quarantine file   |
         |                   |                     |
         |                   | INSERT virus_scan   |
         |                   | INSERT event        |
         |                   |                     |
         |   return results  |                     |
         |<------------------|                     |
```

### Format Conversion Flow

```
+-------------------------------------------------------------------------+
|                      FORMAT CONVERSION SEQUENCE                          |
+-------------------------------------------------------------------------+

    +----------+     +----------------+     +--------------+
    |  Client  |     |PreservationSvc |     | Tool (IM/FF) |
    +----+-----+     +-------+--------+     +------+-------+
         |                   |                     |
         | convertFormat     |                     |
         | (objectId, fmt)   |                     |
         |------------------>|                     |
         |                   |                     |
         |                   | Get digital object  |
         |                   | Get file path       |
         |                   |                     |
         |                   | selectConversionTool()
         |                   | (based on MIME type)|
         |                   |                     |
         |                   | INSERT conversion   |
         |                   | status=processing   |
         |                   |                     |
         |                   | executeConversion() |
         |                   |-------------------->|
         |                   |<--------------------|
         |                   |    (output file)    |
         |                   |                     |
         |                   | Verify output exists|
         |                   | Generate checksum   |
         |                   |                     |
         |                   | UPDATE conversion   |
         |                   | status=completed    |
         |                   |                     |
         |                   | INSERT event        |
         |                   |                     |
         |   return results  |                     |
         |<------------------|                     |
```

### Replication Flow

```
+-------------------------------------------------------------------------+
|                       REPLICATION SEQUENCE                               |
+-------------------------------------------------------------------------+

    +----------+     +----------------+     +--------------+
    |   CLI    |     |ReplicateTask   |     |   Target     |
    +----+-----+     +-------+--------+     +------+-------+
         |                   |                     |
         | Execute task      |                     |
         |------------------>|                     |
         |                   |                     |
         |                   | Get active targets  |
         |                   | Get objects to sync |
         |                   |                     |
         |                   | For each object:    |
         |                   |   Get file path     |
         |                   |   Generate checksum |
         |                   |                     |
         |                   | INSERT log          |
         |                   | status=started      |
         |                   |                     |
         |                   | Transfer file       |
         |                   | (based on type)     |
         |                   |-------------------->|
         |                   |<--------------------|
         |                   |                     |
         |                   | UPDATE log          |
         |                   | status=completed    |
         |                   |                     |
         |                   | UPDATE target       |
         |                   | last_sync_at        |
         |                   |                     |
         |   print summary   |                     |
         |<------------------|                     |
```

---

## Routes

Defined in `modules/preservation/config/module.yml`:

| Route | URL | Action |
|-------|-----|--------|
| `preservation_index` | `/preservation` | Dashboard |
| `preservation_identification` | `/preservation/identification` | Format identification UI |
| `preservation_scheduler` | `/preservation/scheduler` | Workflow scheduler UI |
| `preservation_schedule_edit` | `/preservation/scheduleEdit` | Create/edit schedule |
| `preservation_virus_scan` | `/preservation/virus-scan` | Virus scan UI |
| `preservation_conversion` | `/preservation/conversion` | Conversion UI |
| `preservation_backup` | `/preservation/backup` | Backup verification UI |
| `preservation_extended` | `/preservation/extended` | Extended stats |

Settings routes in ahgThemeB5Plugin:

| Route | URL | Action |
|-------|-----|--------|
| `ahgSettings_preservation` | `/ahgSettings/preservation` | Replication target management |

---

## Configuration

### External Tool Requirements

**Siegfried (PRONOM Format Identification):**
```bash
# Install
curl -sL "https://github.com/richardlehane/siegfried/releases/download/v1.11.1/siegfried_1.11.1-1_amd64.deb" -o /tmp/sf.deb
sudo dpkg -i /tmp/sf.deb

# Verify installation
sf -version

# Update signatures (optional)
sf -update
```

**ClamAV:**
```bash
# Install
sudo apt install clamav clamav-daemon

# Update signatures
sudo freshclam

# Start daemon (optional, for faster scans)
sudo systemctl enable clamav-daemon
sudo systemctl start clamav-daemon
```

**ImageMagick:**
```bash
sudo apt install imagemagick
```

**FFmpeg:**
```bash
sudo apt install ffmpeg
```

**LibreOffice:**
```bash
sudo apt install libreoffice
```

**Ghostscript:**
```bash
sudo apt install ghostscript
```

### Quarantine Directory

Infected files are moved to: `{sf_upload_dir}/quarantine/`

Ensure this directory exists and has appropriate permissions:
```bash
mkdir -p /usr/share/nginx/archive/uploads/quarantine
chmod 750 /usr/share/nginx/archive/uploads/quarantine
chown www-data:www-data /usr/share/nginx/archive/uploads/quarantine
```

### Conversion Output Directory

Converted files are stored in: `{sf_upload_dir}/conversions/`

```bash
mkdir -p /usr/share/nginx/archive/uploads/conversions
chmod 755 /usr/share/nginx/archive/uploads/conversions
chown www-data:www-data /usr/share/nginx/archive/uploads/conversions
```

---

## Security Considerations

1. **Access Control:** All preservation actions require administrator role
2. **File Access:** Service only reads files within configured upload directory
3. **Audit Trail:** All operations logged as PREMIS events
4. **Quarantine:** Infected files isolated with restricted permissions
5. **Input Sanitization:** All file paths escaped for shell commands
6. **Error Messages:** Binary data sanitized before database storage

---

## Performance Considerations

1. **Batch Processing:** Use batch operations for large collections
2. **Scheduling:** Run intensive operations during off-peak hours
3. **ClamAV Daemon:** Use clamdscan (daemon) instead of clamscan for faster scans
4. **Conversion Queue:** Process conversions in batches with limits
5. **Indexing:** All foreign keys and frequent query columns indexed

---

## Cron Configuration

Recommended cron schedule:

```bash
# Daily format identification at 1am
0 1 * * * cd /usr/share/nginx/archive && php symfony preservation:identify --limit=500 >> /var/log/atom/identify.log 2>&1

# Daily fixity check at 2am
0 2 * * * cd /usr/share/nginx/archive && php symfony preservation:fixity --limit=500 >> /var/log/atom/fixity.log 2>&1

# Daily virus scan at 3am (new files only)
0 3 * * * cd /usr/share/nginx/archive && php symfony preservation:virus-scan --limit=200 >> /var/log/atom/virus-scan.log 2>&1

# Weekly format conversion on Sunday at 4am
0 4 * * 0 cd /usr/share/nginx/archive && php symfony preservation:convert --limit=100 >> /var/log/atom/conversion.log 2>&1

# Daily replication at 5am
0 5 * * * cd /usr/share/nginx/archive && php symfony preservation:replicate --limit=500 >> /var/log/atom/replication.log 2>&1

# Weekly backup verification on Saturday at 6am
0 6 * * 6 cd /usr/share/nginx/archive && php symfony preservation:verify-backup --all >> /var/log/atom/backup-verify.log 2>&1
```

---

## Error Handling

| Error | Cause | Resolution |
|-------|-------|------------|
| `Siegfried not installed` | sf command not in PATH | Install Siegfried (see Configuration) |
| `ClamAV not installed` | clamscan not in PATH | Install: `apt install clamav` |
| `No conversion tool available` | Missing tool for format | Install required tool |
| `File not found` | Missing physical file | Check storage, restore backup |
| `Format identification failed` | Siegfried error | Check Siegfried installation, update signatures |
| `PUID showing as UNKNOWN` | Format not in PRONOM | File may have non-standard format |
| `Conversion failed` | Tool error | Check tool logs, verify format |
| `Replication failed` | Network/permission error | Check target connectivity |
| `Incorrect string value` | Binary in error message | Handled by sanitization |

---

## Monitoring

### Key Metrics

- Virus scans per day (clean vs infected)
- Conversions per day (completed vs failed)
- Replication success rate
- Backup verification status
- Fixity check pass rate

### Alerting Thresholds

| Metric | Warning | Critical |
|--------|---------|----------|
| Infected Files (30d) | > 0 | > 5 |
| Conversion Failures (30d) | > 10 | > 50 |
| Replication Failures (30d) | > 5 | > 20 |
| Backup Verification Failures | > 0 | > 0 |
| Fixity Failures (30d) | > 0 | > 10 |

---

## Format Migration

### Overview

The format migration subsystem enables proactive digital preservation through:
- **Migration Pathways**: Define recommended conversion routes between formats
- **Migration Plans**: Batch migration planning with tracking
- **Obsolescence Analysis**: Identify at-risk formats in the repository
- **Automated Recommendations**: Suggest target formats based on risk levels

### Migration Pathway Tables

```
+-------------------------------------------------------------------------+
|                    FORMAT MIGRATION DATABASE SCHEMA                      |
+-------------------------------------------------------------------------+

+---------------------------+           +---------------------------+
|   preservation_format     |           | preservation_migration_   |
|    (existing table)       |           |        pathway            |
+---------------------------+           +---------------------------+
| PK id                     |<----------| PK id                     |
|    puid                   |    FK     | FK source_format_id       |
|    format_name            |           | FK target_format_id       |--------+
|    risk_level             |    +------| FK preferred_tool_id      |        |
|    ...                    |    |      |    pathway_type           |        |
+---------------------------+    |      |    confidence_level       |        |
                                 |      |    complexity             |        |
+---------------------------+    |      |    data_loss_risk         |        |
|  preservation_conversion_ |    |      |    quality_impact         |        |
|         tool              |<---+      |    is_recommended         |        |
+---------------------------+           |    is_automated           |        |
| PK id                     |           |    notes                  |        |
|    tool_name              |           |    created_at             |        |
|    tool_version           |           |    updated_at             |        |
|    ...                    |           +---------------------------+        |
+---------------------------+                      |                         |
                                                   | 1:N                     |
                                                   v                         |
                            +---------------------------+                    |
                            | preservation_migration_   |                    |
                            |      pathway_tool         |                    |
                            +---------------------------+                    |
                            | PK id                     |                    |
                            | FK pathway_id             |                    |
                            | FK tool_id                |                    |
                            |    priority               |                    |
                            |    conversion_options     |                    |
                            +---------------------------+                    |
                                                                             |
+---------------------------+           +---------------------------+        |
| preservation_migration_   |           | preservation_migration_   |        |
|         plan              |           |      plan_item            |        |
+---------------------------+           +---------------------------+        |
| PK id                     |<----------| PK id                     |        |
|    name                   |     FK    | FK plan_id                |        |
|    description            |           | FK digital_object_id      |        |
|    status                 |           | FK pathway_id             |--------+
|    created_by             |           |    source_format          |
|    approved_by            |           |    target_format          |
|    approved_at            |           |    priority               |
|    started_at             |           |    status                 |
|    completed_at           |           |    started_at             |
|    total_items            |           |    completed_at           |
|    items_completed        |           |    conversion_log         |
|    items_failed           |           |    error_message          |
|    notes                  |           |    created_at             |
|    created_at             |           +---------------------------+
|    updated_at             |
+---------------------------+
```

### Migration Database Tables

#### preservation_migration_pathway

Defines conversion routes between format types.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT PK | Auto-increment primary key |
| `source_format_id` | BIGINT FK | Reference to preservation_format |
| `target_format_id` | BIGINT FK | Target format reference |
| `preferred_tool_id` | BIGINT FK | Preferred conversion tool |
| `pathway_type` | ENUM | normalization, migration, emulation |
| `confidence_level` | ENUM | high, medium, low |
| `complexity` | ENUM | simple, moderate, complex |
| `data_loss_risk` | ENUM | none, minimal, moderate, significant |
| `quality_impact` | ENUM | lossless, minimal_loss, noticeable_loss |
| `is_recommended` | TINYINT(1) | Official recommendation |
| `is_automated` | TINYINT(1) | Can be automated |
| `notes` | TEXT | Additional guidance |

#### preservation_migration_plan

Batch migration execution plans.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT PK | Auto-increment primary key |
| `name` | VARCHAR(255) | Plan name |
| `description` | TEXT | Plan description |
| `status` | ENUM | draft, approved, in_progress, completed, cancelled |
| `created_by` | VARCHAR(100) | Creator |
| `approved_by` | VARCHAR(100) | Approver |
| `approved_at` | DATETIME | Approval timestamp |
| `started_at` | DATETIME | Execution start |
| `completed_at` | DATETIME | Execution completion |
| `total_items` | INT UNSIGNED | Total items in plan |
| `items_completed` | INT UNSIGNED | Successfully migrated |
| `items_failed` | INT UNSIGNED | Failed migrations |

#### preservation_migration_plan_item

Individual items within a migration plan.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT PK | Auto-increment primary key |
| `plan_id` | BIGINT FK | Reference to migration_plan |
| `digital_object_id` | INT FK | Reference to digital_object |
| `pathway_id` | BIGINT FK | Reference to migration_pathway |
| `source_format` | VARCHAR(100) | Source PUID or MIME type |
| `target_format` | VARCHAR(100) | Target format |
| `priority` | INT | Processing priority |
| `status` | ENUM | pending, processing, completed, failed, skipped |
| `started_at` | DATETIME | Item processing start |
| `completed_at` | DATETIME | Item processing end |
| `conversion_log` | TEXT | Conversion output log |
| `error_message` | TEXT | Error details if failed |

### Migration Service API

#### MigrationPathwayService

```php
/**
 * Get available migration pathways for a source format.
 *
 * @param string $sourceFormat Source PUID or MIME type
 * @return array Available pathways with recommendations
 */
public function getPathwaysForFormat(string $sourceFormat): array

/**
 * Get recommended target format for a source.
 *
 * @param string $sourceFormat Source PUID or MIME type
 * @return array|null Best pathway with target format
 */
public function getRecommendedTarget(string $sourceFormat): ?array

/**
 * Get obsolescence report for repository.
 *
 * @param string $riskLevel Filter by risk (critical, high, medium, low)
 * @return array Formats at risk with object counts
 */
public function getObsolescenceReport(?string $riskLevel = null): array

/**
 * Create a new migration pathway.
 *
 * @param array $data Pathway configuration
 * @return int New pathway ID
 */
public function createPathway(array $data): int
```

#### MigrationPlanService

```php
/**
 * Create a migration plan.
 *
 * @param string $name Plan name
 * @param string $description Plan description
 * @param string $createdBy Creator user
 * @return int New plan ID
 */
public function createPlan(string $name, string $description, string $createdBy): int

/**
 * Add items to a migration plan.
 *
 * @param int $planId Plan ID
 * @param array $items Array of [digital_object_id, pathway_id, priority]
 * @return int Number of items added
 */
public function addPlanItems(int $planId, array $items): int

/**
 * Add items by format criteria.
 *
 * @param int $planId Plan ID
 * @param string $sourceFormat Source format to match
 * @param int $pathwayId Pathway to use
 * @param int $limit Maximum items to add
 * @return int Number of items added
 */
public function addItemsByFormat(int $planId, string $sourceFormat, int $pathwayId, int $limit = 1000): int

/**
 * Execute a migration plan.
 *
 * @param int $planId Plan ID
 * @param int $limit Items per batch
 * @return array Execution results
 */
public function executePlan(int $planId, int $limit = 100): array

/**
 * Get plan execution status.
 *
 * @param int $planId Plan ID
 * @return array Status with progress metrics
 */
public function getPlanStatus(int $planId): array
```

### preservationMigrationTask

CLI task for format migration operations.

**Location:** `lib/task/preservationMigrationTask.class.php`

**Namespace:** `preservation:migration`

**Actions:**

| Action | Description |
|--------|-------------|
| `pathways` | List available migration pathways |
| `obsolescence` | Generate obsolescence report |
| `recommend` | Get recommendations for a format |
| `plan-list` | List migration plans |
| `plan-create` | Create a new plan |
| `plan-add` | Add items to a plan |
| `plan-execute` | Execute a plan |
| `plan-status` | Show plan status |

**Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--format=X` | - | Source format (PUID or MIME) |
| `--risk=X` | - | Risk level filter |
| `--plan-id=N` | - | Plan ID |
| `--name=X` | - | Plan name |
| `--pathway-id=N` | - | Pathway ID |
| `--limit=N` | 100 | Items limit |

**Usage:**

```bash
# List pathways for a format
php symfony preservation:migration pathways --format=fmt/18

# Generate obsolescence report
php symfony preservation:migration obsolescence
php symfony preservation:migration obsolescence --risk=critical

# Get recommendations
php symfony preservation:migration recommend --format=fmt/18

# Create migration plan
php symfony preservation:migration plan-create --name="TIFF Migration 2026"

# Add items to plan
php symfony preservation:migration plan-add --plan-id=1 --format=fmt/353 --pathway-id=5

# Execute plan
php symfony preservation:migration plan-execute --plan-id=1 --limit=50

# Check status
php symfony preservation:migration plan-status --plan-id=1
```

### Migration Process Flow

```
+-------------------------------------------------------------------------+
|                      FORMAT MIGRATION SEQUENCE                           |
+-------------------------------------------------------------------------+

    +----------+     +----------------+     +----------------+
    |  Admin   |     |MigrationPlanSvc|     |PathwaySvc      |
    +----+-----+     +-------+--------+     +-------+--------+
         |                   |                      |
         | obsolescence      |                      |
         | report            |                      |
         |------------------>|                      |
         |                   | getObsolescence      |
         |                   | Report()             |
         |                   |--------------------->|
         |                   |<---------------------|
         |   at-risk formats |                      |
         |<------------------|                      |
         |                   |                      |
         | create plan       |                      |
         |------------------>|                      |
         |                   | createPlan()         |
         |                   |                      |
         |   plan_id         |                      |
         |<------------------|                      |
         |                   |                      |
         | add items by      |                      |
         | format            |                      |
         |------------------>|                      |
         |                   | addItemsByFormat()   |
         |                   | getRecommendedTarget |
         |                   |--------------------->|
         |                   |<---------------------|
         |                   |                      |
         |                   | INSERT plan_items    |
         |                   |                      |
         |   items added     |                      |
         |<------------------|                      |
         |                   |                      |
         | approve & execute |                      |
         |------------------>|                      |
         |                   | executePlan()        |
         |                   |                      |
         |                   | For each item:       |
         |                   |   convertFormat()    |
         |                   |   UPDATE status      |
         |                   |                      |
         |   results         |                      |
         |<------------------|                      |
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-01 | Initial release with checksums, fixity, events |
| 1.1.0 | 2026-01 | Added virus scanning (ClamAV), format conversion (ImageMagick/FFmpeg/LibreOffice/Ghostscript), backup verification, replication targets, CLI tasks, settings UI |
| 1.2.0 | 2026-01 | Added Siegfried/PRONOM format identification with PUID tracking, confidence levels, batch identification CLI task, identification UI dashboard, auto-population of format registry |
| 1.3.0 | 2026-01 | Added Workflow Scheduler UI for configuring and monitoring automated preservation tasks, preservationSchedulerTask CLI task, schedule management service methods, run history tracking |
| 1.4.0 | 2026-01 | Added Format Migration subsystem: migration pathways, migration plans, obsolescence reporting, MigrationPathwayService, MigrationPlanService, preservationMigrationTask CLI |

---

*Technical Documentation - Last Updated: January 2026*
*Plugin Version: 1.4.0*
