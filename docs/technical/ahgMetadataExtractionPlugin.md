# ahgMetadataExtractionPlugin - Technical Documentation

**Version:** 1.1.0
**Category:** Preservation
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

Universal metadata extraction plugin that automatically extracts embedded metadata from uploaded digital objects using multiple extraction methods (native PHP, ExifTool, FFprobe). Supports images, PDFs, Office documents, video, and audio files with automatic field mapping to AtoM descriptive fields.

---

## Architecture

```
+---------------------------------------------------------------------+
|                    ahgMetadataExtractionPlugin                       |
+---------------------------------------------------------------------+
|                                                                     |
|  +---------------------------------------------------------------+  |
|  |                   Event Dispatcher                             |  |
|  |  - digital_object.post_create                                  |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |              MetadataExtractionHandler                         |  |
|  |  - Coordinates extraction process                              |  |
|  |  - Checks enabled settings                                     |  |
|  |  - Applies metadata to information object                      |  |
|  +---------------------------------------------------------------+  |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |              ahgUniversalMetadataExtractor                     |  |
|  |  - Detects file type by MIME/extension                         |  |
|  |  - Routes to appropriate extraction method                     |  |
|  |  - Consolidates metadata from multiple sources                 |  |
|  +---------------------------------------------------------------+  |
|           |              |              |              |             |
|           v              v              v              v             |
|  +------------+  +------------+  +------------+  +------------+     |
|  |   Image    |  |    PDF     |  |   Office   |  | Video/Audio|     |
|  | Extraction |  | Extraction |  | Extraction |  | Extraction |     |
|  +------------+  +------------+  +------------+  +------------+     |
|  | - EXIF     |  | - Info Dict|  | - core.xml |  | - FFprobe  |     |
|  | - IPTC     |  | - XMP      |  | - app.xml  |  | - getID3   |     |
|  | - XMP      |  | - Smalot   |  | - custom   |  | - ID3 tags |     |
|  +------------+  +------------+  +------------+  +------------+     |
|                              |                                       |
|                              v                                       |
|  +---------------------------------------------------------------+  |
|  |                  MetadataRepository                            |  |
|  |  - Stores metadata in property table                           |  |
|  |  - Uses Laravel Query Builder                                  |  |
|  +---------------------------------------------------------------+  |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Database Schema

### Settings Storage

The plugin uses the standard AtoM settings table with scope `metadata_extraction`:

```
+-------------------------------------+
|             setting                 |
+-------------------------------------+
| id           | INT (PK)            |
| name         | VARCHAR(255)         |
| scope        | 'metadata_extraction'|
+-------------------------------------+
         |
         v
+-------------------------------------+
|           setting_i18n              |
+-------------------------------------+
| id           | INT (FK)            |
| culture      | VARCHAR(7)          |
| value        | TEXT                |
+-------------------------------------+
```

### Property Storage

Extracted metadata is stored as QubitProperty objects:

```
+-------------------------------------+
|            property                 |
+-------------------------------------+
| id           | INT (PK)            |
| object_id    | INT (FK)            | --> digital_object.id
| name         | VARCHAR(255)         |
| value        | TEXT                |
| scope        | 'metadata_extraction'|
| source_culture | VARCHAR(7)        |
| created_at   | TIMESTAMP           |
| updated_at   | TIMESTAMP           |
+-------------------------------------+
         |
         v
+-------------------------------------+
|          property_i18n              |
+-------------------------------------+
| id           | INT (FK)            |
| culture      | VARCHAR(7)          |
+-------------------------------------+
```

---

## File Type Detection

### MIME Type Categories

```php
protected static $mimeCategories = [
    // Images
    'image/jpeg' => self::TYPE_IMAGE,
    'image/png' => self::TYPE_IMAGE,
    'image/tiff' => self::TYPE_IMAGE,
    'image/webp' => self::TYPE_IMAGE,
    'image/gif' => self::TYPE_IMAGE,
    'image/bmp' => self::TYPE_IMAGE,

    // PDFs
    'application/pdf' => self::TYPE_PDF,

    // Office Documents
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => self::TYPE_OFFICE,
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => self::TYPE_OFFICE,
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => self::TYPE_OFFICE,
    'application/msword' => self::TYPE_OFFICE,
    'application/vnd.ms-excel' => self::TYPE_OFFICE,
    'application/vnd.ms-powerpoint' => self::TYPE_OFFICE,

    // Video
    'video/mp4' => self::TYPE_VIDEO,
    'video/webm' => self::TYPE_VIDEO,
    'video/ogg' => self::TYPE_VIDEO,
    'video/quicktime' => self::TYPE_VIDEO,
    'video/x-msvideo' => self::TYPE_VIDEO,
    'video/x-matroska' => self::TYPE_VIDEO,

    // Audio
    'audio/mpeg' => self::TYPE_AUDIO,
    'audio/mp3' => self::TYPE_AUDIO,
    'audio/wav' => self::TYPE_AUDIO,
    'audio/ogg' => self::TYPE_AUDIO,
    'audio/flac' => self::TYPE_AUDIO,
    'audio/aac' => self::TYPE_AUDIO,
    'audio/x-m4a' => self::TYPE_AUDIO,
];
```

### Extension Fallback

```php
protected static $extensionCategories = [
    'jpg' => self::TYPE_IMAGE,
    'jpeg' => self::TYPE_IMAGE,
    'png' => self::TYPE_IMAGE,
    'tif' => self::TYPE_IMAGE,
    'tiff' => self::TYPE_IMAGE,
    'pdf' => self::TYPE_PDF,
    'docx' => self::TYPE_OFFICE,
    'xlsx' => self::TYPE_OFFICE,
    'pptx' => self::TYPE_OFFICE,
    'mp4' => self::TYPE_VIDEO,
    'mp3' => self::TYPE_AUDIO,
    // ... additional mappings
];
```

---

## Extraction Methods

### Image Metadata (EXIF/IPTC/XMP)

#### EXIF Extraction

```php
protected function extractExif()
{
    // Requires PHP EXIF extension
    if (!function_exists('exif_read_data')) {
        return null;
    }

    // Supports JPEG and TIFF
    $supportedTypes = [IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM];
    $imageType = @exif_imagetype($this->filePath);

    if (!in_array($imageType, $supportedTypes)) {
        return null;
    }

    $exif = @exif_read_data($this->filePath, 'ANY_TAG', true);
    // Flatten and clean binary data
    return $this->flattenExifData($exif);
}
```

#### IPTC Extraction

IPTC field mappings:

| IPTC Code | Field Name |
|-----------|------------|
| 2#005 | object_name |
| 2#025 | keywords |
| 2#055 | date_created |
| 2#080 | byline |
| 2#090 | city |
| 2#095 | province_state |
| 2#101 | country |
| 2#105 | headline |
| 2#110 | credit |
| 2#116 | copyright |
| 2#120 | caption |

#### XMP Extraction

```php
protected function extractXmp()
{
    $content = @file_get_contents($this->filePath);

    // Find XMP packet
    $start = strpos($content, '<x:xmpmeta');
    if ($start === false) {
        $start = strpos($content, '<?xpacket begin');
    }

    // Parse Dublin Core, Photoshop, XMP Basic namespaces
    return $this->parseXmpXml($xmpData);
}
```

Supported XMP namespaces:
- Dublin Core (dc:title, dc:description, dc:creator, dc:subject, dc:rights)
- Photoshop (photoshop:City, photoshop:State, photoshop:Country)
- XMP Basic (xmp:CreateDate, xmp:ModifyDate, xmp:CreatorTool)
- EXIF (exif:DateTimeOriginal)

#### GPS Coordinate Extraction

```php
protected function extractGpsCoordinates($exif)
{
    $lat = $this->gpsToDecimal(
        $exif['GPSLatitude'],
        $exif['GPSLatitudeRef'] ?? 'N'
    );

    $lon = $this->gpsToDecimal(
        $exif['GPSLongitude'],
        $exif['GPSLongitudeRef'] ?? 'E'
    );

    return [
        'latitude' => $lat,
        'longitude' => $lon,
        'decimal' => sprintf('%.6f, %.6f', $lat, $lon),
        'altitude' => $this->parseAltitude($exif),
    ];
}
```

#### Metadata Consolidation Priority

```
Title:       XMP > IPTC (object_name/headline) > EXIF (ImageDescription)
Description: XMP > IPTC (caption)
Creator:     XMP + IPTC (byline) + EXIF (Artist)
Keywords:    XMP + IPTC
Copyright:   XMP (rights) > IPTC > EXIF
Date:        EXIF (DateTimeOriginal) > XMP > IPTC
```

---

### PDF Metadata Extraction

#### Extraction Methods (Priority Order)

1. **Smalot PDF Parser** (if available)
```php
if (class_exists('\\Smalot\\PdfParser\\Parser')) {
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($this->filePath);
    $details = $pdf->getDetails();
    // Returns: Title, Author, Subject, Keywords, Creator, Producer, dates
}
```

2. **Manual Extraction** (fallback)
```php
// Extract from PDF Info dictionary
$patterns = [
    'title' => '/\/Title\s*\(([^)]+)\)/',
    'author' => '/\/Author\s*\(([^)]+)\)/',
    'subject' => '/\/Subject\s*\(([^)]+)\)/',
    'keywords' => '/\/Keywords\s*\(([^)]+)\)/',
    'creator' => '/\/Creator\s*\(([^)]+)\)/',
    'producer' => '/\/Producer\s*\(([^)]+)\)/',
    'creation_date' => '/\/CreationDate\s*\(([^)]+)\)/',
];
```

3. **XMP in PDF** (if present)
```php
// Check for embedded XMP
if (preg_match('/<x:xmpmeta.*?<\/x:xmpmeta>/s', $content, $matches)) {
    $xmp = $this->parseXmpXml($matches[0]);
}
```

---

### Office Document Extraction (Open XML)

#### Core Properties (docProps/core.xml)

```xml
<cp:coreProperties>
    <dc:title>Document Title</dc:title>
    <dc:creator>Author Name</dc:creator>
    <dc:subject>Subject</dc:subject>
    <dc:description>Description</dc:description>
    <cp:category>Category</cp:category>
    <cp:keywords>keyword1, keyword2</cp:keywords>
    <cp:lastModifiedBy>Editor</cp:lastModifiedBy>
    <dcterms:created>2025-01-15T10:00:00Z</dcterms:created>
    <dcterms:modified>2025-01-20T14:30:00Z</dcterms:modified>
</cp:coreProperties>
```

#### Application Properties (docProps/app.xml)

| Property | Description |
|----------|-------------|
| Application | Creating application name |
| AppVersion | Application version |
| Company | Organization name |
| Manager | Manager name |
| TotalTime | Total editing time (minutes) |
| Pages | Page count (DOCX) |
| Words | Word count |
| Characters | Character count |
| Slides | Slide count (PPTX) |

#### Custom Properties (docProps/custom.xml)

Custom metadata fields defined by users are extracted and stored with their original names.

---

### Video/Audio Metadata Extraction

#### FFprobe Extraction (Primary)

```bash
ffprobe -v quiet -print_format json -show_format -show_streams <file>
```

Extracted fields:

| Category | Fields |
|----------|--------|
| Format | format_name, duration, bit_rate, size |
| Tags | title, artist, album, date, comment, encoder |
| Video Stream | codec_name, width, height, r_frame_rate, pix_fmt, display_aspect_ratio |
| Audio Stream | codec_name, channels, sample_rate, bit_rate |

#### getID3 Fallback

```php
if (class_exists('getID3')) {
    $getId3 = new getID3();
    $info = $getId3->analyze($this->filePath);
    // Returns: playtime_seconds, video info, audio info, tags
}
```

#### ID3 Tag Extraction (Audio)

```php
// ID3v2 Frame Mappings
$frameMap = [
    'TIT2' => 'title',
    'TPE1' => 'artist',
    'TALB' => 'album',
    'TYER' => 'year',
    'TDRC' => 'year',
    'TRCK' => 'track',
    'TCON' => 'genre',
    'COMM' => 'comment',
    'TCOM' => 'composer',
    'TPUB' => 'publisher',
    'TCOP' => 'copyright',
];
```

---

## AtoM Field Mapping

### MetadataExtractionHandler Mapping

```php
// Field Mapping
protected function applyToInformationObject(array $metadata, int $informationObjectId): void
{
    // Title (only if empty and not set to overwrite)
    if (!empty($metadata['title']) && ($overwriteTitle || empty($currentTitle))) {
        $this->setI18nField($informationObjectId, 'title', $metadata['title']);
    }

    // Description -> scope_and_content
    if (!empty($metadata['description'])) {
        $this->setI18nField($informationObjectId, 'scope_and_content', $metadata['description']);
    }

    // Creator -> Name Access Point (Creation Event)
    if (!empty($metadata['creator'])) {
        $this->addCreator($metadata['creator'], $informationObjectId);
    }

    // Date -> Event (type_id = 111 / TERM_CREATION)
    if (!empty($metadata['date_created'])) {
        $this->addCreationDate($metadata['date_created'], $informationObjectId);
    }

    // Keywords -> Subject Access Points (taxonomy_id = 35)
    if (!empty($metadata['keywords'])) {
        $this->addSubjectAccessPoints($metadata['keywords'], $informationObjectId);
    }

    // GPS -> scope_and_content (appended)
    if (!empty($metadata['gps'])) {
        $this->setGpsCoordinates($metadata['gps'], $informationObjectId);
    }

    // Technical metadata -> physical_characteristics
    $this->appendTechnicalMetadata($metadata, $informationObjectId);
}
```

### Term/Taxonomy IDs

```php
const TAXONOMY_SUBJECT = 35;      // Subject taxonomy
const TERM_CREATION = 111;        // Creation event type
const TERM_NAME_ACCESS_POINT = 177; // Name access point relation
const ROOT_ACTOR_ID = 3;          // Actor hierarchy root
const ROOT_TERM_SUBJECT_ID = 110; // Subject term hierarchy root
```

---

## Service Classes

### MetadataExtractionService

```php
namespace AtomExtensions\Extensions\MetadataExtraction\Services;

class MetadataExtractionService
{
    private array $extractors = [];

    public function registerExtractor(MetadataExtractorInterface $extractor): void;
    public function extractFromDigitalObject(int $digitalObjectId, bool $save = true): array;
    public function getMetadata(int $digitalObjectId): array;
    public function deleteMetadata(int $digitalObjectId): void;

    private function findExtractor(string $mimeType): ?MetadataExtractorInterface;
    private function saveMetadata(int $digitalObjectId, array $metadata): void;
}
```

### MetadataExtractorInterface

```php
namespace AtomExtensions\Extensions\MetadataExtraction\Contracts;

interface MetadataExtractorInterface
{
    public function extract(string $filePath): array;
    public function supports(string $mimeType): bool;
    public function getName(): string;
}
```

### ExifToolExtractor

```php
namespace AtomExtensions\Extensions\MetadataExtraction\Services\Extractors;

class ExifToolExtractor implements MetadataExtractorInterface
{
    public function extract(string $filePath): array
    {
        $command = sprintf(
            '%s -json -a -G1 %s 2>&1',
            escapeshellcmd($this->exifToolPath),
            escapeshellarg($filePath)
        );

        exec($command, $output, $returnCode);
        return json_decode(implode("\n", $output), true)[0] ?? [];
    }

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/jpeg', 'image/png', 'image/tiff', 'image/gif',
            'application/pdf', 'video/mp4', 'audio/mpeg'
        ]);
    }
}
```

---

## Configuration Settings

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| metadata_extraction_enabled | bool | true | Master enable/disable |
| extract_exif | bool | true | Extract EXIF data |
| extract_iptc | bool | true | Extract IPTC data |
| extract_xmp | bool | true | Extract XMP data |
| overwrite_title | bool | false | Overwrite existing titles |
| overwrite_description | bool | false | Overwrite existing descriptions |
| auto_generate_keywords | bool | true | Create subject access points |
| extract_gps_coordinates | bool | true | Extract GPS data |
| add_technical_metadata | bool | true | Add technical summary |
| technical_metadata_target_field | string | physical_characteristics | Target field for technical data |

### Settings Controller

```php
namespace AtomExtensions\Extensions\MetadataExtraction\Controllers;

class SettingsController
{
    protected const SCOPE = 'metadata_extraction';

    public function handleSettings($request, $action): void;
    protected function addFormFields(\sfForm $form): void;
    protected function loadFormDefaults(\sfForm $form): void;
    protected function saveSettings(\sfForm $form): void;
    protected function getSetting(string $name): ?string;
    protected function saveSetting(string $name, string $value): void;
}
```

---

## Repository Methods

### MetadataRepository

```php
namespace AtomExtensions\Extensions\MetadataExtraction\Repositories;

class MetadataRepository
{
    public function getDigitalObject(int $id): ?object;
    public function saveMetadata(int $objectId, string $name, string $value, string $scope = 'digital_object'): void;
    public function getMetadata(int $objectId, ?string $name = null): array;
    public function deleteMetadata(int $objectId, ?string $name = null): void;
}
```

---

## Helper Classes

### ahgMetadataExtractionHelper

Static helper for use outside action classes:

```php
class ahgMetadataExtractionHelper
{
    public static function extractAndApply($filePath, $informationObject, $digitalObject = null);
    public static function extract($filePath);
    public static function getSummary($filePath);
}
```

### arMetadataExtractionTrait

Trait for use in action classes:

```php
trait arMetadataExtractionTrait
{
    protected function extractAllMetadata($filePath);
    protected function applyMetadataToInformationObject($informationObject, $metadata, $digitalObject = null);
    protected function addCreationDateLaravel(int $informationObjectId, string $dateString): void;
    protected function addCreatorAccessPointLaravel(int $informationObjectId, $creatorName): void;
    protected function addSubjectAccessPointsLaravel(int $informationObjectId, $keywords): void;
    protected function addPhysicalCharacteristicsLaravel(int $informationObjectId, string $summary, string $fileType): void;
    protected function addGpsDataLaravel(int $informationObjectId, array $gpsData, ?int $digitalObjectId = null): void;
    protected function addCopyrightNoteLaravel(int $informationObjectId, string $copyright): void;

    // Legacy compatibility
    protected function extractExifMetadata($filePath);
    protected function applyExifToInformationObject($metadata, $informationObject, $digitalObject = null);
}
```

---

## Event Integration

### Plugin Configuration

```php
class ahgMetadataExtractionPluginConfiguration extends sfPluginConfiguration
{
    public function initialize()
    {
        // Register for digital object creation events
        $this->dispatcher->connect(
            'digital_object.post_create',
            [$this, 'onDigitalObjectCreate']
        );
    }

    public function onDigitalObjectCreate(sfEvent $event)
    {
        // Trigger automatic extraction
    }

    public static function isExifToolAvailable(): bool
    {
        exec('which exiftool 2>/dev/null', $output, $returnCode);
        return $returnCode === 0 && !empty($output);
    }
}
```

---

## System Requirements

### Required

- PHP 8.1+
- PHP EXIF extension (`php-exif`)
- PHP ZIP extension (`php-zip`) for Office documents

### Optional (Recommended)

- **ExifTool** - Enhanced metadata extraction
  ```bash
  sudo apt install libimage-exiftool-perl
  ```

- **FFprobe** - Video/audio metadata
  ```bash
  sudo apt install ffmpeg
  ```

- **Smalot PDF Parser** - Enhanced PDF extraction
  ```bash
  composer require smalot/pdfparser
  ```

- **getID3** - Audio metadata fallback
  ```bash
  composer require james-heinrich/getid3
  ```

### Checking Availability

```php
// ExifTool
ahgMetadataExtractionPluginConfiguration::isExifToolAvailable();

// FFprobe
$ffprobe = trim(shell_exec('which ffprobe 2>/dev/null'));

// PHP EXIF
function_exists('exif_read_data');

// Smalot PDF Parser
class_exists('\\Smalot\\PdfParser\\Parser');

// getID3
class_exists('getID3');
```

---

## Date Normalization

### Supported Formats

```php
protected function normalizeDateString($dateString)
{
    // EXIF: 2021:02:05 10:30:45 -> 2021-02-05
    // ISO: 2021-02-05T10:30:45 -> 2021-02-05
    // PDF: D:20210205103045 -> 2021-02-05
    // Year only: 2021 -> 2021
    // Fallback: strtotime() parsing
}
```

---

## Output Formats

### Key Fields Structure

```php
public function getKeyFields()
{
    return [
        'title' => null,        // String or null
        'creator' => null,      // String or null
        'date' => null,         // String (normalized date)
        'description' => null,  // String or null
        'keywords' => [],       // Array of strings
        'copyright' => null,    // String or null
    ];
}
```

### Full Metadata Structure

```php
$metadata = [
    'file' => [
        'path' => '/path/to/file',
        'name' => 'filename.jpg',
        'size' => 1234567,
        'mime_type' => 'image/jpeg',
        'type_category' => 'image',
        'extension' => 'jpg',
        'modified' => '2025-01-15 10:30:00',
    ],
    'image' => [
        'width' => 4032,
        'height' => 3024,
        'type' => 2,
        'bits' => 8,
        'channels' => 3,
    ],
    'exif' => [ /* EXIF data */ ],
    'iptc' => [ /* IPTC data */ ],
    'xmp' => [ /* XMP data */ ],
    'gps' => [
        'latitude' => -33.918861,
        'longitude' => 18.423300,
        'decimal' => '-33.918861, 18.423300',
        'altitude' => 42.0,
    ],
    'consolidated' => [
        'title' => 'Extracted title',
        'description' => 'Extracted description',
        'creators' => ['Creator Name'],
        'keywords' => ['keyword1', 'keyword2'],
        'copyright' => 'Copyright notice',
        'date_created' => '2025-01-15',
        'location' => [
            'city' => 'Cape Town',
            'state' => 'Western Cape',
            'country' => 'South Africa',
        ],
        'camera' => [
            'make' => 'Canon',
            'model' => 'EOS 5D Mark IV',
            'software' => 'Adobe Photoshop',
        ],
        'technical' => [
            'exposure_time' => '1/250',
            'f_number' => 'f/8',
            'iso' => 400,
            'focal_length' => '50mm',
        ],
    ],
];
```

---

## Logging

### Log Location

```
/var/log/atom/metadata-extraction.log
```

### Logger Configuration

```php
$logger = new Logger('metadata-extraction');
$logger->pushHandler(
    new StreamHandler(
        sfConfig::get('sf_log_dir', '/var/log/atom') . '/metadata-extraction.log',
        Logger::INFO
    )
);
```

---

## Error Handling

### Error Collection

```php
$extractor = new ahgUniversalMetadataExtractor($filePath);
$metadata = $extractor->extractAll();
$errors = $extractor->getErrors();

// Example errors:
// - 'File not found: /path/to/file'
// - 'EXIF extension not available'
// - 'PDF Parser error: Unable to parse'
// - 'getID3 error: Cannot read file'
```

---

## Performance Considerations

1. **Large files**: Video/audio extraction can be slow for large files
2. **PDF parsing**: Smalot parser loads entire file into memory
3. **Batch processing**: Consider queueing for bulk uploads
4. **FFprobe timeout**: Long videos may exceed default timeout

---

## Web Interface Module

### Routes

| Route | URL | Action | Description |
|-------|-----|--------|-------------|
| metadataExtraction_index | `/metadataExtraction` | index | List digital objects with extraction status |
| metadataExtraction_view | `/metadataExtraction/view/:id` | view | View extracted metadata for object |
| metadataExtraction_extract | `/metadataExtraction/extract` | extract | Trigger extraction (AJAX) |
| metadataExtraction_batchExtract | `/metadataExtraction/batchExtract` | batchExtract | Batch extract multiple objects |
| metadataExtraction_delete | `/metadataExtraction/delete` | delete | Delete metadata (AJAX) |
| metadataExtraction_status | `/metadataExtraction/status` | status | System status and statistics |

### Module Structure

```
modules/metadataExtraction/
├── actions/
│   └── actions.class.php     # 6 actions (index, view, extract, batchExtract, delete, status)
└── templates/
    ├── indexSuccess.php       # List digital objects with filtering
    ├── viewSuccess.php        # View/extract metadata for single object
    └── statusSuccess.php      # ExifTool status and statistics
```

### Actions

#### executeIndex
- Lists digital objects with their extraction status
- Filters by MIME type and extraction status
- Pagination support (25 per page)
- Shows metadata field count per object

#### executeView
- Displays all extracted metadata for a digital object
- Groups metadata by category (EXIF, IPTC, XMP, etc.)
- Accordion-style display
- Links to parent information object

#### executeExtract
- AJAX endpoint for triggering extraction
- Requires authentication and update permission
- Uses ExifTool if available
- Returns JSON with success/error status

#### executeBatchExtract
- Processes up to 50 unextracted digital objects
- Shows progress and remaining count
- Skips missing files with error logging

#### executeDelete
- AJAX endpoint for removing metadata
- Cleans up property, object, and i18n records

#### executeStatus
- ExifTool availability and version
- Extraction statistics (total objects, extracted count)
- MIME type breakdown with support status

### UI Features

**Index Page:**
- Filter by MIME type (dropdown)
- Filter by extraction status (extracted/not extracted)
- Direct extract button per row
- Batch extract button in header

**View Page:**
- Digital object details table
- Grouped metadata display (accordion)
- Extract and Delete action buttons
- JSON array values displayed as lists

**Status Page:**
- ExifTool installation status with version
- Statistics cards (total objects, with metadata, field count)
- MIME type breakdown with support indicators
- Installation instructions if ExifTool missing

---

## Changelog

### v1.1.0 (February 2026)
- Added complete web interface module with 6 actions
- Index page with filtering and pagination
- View page with grouped metadata display
- Batch extraction for unprocessed objects
- Status page with system diagnostics
- AJAX extraction and deletion endpoints
- Bootstrap 5 compatible templates

### v1.0.0 (Initial)
- Service layer implementation
- ExifTool extractor
- Metadata repository
- Event-driven extraction on upload

---

*Part of the AtoM AHG Framework*
