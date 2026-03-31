# ahgMigrationPlugin - Technical Documentation

**Version:** 1.0.3
**Category:** Migration
**Dependencies:** atom-framework, ahgCorePlugin
**Status:** Deprecated (use ahgDataMigrationPlugin instead)

---

## Overview

Universal data migration tool with sector-based field mapping for importing data from external collection management systems into AtoM. Supports Vernon CMS, ArchivesSpace, DB/TextWorks, PastPerfect, CollectiveAccess, EAD files, and custom CSV/XML formats.

---

## Architecture

```
+---------------------------------------------------------------------+
|                      ahgMigrationPlugin                              |
+---------------------------------------------------------------------+
|                                                                     |
|  +---------------------------------------------------------------+  |
|  |                      ParserFactory                             |  |
|  |  - CsvParser    (CSV, TSV, TXT files)                          |  |
|  |  - XmlParser    (Generic XML files)                            |  |
|  |  - EadParser    (EAD 2002 / EAD3 finding aids)                 |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                     FieldMapper                                |  |
|  |  - Auto-suggestion based on field names                        |  |
|  |  - Transformation engine for data conversion                   |  |
|  |  - Default value assignment                                    |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                    SectorFactory                               |  |
|  |  - ArchivesSector  (ISAD(G))                                   |  |
|  |  - MuseumSector    (SPECTRUM 5.0)                              |  |
|  |  - LibrarySector   (MARC/RDA)                                  |  |
|  |  - GallerySector   (CCO/VRA)                                   |  |
|  |  - DamSector       (Dublin Core)                               |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                  MigrationRepository                           |  |
|  |  - Job management (CRUD)                                       |  |
|  |  - Template management                                         |  |
|  |  - Staged records                                              |  |
|  |  - Import logging                                              |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                 Database Tables                                |  |
|  |  - atom_migration_job                                          |  |
|  |  - atom_migration_template                                     |  |
|  |  - atom_migration_log                                          |  |
|  |  - atom_migration_staged                                       |  |
|  +---------------------------------------------------------------+  |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+-----------------------------------+       +----------------------------------+
|       atom_migration_job          |       |     atom_migration_template      |
+-----------------------------------+       +----------------------------------+
| PK id BIGINT                      |       | PK id BIGINT                     |
|    name VARCHAR(255)              |       |    name VARCHAR(255)             |
|                                   |       |    slug VARCHAR(255) UNIQUE      |
| -- Source --                      |       |    description TEXT              |
|    source_system VARCHAR(100)     |       |                                  |
|    source_format VARCHAR(50)      |       | -- Source/Dest Pair --           |
|    source_file VARCHAR(500)       |       |    source_system VARCHAR(100)    |
|    source_file_hash VARCHAR(64)   |       |    source_format VARCHAR(50)     |
|    source_headers JSON            |       |    destination_sector VARCHAR(100)|
|                                   |       |                                  |
| -- Destination --                 |<------+    field_mappings JSON           |
|    destination_sector VARCHAR(100)|   FK  |    transformations JSON          |
|    destination_repository_id INT  |       |    hierarchy_config JSON         |
|    destination_parent_id INT      |       |    default_values JSON           |
| FK template_id BIGINT             +------>|                                  |
|                                   |       | -- Metadata --                   |
| -- Mapping --                     |       |    is_system TINYINT(1)          |
|    field_mappings JSON            |       |    is_enabled TINYINT(1)         |
|    transformations JSON           |       |    usage_count INT               |
|    default_values JSON            |       |    version VARCHAR(20)           |
|    import_options JSON            |       |    created_by INT                |
|                                   |       |    created_at TIMESTAMP          |
| -- Output --                      |       |    updated_at TIMESTAMP          |
|    output_mode ENUM               |       +----------------------------------+
|    export_file VARCHAR(500)       |
|                                   |
| -- Status --                      |
|    status ENUM                    |
|    total_records INT              |
|    processed_records INT          |
|    imported_records INT           |
|    updated_records INT            |
|    skipped_records INT            |
|    error_count INT                |
|    validation_errors JSON         |
|                                   |
| -- Timestamps --                  |
|    started_at TIMESTAMP           |
|    completed_at TIMESTAMP         |
|    created_by INT                 |
|    created_at TIMESTAMP           |
|    updated_at TIMESTAMP           |
+-----------------------------------+
          |
          | 1:N
          v
+-----------------------------------+       +----------------------------------+
|       atom_migration_log          |       |      atom_migration_staged       |
+-----------------------------------+       +----------------------------------+
| PK id BIGINT                      |       | PK id BIGINT                     |
| FK job_id BIGINT                  |       | FK job_id BIGINT                 |
|    row_number INT                 |       |    row_number INT                |
|    source_id VARCHAR(255)         |       |    source_id VARCHAR(255)        |
|                                   |       |                                  |
| -- Record Info --                 |       | -- Classification --             |
|    record_type VARCHAR(100)       |       |    record_type VARCHAR(100)      |
|    atom_object_id INT             |       |    parent_source_id VARCHAR(255) |
|    atom_slug VARCHAR(255)         |       |    hierarchy_level INT           |
|    action ENUM                    |       |    sort_order INT                |
|                                   |       |                                  |
| -- Hierarchy --                   |       | -- Data --                       |
|    parent_source_id VARCHAR(255)  |       |    source_data JSON              |
|                                   |       |    mapped_data JSON              |
| -- Data --                        |       |                                  |
|    source_data JSON               |       | -- Validation --                 |
|    mapped_data JSON               |       |    validation_status ENUM        |
|                                   |       |    validation_messages JSON      |
| -- Messages --                    |       |    import_status ENUM            |
|    error_message TEXT             |       |                                  |
|    warning_message TEXT           |       |    created_at TIMESTAMP          |
|    created_at TIMESTAMP           |       +----------------------------------+
+-----------------------------------+
```

### SQL Schema

```sql
-- Migration Jobs - Track import sessions
CREATE TABLE IF NOT EXISTS atom_migration_job (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,

    -- Source information
    source_system VARCHAR(100) NOT NULL COMMENT 'vernon, archivesspace, dbtextworks, custom',
    source_format VARCHAR(50) NOT NULL COMMENT 'csv, xml, ead',
    source_file VARCHAR(500),
    source_file_hash VARCHAR(64),
    source_headers JSON COMMENT 'Detected source field names',

    -- Destination information
    destination_sector VARCHAR(100) NOT NULL COMMENT 'archives, museum, library, gallery, dam',
    destination_repository_id INT UNSIGNED,
    destination_parent_id INT UNSIGNED,

    -- Mapping configuration
    template_id BIGINT UNSIGNED NULL,
    field_mappings JSON NOT NULL COMMENT 'Source field to destination field mappings',
    transformations JSON COMMENT 'Field transformation rules',
    default_values JSON COMMENT 'Default values for unmapped required fields',

    -- Output options
    output_mode ENUM('direct', 'export', 'both') DEFAULT 'direct',
    export_file VARCHAR(500),
    import_options JSON COMMENT 'Match existing, update mode, etc.',

    -- Status tracking
    status ENUM('pending', 'mapping', 'validating', 'validated', 'importing',
                'exporting', 'completed', 'failed', 'cancelled', 'rollback') DEFAULT 'pending',
    total_records INT UNSIGNED DEFAULT 0,
    processed_records INT UNSIGNED DEFAULT 0,
    imported_records INT UNSIGNED DEFAULT 0,
    updated_records INT UNSIGNED DEFAULT 0,
    skipped_records INT UNSIGNED DEFAULT 0,
    error_count INT UNSIGNED DEFAULT 0,
    validation_errors JSON,

    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_source_system (source_system),
    INDEX idx_destination_sector (destination_sector),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration Templates
CREATE TABLE IF NOT EXISTS atom_migration_template (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,

    source_system VARCHAR(100) NOT NULL,
    source_format VARCHAR(50) NOT NULL,
    destination_sector VARCHAR(100) NOT NULL,

    field_mappings JSON NOT NULL,
    transformations JSON,
    hierarchy_config JSON,
    default_values JSON,

    is_system TINYINT(1) DEFAULT 0,
    is_enabled TINYINT(1) DEFAULT 1,
    usage_count INT UNSIGNED DEFAULT 0,
    version VARCHAR(20) DEFAULT '1.0.0',

    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_source_dest (source_system, destination_sector),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration Log
CREATE TABLE IF NOT EXISTS atom_migration_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    row_number INT UNSIGNED,
    source_id VARCHAR(255),

    record_type VARCHAR(100) NOT NULL,
    atom_object_id INT UNSIGNED,
    atom_slug VARCHAR(255),
    action ENUM('created', 'updated', 'skipped', 'error') NOT NULL,

    parent_source_id VARCHAR(255),
    source_data JSON,
    mapped_data JSON,

    error_message TEXT,
    warning_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_job_id (job_id),
    INDEX idx_source_id (source_id),
    INDEX idx_atom_object (record_type, atom_object_id),
    INDEX idx_action (action),
    FOREIGN KEY (job_id) REFERENCES atom_migration_job(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staged Records
CREATE TABLE IF NOT EXISTS atom_migration_staged (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    row_number INT UNSIGNED NOT NULL,
    source_id VARCHAR(255),

    record_type VARCHAR(100) DEFAULT 'information_object',
    parent_source_id VARCHAR(255),
    hierarchy_level INT UNSIGNED DEFAULT 0,
    sort_order INT UNSIGNED DEFAULT 0,

    source_data JSON NOT NULL,
    mapped_data JSON,

    validation_status ENUM('pending', 'valid', 'warning', 'error') DEFAULT 'pending',
    validation_messages JSON,
    import_status ENUM('pending', 'imported', 'skipped', 'error') DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_job_id (job_id),
    INDEX idx_validation (job_id, validation_status),
    INDEX idx_hierarchy (job_id, hierarchy_level, sort_order),
    FOREIGN KEY (job_id) REFERENCES atom_migration_job(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Parser Classes

### ParserInterface

All parsers implement this interface:

```php
namespace AhgMigration\Parsers;

interface ParserInterface
{
    public function parse(string $filePath): \Generator;
    public function getHeaders(): array;
    public function getRowCount(): int;
    public function getFormat(): string;
    public function validate(string $filePath): array;
    public function getSample(string $filePath, int $count = 5): array;
}
```

### CsvParser

Handles CSV, TSV, and delimited text files:

```php
namespace AhgMigration\Parsers;

class CsvParser implements ParserInterface
{
    // Auto-detects delimiter (comma, tab, semicolon, pipe)
    // Auto-detects encoding (UTF-8, UTF-16, ISO-8859-1, Windows-1252)
    // Handles BOM markers
    // Yields associative arrays with header keys

    public function setDelimiter(string $d): self;
    public function setEnclosure(string $e): self;
    public function setEncoding(string $e): self;
}
```

### XmlParser

Handles generic XML files with XPath configuration:

```php
namespace AhgMigration\Parsers;

class XmlParser implements ParserInterface
{
    public function __construct(
        string $recordXPath = '//record',
        array $fieldXPaths = []
    );

    public function setRecordXPath(string $xpath): self;
    public function setFieldXPaths(array $xpaths): self;
    public function addNamespace(string $prefix, string $uri): self;
}
```

### EadParser

Specialized parser for EAD finding aids:

```php
namespace AhgMigration\Parsers;

class EadParser implements ParserInterface
{
    const EAD_NS = 'urn:isbn:1-931666-22-9';
    const EAD3_NS = 'http://ead3.archivists.org/schema/';

    // Auto-detects EAD version (EAD 2002 or EAD3)
    // Preserves hierarchy (archdesc -> dsc -> c/c01/c02...)
    // Maps standard EAD elements to AtoM fields
    // Handles controlaccess, did, notes
}
```

### ParserFactory

Creates appropriate parser based on format:

```php
namespace AhgMigration\Parsers;

class ParserFactory
{
    public static function create(string $format): ParserInterface;
    public static function detectFormat(string $filePath): array;
    public static function detectSourceSystem(array $headers): string;
}
```

**Detected Source Systems:**
| System | Detection Pattern |
|--------|-------------------|
| vernon | `object.?number`, `primary.?maker`, `vernonsystems` |
| archivesspace | `res_uri`, `ao_uri`, `component_id.*level` |
| dbtextworks | `scopenote`, `adminhistory`, `custodial` |
| pastperfect | `objectid`, `objectname`, `lexicon` |
| collectiveaccess | `ca_objects`, `idno`, `preferred_labels` |

---

## Field Mapper

### FieldMapper Class

```php
namespace AhgMigration\Mappers;

class FieldMapper
{
    public function setDestinationSector(string $sector): self;
    public function setMappings(array $mappings): self;
    public function setDefaults(array $defaults): self;
    public function loadFromTemplate(object $template): self;
    public function mapRecord(array $sourceData): array;
    public function suggestMappings(array $sourceFields): array;
    public function getTargetFields(): array;
    public function validateMappings(): array;
}
```

### Mapping Configuration Format

```php
$mappings = [
    // Simple direct mapping
    'Object Number' => 'identifier',

    // Mapping with transformation
    'Primary Maker' => [
        'target' => 'creators',
        'transform' => 'createActor',
        'options' => ['type' => 'person']
    ],

    // Mapping with custom transformation
    'Date Made' => [
        'target' => 'eventDates',
        'transform' => 'parseVernonDate'
    ],

    // Multi-value mapping
    'Materials' => [
        'target' => 'materials',
        'transform' => 'splitMultiValue',
        'options' => ['delimiter' => ';']
    ]
];
```

### Common Field Mappings

Pre-configured mappings for common source field names:

| Source Field Pattern | Target Field |
|---------------------|--------------|
| `id`, `objectid`, `accessionnumber` | `identifier` |
| `name`, `objectname`, `unittitle` | `title` |
| `description`, `scopecontent` | `scopeAndContent` |
| `date`, `datemade`, `unitdate` | `eventDates.description` |
| `creator`, `maker`, `author` | `creators` |
| `subject`, `subjects` | `subjectAccessPoints` |
| `location`, `physloc` | `physicalStorage` |
| `image`, `filename` | `digitalObject.path` |
| `parent`, `parentid` | `_parentIdentifier` |

---

## Transformation Engine

### TransformationEngine Class

```php
namespace AhgMigration\Mappers;

class TransformationEngine
{
    public function loadCustomTransformations(array $transformations): void;
    public function apply(string $transform, $value, array $context = [], array $options = []): mixed;
    public static function getAvailableTransformations(): array;
}
```

### Built-in Transformations

| Category | Transform | Description |
|----------|-----------|-------------|
| **Date** | `parseDate` | Auto-detect date format, extract start/end dates |
| | `parseDateRange` | Parse date ranges (1900-1950) |
| | `parseVernonDate` | Handle circa dates (c.1920), decades (1900s) |
| | `parseArchivesSpaceDate` | Parse ArchivesSpace ISO dates |
| | `formatDate` | Convert to specific format |
| **Mapping** | `mapLevel` | Map to level of description taxonomy |
| | `mapTaxonomy` | Match to existing taxonomy terms |
| | `mapBoolean` | Convert yes/no to true/false |
| | `mapEntityType` | Map to person/corporate_body/family |
| **Multi-value** | `splitMultiValue` | Split by delimiter (auto-detect) |
| | `joinValues` | Join array into string |
| | `firstValue` | Take only first value |
| **Actor** | `createActor` | Create person actor structure |
| | `createCorporateBody` | Create corporate body structure |
| | `createFamily` | Create family structure |
| **Text** | `trim` | Remove whitespace |
| | `uppercase` | Convert to UPPERCASE |
| | `lowercase` | Convert to lowercase |
| | `titlecase` | Convert to Title Case |
| | `stripHtml` | Remove HTML tags |
| | `normalizeWhitespace` | Collapse multiple spaces |
| | `truncate` | Truncate to length |

### Custom Transformation Configuration

```php
$customTransformations = [
    'myLevelMapping' => [
        'type' => 'replace',
        'mappings' => [
            'object' => 'Item',
            'collection' => 'Collection',
            'group' => 'Series'
        ]
    ],

    'extractYear' => [
        'type' => 'regex',
        'pattern' => '/(\d{4})/',
        'replacement' => '$1'
    ],

    'buildTitle' => [
        'type' => 'concat',
        'fields' => ['ObjectName', 'ObjectNumber'],
        'delimiter' => ' - '
    ],

    'conditionalAccess' => [
        'type' => 'conditional',
        'field' => 'Restricted',
        'operator' => 'equals',
        'value' => 'Yes',
        'then' => 'Restricted access',
        'else' => 'Open access'
    ]
];
```

---

## Sector Definitions

### SectorInterface

```php
namespace AhgMigration\Sectors;

interface SectorInterface
{
    public function getId(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function getPlugin(): ?string;
    public function getStandard(): string;
    public function getFields(): array;
    public function getRequiredFields(): array;
    public function getFieldGroups(): array;
    public function getLevels(): array;
    public function validate(array $data): array;
}
```

### Sector Summary

| Sector | Standard | Plugin Required | Levels |
|--------|----------|-----------------|--------|
| Archives | ISAD(G) | None (core) | Fonds, Sub-fonds, Collection, Series, Sub-series, File, Item |
| Museum | SPECTRUM 5.0 | ahgMuseumPlugin | Collection, Item, Part |
| Library | MARC/RDA | ahgLibraryPlugin | Collection, Series, Item |
| Gallery | CCO/VRA Core | ahgGalleryPlugin | Collection, Series, Item |
| DAM | Dublin Core | ahgDAMPlugin | Collection, Item |

### Archives Sector Fields (ISAD(G))

| Field Group | Fields |
|-------------|--------|
| Identity (3.1) | identifier, title, levelOfDescription, extentAndMedium, eventDates |
| Context (3.2) | creators, repository, archivalHistory, acquisition |
| Content (3.3) | scopeAndContent, appraisal, accruals, arrangement |
| Conditions (3.4) | accessConditions, reproductionConditions, language, physicalCharacteristics, findingAids |
| Allied (3.5) | locationOfOriginals, locationOfCopies, relatedUnitsOfDescription, publicationNote |
| Notes (3.6) | generalNote |
| Control (3.7) | archivistNote, rules |
| Access Points | subjectAccessPoints, placeAccessPoints, nameAccessPoints, genreAccessPoints |

### Museum Sector Fields (SPECTRUM)

| Field Group | Fields |
|-------------|--------|
| Identification | identifier, title, objectType, numberOfObjects |
| Description | scopeAndContent, physicalDescription, distinguishingFeatures, inscriptions |
| Physical | materials, technique, dimensions, dimensionHeight/Width/Depth/Weight |
| Production | creators, makerRole, eventDates, productionPlace |
| Acquisition | acquisitionMethod, acquisitionDate, acquisitionSource, provenance |
| Condition | condition, conditionDate, conditionNote |
| Location | physicalStorage, normalLocation |
| Rights | accessConditions, copyright, creditLine |

---

## Repository Class

### MigrationRepository Methods

```php
namespace AhgMigration\Repositories;

class MigrationRepository
{
    // Job Management
    public function createJob(array $data): int;
    public function getJob(int $id): ?object;
    public function updateJob(int $id, array $data): bool;
    public function deleteJob(int $id): bool;
    public function getJobs(array $filters = [], int $limit = 50, int $offset = 0): Collection;
    public function incrementCounter(int $jobId, string $counter, int $amount = 1): void;

    // Template Management
    public function getTemplates(?string $sourceSystem = null, ?string $sector = null): Collection;
    public function getTemplate(int $id): ?object;
    public function getTemplateBySlug(string $slug): ?object;
    public function findTemplate(string $sourceSystem, string $sector): ?object;
    public function saveTemplate(array $data): int;
    public function deleteTemplate(int $id): bool;
    public function incrementTemplateUsage(int $id): void;

    // Migration Log
    public function logRecord(array $data): int;
    public function logBatch(array $records): int;
    public function getJobLogs(int $jobId, ?string $action = null, int $limit = 100, int $offset = 0): Collection;
    public function getLogStats(int $jobId): array;
    public function getImportedObjectIds(int $jobId, string $recordType = 'information_object'): array;

    // Staged Records
    public function stageRecord(array $data): int;
    public function stageBatch(array $records): int;
    public function getStagedRecords(int $jobId, int $limit = 100, int $offset = 0): Collection;
    public function getStagedPreview(int $jobId, int $limit = 10): Collection;
    public function updateStagedRecord(int $id, array $data): bool;
    public function getStagedStats(int $jobId): array;
    public function clearStagedRecords(int $jobId): int;
    public function getPendingStaged(int $jobId, int $limit = 100): Collection;
}
```

---

## Job Status Flow

```
                           +----------------+
                           |    pending     |
                           +----------------+
                                   |
                                   v
                           +----------------+
                           |    mapping     |
                           +----------------+
                                   |
                                   v
                           +----------------+
                           |  validating    |
                           +----------------+
                                   |
                    +--------------+--------------+
                    |                             |
                    v                             v
           +----------------+            +----------------+
           |   validated    |            |    failed      |
           +----------------+            +----------------+
                    |
         +----------+----------+
         |                     |
         v                     v
+----------------+     +----------------+
|   importing    |     |   exporting    |
+----------------+     +----------------+
         |                     |
         +----------+----------+
                    |
                    v
           +----------------+
           |   completed    |
           +----------------+
                    |
                    v
           +----------------+
           |   rollback     | (optional)
           +----------------+
```

---

## Configuration Settings

| Setting | Default | Description |
|---------|---------|-------------|
| `migration_upload_path` | `uploads/migration` | Directory for uploaded files |
| `migration_max_file_size` | `104857600` (100MB) | Maximum upload file size |
| `migration_batch_size` | `100` | Records per batch |
| `migration_default_output` | `direct` | Default output mode |
| `migration_create_actors` | `true` | Auto-create actors |
| `migration_create_terms` | `true` | Auto-create taxonomy terms |

---

## Usage Examples

### Programmatic Import

```php
use AhgMigration\Parsers\ParserFactory;
use AhgMigration\Mappers\FieldMapper;
use AhgMigration\Repositories\MigrationRepository;

// Create parser
$parser = ParserFactory::create('csv');
$detection = ParserFactory::detectFormat('/path/to/file.csv');

// Create field mapper
$mapper = new FieldMapper();
$mapper->setDestinationSector('museum');

// Auto-suggest mappings
$suggestions = $mapper->suggestMappings($detection['headers']);

// Configure mappings
$mapper->setMappings([
    'Object Number' => 'identifier',
    'Object Name' => 'title',
    'Primary Maker' => [
        'target' => 'creators',
        'transform' => 'createActor'
    ]
]);
$mapper->setDefaults([
    'levelOfDescription' => 'Item'
]);

// Process records
$repo = new MigrationRepository();
$jobId = $repo->createJob([
    'name' => 'Museum Import',
    'source_system' => $detection['source_system'],
    'source_format' => 'csv',
    'destination_sector' => 'museum',
    'field_mappings' => $mapper->getMappings()
]);

foreach ($parser->parse('/path/to/file.csv') as $record) {
    $mapped = $mapper->mapRecord($record['data']);

    $repo->stageRecord([
        'job_id' => $jobId,
        'row_number' => $record['row_number'],
        'source_data' => $record['data'],
        'mapped_data' => $mapped
    ]);
}
```

### Using Templates

```php
// Find existing template
$template = $repo->findTemplate('vernon', 'museum');

if ($template) {
    $mapper->loadFromTemplate($template);
    $repo->incrementTemplateUsage($template->id);
}

// Save new template
$templateId = $repo->saveTemplate([
    'name' => 'Vernon to Museum Objects',
    'source_system' => 'vernon',
    'source_format' => 'csv',
    'destination_sector' => 'museum',
    'field_mappings' => $mappings,
    'transformations' => $transformations,
    'default_values' => $defaults
]);
```

---

## File Structure

```
ahgMigrationPlugin/
+-- config/
|   +-- ahgMigrationPluginConfiguration.class.php
+-- database/
|   +-- install.sql
+-- lib/
|   +-- Mappers/
|   |   +-- FieldMapper.php
|   |   +-- TransformationEngine.php
|   +-- Parsers/
|   |   +-- ParserInterface.php
|   |   +-- ParserFactory.php
|   |   +-- CsvParser.php
|   |   +-- XmlParser.php
|   |   +-- EadParser.php
|   +-- Repositories/
|   |   +-- MigrationRepository.php
|   +-- Sectors/
|       +-- SectorInterface.php
|       +-- AbstractSector.php
|       +-- SectorFactory.php
|       +-- ArchivesSector.php
|       +-- MuseumSector.php
|       +-- LibrarySector.php
|       +-- GallerySector.php
|       +-- DamSector.php
+-- extension.json
```

---

## Deprecation Notice

This plugin is deprecated in favor of `ahgDataMigrationPlugin`, which provides:
- Enhanced UI with drag-and-drop field mapping
- Real-time preview with sample data
- Improved validation and error handling
- Better performance for large imports
- Support for hierarchical imports
- Digital object attachment during import

Migration path: Templates created in ahgMigrationPlugin are compatible with ahgDataMigrationPlugin.

---

*Part of the AtoM AHG Framework*
