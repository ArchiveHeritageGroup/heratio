# ahgDedupePlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Data Quality
**Dependencies:** atom-framework

---

## Overview

Comprehensive duplicate detection and management system for archival records. Provides multiple detection algorithms, configurable rules, batch scanning, real-time checking, and merge workflow with full audit trail.

---

## Architecture

```
+---------------------------------------------------------------------+
|                        ahgDedupePlugin                               |
+---------------------------------------------------------------------+
|                                                                      |
|  +---------------------------------------------------------------+  |
|  |                     Detection Engine                          |  |
|  |  - Title Similarity (Levenshtein, Jaro-Winkler, Soundex)     |  |
|  |  - Identifier Matching (exact, fuzzy)                         |  |
|  |  - Date + Creator Combination                                 |  |
|  |  - File Checksum (MD5, SHA-256)                              |  |
|  |  - Combined Multi-factor Analysis                             |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                     DedupeService                             |  |
|  |  - checkForDuplicates()    - realtimeCheck()                 |  |
|  |  - runRule()               - mergeRecords()                  |  |
|  |  - startScan()             - runScan()                       |  |
|  |  - dismissDuplicate()      - getStatistics()                 |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                    Database Tables                            |  |
|  |  ahg_duplicate_detection | ahg_duplicate_rule                |  |
|  |  ahg_merge_log           | ahg_file_checksum                 |  |
|  |  ahg_dedupe_scan                                              |  |
|  +---------------------------------------------------------------+  |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+-------------------------------------+
|       ahg_duplicate_detection       |
+-------------------------------------+
| PK id BIGINT UNSIGNED              |
|    record_a_id INT                  |
|    record_b_id INT                  |
|    similarity_score DECIMAL(5,4)    |
|    detection_method VARCHAR(50)     |
|    detection_details JSON           |
|    status ENUM                      |
|    reviewed_by INT                  |
|    reviewed_at DATETIME             |
|    review_notes TEXT                |
|    auto_detected TINYINT            |
|    detected_at DATETIME             |
|    created_at DATETIME              |
|    updated_at DATETIME              |
+-------------------------------------+
         |
         | detection_id
         v
+-------------------------------------+
|          ahg_merge_log              |
+-------------------------------------+
| PK id BIGINT UNSIGNED              |
|    primary_id INT                   |
|    merged_id INT                    |
| FK detection_id BIGINT             |
|    field_choices_json JSON          |
|    slugs_redirected JSON            |
|    digital_objects_moved JSON       |
|    merged_by INT                    |
|    merged_at DATETIME               |
|    notes TEXT                       |
+-------------------------------------+

+-------------------------------------+
|        ahg_duplicate_rule           |
+-------------------------------------+
| PK id BIGINT UNSIGNED              |
|    repository_id INT (nullable)     |
|    name VARCHAR(255)                |
|    rule_type ENUM                   |
|    threshold DECIMAL(5,4)           |
|    config_json JSON                 |
|    is_enabled TINYINT               |
|    is_blocking TINYINT              |
|    priority INT                     |
|    created_at DATETIME              |
|    updated_at DATETIME              |
+-------------------------------------+

+-------------------------------------+
|        ahg_file_checksum            |
+-------------------------------------+
| PK id BIGINT UNSIGNED              |
|    digital_object_id INT            |
|    information_object_id INT        |
|    checksum_md5 CHAR(32)            |
|    checksum_sha256 CHAR(64)         |
|    file_size BIGINT UNSIGNED        |
|    file_name VARCHAR(500)           |
|    mime_type VARCHAR(100)           |
|    created_at DATETIME              |
+-------------------------------------+

+-------------------------------------+
|          ahg_dedupe_scan            |
+-------------------------------------+
| PK id BIGINT UNSIGNED              |
|    repository_id INT (nullable)     |
|    status ENUM                      |
|    total_records INT                |
|    processed_records INT            |
|    duplicates_found INT             |
|    started_at DATETIME              |
|    completed_at DATETIME            |
|    error_message TEXT               |
|    started_by INT                   |
|    created_at DATETIME              |
+-------------------------------------+
```

### SQL Schema

```sql
-- Detected Duplicates
CREATE TABLE IF NOT EXISTS ahg_duplicate_detection (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    record_a_id INT NOT NULL COMMENT 'First record ID',
    record_b_id INT NOT NULL COMMENT 'Second record ID',
    similarity_score DECIMAL(5,4) NOT NULL COMMENT 'Score 0.0000 to 1.0000',
    detection_method VARCHAR(50) NOT NULL,
    detection_details JSON COMMENT 'Detailed matching information',
    status ENUM('pending', 'confirmed', 'dismissed', 'merged') NOT NULL DEFAULT 'pending',
    reviewed_by INT,
    reviewed_at DATETIME,
    review_notes TEXT,
    auto_detected TINYINT(1) NOT NULL DEFAULT 1,
    detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pair (record_a_id, record_b_id),
    INDEX idx_record_a (record_a_id),
    INDEX idx_record_b (record_b_id),
    INDEX idx_status (status),
    INDEX idx_score (similarity_score DESC),
    INDEX idx_method (detection_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detection Rules
CREATE TABLE IF NOT EXISTS ahg_duplicate_rule (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    repository_id INT COMMENT 'NULL = global rule',
    name VARCHAR(255) NOT NULL,
    rule_type ENUM('title_similarity', 'identifier_exact', 'identifier_fuzzy',
                   'date_creator', 'checksum', 'combined', 'custom') NOT NULL,
    threshold DECIMAL(5,4) NOT NULL DEFAULT 0.8000,
    config_json JSON COMMENT 'Rule-specific configuration',
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    is_blocking TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Block save if duplicate found',
    priority INT NOT NULL DEFAULT 100 COMMENT 'Higher = runs first',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_repository (repository_id),
    INDEX idx_is_enabled (is_enabled),
    INDEX idx_priority (priority DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Merge Log (audit trail)
CREATE TABLE IF NOT EXISTS ahg_merge_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    primary_id INT NOT NULL COMMENT 'Record kept as primary',
    merged_id INT NOT NULL COMMENT 'Record merged into primary',
    detection_id BIGINT UNSIGNED COMMENT 'Original detection record',
    field_choices_json JSON COMMENT 'Which fields were taken from which record',
    slugs_redirected JSON COMMENT 'Old slugs now redirecting',
    digital_objects_moved JSON COMMENT 'Digital objects transferred',
    merged_by INT NOT NULL,
    merged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    INDEX idx_primary (primary_id),
    INDEX idx_merged (merged_id),
    INDEX idx_merged_at (merged_at),
    FOREIGN KEY (detection_id) REFERENCES ahg_duplicate_detection(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- File Checksums
CREATE TABLE IF NOT EXISTS ahg_file_checksum (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT NOT NULL,
    information_object_id INT NOT NULL,
    checksum_md5 CHAR(32),
    checksum_sha256 CHAR(64),
    file_size BIGINT UNSIGNED,
    file_name VARCHAR(500),
    mime_type VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_digital_object (digital_object_id),
    INDEX idx_information_object (information_object_id),
    INDEX idx_md5 (checksum_md5),
    INDEX idx_sha256 (checksum_sha256),
    INDEX idx_file_size (file_size)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scan Jobs
CREATE TABLE IF NOT EXISTS ahg_dedupe_scan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    repository_id INT COMMENT 'NULL = all repositories',
    status ENUM('pending', 'running', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    total_records INT DEFAULT 0,
    processed_records INT DEFAULT 0,
    duplicates_found INT DEFAULT 0,
    started_at DATETIME,
    completed_at DATETIME,
    error_message TEXT,
    started_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Detection Algorithms

### Levenshtein Similarity

Calculates edit distance between strings and converts to 0-1 similarity score.

```php
protected function calculateLevenshteinSimilarity(string $str1, string $str2): float
{
    if ($str1 === $str2) {
        return 1.0;
    }

    $distance = levenshtein($str1, $str2);
    $maxLen = max(strlen($str1), strlen($str2));

    return 1 - ($distance / $maxLen);
}
```

**Use Case:** General title comparison
**Threshold:** 0.80-0.90 recommended

### Jaro-Winkler Similarity

Better for short strings and typos, gives bonus for matching prefix.

```php
protected function calculateJaroWinkler(string $str1, string $str2): float
{
    // Jaro similarity calculation
    $jaro = (($matches / $len1) + ($matches / $len2) +
             (($matches - $transpositions / 2) / $matches)) / 3;

    // Winkler modification - prefix bonus
    $prefix = 0;
    for ($i = 0; $i < min(4, min($len1, $len2)); $i++) {
        if ($str1[$i] === $str2[$i]) {
            $prefix++;
        } else {
            break;
        }
    }

    return $jaro + ($prefix * 0.1 * (1 - $jaro));
}
```

**Use Case:** Identifier matching, names
**Threshold:** 0.85-0.95 recommended

### Soundex Matching

Phonetic algorithm for names that sound alike.

```php
$score = soundex($str1) === soundex($str2) ? 1.0 : 0.0;
```

**Use Case:** Personal names, place names
**Threshold:** 1.0 (binary match)

---

## Rule Types

### title_similarity

Compares record titles using specified algorithm.

**Config:**
```json
{
    "algorithm": "levenshtein",  // or "jaro_winkler", "soundex"
    "normalize": true,
    "ignore_case": true,
    "min_length": 10
}
```

### identifier_exact

Exact match on identifier field.

**Config:**
```json
{
    "fields": ["identifier", "alternate_identifiers"]
}
```

### identifier_fuzzy

Fuzzy identifier matching using Jaro-Winkler.

**Config:**
```json
{
    "fields": ["identifier"],
    "algorithm": "jaro_winkler"
}
```

### date_creator

Matches records with overlapping date ranges and similar creators.

**Config:**
```json
{
    "date_overlap_required": true,
    "creator_similarity": 0.8
}
```

### checksum

Exact file duplicate detection using cryptographic hashes.

**Config:**
```json
{
    "algorithm": "sha256",
    "same_filename_bonus": 0.1
}
```

### combined

Multi-factor weighted analysis.

**Config:**
```json
{
    "weights": {
        "title": 0.4,
        "identifier": 0.3,
        "date": 0.15,
        "creator": 0.15
    }
}
```

---

## Service Methods

### DedupeService

```php
namespace ahgDedupePlugin\Services;

class DedupeService
{
    // Detection
    public function checkForDuplicates(array $recordData, ?int $excludeId = null, ?int $repositoryId = null): array
    public function realtimeCheck(string $title, ?int $repositoryId = null, ?int $excludeId = null): array

    // Batch Scanning
    public function startScan(?int $repositoryId = null): int
    public function runScan(int $scanId, ?callable $progress = null): array

    // Record Management
    public function recordDuplicate(int $recordA, int $recordB, float $score, string $method, array $details = []): ?int
    public function dismissDuplicate(int $detectionId, ?string $notes = null): bool
    public function mergeRecords(int $primaryId, int $mergedId, array $fieldChoices = [], ?string $notes = null): bool

    // Rules
    public function getActiveRules(?int $repositoryId = null): Collection
    public function getRules(): Collection

    // Statistics
    public function getStatistics(): array

    // Protected: Algorithms
    protected function calculateLevenshteinSimilarity(string $str1, string $str2): float
    protected function calculateJaroWinkler(string $str1, string $str2): float
    protected function normalizeText(string $text, array $config = []): string
    protected function datesOverlap(?string $start1, ?string $end1, ?string $start2, ?string $end2): bool
}
```

---

## CLI Commands

### dedupe:scan

Batch scan for duplicates.

```bash
php symfony dedupe:scan --repository=1    # Specific repository
php symfony dedupe:scan --all             # Entire system
php symfony dedupe:scan --all --limit=1000
```

**Options:**
| Option | Description |
|--------|-------------|
| --repository | Repository ID to scan |
| --all | Scan entire system |
| --limit | Maximum records to scan |

### dedupe:merge

Merge duplicate records.

```bash
php symfony dedupe:merge 123               # Merge detection #123, keep record A
php symfony dedupe:merge 123 --primary=b   # Keep record B
php symfony dedupe:merge 123 --dry-run     # Preview without changes
php symfony dedupe:merge 123 --force       # Skip confirmation
```

**Options:**
| Option | Description |
|--------|-------------|
| --primary | Primary record (a or b), default: a |
| --dry-run | Preview merge without changes |
| --force | Skip confirmation prompts |

### dedupe:report

Generate duplicate reports.

```bash
php symfony dedupe:report                           # Show pending
php symfony dedupe:report --status=pending          # Filter by status
php symfony dedupe:report --min-score=0.9           # High confidence
php symfony dedupe:report --format=csv --output=dupes.csv
php symfony dedupe:report --format=json
```

**Options:**
| Option | Description |
|--------|-------------|
| --status | Filter: pending, confirmed, dismissed, merged |
| --method | Filter by detection method |
| --min-score | Minimum similarity score (0.0-1.0) |
| --repository | Filter by repository ID |
| --format | Output: table, csv, json |
| --output | Output file path |
| --limit | Max results (default: 100) |

---

## Web Routes

| Route | Path | Description |
|-------|------|-------------|
| ahg_dedupe_index | /admin/dedupe | Dashboard |
| ahg_dedupe_browse | /admin/dedupe/browse | Browse duplicates |
| ahg_dedupe_compare | /admin/dedupe/compare/:id | Side-by-side comparison |
| ahg_dedupe_merge | /admin/dedupe/merge/:id | Merge workflow |
| ahg_dedupe_dismiss | /admin/dedupe/dismiss/:id | Dismiss false positive |
| ahg_dedupe_scan | /admin/dedupe/scan | Start new scan |
| ahg_dedupe_rules | /admin/dedupe/rules | Rule management |
| ahg_dedupe_rule_create | /admin/dedupe/rule/create | Create rule |
| ahg_dedupe_rule_edit | /admin/dedupe/rule/:id/edit | Edit rule |
| ahg_dedupe_rule_delete | /admin/dedupe/rule/:id/delete | Delete rule |
| ahg_dedupe_report | /admin/dedupe/report | Reports & analytics |
| ahg_dedupe_api_check | /api/dedupe/check | API: Full check |
| ahg_dedupe_api_realtime | /api/dedupe/realtime | API: Real-time check |

---

## API Endpoints

### POST /api/dedupe/check

Full duplicate check for a record.

**Request:**
```json
{
    "title": "Meeting Minutes 1985",
    "identifier": "MIN-1985-001",
    "repository_id": 1
}
```

**Response:**
```json
{
    "duplicates": [
        {
            "record_id": 456,
            "title": "Meeting Minutes 1985-1990",
            "identifier": "MIN-85",
            "slug": "meeting-minutes-1985",
            "scores": [0.92, 0.88],
            "methods": ["title_similarity", "identifier_fuzzy"],
            "combined_score": 0.90,
            "max_score": 0.92,
            "is_blocking": false
        }
    ],
    "count": 1
}
```

### GET /api/dedupe/realtime

Real-time title check during data entry.

**Request:**
```
GET /api/dedupe/realtime?title=Meeting+Minutes+1985
```

**Response:**
```json
{
    "matches": [
        {
            "record_id": 456,
            "title": "Meeting Minutes 1985-1990",
            "slug": "meeting-minutes-1985",
            "score": 0.9200
        }
    ]
}
```

---

## Merge Workflow

### Process Flow

```
+-------------------+
|  Select Detection |
+-------------------+
         |
         v
+-------------------+
| Load Both Records |
+-------------------+
         |
         v
+-------------------+
| Choose Primary    |
| (Record A or B)   |
+-------------------+
         |
         v
+-------------------+
| Transfer Assets:  |
| - Digital Objects |
| - Child Records   |
| - Slugs           |
+-------------------+
         |
         v
+-------------------+
| Update Detection  |
| Status = 'merged' |
+-------------------+
         |
         v
+-------------------+
| Create Merge Log  |
| (Audit Trail)     |
+-------------------+
```

### Merge Operations

1. **Digital Object Transfer**
   - All digital objects from secondary moved to primary
   - Maintains file relationships

2. **Child Record Transfer**
   - All child records re-parented to primary
   - Hierarchy preserved

3. **Slug Redirect**
   - Secondary record's slug recorded for redirect
   - Old URLs continue to work

4. **Audit Trail**
   - Merge log entry created
   - Records what was transferred
   - Links back to original detection

---

## Event Hooks

### QubitInformationObject.preSave

Optional hook for real-time duplicate checking during record save.

```php
public function onRecordPreSave(sfEvent $event)
{
    // Can be implemented to warn or block saves
    // based on blocking rules
}
```

---

## Configuration

### Plugin Configuration

```php
// ahgDedupePluginConfiguration.class.php
public static $summary = 'Duplicate Detection: Identify and manage duplicate records';
public static $version = '1.0.0';
```

### Default Rule Configuration

| Rule | Type | Threshold | Blocking | Priority |
|------|------|-----------|----------|----------|
| Title Similarity | title_similarity | 0.85 | No | 100 |
| Identifier Exact | identifier_exact | 1.00 | Yes | 200 |
| Identifier Fuzzy | identifier_fuzzy | 0.90 | No | 150 |
| Date + Creator | date_creator | 0.90 | No | 80 |
| File Checksum | checksum | 1.00 | No | 250 |
| Combined | combined | 0.75 | No | 50 |

---

## Text Normalization

Before comparison, text is normalized:

```php
protected function normalizeText(string $text, array $config = []): string
{
    $text = mb_strtolower($text);                    // Lowercase
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);  // Remove punctuation
    $text = preg_replace('/\s+/', ' ', $text);       // Collapse whitespace
    $text = trim($text);                             // Trim
    return $text;
}
```

---

## Performance Considerations

### Indexing Strategy

- `idx_score`: For sorting by similarity
- `idx_status`: For filtering pending items
- `idx_method`: For filtering by detection method
- `uk_pair`: Prevents duplicate detection records

### Batch Scanning

- Progress updates every 100 records
- Scan status tracked in `ahg_dedupe_scan`
- Supports CLI execution for large scans

### Real-time Checking

- Minimum 5 characters before checking
- Returns top 5 matches only
- Optimized for fast response

---

## Integration Points

### With ahgAuditTrailPlugin

Merge operations are logged to audit trail for compliance.

### With Digital Object System

Transfers digital objects during merge, maintains relationships.

### With Slug System

Records redirected slugs for URL preservation.

---

## Security

### Access Control

All actions require `administrator` credential:

```php
if (!$this->context->user->hasCredential('administrator')) {
    $this->forward('admin', 'secure');
}
```

### Merge Safety

- Confirmation required via checkbox
- Dry-run option for CLI
- Force flag for automated scripts

---

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| No duplicates found | Lower threshold values or enable more rules |
| Too many false positives | Raise threshold or use combined analysis |
| Scan running slowly | Limit records or scan specific repository |
| Merge failed | Check record exists, verify permissions |

### Logging

Enable Symfony logging for debugging:

```php
sfContext::getInstance()->getLogger()->info('Dedupe: ...');
```

---

*Part of the AtoM AHG Framework*
