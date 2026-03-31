# ahgMetadataExportPlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Export
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

GLAM Metadata Export plugin providing standards-compliant export to 9 international metadata formats across archives, libraries, museums, visual resources, media, and digital preservation sectors. Implements a clean architecture with abstract base classes for XML and RDF exports.

---

## Architecture

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                        ahgMetadataExportPlugin                                │
├──────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐  │
│  │                         ExporterInterface                              │  │
│  │  • export($resource, $options): string                                 │  │
│  │  • exportBatch($resources, $options): Generator                        │  │
│  │  • getFormat(): string                                                 │  │
│  │  • getMimeType(): string                                               │  │
│  │  • getFileExtension(): string                                          │  │
│  └────────────────────────────────────────────────────────────────────────┘  │
│                          │                        │                          │
│            ┌─────────────┴──────────┐   ┌────────┴─────────────┐            │
│            ▼                        ▼   ▼                      ▼            │
│  ┌──────────────────────┐    ┌──────────────────────┐                       │
│  │  AbstractXmlExporter │    │  AbstractRdfExporter │                       │
│  │  • DOMDocument       │    │  • JSON-LD context   │                       │
│  │  • Namespaces        │    │  • RDF prefixes      │                       │
│  │  • XML validation    │    │  • Serialization     │                       │
│  └──────────┬───────────┘    └──────────┬───────────┘                       │
│             │                           │                                    │
│    ┌────────┼────────┬─────────┐       │                                    │
│    ▼        ▼        ▼         ▼       ▼                                    │
│ ┌──────┐ ┌──────┐ ┌──────┐ ┌───────┐ ┌──────┐ ┌──────────┐                 │
│ │EAD3  │ │LIDO  │ │MARC21│ │VRACore│ │RIC-O │ │BIBFRAME  │                 │
│ └──────┘ └──────┘ └──────┘ └───────┘ └──────┘ └──────────┘                 │
│ ┌──────┐ ┌──────┐ ┌──────┐                                                  │
│ │PBCore│ │EBU   │ │PREMIS│                                                  │
│ └──────┘ └──────┘ └──────┘                                                  │
│                                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐  │
│  │                         ExportService                                  │  │
│  │  • Factory pattern for exporter instantiation                         │  │
│  │  • Format registry                                                     │  │
│  │  • Batch export orchestration                                          │  │
│  └────────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## Directory Structure

```
ahgMetadataExportPlugin/
├── config/
│   └── ahgMetadataExportPluginConfiguration.class.php
├── lib/
│   ├── Contracts/
│   │   └── ExporterInterface.php
│   ├── Exporters/
│   │   ├── AbstractXmlExporter.php
│   │   ├── AbstractRdfExporter.php
│   │   ├── Ead3Exporter.php
│   │   ├── RicoExporter.php
│   │   ├── LidoExporter.php
│   │   ├── Marc21Exporter.php
│   │   ├── BibframeExporter.php
│   │   ├── VraCoreExporter.php
│   │   ├── PbcoreExporter.php
│   │   ├── EbucoreExporter.php
│   │   └── PremisExporter.php
│   ├── Services/
│   │   └── ExportService.php
│   └── task/
│       └── metadataExportTask.class.php
├── modules/
│   └── metadataExport/
│       ├── actions/
│       │   └── actions.class.php
│       └── templates/
│           ├── indexSuccess.php
│           └── exportSuccess.php
├── database/
│   └── install.sql
└── extension.json
```

---

## Export Formats

| Code | Format | Sector | Output | Namespace/Context |
|------|--------|--------|--------|-------------------|
| `ead3` | EAD3 | Archives | XML | `http://ead3.archivists.org/schema/` |
| `rico` | RIC-O | Archives | JSON-LD | `https://www.ica.org/standards/RiC/ontology#` |
| `lido` | LIDO | Museums | XML | `http://www.lido-schema.org` |
| `marc21` | MARC21 | Libraries | XML | `http://www.loc.gov/MARC21/slim` |
| `bibframe` | BIBFRAME | Libraries | JSON-LD | `http://id.loc.gov/ontologies/bibframe/` |
| `vra-core` | VRA Core 4 | Visual | XML | `http://www.vraweb.org/vracore4.htm` |
| `pbcore` | PBCore | Media | XML | `http://www.pbcore.org/PBCore/PBCoreNamespace.html` |
| `ebucore` | EBUCore | Media | XML | `urn:ebu:metadata-schema:ebucore` |
| `premis` | PREMIS 3 | Preservation | XML | `http://www.loc.gov/premis/v3` |

---

## Core Classes

### ExporterInterface

```php
namespace AhgMetadataExport\Contracts;

interface ExporterInterface
{
    public function export($resource, array $options = []): string;
    public function exportBatch(array $resources, array $options = []): \Generator;
    public function getFormat(): string;
    public function getFormatName(): string;
    public function getSector(): string;
    public function getMimeType(): string;
    public function getFileExtension(): string;
    public function supportsResourceType(string $type): bool;
}
```

### AbstractXmlExporter

Base class for XML-based exporters (EAD3, LIDO, MARC21, VRA Core, PBCore, EBUCore, PREMIS).

```php
namespace AhgMetadataExport\Exporters;

abstract class AbstractXmlExporter implements ExporterInterface
{
    protected \DOMDocument $dom;
    protected array $namespaces = [];
    protected array $options = [];

    abstract protected function buildDocument($resource): \DOMDocument;

    public function export($resource, array $options = []): string;
    protected function createElement(string $name, ?string $value = null, ?string $ns = null): \DOMElement;
    protected function addAttribute(\DOMElement $element, string $name, string $value): void;
    protected function getValue($resource, string $property, string $culture = 'en'): ?string;
    protected function escapeXml(string $value): string;
    protected function getDateRange($resource): array;
    protected function getCreators($resource): array;
    protected function getSubjects($resource): array;
    protected function getPlaces($resource): array;
    protected function getLevelOfDescription($resource): ?string;
    protected function getRepository($resource): ?array;
    protected function getDigitalObjects($resource): array;
}
```

### AbstractRdfExporter

Base class for RDF/JSON-LD exporters (RIC-O, BIBFRAME).

```php
namespace AhgMetadataExport\Exporters;

abstract class AbstractRdfExporter implements ExporterInterface
{
    protected array $graph = [];
    protected array $context = [];
    protected array $prefixes = [];
    protected string $baseUri;
    protected string $outputFormat = 'jsonld';

    abstract protected function buildGraph($resource): array;
    abstract protected function initializePrefixes(): void;
    abstract protected function initializeContext(): void;

    public function export($resource, array $options = []): string;
    public function setOutputFormat(string $format): void; // jsonld, turtle, rdfxml, ntriples
    protected function createUri($resource, string $type = 'record'): string;
    protected function getValue($resource, string $property, string $culture = 'en'): ?string;
    protected function serializeToTurtle(): string;
    protected function serializeToRdfXml(): string;
    protected function serializeToNTriples(): string;
}
```

### ExportService

Factory and orchestration service.

```php
namespace AhgMetadataExport\Services;

class ExportService
{
    public function getExporter(string $format): ExporterInterface;
    public function getAvailableFormats(): array;
    public function export($resource, string $format, array $options = []): string;
    public function exportToFile($resource, string $format, string $outputPath, array $options = []): string;
    public function exportBatch(array $resources, string $format, string $outputPath, array $options = []): array;
}
```

---

## CLI Task

### Command

```bash
php symfony metadata:export [options]
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--format=FORMAT` | Export format code or "all" | Required |
| `--slug=SLUG` | Record slug to export | - |
| `--repository=ID` | Export all from repository | - |
| `--output=PATH` | Output directory | `/tmp` |
| `--include-children` | Include hierarchical children | `false` |
| `--include-digital-objects` | Include digital object metadata | `false` |
| `--include-drafts` | Include draft records | `false` |
| `--max-depth=N` | Maximum hierarchy depth | `0` (unlimited) |
| `--culture=CODE` | Export culture | `en` |
| `--list` | List available formats | - |

### Examples

```bash
# List formats
php symfony metadata:export --list

# Single record
php symfony metadata:export --format=ead3 --slug=my-fonds

# Repository bulk export
php symfony metadata:export --format=rico --repository=5 --output=/exports/

# All formats
php symfony metadata:export --format=all --slug=my-record --output=/exports/multi/

# With options
php symfony metadata:export --format=lido --repository=3 \
    --include-digital-objects --include-children --output=/exports/lido/
```

---

## Field Mappings

### ISAD(G) to EAD3

| ISAD(G) Element | EAD3 Element |
|-----------------|--------------|
| Reference Code | `<control><recordid>`, `<unitid>` |
| Title | `<unittitle>` |
| Date | `<unitdate>`, `<unitdatestructured>` |
| Level of Description | `<archdesc level="">`, `<c level="">` |
| Extent | `<physdescstructured><quantity>` |
| Creator | `<origination>` |
| Repository | `<repository><corpname>` |
| Scope and Content | `<scopecontent>` |
| Arrangement | `<arrangement>` |
| Access Conditions | `<accessrestrict>` |
| Reproduction Conditions | `<userestrict>` |
| Language | `<langmaterial><language>` |
| Finding Aids | `<otherfindaid>` |
| Archival History | `<custodhist>` |
| Acquisition | `<acqinfo>` |

### ISAD(G) to RIC-O

| ISAD(G) Element | RIC-O Property |
|-----------------|----------------|
| Fonds/Series/File | `rico:RecordSet` |
| Item | `rico:Record` |
| Reference Code | `rico:identifier` |
| Title | `rico:title`, `rico:name` |
| Date (start) | `rico:beginningDate` |
| Date (end) | `rico:endDate` |
| Extent | `rico:hasExtent` |
| Creator | `rico:hasOrHadCreator` → `rico:Agent` |
| Repository | `rico:isOrWasHeldBy` → `rico:CorporateBody` |
| Scope and Content | `rico:scopeAndContent` |
| Access Conditions | `rico:conditionsOfAccess` |
| Language | `rico:hasOrHadLanguage` |
| Hierarchy | `rico:includesOrIncluded`, `rico:isOrWasIncludedIn` |
| Digital Object | `rico:hasOrHadInstantiation` → `rico:Instantiation` |

### ISAD(G) to LIDO

| ISAD(G) / AtoM Field | LIDO Element |
|----------------------|--------------|
| Identifier | `<lidoRecID>` |
| Title | `<titleSet><appellationValue>` |
| Object Type | `<objectWorkType><term>` |
| Creator | `<eventSet type="production"><eventActor>` |
| Date | `<eventDate><displayDate>` |
| Extent/Dimensions | `<objectMeasurementsSet>` |
| Material | `<materialsTech><termMaterialsTech>` |
| Scope and Content | `<objectDescriptionSet>` |
| Subject | `<subjectConcept><term>` |
| Place | `<subjectPlace><displayPlace>` |
| Repository | `<repositorySet><repositoryName>` |
| Digital Object | `<resourceSet><resourceRepresentation>` |

### ISAD(G) to MARC21

| ISAD(G) Element | MARC21 Tag |
|-----------------|------------|
| Identifier | 001, 035 |
| Title | 245 $a |
| Creator (Person) | 100 $a |
| Creator (Corporate) | 110 $a |
| Date | 260 $c, 264 $c |
| Extent | 300 $a |
| Scope and Content | 520 $a |
| Biographical Note | 545 $a |
| Finding Aids Note | 555 $a |
| Access Restrictions | 506 $a |
| Use Restrictions | 540 $a |
| Language | 008/35-37, 041 $a |
| Subject | 650 $a |
| Place | 651 $a |
| Digital Object URL | 856 $u |

### Digital Object to PREMIS

| AtoM Field | PREMIS Element |
|------------|----------------|
| Digital Object ID | `<objectIdentifier>` |
| Filename | `<originalName>` |
| MIME Type | `<formatName>` |
| File Size | `<size>` |
| Checksum | `<messageDigest>` |
| Checksum Type | `<messageDigestAlgorithm>` |
| Creation Date | `<dateCreatedByApplication>` |
| Storage Path | `<contentLocationValue>` |
| Rights | `<rightsStatement>` |
| Ingestion Event | `<event><eventType>ingestion` |

---

## Output Examples

### EAD3

```xml
<?xml version="1.0" encoding="UTF-8"?>
<ead xmlns="http://ead3.archivists.org/schema/"
     xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
     xmlns:xlink="http://www.w3.org/1999/xlink">
  <control>
    <recordid>F001</recordid>
    <filedesc>
      <titlestmt>
        <titleproper>Smith Family Papers</titleproper>
      </titlestmt>
    </filedesc>
    <maintenancestatus value="derived"/>
    <maintenanceagency>
      <agencyname>Example Archive</agencyname>
    </maintenanceagency>
  </control>
  <archdesc level="fonds">
    <did>
      <unitid>F001</unitid>
      <unittitle>Smith Family Papers</unittitle>
      <unitdate datechar="creation">1920-1950</unitdate>
      <physdesc>5 boxes</physdesc>
      <repository><corpname>Example Archive</corpname></repository>
    </did>
    <scopecontent>
      <p>Personal and business papers of the Smith family...</p>
    </scopecontent>
    <dsc>
      <c level="series">
        <did>
          <unitid>F001/S1</unitid>
          <unittitle>Correspondence</unittitle>
        </did>
      </c>
    </dsc>
  </archdesc>
</ead>
```

### RIC-O (JSON-LD)

```json
{
  "@context": {
    "rico": "https://www.ica.org/standards/RiC/ontology#",
    "rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
    "rdfs": "http://www.w3.org/2000/01/rdf-schema#",
    "xsd": "http://www.w3.org/2001/XMLSchema#",
    "skos": "http://www.w3.org/2004/02/skos/core#"
  },
  "@id": "https://example.org/record/F001",
  "@type": "rico:RecordSet",
  "rico:identifier": {
    "@type": "rico:Identifier",
    "rico:textualValue": "F001"
  },
  "rico:title": {
    "@type": "rico:Title",
    "rico:textualValue": "Smith Family Papers"
  },
  "rico:beginningDate": {
    "@type": "rico:SingleDate",
    "rico:normalizedDateValue": "1920-01-01"
  },
  "rico:endDate": {
    "@type": "rico:SingleDate",
    "rico:normalizedDateValue": "1950-12-31"
  },
  "rico:hasOrHadCreator": {
    "@id": "https://example.org/agent/john-smith",
    "@type": "rico:Person",
    "rico:name": "John Smith"
  },
  "rico:isOrWasHeldBy": {
    "@type": "rico:CorporateBody",
    "rico:name": "Example Archive"
  }
}
```

### PREMIS

```xml
<?xml version="1.0" encoding="UTF-8"?>
<premis:premis xmlns:premis="http://www.loc.gov/premis/v3"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               version="3.0">
  <premis:object xsi:type="premis:intellectualEntity">
    <premis:objectIdentifier>
      <premis:objectIdentifierType>local</premis:objectIdentifierType>
      <premis:objectIdentifierValue>F001</premis:objectIdentifierValue>
    </premis:objectIdentifier>
    <premis:originalName>Smith Family Papers</premis:originalName>
  </premis:object>
  <premis:object xsi:type="premis:file">
    <premis:objectIdentifier>
      <premis:objectIdentifierType>local</premis:objectIdentifierType>
      <premis:objectIdentifierValue>DO-001</premis:objectIdentifierValue>
    </premis:objectIdentifier>
    <premis:objectCharacteristics>
      <premis:format>
        <premis:formatDesignation>
          <premis:formatName>image/tiff</premis:formatName>
        </premis:formatDesignation>
      </premis:format>
      <premis:size>10485760</premis:size>
      <premis:fixity>
        <premis:messageDigestAlgorithm>SHA-256</premis:messageDigestAlgorithm>
        <premis:messageDigest>a1b2c3...</premis:messageDigest>
      </premis:fixity>
    </premis:objectCharacteristics>
  </premis:object>
  <premis:event>
    <premis:eventIdentifier>
      <premis:eventIdentifierType>local</premis:eventIdentifierType>
      <premis:eventIdentifierValue>EVT-001</premis:eventIdentifierValue>
    </premis:eventIdentifier>
    <premis:eventType>ingestion</premis:eventType>
    <premis:eventDateTime>2025-01-15T10:30:00Z</premis:eventDateTime>
  </premis:event>
  <premis:agent>
    <premis:agentIdentifier>
      <premis:agentIdentifierType>local</premis:agentIdentifierType>
      <premis:agentIdentifierValue>agent_software</premis:agentIdentifierValue>
    </premis:agentIdentifier>
    <premis:agentName>AtoM</premis:agentName>
    <premis:agentType>software</premis:agentType>
  </premis:agent>
</premis:premis>
```

---

## Extending the Plugin

### Adding a New Export Format

1. Create exporter class extending `AbstractXmlExporter` or `AbstractRdfExporter`:

```php
namespace AhgMetadataExport\Exporters;

class MyFormatExporter extends AbstractXmlExporter
{
    public const NS_MYFORMAT = 'http://example.org/myformat/';

    protected function initializeNamespaces(): void
    {
        $this->namespaces = [
            'mf' => self::NS_MYFORMAT,
        ];
    }

    public function getFormat(): string
    {
        return 'myformat';
    }

    public function getFormatName(): string
    {
        return 'My Format';
    }

    public function getSector(): string
    {
        return 'Custom';
    }

    public function getMimeType(): string
    {
        return 'application/xml';
    }

    public function getFileExtension(): string
    {
        return 'xml';
    }

    protected function buildDocument($resource): \DOMDocument
    {
        // Build XML document
        $root = $this->dom->createElementNS(self::NS_MYFORMAT, 'mf:root');
        $this->dom->appendChild($root);

        // Add elements...

        return $this->dom;
    }
}
```

2. Register in `ExportService`:

```php
protected function registerExporters(): void
{
    $this->exporters['myformat'] = MyFormatExporter::class;
}
```

---

## DOI Integration

### Overview

The plugin integrates with ahgDoiPlugin to include Digital Object Identifiers in exports. DOIs provide persistent, citable identifiers for archival records.

### DOI Lookup Method

Both base classes include a `getDoi()` method:

```php
protected function getDoi($resource): ?array
{
    $objectId = $resource->id ?? null;
    if (!$objectId) {
        return null;
    }

    $doi = DB::table('ahg_doi')
        ->where('information_object_id', $objectId)
        ->where('status', 'findable')
        ->first();

    if (!$doi) {
        return null;
    }

    return [
        'doi' => $doi->doi,
        'url' => 'https://doi.org/' . $doi->doi,
        'status' => $doi->status,
        'minted_at' => $doi->minted_at,
    ];
}
```

### DOI CLI Options

| Option | Type | Description |
|--------|------|-------------|
| `--include-doi` | boolean | Include DOI in export (default: true) |
| `--mint-doi` | boolean | Mint new DOI for records without one |
| `--doi-state` | string | State for new DOIs: draft, registered, findable |
| `--skip-no-doi` | boolean | Only export records with existing DOIs |

### Format-Specific DOI Elements

| Format | Element | Standard Reference |
|--------|---------|-------------------|
| EAD3 | `<otherrecordid localtype="doi">` | EAD3 §2.4.2 |
| RIC-O | `rico:Identifier` with `identifierType` | RIC-O §4.2 |
| LIDO | `<objectPublishedID lido:type="doi">` | LIDO 1.1 §5.1 |
| MARC21 | `024 $a` with `$2=doi` | MARC21 024 |
| BIBFRAME | `bf:identifiedBy` → `bf:Doi` | BIBFRAME 2.0 |
| VRA Core | `<refid type="doi">` | VRA Core 4 |
| PBCore | `<pbcoreIdentifier source="DOI">` | PBCore 2.1 |
| EBUCore | `<identifier typeLabel="DOI">` | EBUCore 1.10 |
| PREMIS | `<objectIdentifierType>DOI` | PREMIS 3.0 §1.1 |

### DOI Output Examples

#### EAD3
```xml
<control>
  <recordid>F001</recordid>
  <otherrecordid localtype="doi">10.12345/archive.2025.001</otherrecordid>
  <representation href="https://doi.org/10.12345/archive.2025.001" localtype="doi"/>
</control>
```

#### RIC-O (JSON-LD)
```json
{
  "@type": "rico:RecordSet",
  "rico:identifier": [
    {"@type": "rico:Identifier", "rico:textualValue": "F001"},
    {
      "@type": "rico:Identifier",
      "rico:identifierType": "DOI",
      "rico:textualValue": "10.12345/archive.2025.001"
    }
  ],
  "owl:sameAs": {"@id": "https://doi.org/10.12345/archive.2025.001"}
}
```

#### MARC21
```xml
<datafield tag="024" ind1="7" ind2=" ">
  <subfield code="a">10.12345/archive.2025.001</subfield>
  <subfield code="2">doi</subfield>
</datafield>
```

#### LIDO
```xml
<lido:objectPublishedID lido:type="doi" lido:source="DataCite">
  10.12345/archive.2025.001
</lido:objectPublishedID>
```

#### PREMIS
```xml
<premis:objectIdentifier>
  <premis:objectIdentifierType>DOI</premis:objectIdentifierType>
  <premis:objectIdentifierValue>10.12345/archive.2025.001</premis:objectIdentifierValue>
</premis:objectIdentifier>
```

### Dependencies

- **ahgDoiPlugin** - Required for DOI minting; optional for including existing DOIs
- **ahg_doi table** - Stores DOI records with status

### Export Summary with DOI

When DOIs are processed, the export summary includes:

```
>> summary   Export complete: 50 successful, 0 failed
>> doi       DOIs included: 35 existing, 15 minted, 0 failed
```

---

## Database Schema

```sql
-- Export logging (optional)
CREATE TABLE IF NOT EXISTS metadata_export_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    format_code VARCHAR(20) NOT NULL,
    resource_type VARCHAR(50),
    resource_id INT UNSIGNED,
    resource_slug VARCHAR(255),
    export_count INT DEFAULT 1,
    file_path VARCHAR(500),
    file_size BIGINT UNSIGNED,
    user_id INT UNSIGNED,
    options JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_format (format_code),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Integration Points

### AHG Settings - System Info

Export formats displayed at: **Admin > AHG Settings > System Info**

```php
// systemInfoAction.class.php
protected function getMetadataExportFormats(): array
{
    // Returns format list with status
}
```

### AHG Settings - Cron Jobs

Export cron examples at: **Admin > AHG Settings > Cron Jobs** under "Metadata Export" category.

---

## Validation

### EAD3 Validation

```bash
xmllint --schema https://www.loc.gov/ead/ead3.xsd export.xml
```

### RIC-O Validation

```bash
# Using rapper (Raptor RDF tools)
rapper -i guess -c export.jsonld
```

### PREMIS Validation

```bash
xmllint --schema https://www.loc.gov/standards/premis/premis.xsd export.xml
```

---

## Dependencies

- **PHP 8.0+**
- **DOMDocument** (PHP core)
- **json_encode** with JSON_PRETTY_PRINT (PHP core)
- **atom-framework** (Laravel Query Builder)

Optional for RDF serialization:
- **EasyRdf** (for Turtle/RDF-XML output)
- **ML\JsonLD** (for JSON-LD processing)

---

## References

- [EAD3 Schema](https://www.loc.gov/ead/)
- [RIC-O Ontology](https://www.ica.org/standards/RiC/ontology)
- [LIDO Schema](http://www.lido-schema.org/)
- [MARC21 XML](https://www.loc.gov/standards/marcxml/)
- [BIBFRAME](https://www.loc.gov/bibframe/)
- [VRA Core 4](http://www.loc.gov/standards/vracore/)
- [PBCore](https://pbcore.org/)
- [EBUCore](https://tech.ebu.ch/MetadataEbuCore)
- [PREMIS](https://www.loc.gov/standards/premis/)

---

*Version 1.0.0 - January 2026*
