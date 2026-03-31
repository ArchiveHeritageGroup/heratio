# ahg-ric - Records in Contexts (RIC-O) Package

**RIC-O Implementation for Heratio - ISO 23278 Compliant**

## Overview

This package provides complete implementation of the Records in Contexts (RIC-O) ontology for Heratio, enabling Linked Data publication of archival holdings according to ICA standards.

## Standards Compliance

| Standard | Description | Status |
|----------|-------------|--------|
| **RIC-O** | Records in Contexts Ontology | ✅ Full |
| **ISAAR(CPF)** | Agents (Persons, Corporate Bodies, Families) | ✅ Full |
| **ISDF** | Functions | ✅ Full |
| **ISAD** | Records (Information Objects) | ✅ Full |
| **ISDIAH** | Repositories | ✅ Full |
| **ISCAP** | Security/Access Control | ✅ Partial |
| **W3C SHACL** | Data Validation | ✅ Full |
| **SPARQL 1.1** | Triplestore Queries | ✅ Full |

## Installation

```bash
composer require ahg/ahg-ric
```

## Configuration

Add to `.env`:

```env
FUSEKI_ENDPOINT=http://localhost:3030/heratio
RICO_INSTANCE_ID=your-instance-id
```

## Services

### RicSerializationService

Serializes AtoM entities to RIC-O JSON-LD format.

```php
use AhgRic\Services\RicSerializationService;

$service = new RicSerializationService();

// Serialize a record
$ric = $service->serializeRecord($ioId);

// Serialize an agent (ISAAR compliant)
$ric = $service->serializeAgent($actorId);

// Export entire RecordSet
$graph = $service->exportRecordSet($fondsId, ['pretty' => true]);
```

### ShaclValidationService

Validates entities against SHACL shapes before CRUD operations.

```php
use AhgRic\Services\ShaclValidationService;

$validator = new ShaclValidationService();

// Validate before save
$result = $validator->validateBeforeSave($ricEntity, 'Record');

if (!$result['valid']) {
    // Handle validation errors
    return $result['errors'];
}
```

### SparqlQueryService

Execute SPARQL queries against the Fuseki triplestore.

```php
use AhgRic\Services\SparqlQueryService;

$sparql = new SparqlQueryService();

// Search entities
$results = $sparql->search('archival fonds', ['type' => 'record']);

// Get entity relationships
$relationships = $sparql->getRelationships($uri);

// Get statistics
$stats = $sparql->getStatistics();
```

## API Endpoints

Base URL: `/api/ric/v1`

### Agents (ISAAR-CPF)
- `GET /api/ric/v1/agents` - List all agents
- `GET /api/ric/v1/agents/{slug}` - Get agent as RIC-O JSON-LD

### Records (ISAD)
- `GET /api/ric/v1/records` - List all records
- `GET /api/ric/v1/records/{slug}` - Get record as RIC-O JSON-LD
- `GET /api/ric/v1/records/{slug}/export` - Export entire RecordSet

### Functions (ISDF)
- `GET /api/ric/v1/functions` - List all functions
- `GET /api/ric/v1/functions/{id}` - Get function as RIC-O JSON-LD

### Repositories (ISDIAH)
- `GET /api/ric/v1/repositories` - List all repositories
- `GET /api/ric/v1/repositories/{slug}` - Get repository as RIC-O JSON-LD

### SPARQL & Graph
- `GET /api/ric/v1/sparql?query=...` - Execute SPARQL query
- `GET /api/ric/v1/graph?uri=...` - Get entity relationships

### Validation
- `POST /api/ric/v1/validate` - Validate RIC-O entity

### Vocabulary
- `GET /api/ric/v1/vocabulary` - Get RIC-O vocabulary terms

### Health
- `GET /api/ric/v1/health` - API health check

## RIC-O Classes Supported

- `rico:Agent` - Generic agent
- `rico:Person` - Person
- `rico:CorporateBody` - Corporate body
- `rico:Family` - Family
- `rico:Function` - Function
- `rico:Record` - Record (item)
- `rico:RecordSet` - Record set (fonds, series, etc.)
- `rico:RecordPart` - Record part
- `rico:Instantiation` - Digital object
- `rico:Place` - Place
- `rico:Activity` - Activity/Event
- `rico:DateRange` - Date range
- `rico:Language` - Language
- `rico:Mandate` - Mandate

## Python Tools

Located in `tools/`:

| Tool | Purpose |
|------|---------|
| `ric_extractor_v5.py` | Extract AtoM data to RIC-O JSON-LD |
| `ric_shacl_validator.py` | Validate RIC-O against SHACL shapes |
| `ric_semantic_search.py` | Semantic search in triplestore |
| `ric_authority_linker.py` | Link authority records |
| `ric_provenance.py` | Provenance tracing |
| `ric_editor.py` | RIC-O entity editor |
| `ric_shacl_shapes.ttl` | SHACL validation shapes |

## Database Tables Used

- `actor` / `actor_i18n` - Agents
- `information_object` / `information_object_i18n` - Records
- `function` / `function_i18n` - Functions
- `repository` - Repositories
- `date` - Dates
- `relation` - Relationships
- `digital_object` - Instantiation
- `security_level` - Access control

## License

This package is part of Heratio, licensed under AGPL-3.0.

## Author

Johan Pieterse - Plain Sailing Information Systems
