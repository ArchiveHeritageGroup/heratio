# ahgDataMigrationPlugin - Technical Documentation

**Plugin Version:** 1.4.0
**Last Updated:** 2026-02-03
**Framework:** AtoM AHG Framework (Laravel Query Builder + Symfony 1.x)

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Directory Structure](#2-directory-structure)
3. [Database Schema](#3-database-schema)
4. [Core Components](#4-core-components)
5. [Validation Framework](#5-validation-framework)
6. [Parsers](#6-parsers)
7. [Exporters](#7-exporters)
8. [Preservica Integration](#8-preservica-integration)
9. [Sector Definitions](#9-sector-definitions)
10. [CLI Tasks](#10-cli-tasks)
11. [Gearman Jobs](#11-gearman-jobs)
12. [Extending the Plugin](#12-extending-the-plugin)
13. [Digital Object Import](#13-digital-object-import)

---

## 1. Architecture Overview
```
┌─────────────────────────────────────────────────────────────────┐
│                        Web UI / CLI                             │
├─────────────────────────────────────────────────────────────────┤
│                   ahgDataMigrationActions                       │
│       (Upload, Map, Preview, Validate, Import, Export)          │
├─────────────────────────────────────────────────────────────────┤
│  MigrationService │ ValidationService │ PreservicaImportService │
├─────────────────────────────────────────────────────────────────┤
│              Validation Framework (NEW in 1.4.0)                │
│  ┌──────────────┬──────────────┬─────────────┬───────────────┐  │
│  │ SchemaValid. │ Referential  │ Duplicate   │ SectorValid.  │  │
│  │              │ Validator    │ Detector    │ (5 sectors)   │  │
│  └──────────────┴──────────────┴─────────────┴───────────────┘  │
├─────────────────────────────────────────────────────────────────┤
│  ParserFactory  │  Parsers (CSV, Excel, OPEX, PAX)              │
├─────────────────────────────────────────────────────────────────┤
│  SourceDetector  │  Mappings (Field Definitions)                │
├─────────────────────────────────────────────────────────────────┤
│  Sectors (Archives, Museum, Library, Gallery, DAM)              │
├─────────────────────────────────────────────────────────────────┤
│           Laravel Query Builder (Illuminate\Database)           │
├─────────────────────────────────────────────────────────────────┤
│                      MySQL Database                             │
└─────────────────────────────────────────────────────────────────┘
```

### Data Flow

1. **Upload** → File received, stored in `/uploads/migrations/`
2. **Detect** → SourceDetector identifies format and source system
3. **Parse** → ParserFactory creates appropriate parser
4. **Map** → Field mappings applied from `atom_data_mapping`
5. **Transform** → Transformations applied (trim, date format, etc.)
6. **Import** → Records created in AtoM database
7. **Post-process** → Slugs generated, nested set calculated, rights imported

---

## 2. Directory Structure
```
atom-ahg-plugins/ahgDataMigrationPlugin/
├── bin/
│   └── setup-gearman.sh          # Gearman installation script
├── config/
│   ├── ahgDataMigrationPluginConfiguration.class.php
│   └── routing.yml
├── data/
│   ├── install.sql
│   ├── samples/                   # NEW: Sample CSV files
│   │   ├── archives_sample.csv   # ISAD-G hierarchy example
│   │   ├── museum_sample.csv     # Spectrum objects
│   │   ├── library_sample.csv    # MARC/RDA records
│   │   ├── gallery_sample.csv    # CCO artworks
│   │   └── dam_sample.csv        # Dublin Core assets
│   ├── validation/               # NEW: Validation rules
│   │   ├── archive_rules.json
│   │   ├── museum_rules.json
│   │   ├── library_rules.json
│   │   ├── gallery_rules.json
│   │   └── dam_rules.json
│   └── mappings/
│       └── defaults/
│           ├── information_object.json
│           ├── museum.json
│           ├── library.json          # MARC/RDA fields
│           ├── gallery.json          # CCO/VRA fields
│           ├── dam.json              # Dublin Core/IPTC fields
│           ├── preservica_opex.json
│           ├── preservica_xip.json
│           └── ...
├── docs/
│   └── GEARMAN.md                # Gearman setup documentation
├── lib/
│   ├── Validation/               # NEW: Validation framework
│   │   ├── AhgBaseValidator.class.php
│   │   ├── AhgValidatorCollection.class.php
│   │   ├── AhgValidationReport.class.php
│   │   ├── AhgSchemaValidator.class.php
│   │   ├── AhgReferentialValidator.class.php
│   │   ├── AhgDuplicateDetector.class.php
│   │   └── Sectors/
│   │       ├── ArchivesValidator.class.php
│   │       ├── MuseumValidator.class.php
│   │       ├── LibraryValidator.class.php
│   │       ├── GalleryValidator.class.php
│   │       └── DamValidator.class.php
│   ├── Services/
│   │   ├── MigrationService.php
│   │   ├── ValidationService.php   # NEW: Validation orchestration
│   │   ├── PreservicaImportService.php
│   │   ├── PreservicaExportService.php
│   │   ├── PathTransformer.php
│   │   └── RightsImportService.php
│   ├── Exporters/                # Sector-specific CSV exporters
│   │   ├── BaseExporter.php
│   │   ├── ExporterFactory.php
│   │   ├── ArchivesExporter.php
│   │   ├── MuseumExporter.php
│   │   ├── LibraryExporter.php
│   │   ├── GalleryExporter.php
│   │   └── DamExporter.php
│   ├── Mappings/
│   │   └── PreservicaMapping.php
│   ├── Parsers/
│   │   ├── CsvParser.php
│   │   ├── ExcelParser.php
│   │   ├── OpexParser.php
│   │   ├── PaxParser.php
│   │   └── ParserFactory.php
│   ├── Sectors/
│   │   ├── SectorFactory.php
│   │   ├── ArchivesSector.php
│   │   ├── MuseumSector.php
│   │   ├── LibrarySector.php
│   │   ├── GallerySector.php
│   │   └── DamSector.php
│   ├── SourceDetector.php
│   └── task/
│       ├── migrationImportTask.class.php
│       ├── sectorImportTask.class.php         # NEW: Base sector import
│       ├── archivesCsvImportTask.class.php    # NEW: ISAD-G import
│       ├── museumCsvImportTask.class.php      # NEW: Spectrum import
│       ├── libraryCsvImportTask.class.php     # NEW: MARC/RDA import
│       ├── galleryCsvImportTask.class.php     # NEW: CCO import
│       ├── damCsvImportTask.class.php         # NEW: Dublin Core import
│       ├── preservicaImportTask.class.php
│       ├── preservicaExportTask.class.php
│       └── preservicaInfoTask.class.php
├── modules/
│   └── dataMigration/
│       ├── actions/
│       │   ├── indexAction.class.php
│       │   ├── uploadAction.class.php
│       │   ├── mapAction.class.php
│       │   ├── previewAction.class.php
│       │   ├── executeAction.class.php
│       │   ├── validateAction.class.php       # NEW: Validation-only
│       │   ├── previewValidationAction.class.php # NEW: AJAX validation
│       │   ├── exportMappingAction.class.php  # NEW: Profile export
│       │   ├── importMappingAction.class.php  # NEW: Profile import
│       │   ├── sectorExportAction.class.php   # NEW: DB export
│       │   ├── batchExportAction.class.php    # Batch export UI
│       │   ├── exportCsvAction.class.php
│       │   ├── jobsAction.class.php
│       │   └── ...
│       └── templates/
│           ├── indexSuccess.php
│           ├── mapSuccess.php
│           ├── previewSuccess.php
│           ├── validateSuccess.php            # NEW: Validation UI
│           ├── batchExportSuccess.php
│           └── jobsSuccess.php
└── css/
    └── data-migration.css
```

---

## 3. Database Schema

### atom_data_mapping

Stores field mapping configurations.
```sql
CREATE TABLE IF NOT EXISTS atom_data_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    source_type VARCHAR(100),
    target_type VARCHAR(50),
    field_mappings JSON,
    transformations JSON,
    default_values JSON,
    is_system TINYINT(1) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES user(id) ON DELETE SET NULL
);
```

**Field Descriptions:**
- `source_type` - Source system identifier (archivesspace, vernon, preservica_opex)
- `target_type` - Target sector (ARCHIVES, MUSEUM, LIBRARY, GALLERY, DAM)
- `field_mappings` - JSON object `{"source_field": "target_field"}`
- `transformations` - JSON object `{"field": "transform_type"}`
- `default_values` - JSON object `{"field": "default_value"}`

### atom_data_migration_job

Tracks background import jobs.
```sql
CREATE TABLE IF NOT EXISTS atom_data_migration_job (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mapping_id INT,
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    total_records INT DEFAULT 0,
    processed_records INT DEFAULT 0,
    created_records INT DEFAULT 0,
    updated_records INT DEFAULT 0,
    skipped_records INT DEFAULT 0,
    error_count INT DEFAULT 0,
    status ENUM('queued','running','completed','failed','cancelled') DEFAULT 'queued',
    error_log JSON,
    options JSON,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mapping_id) REFERENCES atom_data_mapping(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES user(id) ON DELETE SET NULL
);
```

### atom_data_migration_log

Audit log for individual record imports.
```sql
CREATE TABLE IF NOT EXISTS atom_data_migration_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT,
    record_id INT,
    legacy_id VARCHAR(255),
    action ENUM('create','update','skip','error'),
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES atom_data_migration_job(id) ON DELETE CASCADE
);
```

### atom_validation_rule (NEW in 1.4.0)

Stores configurable validation rules per sector.
```sql
CREATE TABLE IF NOT EXISTS atom_validation_rule (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sector_code VARCHAR(50) NOT NULL,
    rule_type ENUM('required', 'type', 'pattern', 'enum', 'range', 'length', 'referential', 'custom') NOT NULL,
    field_name VARCHAR(255) NOT NULL,
    rule_config JSON NOT NULL,
    error_message VARCHAR(500),
    severity ENUM('error', 'warning', 'info') DEFAULT 'error',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sector (sector_code),
    INDEX idx_field (field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Rule Types:**
- `required` - Field must not be empty
- `type` - Data type validation (string, integer, float, date, boolean)
- `pattern` - Regex pattern matching
- `enum` - Value must be in allowed list
- `range` - Numeric range validation
- `length` - String length validation
- `referential` - Parent/child relationship validation
- `custom` - Custom PHP validation callback

### atom_validation_log (NEW in 1.4.0)

Logs validation errors per job.
```sql
CREATE TABLE IF NOT EXISTS atom_validation_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED,
    row_number INT,
    column_name VARCHAR(255),
    rule_type VARCHAR(50),
    severity ENUM('error', 'warning', 'info'),
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job (job_id),
    INDEX idx_row (row_number),
    FOREIGN KEY (job_id) REFERENCES atom_data_migration_job(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 4. Core Components

### SourceDetector.php

Auto-detects source system from file content.
```php
class SourceDetector
{
    public function detect(string $filePath): array
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        return match($extension) {
            'opex' => ['format' => 'opex', 'source' => 'preservica_opex'],
            'pax', 'zip' => $this->detectPaxOrZip($filePath),
            'csv' => $this->detectCsvSource($filePath),
            'xlsx', 'xls' => $this->detectExcelSource($filePath),
            'xml' => $this->detectXmlSource($filePath),
            default => ['format' => 'unknown', 'source' => 'unknown']
        };
    }
    
    protected function detectCsvSource(string $filePath): array
    {
        $headers = $this->getCsvHeaders($filePath);
        
        // ArchivesSpace detection
        if (in_array('ead_id', $headers) || in_array('resource_type', $headers)) {
            return ['format' => 'csv', 'source' => 'archivesspace'];
        }
        
        // Vernon CMS detection
        if (in_array('object_number', $headers) || in_array('accession_number', $headers)) {
            return ['format' => 'csv', 'source' => 'vernon'];
        }
        
        // Generic CSV
        return ['format' => 'csv', 'source' => 'generic'];
    }
}
```

### MigrationService.php

Main orchestration service for imports.
```php
namespace ahgDataMigrationPlugin\Services;

use Illuminate\Database\Capsule\Manager as DB;

class MigrationService
{
    protected $mapping;
    protected $parser;
    protected $sector;
    protected $options = [];
    protected $stats = [
        'total' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0
    ];

    public function import(string $filePath, int $mappingId, array $options = []): array
    {
        $this->loadMapping($mappingId);
        $this->initParser($filePath);
        $this->initSector();
        $this->options = $options;
        
        $records = $this->parser->parse($filePath);
        $this->stats['total'] = count($records);
        
        // Build hierarchy map for parent resolution
        $hierarchyMap = $this->buildHierarchyMap($records);
        
        foreach ($records as $record) {
            try {
                $this->processRecord($record, $hierarchyMap);
            } catch (\Exception $e) {
                $this->logError($record, $e->getMessage());
            }
        }
        
        return $this->stats;
    }

    protected function processRecord(array $data, array $hierarchyMap): void
    {
        // Apply field mappings
        $mapped = $this->applyMappings($data);
        
        // Apply transformations
        $transformed = $this->applyTransformations($mapped);
        
        // Apply defaults
        $final = $this->applyDefaults($transformed);
        
        // Resolve parent ID
        if (!empty($final['parentId'])) {
            $final['parent_id'] = $hierarchyMap[$final['parentId']] ?? null;
        }
        
        // Create or update record
        $this->saveRecord($final);
    }

    protected function saveRecord(array $data): int
    {
        // Check for existing record (update mode)
        if ($this->options['update'] ?? false) {
            $existing = $this->findExisting($data);
            if ($existing) {
                return $this->updateRecord($existing, $data);
            }
        }
        
        // Create new information_object
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Insert information_object
        DB::table('information_object')->insert([
            'id' => $objectId,
            'identifier' => $data['identifier'] ?? null,
            'level_of_description_id' => $this->resolveLevelId($data['levelOfDescription']),
            'repository_id' => $data['repository_id'] ?? $this->options['repository'] ?? null,
            'parent_id' => $data['parent_id'] ?? QubitInformationObject::ROOT_ID,
            'source_culture' => $data['culture'] ?? 'en',
        ]);
        
        // Insert i18n data
        DB::table('information_object_i18n')->insert([
            'id' => $objectId,
            'culture' => $data['culture'] ?? 'en',
            'title' => $data['title'],
            'scope_and_content' => $data['scopeAndContent'] ?? null,
            // ... other i18n fields
        ]);
        
        // Generate slug
        $this->generateSlug($objectId, $data['title']);
        
        // Calculate nested set (lft/rgt)
        $this->updateNestedSet($objectId, $data['parent_id'] ?? QubitInformationObject::ROOT_ID);
        
        // Set publication status
        $this->setPublicationStatus($objectId);
        
        $this->stats['created']++;
        return $objectId;
    }
}
```

---

## 5. Validation Framework

The validation framework (new in 1.4.0) provides comprehensive data quality checks before import.

### AhgValidationReport.class.php

Tracks errors by row and column with severity levels.
```php
class AhgValidationReport
{
    const SEVERITY_ERROR = 'error';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_INFO = 'info';

    protected array $errors = [];      // [row => [column => [errors]]]
    protected array $summary = [];     // Counts by severity
    protected int $totalRows = 0;

    public function addError(int $row, string $column, string $message, string $severity = 'error'): void
    {
        $this->errors[$row][$column][] = [
            'message' => $message,
            'severity' => $severity
        ];
        $this->summary[$severity] = ($this->summary[$severity] ?? 0) + 1;
    }

    public function hasErrors(): bool
    {
        return ($this->summary['error'] ?? 0) > 0;
    }

    public function getRowErrors(int $row): array
    {
        return $this->errors[$row] ?? [];
    }

    public function toArray(): array
    {
        return [
            'total_rows' => $this->totalRows,
            'error_count' => $this->summary['error'] ?? 0,
            'warning_count' => $this->summary['warning'] ?? 0,
            'info_count' => $this->summary['info'] ?? 0,
            'errors' => $this->errors
        ];
    }
}
```

### AhgSchemaValidator.class.php

Validates required fields, data types, patterns, and max lengths.
```php
class AhgSchemaValidator extends AhgBaseValidator
{
    protected array $rules = [];

    public function loadRulesFromJson(string $path): void
    {
        $json = file_get_contents($path);
        $config = json_decode($json, true);
        $this->rules = $config['rules'] ?? [];
    }

    public function validate(array $row, int $rowNumber): void
    {
        // Required fields
        foreach ($this->rules['required'] ?? [] as $field) {
            if (empty($row[$field])) {
                $this->report->addError($rowNumber, $field, "Required field is empty");
            }
        }

        // Data types
        foreach ($this->rules['types'] ?? [] as $field => $type) {
            if (!empty($row[$field]) && !$this->validateType($row[$field], $type)) {
                $this->report->addError($rowNumber, $field, "Invalid type: expected $type");
            }
        }

        // Patterns (regex)
        foreach ($this->rules['patterns'] ?? [] as $field => $pattern) {
            if (!empty($row[$field]) && !preg_match("/$pattern/", $row[$field])) {
                $this->report->addError($rowNumber, $field, "Value does not match pattern");
            }
        }

        // Max lengths
        foreach ($this->rules['maxLengths'] ?? [] as $field => $maxLen) {
            if (!empty($row[$field]) && strlen($row[$field]) > $maxLen) {
                $this->report->addError($rowNumber, $field, "Exceeds max length of $maxLen");
            }
        }

        // Enums (allowed values)
        foreach ($this->rules['enums'] ?? [] as $field => $allowed) {
            if (!empty($row[$field]) && !in_array($row[$field], $allowed)) {
                $this->report->addError($rowNumber, $field,
                    "Invalid value: must be one of " . implode(', ', $allowed));
            }
        }
    }
}
```

### AhgReferentialValidator.class.php

Validates parent-child relationships and detects circular references.
```php
class AhgReferentialValidator extends AhgBaseValidator
{
    protected array $idIndex = [];     // legacyId => row number
    protected array $parentIndex = []; // legacyId => parentId
    protected array $existingIds = []; // IDs from database

    public function buildIndex(array $rows): void
    {
        foreach ($rows as $rowNum => $row) {
            $legacyId = $row['legacyId'] ?? $row['identifier'] ?? null;
            if ($legacyId) {
                $this->idIndex[$legacyId] = $rowNum;
                $this->parentIndex[$legacyId] = $row['parentId'] ?? null;
            }
        }
    }

    public function validate(array $row, int $rowNumber): void
    {
        $parentId = $row['parentId'] ?? null;
        $legacyId = $row['legacyId'] ?? $row['identifier'] ?? null;

        if (empty($parentId)) {
            return; // Root record, no parent to validate
        }

        // Check parent exists in file or database
        if (!isset($this->idIndex[$parentId]) && !$this->existsInDatabase($parentId)) {
            $this->report->addError($rowNumber, 'parentId',
                "Parent '$parentId' not found in file or database");
        }

        // Check for circular reference
        if ($this->detectCycle($legacyId)) {
            $this->report->addError($rowNumber, 'parentId',
                "Circular reference detected in hierarchy");
        }
    }

    protected function detectCycle(string $id): bool
    {
        $visited = [];
        $current = $id;

        while ($current && isset($this->parentIndex[$current])) {
            if (isset($visited[$current])) {
                return true; // Cycle detected
            }
            $visited[$current] = true;
            $current = $this->parentIndex[$current];
        }

        return false;
    }
}
```

### AhgDuplicateDetector.class.php

Configurable duplicate detection with multiple strategies.
```php
class AhgDuplicateDetector extends AhgBaseValidator
{
    const STRATEGY_IDENTIFIER = 'identifier';
    const STRATEGY_LEGACY_ID = 'legacyId';
    const STRATEGY_TITLE_DATE = 'title_date';
    const STRATEGY_COMPOSITE = 'composite';

    protected string $strategy = self::STRATEGY_IDENTIFIER;
    protected array $compositeFields = [];
    protected array $seenValues = [];

    public function setStrategy(string $strategy, array $fields = []): void
    {
        $this->strategy = $strategy;
        $this->compositeFields = $fields;
    }

    public function validate(array $row, int $rowNumber): void
    {
        $key = $this->buildKey($row);

        if (isset($this->seenValues[$key])) {
            $firstRow = $this->seenValues[$key];
            $this->report->addError($rowNumber, $this->getKeyField(),
                "Duplicate of row $firstRow", self::SEVERITY_WARNING);
        } else {
            $this->seenValues[$key] = $rowNumber;
        }

        // Check against database
        if ($this->checkDatabase && $this->existsInDatabase($key)) {
            $this->report->addError($rowNumber, $this->getKeyField(),
                "Record already exists in database", self::SEVERITY_WARNING);
        }
    }

    protected function buildKey(array $row): string
    {
        return match($this->strategy) {
            self::STRATEGY_IDENTIFIER => $row['identifier'] ?? '',
            self::STRATEGY_LEGACY_ID => $row['legacyId'] ?? '',
            self::STRATEGY_TITLE_DATE => ($row['title'] ?? '') . '|' . ($row['dateRange'] ?? ''),
            self::STRATEGY_COMPOSITE => implode('|', array_map(
                fn($f) => $row[$f] ?? '',
                $this->compositeFields
            )),
        };
    }
}
```

### Sector-Specific Validators

Each sector has specialized validation rules:

| Validator | Sector | Key Validations |
|-----------|--------|-----------------|
| `ArchivesValidator` | ISAD-G | Level hierarchy, fonds→series→file→item flow |
| `MuseumValidator` | Spectrum | Object number format, acquisition date |
| `LibraryValidator` | MARC/RDA | ISBN-10/13 checksum, ISSN format |
| `GalleryValidator` | CCO | Work type vocabulary, creator format |
| `DamValidator` | Dublin Core | DC type, MIME type, GPS coordinates |

#### LibraryValidator ISBN Validation
```php
protected function validateIsbn(string $isbn, int $row): void
{
    $clean = preg_replace('/[^0-9X]/', '', strtoupper($isbn));

    if (strlen($clean) === 10) {
        // ISBN-10 checksum
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$clean[$i] * (10 - $i);
        }
        $check = (11 - ($sum % 11)) % 11;
        $expected = $check === 10 ? 'X' : (string)$check;

        if ($clean[9] !== $expected) {
            $this->report->addError($row, 'isbn', "Invalid ISBN-10 checksum");
        }
    } elseif (strlen($clean) === 13) {
        // ISBN-13 checksum
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$clean[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $check = (10 - ($sum % 10)) % 10;

        if ((int)$clean[12] !== $check) {
            $this->report->addError($row, 'isbn', "Invalid ISBN-13 checksum");
        }
    } else {
        $this->report->addError($row, 'isbn', "ISBN must be 10 or 13 digits");
    }
}
```

### ValidationService.php

Orchestrates all validators.
```php
namespace ahgDataMigrationPlugin\Services;

class ValidationService
{
    protected AhgValidatorCollection $validators;
    protected string $sectorCode;

    public function validate(string $filepath, array $mapping = [], array $rows = []): AhgValidationReport
    {
        $report = new AhgValidationReport();

        // Parse file if rows not provided
        if (empty($rows)) {
            $parser = ParserFactory::create($this->detectFormat($filepath));
            $rows = $parser->parse($filepath);
        }

        $report->setTotalRows(count($rows));

        // Initialize validators
        $this->validators = new AhgValidatorCollection($report);
        $this->validators->add(new AhgSchemaValidator($report, $this->sectorCode));
        $this->validators->add(new AhgReferentialValidator($report));
        $this->validators->add(new AhgDuplicateDetector($report));
        $this->validators->add($this->getSectorValidator($report));

        // Build index for referential validation
        $this->validators->buildIndex($rows);

        // Validate each row
        foreach ($rows as $rowNum => $row) {
            $mapped = $this->applyMapping($row, $mapping);
            $this->validators->validateRow($mapped, $rowNum + 1);
        }

        return $report;
    }

    public function validateOnly(string $filepath, array $mapping = []): AhgValidationReport
    {
        return $this->validate($filepath, $mapping);
    }

    protected function getSectorValidator(AhgValidationReport $report): AhgBaseValidator
    {
        return match($this->sectorCode) {
            'archive', 'archives' => new ArchivesValidator($report),
            'museum', 'spectrum' => new MuseumValidator($report),
            'library', 'marc' => new LibraryValidator($report),
            'gallery', 'cco' => new GalleryValidator($report),
            'dam', 'dc' => new DamValidator($report),
            default => new AhgBaseValidator($report),
        };
    }
}
```

### Validation Rules JSON Format

Located in `data/validation/{sector}_rules.json`:
```json
{
    "sector": "archive",
    "rules": {
        "required": ["identifier", "title", "levelOfDescription"],
        "types": {
            "legacyId": "string",
            "dateRange": "string"
        },
        "patterns": {
            "identifier": "^[A-Za-z0-9/-]+$"
        },
        "maxLengths": {
            "title": 1024,
            "identifier": 255
        },
        "enums": {
            "levelOfDescription": ["fonds", "collection", "series", "subseries", "file", "item"]
        },
        "referential": {
            "parentId": "legacyId"
        }
    }
}
```

### New Routes

```yaml
# config/routing.yml

dataMigration_validate:
  url: /dataMigration/validate
  param: { module: dataMigration, action: validate }

dataMigration_previewValidation:
  url: /dataMigration/previewValidation
  param: { module: dataMigration, action: previewValidation }

dataMigration_exportMapping:
  url: /dataMigration/exportMapping/:id
  param: { module: dataMigration, action: exportMapping }

dataMigration_importMapping:
  url: /dataMigration/importMapping
  param: { module: dataMigration, action: importMapping }

dataMigration_sectorExport:
  url: /dataMigration/export/:sector
  param: { module: dataMigration, action: sectorExport }
```

---

## 6. Parsers

### ParserFactory.php
```php
class ParserFactory
{
    public static function create(string $format): ParserInterface
    {
        return match($format) {
            'csv' => new CsvParser(),
            'xlsx', 'xls' => new ExcelParser(),
            'opex' => new OpexParser(),
            'pax', 'xip' => new PaxParser(),
            default => throw new \InvalidArgumentException("Unknown format: $format")
        };
    }
}
```

### OpexParser.php

Parses Preservica OPEX XML format with full rights extraction.
```php
class OpexParser implements ParserInterface
{
    protected $namespaces = [
        'opex' => 'http://www.openpreservationexchange.org/opex/v1.2',
        'dc' => 'http://purl.org/dc/elements/1.1/',
        'dcterms' => 'http://purl.org/dc/terms/',
        'mods' => 'http://www.loc.gov/mods/v3',
        'ead' => 'urn:isbn:1-931666-22-9',
    ];

    public function parse(string $filePath): array
    {
        $xml = simplexml_load_file($filePath);
        foreach ($this->namespaces as $prefix => $uri) {
            $xml->registerXPathNamespace($prefix, $uri);
        }
        
        $records = [];
        
        // Parse folders
        foreach ($xml->xpath('//opex:Folder') as $folder) {
            $records[] = $this->parseFolder($folder);
        }
        
        // Parse assets
        foreach ($xml->xpath('//opex:Asset') as $asset) {
            $records[] = $this->parseAsset($asset);
        }
        
        return $records;
    }

    protected function parseFolder(\SimpleXMLElement $folder): array
    {
        $record = [
            'legacyId' => (string)$folder['id'],
            'title' => (string)$folder->Title,
            'levelOfDescription' => 'Series',
        ];
        
        // Extract Dublin Core
        $this->extractDublinCore($folder, $record);
        
        // Extract rights
        $record['rights'] = $this->extractRights($folder);
        
        // Extract provenance/history
        $record['provenance'] = $this->extractProvenance($folder);
        
        return $record;
    }

    protected function extractRights(\SimpleXMLElement $element): array
    {
        $rights = [];
        
        // SecurityDescriptor
        $security = $element->xpath('.//opex:SecurityDescriptor');
        if (!empty($security)) {
            $rights[] = [
                'type' => 'access',
                'basis' => 'policy',
                'value' => (string)$security[0],
            ];
        }
        
        // dc:rights
        $dcRights = $element->xpath('.//dc:rights');
        foreach ($dcRights as $r) {
            $rights[] = [
                'type' => 'copyright',
                'basis' => 'copyright',
                'value' => (string)$r,
            ];
        }
        
        // dcterms:license
        $license = $element->xpath('.//dcterms:license');
        foreach ($license as $l) {
            $rights[] = [
                'type' => 'license',
                'basis' => 'license',
                'value' => (string)$l,
            ];
        }
        
        // MODS accessCondition
        $mods = $element->xpath('.//mods:accessCondition');
        foreach ($mods as $m) {
            $rights[] = [
                'type' => (string)$m['type'] ?: 'access',
                'basis' => 'statute',
                'value' => (string)$m,
            ];
        }
        
        // EAD userestrict/accessrestrict
        foreach (['userestrict', 'accessrestrict'] as $tag) {
            $ead = $element->xpath(".//ead:$tag");
            foreach ($ead as $e) {
                $rights[] = [
                    'type' => $tag === 'userestrict' ? 'use' : 'access',
                    'basis' => 'policy',
                    'value' => (string)$e->p,
                ];
            }
        }
        
        return $rights;
    }

    protected function extractProvenance(\SimpleXMLElement $element): array
    {
        $provenance = [];
        
        $history = $element->xpath('.//opex:History/opex:Event');
        foreach ($history as $event) {
            $provenance[] = [
                'date' => (string)$event->Date,
                'type' => (string)$event->Type,
                'agent' => (string)$event->Agent,
                'description' => (string)$event->Description,
            ];
        }
        
        return $provenance;
    }
}
```

---

## 7. Exporters

The plugin includes sector-specific CSV exporters for both transformation (during import) and batch export of existing AtoM records.

### ExporterFactory.php

Creates the appropriate exporter based on sector code.
```php
namespace ahgDataMigrationPlugin\Exporters;

class ExporterFactory
{
    private static array $exporters = [
        'archive' => ArchivesExporter::class,
        'archives' => ArchivesExporter::class,
        'museum' => MuseumExporter::class,
        'spectrum' => MuseumExporter::class,
        'library' => LibraryExporter::class,
        'marc' => LibraryExporter::class,
        'gallery' => GalleryExporter::class,
        'cco' => GalleryExporter::class,
        'dam' => DamExporter::class,
        'dc' => DamExporter::class,
    ];

    public static function create(string $sector): BaseExporter
    {
        $sector = strtolower(trim($sector));
        if (!isset(self::$exporters[$sector])) {
            throw new \InvalidArgumentException("Unknown sector: $sector");
        }
        return new (self::$exporters[$sector])();
    }

    public static function getAvailableSectors(): array
    {
        return ['archives', 'museum', 'library', 'gallery', 'dam'];
    }
}
```

### BaseExporter.php

Abstract base class for all exporters.
```php
abstract class BaseExporter
{
    protected array $data = [];

    abstract public function getSectorCode(): string;
    abstract public function getColumns(): array;
    abstract public function mapRecord(array $record): array;

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function export(): string
    {
        $columns = $this->getColumns();
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $columns);

        foreach ($this->data as $record) {
            $mapped = $this->mapRecord($record);
            $row = [];
            foreach ($columns as $col) {
                $row[] = $mapped[$col] ?? '';
            }
            fputcsv($output, $row);
        }

        rewind($output);
        return stream_get_contents($output);
    }

    public function getFilename(string $baseName): string
    {
        return pathinfo($baseName, PATHINFO_FILENAME) . '_' . $this->getSectorCode() . '_import.csv';
    }
}
```

### Sector Exporters

| Exporter | Columns | Standard |
|----------|---------|----------|
| `ArchivesExporter` | 45 | ISAD(G) |
| `MuseumExporter` | 38 | Spectrum 5.1 |
| `LibraryExporter` | 32 | MARC/RDA |
| `GalleryExporter` | 35 | CCO/VRA |
| `DamExporter` | 52 | Dublin Core/IPTC |

### Default Mapping Files

Located in `data/mappings/defaults/`:

| File | Description |
|------|-------------|
| `library.json` | Maps MARC/RDA fields (ISBN, call number, publisher, etc.) |
| `gallery.json` | Maps CCO/VRA fields (creator, provenance, exhibition history, etc.) |
| `dam.json` | Maps Dublin Core/IPTC fields (camera metadata, GPS, keywords, etc.) |
| `museum.json` | Maps Spectrum 5.1 fields |
| `information_object.json` | Generic ISAD(G) mapping |

### Database Export (NEW in 1.4.0)

The `exportFromDatabase()` method allows exporting directly from AtoM database:
```php
abstract class BaseExporter
{
    // ... existing methods

    /**
     * Export records from database
     * @param array $objectIds Array of information_object IDs to export
     * @return string CSV content
     */
    public function exportFromDatabase(array $objectIds): string
    {
        $columns = $this->getColumns();
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $columns);

        foreach ($objectIds as $id) {
            $record = $this->loadRecordFromDatabase($id);
            if ($record) {
                $mapped = $this->mapRecord($record);
                $row = [];
                foreach ($columns as $col) {
                    $row[] = $mapped[$col] ?? '';
                }
                fputcsv($output, $row);
            }
        }

        rewind($output);
        return stream_get_contents($output);
    }

    /**
     * Load a single record from database
     */
    protected function loadRecordFromDatabase(int $id): ?array
    {
        $record = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', 'io.id', '=', 'ioi.id')
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('term_i18n as ti', 'io.level_of_description_id', '=', 'ti.id')
            ->leftJoin('repository_i18n as ri', 'io.repository_id', '=', 'ri.id')
            ->where('io.id', $id)
            ->where('ioi.culture', 'en')
            ->first();

        if (!$record) {
            return null;
        }

        return (array)$record;
    }
}
```

### Batch Export Action

The `batchExportAction` allows exporting existing AtoM records:

```php
class dataMigrationBatchExportAction extends sfAction
{
    public function execute($request)
    {
        // Filter options
        $sector = $request->getParameter('sector', 'archives');
        $repositoryId = $request->getParameter('repository_id');
        $levelIds = $request->getParameter('level_ids', []);
        $parentSlug = $request->getParameter('parent_slug', '');
        $includeDescendants = $request->getParameter('include_descendants', false);

        // Build query with filters
        $query = $DB::table('information_object')
            ->join('information_object_i18n', ...)
            ->where(...);

        $count = $query->count();

        // Direct download for small exports
        if ($count <= 500) {
            return $this->directExport($query, $sector, $DB);
        }

        // Queue background job for large exports
        return $this->queueBackgroundExport($request, $DB, $count);
    }
}
```

**Route:** `GET/POST /dataMigration/batchExport`

---

## 8. Preservica Integration

### PreservicaImportService.php

Handles full Preservica import workflow.
```php
class PreservicaImportService
{
    protected $parser;
    protected $rightsService;
    protected $provenanceService;
    protected $stats = [];

    public function import(string $filePath, array $options = []): array
    {
        $format = $this->detectFormat($filePath);
        $this->parser = ParserFactory::create($format);
        
        $records = $this->parser->parse($filePath);
        
        foreach ($records as $record) {
            $objectId = $this->createRecord($record, $options);
            
            // Import rights
            if (!empty($record['rights'])) {
                $this->rightsService->importRights($objectId, $record['rights']);
            }
            
            // Import provenance
            if (!empty($record['provenance'])) {
                $this->provenanceService->importEvents($objectId, $record['provenance']);
            }
            
            // Handle digital objects (PAX only)
            if (!empty($record['digitalObjects'])) {
                $this->importDigitalObjects($objectId, $record['digitalObjects']);
            }
        }
        
        return $this->stats;
    }
}
```

### PreservicaExportService.php

Exports AtoM records to Preservica formats.
```php
class PreservicaExportService
{
    public function exportOpex(int $objectId, array $options = []): string
    {
        $record = $this->loadRecord($objectId);
        
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $opex = $xml->createElementNS(
            'http://www.openpreservationexchange.org/opex/v1.2',
            'opex:OPEXMetadata'
        );
        
        // Add Dublin Core
        $this->addDublinCore($opex, $record);
        
        // Add rights
        $this->addRights($opex, $record);
        
        // Add history/provenance
        $this->addHistory($opex, $record);
        
        // Include children if hierarchy requested
        if ($options['hierarchy'] ?? false) {
            $this->addChildren($opex, $objectId);
        }
        
        $xml->appendChild($opex);
        return $xml->saveXML();
    }

    public function exportPax(int $objectId, array $options = []): string
    {
        // Create temporary directory
        $tempDir = sys_get_temp_dir() . '/pax_' . uniqid();
        mkdir($tempDir);
        
        // Export metadata
        $metadata = $this->exportXip($objectId, $options);
        file_put_contents("$tempDir/metadata.xml", $metadata);
        
        // Copy digital objects
        $this->copyDigitalObjects($objectId, "$tempDir/content");
        
        // Create ZIP
        $zipPath = "/uploads/exports/preservica/{$objectId}.pax";
        $this->createZip($tempDir, $zipPath);
        
        // Cleanup
        $this->removeDirectory($tempDir);
        
        return $zipPath;
    }
}
```

---

## 9. Sector Definitions

Each sector defines its target fields.

### ArchivesSector.php
```php
class ArchivesSector implements SectorInterface
{
    public function getFields(): array
    {
        return [
            'legacyId' => ['required' => true],
            'parentId' => ['required' => false],
            'title' => ['required' => true],
            'identifier' => ['required' => false],
            'levelOfDescription' => ['required' => true],
            'repository' => ['required' => false],
            'scopeAndContent' => ['required' => false],
            'arrangement' => ['required' => false],
            'extentAndMedium' => ['required' => false],
            'dateRange' => ['required' => false],
            'creators' => ['required' => false, 'multivalue' => true],
            'subjectAccessPoints' => ['required' => false, 'multivalue' => true],
            'placeAccessPoints' => ['required' => false, 'multivalue' => true],
            'nameAccessPoints' => ['required' => false, 'multivalue' => true],
            'genreAccessPoints' => ['required' => false, 'multivalue' => true],
            'digitalObjectPath' => ['required' => false],
            'digitalObjectURI' => ['required' => false],
        ];
    }

    public function getLevelMappings(): array
    {
        return [
            'fonds' => QubitTerm::FONDS_ID,
            'collection' => QubitTerm::COLLECTION_ID,
            'series' => QubitTerm::SERIES_ID,
            'subseries' => QubitTerm::SUBSERIES_ID,
            'file' => QubitTerm::FILE_ID,
            'item' => QubitTerm::ITEM_ID,
        ];
    }
}
```

### MuseumSector.php
```php
class MuseumSector implements SectorInterface
{
    public function getFields(): array
    {
        return [
            // Core fields
            'legacyId' => ['required' => true],
            'title' => ['required' => true],
            'objectNumber' => ['required' => false],
            'accessionNumber' => ['required' => false],
            
            // CCO/CDWA fields
            'objectType' => ['required' => false],
            'materials' => ['required' => false],
            'techniques' => ['required' => false],
            'measurements' => ['required' => false],
            'inscriptions' => ['required' => false],
            'condition' => ['required' => false],
            
            // Spectrum fields
            'acquisitionMethod' => ['required' => false],
            'acquisitionDate' => ['required' => false],
            'currentLocation' => ['required' => false],
            'normalLocation' => ['required' => false],
        ];
    }
}
```

---

## 10. CLI Tasks

### migrationImportTask.class.php
```php
class migrationImportTask extends arBaseTask
{
    protected function configure()
    {
        $this->addArguments([
            new sfCommandArgument('file', sfCommandArgument::REQUIRED, 'File to import'),
        ]);
        
        $this->addOptions([
            new sfCommandOption('mapping', null, sfCommandOption::PARAMETER_REQUIRED, 'Mapping ID or name'),
            new sfCommandOption('repository', null, sfCommandOption::PARAMETER_OPTIONAL, 'Repository ID'),
            new sfCommandOption('culture', null, sfCommandOption::PARAMETER_OPTIONAL, 'Culture code', 'en'),
            new sfCommandOption('update', null, sfCommandOption::PARAMETER_NONE, 'Update existing records'),
            new sfCommandOption('dry-run', null, sfCommandOption::PARAMETER_NONE, 'Preview without importing'),
            new sfCommandOption('list-mappings', null, sfCommandOption::PARAMETER_NONE, 'List available mappings'),
        ]);
        
        $this->namespace = 'migration';
        $this->name = 'import';
        $this->briefDescription = 'Import records using field mappings';
    }

    protected function execute($arguments = [], $options = [])
    {
        if ($options['list-mappings']) {
            return $this->listMappings();
        }
        
        $service = new MigrationService();
        $stats = $service->import(
            $arguments['file'],
            $this->resolveMapping($options['mapping']),
            [
                'repository' => $options['repository'],
                'culture' => $options['culture'],
                'update' => $options['update'],
                'dry_run' => $options['dry-run'],
            ]
        );
        
        $this->logSection('import', sprintf(
            'Complete: %d total, %d created, %d updated, %d errors',
            $stats['total'], $stats['created'], $stats['updated'], $stats['errors']
        ));
    }
}
```

### sectorImportTask.class.php (NEW in 1.4.0)

Abstract base class for sector-specific imports with integrated validation.
```php
abstract class sectorImportTask extends arBaseTask
{
    abstract protected function getSectorCode(): string;
    abstract protected function getColumnMap(): array;
    abstract protected function getRequiredColumns(): array;
    abstract protected function saveSectorMetadata(int $objectId, array $row): void;

    protected function configure()
    {
        $this->addArguments([
            new sfCommandArgument('file', sfCommandArgument::REQUIRED, 'CSV file to import'),
        ]);

        $this->addOptions([
            new sfCommandOption('validate-only', null, sfCommandOption::PARAMETER_NONE,
                'Validate without importing'),
            new sfCommandOption('mapping', null, sfCommandOption::PARAMETER_OPTIONAL,
                'Mapping profile ID'),
            new sfCommandOption('repository', null, sfCommandOption::PARAMETER_OPTIONAL,
                'Target repository slug'),
            new sfCommandOption('update', null, sfCommandOption::PARAMETER_OPTIONAL,
                'Match field for updates'),
        ]);
    }

    protected function execute($arguments = [], $options = [])
    {
        $filepath = $arguments['file'];

        // Parse CSV
        $parser = new CsvParser();
        $rows = $parser->parse($filepath);

        $this->logSection('import', sprintf('Parsed %d rows from %s', count($rows), basename($filepath)));

        // Validate
        $validationService = new ValidationService();
        $validationService->setSector($this->getSectorCode());
        $report = $validationService->validate($filepath, [], $rows);

        // Output validation results
        $this->outputValidationReport($report);

        if ($options['validate-only']) {
            $this->logSection('validate', 'Validation-only mode - no records imported');
            return $report->hasErrors() ? 1 : 0;
        }

        if ($report->hasErrors()) {
            $this->logSection('error', 'Validation failed - fix errors and retry');
            return 1;
        }

        // Process import
        foreach ($rows as $rowNum => $row) {
            try {
                $objectId = $this->processRow($row, $options);
                $this->stats['created']++;
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->log(sprintf('Row %d: %s', $rowNum + 1, $e->getMessage()));
            }
        }

        $this->logSection('import', sprintf(
            'Complete: %d created, %d updated, %d errors',
            $this->stats['created'],
            $this->stats['updated'],
            $this->stats['errors']
        ));
    }
}
```

### Sector-Specific Import Tasks

| Task Class | Command | Sector |
|------------|---------|--------|
| `archivesCsvImportTask` | `php symfony sector:archives-csv-import` | ISAD-G |
| `museumCsvImportTask` | `php symfony sector:museum-csv-import` | Spectrum |
| `libraryCsvImportTask` | `php symfony sector:library-csv-import` | MARC/RDA |
| `galleryCsvImportTask` | `php symfony sector:gallery-csv-import` | CCO |
| `damCsvImportTask` | `php symfony sector:dam-csv-import` | Dublin Core |

**Example: archivesCsvImportTask**
```php
class archivesCsvImportTask extends sectorImportTask
{
    protected function configure()
    {
        parent::configure();
        $this->namespace = 'sector';
        $this->name = 'archives-csv-import';
        $this->briefDescription = 'Import archival records from CSV (ISAD-G)';
    }

    protected function getSectorCode(): string
    {
        return 'archive';
    }

    protected function getColumnMap(): array
    {
        return [
            'legacyId' => 'legacyId',
            'parentId' => 'parentId',
            'identifier' => 'identifier',
            'title' => 'title',
            'levelOfDescription' => 'levelOfDescription',
            'repository' => 'repository',
            'scopeAndContent' => 'scopeAndContent',
            'arrangement' => 'arrangement',
            'extentAndMedium' => 'extentAndMedium',
            'dateRange' => 'dateRange',
            'creators' => 'creators',
            // ... more fields
        ];
    }

    protected function getRequiredColumns(): array
    {
        return ['identifier', 'title', 'levelOfDescription'];
    }

    protected function saveSectorMetadata(int $objectId, array $row): void
    {
        // Archives don't have separate metadata table - all data in information_object
    }
}
```

### preservicaImportTask.class.php
```php
class preservicaImportTask extends arBaseTask
{
    protected function configure()
    {
        $this->addArguments([
            new sfCommandArgument('source', sfCommandArgument::REQUIRED, 'OPEX file or PAX package'),
        ]);

        $this->addOptions([
            new sfCommandOption('format', null, sfCommandOption::PARAMETER_OPTIONAL, 'Format: opex or xip', 'opex'),
            new sfCommandOption('repository', null, sfCommandOption::PARAMETER_OPTIONAL, 'Repository ID'),
            new sfCommandOption('parent', null, sfCommandOption::PARAMETER_OPTIONAL, 'Parent object ID'),
            new sfCommandOption('update', null, sfCommandOption::PARAMETER_NONE, 'Update existing records'),
            new sfCommandOption('dry-run', null, sfCommandOption::PARAMETER_NONE, 'Preview without importing'),
            new sfCommandOption('batch', null, sfCommandOption::PARAMETER_NONE, 'Batch import directory'),
        ]);

        $this->namespace = 'preservica';
        $this->name = 'import';
        $this->briefDescription = 'Import from Preservica OPEX or PAX format';
    }
}
```

---

## 11. Gearman Jobs

For detailed Gearman setup instructions, see: `atom-ahg-plugins/ahgDataMigrationPlugin/docs/GEARMAN.md`

### Quick Setup

```bash
# Automated setup
cd /usr/share/nginx/archive/atom-ahg-plugins/ahgDataMigrationPlugin
sudo ./bin/setup-gearman.sh

# Or manual
sudo apt-get install -y gearman-job-server php8.3-gearman
sudo systemctl enable gearman-job-server atom-worker
sudo systemctl start gearman-job-server atom-worker
```

### DataMigrationJob.class.php

Background job for large imports.
```php
class DataMigrationJob extends arBaseJob
{
    public function run($payload)
    {
        $jobId = $payload['job_id'];
        
        // Update job status
        DB::table('atom_data_migration_job')
            ->where('id', $jobId)
            ->update(['status' => 'running', 'started_at' => now()]);
        
        try {
            $job = DB::table('atom_data_migration_job')->find($jobId);
            $options = json_decode($job->options, true);
            
            $service = new MigrationService();
            $service->setProgressCallback(function($processed, $total) use ($jobId) {
                DB::table('atom_data_migration_job')
                    ->where('id', $jobId)
                    ->update(['processed_records' => $processed]);
            });
            
            $stats = $service->import($job->file_path, $job->mapping_id, $options);
            
            // Update job completion
            DB::table('atom_data_migration_job')
                ->where('id', $jobId)
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'created_records' => $stats['created'],
                    'updated_records' => $stats['updated'],
                    'skipped_records' => $stats['skipped'],
                    'error_count' => $stats['errors'],
                ]);
                
        } catch (\Exception $e) {
            DB::table('atom_data_migration_job')
                ->where('id', $jobId)
                ->update([
                    'status' => 'failed',
                    'error_log' => json_encode(['message' => $e->getMessage()]),
                ]);
        }
    }
}
```

---

## 12. Extending the Plugin

### Adding a New Source System

1. **Update SourceDetector.php:**
```php
protected function detectCsvSource(string $filePath): array
{
    $headers = $this->getCsvHeaders($filePath);
    
    // Add detection for new system
    if (in_array('my_system_field', $headers)) {
        return ['format' => 'csv', 'source' => 'my_system'];
    }
    // ...
}
```

2. **Create default mapping JSON:**
```json
// data/mappings/defaults/my_system.json
{
    "name": "My System Import",
    "source_type": "my_system",
    "target_type": "ARCHIVES",
    "field_mappings": {
        "my_id": "legacyId",
        "my_title": "title",
        "my_description": "scopeAndContent"
    }
}
```

3. **Register mapping in install.sql:**
```sql
INSERT INTO atom_data_mapping (name, source_type, target_type, field_mappings, is_system)
VALUES ('My System Import', 'my_system', 'ARCHIVES', '{"my_id":"legacyId",...}', 1);
```

### Adding a New Parser

1. **Create parser class:**
```php
// lib/Parsers/MyFormatParser.php
class MyFormatParser implements ParserInterface
{
    public function parse(string $filePath): array
    {
        // Parse your format
        return $records;
    }
}
```

2. **Register in ParserFactory:**
```php
return match($format) {
    // ...existing parsers
    'myformat' => new MyFormatParser(),
};
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.4.0 | 2026-02-03 | Universal validation framework, sector-specific validators (Archives/Museum/Library/Gallery/DAM), sector CLI import tasks with --validate-only, validation-only mode, sample CSV files, mapping profile export/import, validation rules JSON |
| 1.3.0 | 2026-02-01 | Batch Export UI, Library/Gallery/DAM default mappings, Gearman setup script and docs |
| 1.2.0 | 2026-01-17 | Preservica OPEX/PAX, rights import, provenance, Gearman jobs |
| 1.1.0 | 2026-01-10 | Sector-specific CSV exporters |
| 1.0.0 | 2025-12-15 | Initial release |

---

## Related Plugins

- **ahgRightsPlugin** - Rights management (used for OPEX rights import)
- **ahgProvenancePlugin** - Provenance tracking (used for OPEX history import)
- **ahgOaisPlugin** - OAIS preservation (native SIP/AIP/DIP)

---

## Support

- **Documentation:** https://github.com/ArchiveHeritageGroup/atom-extensions-catalog/docs/
- **Issues:** https://github.com/ArchiveHeritageGroup/atom-extensions-catalog/issues
- **Contact:** support@theahg.co.za

---

## 13. Digital Object Import

### How It Works

Digital objects are imported from Preservica packages using two methods:

#### Method 1: Native AtoM (Default) - `generate_derivatives: true`

Uses `QubitDigitalObject` class which automatically:
- Creates master file record
- Generates thumbnail (150px)
- Generates reference image (480px)
- Applies watermarks if configured
```php
$digitalObject = new \QubitDigitalObject();
$digitalObject->informationObjectId = $objectId;
$digitalObject->usageId = \QubitTerm::MASTER_ID;
$digitalObject->createDerivatives = true;
$digitalObject->assets[] = new \QubitAsset($filePath);
$digitalObject->save();
```

#### Method 2: Direct DB Insert - `generate_derivatives: false`

Faster for large batch imports but skips derivative generation:
- Copies master file to uploads
- Creates `digital_object` record directly
- Optional: Queue derivative generation via Gearman

### CLI Options

| Option | Description |
|--------|-------------|
| `--no-digital-objects` | Skip digital object import entirely |
| `--no-derivatives` | Import masters but skip thumbnail/reference generation |
| `--queue-derivatives` | Queue derivative generation as background job |
| `--no-checksums` | Skip SHA256 checksum verification |

### File Resolution

The importer looks for digital objects in this order:
1. `{basePath}/{filename}` - Direct path
2. `{basePath}/content/{filename}` - PAX content directory

### Checksum Verification

When `verify_checksums: true` (default):
- Extracts expected checksum from `Fixity` or `Checksum` field
- Computes SHA256 of actual file
- Fails import if mismatch

### Upload Path Structure

Files are copied to AtoM's standard structure:
```
/uploads/r/{XX}/{digitalObjectId}_{filename}
```
Where `{XX}` is first 2 characters of MD5 hash of the ID.

### Performance Recommendations

| Scenario | Recommended Options |
|----------|---------------------|
| Small import (<100 files) | Default (generate_derivatives: true) |
| Large import (100-1000) | `--no-derivatives --queue-derivatives` |
| Very large (>1000) | `--no-derivatives` then run `digitalobject:regen-derivatives` |

### Supported File Types

AtoM generates derivatives for:
- Images: JPG, PNG, GIF, TIFF, BMP
- Documents: PDF (first page thumbnail)
- Audio: MP3, WAV, OGG (waveform)
- Video: MP4, AVI, MOV (frame grab)

3D models use ahg3DModelPlugin for Blender-based thumbnails.
