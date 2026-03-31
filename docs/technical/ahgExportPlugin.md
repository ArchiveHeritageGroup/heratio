# ahgExportPlugin - Technical Documentation

**Version:** 1.1.0
**Category:** Export
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

Comprehensive data export functionality for archival descriptions, authority records, and repositories. Supports multiple export formats including CSV (ISAD-G compliant), EAD 2002, EAD3, Dublin Core, and MODS. Uses Laravel Illuminate Database for all queries.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     ahgExportPlugin                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    Web Interface                        │   │
│  │  • /export           - Export dashboard                 │   │
│  │  • /export/archival  - Archival descriptions export     │   │
│  │  • /export/authority - Authority records export         │   │
│  │  • /export/repository - Repository export               │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           │                                     │
│                           ▼                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                 exportActions                           │   │
│  │  • Authentication check (preExecute)                    │   │
│  │  • Format selection                                     │   │
│  │  • Export options (descendants, digital objects)        │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           │                                     │
│                           ▼                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              ahgArchivalExportService                   │   │
│  │  • Data retrieval (Laravel Query Builder)               │   │
│  │  • Format conversion (CSV, EAD, DC, MODS)               │   │
│  │  • Hierarchy traversal                                  │   │
│  │  • Package creation (ZIP)                               │   │
│  └─────────────────────────────────────────────────────────┘   │
│                           │                                     │
│                           ▼                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              Output Formats                             │   │
│  │  • CSV (ISAD-G)  • EAD 2002  • EAD3                    │   │
│  │  • Dublin Core   • MODS      • ZIP Package             │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Routes

| Route | Action | Description |
|-------|--------|-------------|
| `/export` | index | Export dashboard with format selection |
| `/export/archival` | archival | Archival descriptions export form |
| `/export/authority` | authority | Authority records export form |
| `/export/repository` | repository | Repository records export form |
| `/export/csv` | archival | CSV export (alias) |
| `/export/ead` | archival | EAD export (alias) |
| `/export/grap` | archival | GRAP export (alias) |
| `/export/authorities` | archival | Authorities export (alias) |

---

## Export Formats

### CSV (ISAD-G)

Comma-separated values following ISAD(G) archival standard. 52 columns covering all ISAD-G elements.

| Column Group | Fields |
|--------------|--------|
| **Identity** | legacyId, parentId, qubitParentSlug, accessionNumber, identifier |
| **Title/Level** | title, levelOfDescription |
| **Context** | repository, archivalHistory, acquisition |
| **Content** | extentAndMedium, scopeAndContent, appraisal, accruals, arrangement |
| **Access** | accessConditions, reproductionConditions, language, script, languageNote |
| **Allied Materials** | physicalCharacteristics, findingAids, locationOfOriginals, locationOfCopies, relatedUnitsOfDescription, publicationNote |
| **Digital Objects** | digitalObjectPath, digitalObjectURI |
| **Access Points** | subjectAccessPoints, placeAccessPoints, nameAccessPoints, genreAccessPoints |
| **Description Control** | descriptionIdentifier, institutionIdentifier, rules, descriptionStatus, levelOfDetail, revisionHistory |
| **Notes** | generalNote, archivistNote, sources |
| **Physical Objects** | physicalObjectName, physicalObjectLocation, physicalObjectType |
| **Events** | eventDates, eventTypes, eventStartDates, eventEndDates, eventActors, eventActorHistories |
| **Other** | alternativeIdentifiers, alternativeIdentifierLabels, publicationStatus, culture |

### EAD 2002

Encoded Archival Description XML format (urn:isbn:1-931666-22-9).

**Structure:**
```xml
<ead xmlns="urn:isbn:1-931666-22-9">
  <eadheader>
    <eadid>identifier</eadid>
    <filedesc>
      <titlestmt>
        <titleproper>Title</titleproper>
      </titlestmt>
    </filedesc>
  </eadheader>
  <archdesc level="collection">
    <did>...</did>
    <scopecontent>...</scopecontent>
    <dsc>
      <c level="series">...</c>
    </dsc>
  </archdesc>
</ead>
```

**EAD Elements Mapped:**
- `<unitid>` - identifier
- `<unittitle>` - title
- `<unitdate>` - event dates
- `<physdesc><extent>` - extent and medium
- `<repository><corpname>` - repository name
- `<origination>` - creator(s)
- `<scopecontent>` - scope and content
- `<custodhist>` - archival history
- `<acqinfo>` - acquisition
- `<accessrestrict>` - access conditions
- `<userestrict>` - reproduction conditions
- `<arrangement>` - arrangement
- `<controlaccess>` - subject/place/name access points

### EAD3

Modern EAD format (https://archivists.org/ns/ead/v3).

Uses `<control>` instead of `<eadheader>` and `<recordid>` instead of `<eadid>`.

### Dublin Core

Simple Dublin Core XML (http://purl.org/dc/elements/1.1/).

**Elements:**
- `dc:title` - title
- `dc:creator` - creator(s) from events
- `dc:date` - date from events
- `dc:description` - scope and content
- `dc:subject` - subject access points
- `dc:identifier` - identifier
- `dc:publisher` - repository
- `dc:type` - level of description

### MODS

Metadata Object Description Schema (http://www.loc.gov/mods/v3).

**Elements:**
- `<titleInfo><title>` - title
- `<name type="personal"><namePart>` - creator(s)
- `<originInfo><dateCreated>` - date
- `<abstract>` - scope and content
- `<subject><topic>` - subject access points
- `<identifier type="local">` - identifier

---

## Service Methods

### ahgArchivalExportService

```php
class ahgArchivalExportService
{
    // Format constants
    const FORMAT_CSV = 'csv';
    const FORMAT_EAD = 'ead';
    const FORMAT_EAD3 = 'ead3';
    const FORMAT_DC = 'dc';
    const FORMAT_MODS = 'mods';

    // Configuration setters (fluent interface)
    public function setFormat($format): self
    public function setIncludeDescendants($include): self
    public function setIncludeDigitalObjects($include): self
    public function setCreatePackage($create): self
    public function setRepositoryId($id): self
    public function setCulture($culture): self

    // Export methods
    public function export($objectId): string
    public function exportBySlug($slug): string|null
    public function exportRepository($repositoryId = null): string

    // Package creation
    public function createExportPackage($objectId, $outputPath): string|false

    // Statistics
    public function getExportStats($objectId = null): array
}
```

### Usage Examples

**Export single record as CSV:**
```php
$service = new ahgArchivalExportService();
$csv = $service
    ->setFormat(ahgArchivalExportService::FORMAT_CSV)
    ->setIncludeDescendants(true)
    ->setCulture('en')
    ->export($objectId);
```

**Export by slug as EAD:**
```php
$service = new ahgArchivalExportService();
$ead = $service
    ->setFormat(ahgArchivalExportService::FORMAT_EAD)
    ->exportBySlug('collection-slug');
```

**Export entire repository:**
```php
$service = new ahgArchivalExportService();
$csv = $service
    ->setFormat(ahgArchivalExportService::FORMAT_CSV)
    ->setRepositoryId($repoId)
    ->exportRepository();
```

**Create ZIP package:**
```php
$service = new ahgArchivalExportService();
$zipPath = $service
    ->setIncludeDigitalObjects(true)
    ->createExportPackage($objectId, '/tmp');
// Returns: /tmp/export_2026-01-30_123456.zip
```

**Get export statistics:**
```php
$service = new ahgArchivalExportService();
$stats = $service
    ->setRepositoryId($repoId)
    ->getExportStats($objectId);
// Returns: ['totalRecords' => 150, 'totalDigitalObjects' => 45]
```

---

## Controller Actions

### exportActions

| Action | Parameters | Description |
|--------|------------|-------------|
| `executeIndex` | - | Display export dashboard |
| `executeCsv` | - | CSV export form |
| `executeEad` | - | EAD export form |
| `executeArchival` | format (default: ead) | Archival descriptions export |
| `executeAuthority` | format (default: eac) | Authority records export |
| `executeRepository` | format (default: csv) | Repository records export |

**Authentication:** All actions require user authentication (checked in `preExecute`).

---

## Data Flow

### Export Process

```
┌─────────────────────────────────────────────────────────────────┐
│                     EXPORT FLOW                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. User Request                                                │
│     └─> exportActions validates authentication                  │
│                                                                 │
│  2. Service Configuration                                       │
│     └─> setFormat(), setIncludeDescendants(), etc.             │
│                                                                 │
│  3. Data Retrieval                                              │
│     ├─> getObjectBySlug() or by ID                             │
│     ├─> getExportObjects() - main object + descendants         │
│     └─> getFullObjectData() for each object:                   │
│         ├─> information_object (main data)                     │
│         ├─> information_object_i18n (translated fields)        │
│         ├─> slug (URL slug)                                    │
│         ├─> term_i18n (level of description)                   │
│         ├─> actor_i18n (repository name)                       │
│         ├─> event + event_i18n (dates, creators)               │
│         ├─> object_term_relation (access points)               │
│         ├─> relation (name access points)                      │
│         ├─> digital_object (digital files)                     │
│         ├─> physical_object (containers)                       │
│         ├─> note + note_i18n (notes)                           │
│         └─> property (alternative identifiers)                 │
│                                                                 │
│  4. Format Conversion                                           │
│     ├─> toCSV() - PHP fputcsv                                  │
│     ├─> toEAD() - DOMDocument XML                              │
│     ├─> toDublinCore() - DOMDocument XML                       │
│     └─> toMODS() - DOMDocument XML                             │
│                                                                 │
│  5. Output                                                      │
│     └─> Return formatted string (CSV, XML)                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Hierarchy Traversal

Descendants are retrieved recursively using parent_id:

```php
protected function getDescendants($parentId)
{
    $descendants = [];
    $children = DB::table('information_object')
        ->where('parent_id', $parentId)
        ->orderBy('lft')
        ->get();

    foreach ($children as $child) {
        $descendants[] = $child;
        $descendants = array_merge(
            $descendants,
            $this->getDescendants($child->id)
        );
    }
    return $descendants;
}
```

---

## Package Structure

When using `createExportPackage()`, the ZIP contains:

```
export_YYYY-MM-DD_HHMMSS.zip
├── manifest.json           # Export metadata
├── metadata/
│   ├── descriptions.csv    # ISAD-G CSV export
│   ├── ead.xml            # EAD 2002 XML
│   └── dc.xml             # Dublin Core XML
└── objects/               # (if includeDigitalObjects=true)
    ├── 123_image.jpg
    ├── 124_document.pdf
    └── ...
```

**manifest.json structure:**
```json
{
    "created": "2026-01-30T12:00:00+02:00",
    "generator": "AHG AtoM Export Module",
    "version": "2.0",
    "format": "csv",
    "includesDigitalObjects": true,
    "recordCount": 150
}
```

---

## Database Tables Used

The service queries the following tables (read-only):

| Table | Purpose |
|-------|---------|
| `information_object` | Main archival description data |
| `information_object_i18n` | Translated fields (title, scope, etc.) |
| `slug` | URL slugs for records |
| `term` | Taxonomy terms |
| `term_i18n` | Translated term names |
| `actor_i18n` | Repository/creator names |
| `event` | Date and creator events |
| `event_i18n` | Event date strings |
| `object_term_relation` | Subject/place/genre access points |
| `relation` | Name access points, physical objects |
| `digital_object` | Digital file metadata |
| `physical_object` | Container/location data |
| `physical_object_i18n` | Container names |
| `note` | Notes |
| `note_i18n` | Note content |
| `property` | Alternative identifiers |
| `property_i18n` | Property values |

---

## Taxonomy IDs

| ID | Taxonomy | Usage |
|----|----------|-------|
| 35 | Subjects | Subject access points |
| 42 | Places | Place access points |
| 78 | Genres | Genre access points |

## Relation Type IDs

| ID | Type | Usage |
|----|------|-------|
| 111 | Creation | Creator events |
| 161 | Name Access Point | Name access points |
| 173 | Physical Object | Container relations |

## Note Type IDs

| ID | Type | CSV Column |
|----|------|------------|
| 121 | General Note | generalNote |
| 122 | Archivist Note | archivistNote |

## Publication Status IDs

| ID | Status |
|----|--------|
| 160 | Published |
| Other | Draft |

---

## Configuration

### Plugin Settings

| Setting | Default | Description |
|---------|---------|-------------|
| culture | 'en' | Default export language |
| includeDescendants | true | Include child records |
| includeDigitalObjects | false | Include file paths |
| createPackage | false | Create ZIP archive |

---

## File Structure

```
ahgExportPlugin/
├── config/
│   └── ahgExportPluginConfiguration.class.php
├── extension.json
├── lib/
│   └── ahgArchivalExportService.class.php
└── modules/
    └── export/
        ├── actions/
        │   └── actions.class.php
        └── templates/
            ├── indexSuccess.php
            ├── archivalSuccess.php
            ├── authoritySuccess.php
            ├── repositorySuccess.php
            ├── csvSuccess.php
            └── eadSuccess.php
```

---

## CLI Commands

For bulk exports, use the command line:

```bash
# Bulk export (referenced in UI)
php symfony export:bulk

# Note: Specific CLI commands may be implemented in atom-framework
```

---

## Security

- All export routes require authentication
- Authentication check in `preExecute()` redirects to login
- Exports respect publication status (published vs draft)
- No write operations performed on database

---

## Integration Points

### With Other Plugins

| Plugin | Integration |
|--------|-------------|
| ahgAuditTrailPlugin | Logs export actions |
| ahgPrivacyPlugin | Respects access restrictions |
| ahgSecurityClearancePlugin | Security level filtering |

### API Usage

The service can be used programmatically from other plugins:

```php
use ahgArchivalExportService;

$export = new ahgArchivalExportService();
$data = $export->export($id);
```

---

## Performance Considerations

- Large exports use `php://temp` stream for memory efficiency
- Descendants retrieved recursively (may be slow for deep hierarchies)
- Consider batch processing for repositories with 10,000+ records
- Digital object packaging adds disk I/O overhead

---

## Related Documentation

- User Guide: `export-data-user-guide.md`
- ISAD(G) Standard: https://www.ica.org/en/isadg-general-international-standard-archival-description-second-edition
- EAD Standard: https://www.loc.gov/ead/
- Dublin Core: https://www.dublincore.org/specifications/dublin-core/

---

## Changelog

### v1.1.0 (February 2026)
- **CSV Export** - Full implementation with:
  - Repository and level of description filters
  - Parent scope filtering by slug
  - Include descendants option
  - ISAD-G compliant 52-column CSV output
  - Direct download for user
- **EAD 2002 Export** - Full implementation with:
  - Top-level record selection (fonds/collections)
  - Include descendants option
  - Complete EAD 2002 XML structure
  - Hierarchical component (`<dsc>/<c>`) output
  - All descriptive elements mapped

### v1.0.0 (Initial)
- Export dashboard interface
- Format selection UI
- Authentication integration

---

*Part of the AtoM AHG Framework*
