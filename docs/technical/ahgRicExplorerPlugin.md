# ahgRicExplorerPlugin - Technical Documentation

**Version:** 1.1.5
**Category:** Linked Data / RiC-O
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

The ahgRicExplorerPlugin implements the ICA's Records in Contexts Ontology (RiC-O) for archival descriptions, providing extraction, storage, visualization, and semantic search capabilities. It transforms AtoM's relational data into RDF linked data and synchronizes it with an Apache Jena Fuseki triplestore.

---

## Architecture

```
+-------------------------------------------------------------------+
|                     ahgRicExplorerPlugin                          |
+-------------------------------------------------------------------+
|                                                                   |
|  +---------------------+     +-----------------------------+      |
|  |  RiC Extractor v5   |     |    Fuseki Triplestore       |      |
|  |  (Python)           |---->|    (Apache Jena)            |      |
|  |  - MySQL Reader     |     |    - SPARQL Endpoint        |      |
|  |  - JSON-LD Writer   |     |    - RDF Storage            |      |
|  +---------------------+     +-----------------------------+      |
|           |                              ^                        |
|           v                              |                        |
|  +---------------------+     +-----------------------------+      |
|  |  RicSyncListener    |     |    ricExplorerActions       |      |
|  |  (PHP)              |---->|    (PHP)                    |      |
|  |  - Event Handler    |     |    - Graph Data API         |      |
|  |  - Queue Manager    |     |    - SPARQL Proxy           |      |
|  +---------------------+     +-----------------------------+      |
|           |                              |                        |
|           v                              v                        |
|  +---------------------+     +-----------------------------+      |
|  |  ric_sync_queue     |     |    Visualization Layer      |      |
|  |  (MySQL)            |     |    - Cytoscape.js (2D)      |      |
|  |  - Pending Ops      |     |    - 3D Force Graph         |      |
|  +---------------------+     +-----------------------------+      |
|                                                                   |
+-------------------------------------------------------------------+
```

---

## RiC-O Ontology Mapping

### AtoM to RiC-O Entity Mapping

| AtoM Entity | RiC-O Class | Notes |
|-------------|-------------|-------|
| information_object (fonds/subfonds/series/collection) | rico:RecordSet | Aggregations |
| information_object (item) | rico:Record | Single records |
| information_object (part) | rico:RecordPart | Record components |
| actor (person) | rico:Person | Named individuals |
| actor (corporate body) | rico:CorporateBody | Organizations |
| actor (family) | rico:Family | Family groups |
| repository | rico:CorporateBody | Holding institutions |
| event (creation) | rico:Production | Creation activity |
| event (accumulation) | rico:Accumulation | Collection activity |
| digital_object | rico:Instantiation | Digital representations |
| term (subject) | rico:Thing | Subject access points |
| term (place) | rico:Place | Geographic terms |
| term (genre) | rico:DocumentaryFormType | Form/genre terms |
| rights | rico:Rule | Access/use rules |
| function_object | rico:Function | Business functions |

### Spectrum/GRAP Extensions

The extractor includes custom namespace extensions:

| Extension | Namespace | Purpose |
|-----------|-----------|---------|
| Spectrum | `spectrum:` | Collections Trust activities |
| GRAP | `grap:` | Heritage asset accounting (GRAP 103) |

| Spectrum Activity Type | Description |
|------------------------|-------------|
| ConditionCheck | Condition assessments |
| Valuation | Financial valuations |
| LoanOut | Outgoing loans |
| LocationMovement | Physical movements |

---

## Database Schema

### ERD Diagram

```
+---------------------------+     +---------------------------+
|     ric_sync_status       |     |     ric_sync_queue        |
+---------------------------+     +---------------------------+
| PK id INT                 |     | PK id BIGINT              |
|    entity_type VARCHAR    |     |    entity_type VARCHAR    |
|    entity_id INT          |     |    entity_id INT          |
|    ric_uri VARCHAR(500)   |     |    operation ENUM         |
|    ric_type VARCHAR       |     |    priority TINYINT       |
|    sync_status ENUM       |     |    status ENUM            |
|    last_synced_at DATETIME|     |    attempts INT           |
|    sync_error TEXT        |     |    old_parent_id INT      |
|    retry_count INT        |     |    new_parent_id INT      |
|    content_hash VARCHAR   |     |    scheduled_at DATETIME  |
|    parent_id INT          |     |    last_error TEXT        |
|    hierarchy_path TEXT    |     |    created_at DATETIME    |
+---------------------------+     +---------------------------+

+---------------------------+     +---------------------------+
|     ric_sync_log          |     |   ric_orphan_tracking     |
+---------------------------+     +---------------------------+
| PK id BIGINT              |     | PK id INT                 |
|    operation ENUM         |     |    ric_uri VARCHAR(500)   |
|    entity_type VARCHAR    |     |    ric_type VARCHAR       |
|    entity_id INT          |     |    expected_entity_type   |
|    ric_uri VARCHAR(500)   |     |    expected_entity_id INT |
|    status ENUM            |     |    detected_at DATETIME   |
|    triples_affected INT   |     |    detection_method ENUM  |
|    details JSON           |     |    status ENUM            |
|    error_message TEXT     |     |    resolved_at DATETIME   |
|    execution_time_ms INT  |     |    resolved_by INT        |
|    triggered_by ENUM      |     |    resolution_notes TEXT  |
|    batch_id VARCHAR       |     |    triple_count INT       |
|    created_at DATETIME    |     +---------------------------+
+---------------------------+

+---------------------------+
|     ric_sync_config       |
+---------------------------+
| PK id INT                 |
|    config_key VARCHAR     |
|    config_value TEXT      |
|    description TEXT       |
|    updated_at DATETIME    |
+---------------------------+
```

### Database Views

```sql
-- ric_sync_summary: Aggregated sync statistics by entity type and status
CREATE VIEW ric_sync_summary AS
SELECT entity_type, sync_status, COUNT(*) as count,
       MAX(last_synced_at) as last_sync,
       SUM(CASE WHEN retry_count > 0 THEN 1 ELSE 0 END) as with_retries
FROM ric_sync_status
GROUP BY entity_type, sync_status;

-- ric_queue_status: Queue statistics by status
CREATE VIEW ric_queue_status AS
SELECT status, COUNT(*) as count,
       MIN(scheduled_at) as oldest, MAX(scheduled_at) as newest
FROM ric_sync_queue
GROUP BY status;

-- ric_recent_operations: Last 100 sync operations
CREATE VIEW ric_recent_operations AS
SELECT * FROM ric_sync_log
ORDER BY created_at DESC LIMIT 100;
```

---

## SPARQL Queries

### Common Query Patterns

**1. Get all records for a fonds:**
```sparql
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>

SELECT ?record ?title ?type ?identifier
WHERE {
    ?record a ?type .
    FILTER(?type IN (rico:RecordSet, rico:Record, rico:RecordPart))
    ?record rico:title ?title .
    OPTIONAL { ?record rico:identifier ?identifier }
}
ORDER BY ?title
```

**2. Find creators of a record:**
```sparql
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>

SELECT ?record ?creator ?creatorName
WHERE {
    ?record rico:hasCreator ?creator .
    ?creator rico:hasAgentName/rico:textualValue ?creatorName .
}
```

**3. Get record hierarchy:**
```sparql
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>

SELECT ?parent ?child ?parentTitle ?childTitle
WHERE {
    ?parent rico:includes ?child .
    ?parent rico:title ?parentTitle .
    ?child rico:title ?childTitle .
}
```

**4. Find records by subject:**
```sparql
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>

SELECT ?record ?title ?subject
WHERE {
    ?record rico:hasOrHadSubject ?subjectUri .
    ?subjectUri rico:hasOrHadName/rico:textualValue ?subject .
    ?record rico:title ?title .
    FILTER(CONTAINS(LCASE(?subject), "example"))
}
```

**5. Get Spectrum condition checks:**
```sparql
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
PREFIX spectrum: <https://collectionstrust.org.uk/spectrum#>

SELECT ?record ?checkDate ?condition ?priority
WHERE {
    ?activity a rico:Activity ;
              rico:hasActivityType "ConditionCheck" ;
              rico:resultsOrResultedIn ?record .
    OPTIONAL { ?activity rico:isOrWasAssociatedWithDate/rico:beginningDate ?checkDate }
    OPTIONAL { ?activity spectrum:overallCondition ?condition }
    OPTIONAL { ?activity spectrum:treatmentPriority ?priority }
}
ORDER BY DESC(?checkDate)
```

**6. Count entities by type:**
```sparql
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>

SELECT ?type (COUNT(?s) as ?count)
WHERE {
    ?s a ?type .
    FILTER(STRSTARTS(STR(?type), "https://www.ica.org"))
}
GROUP BY ?type
ORDER BY DESC(?count)
```

---

## Fuseki Integration

### Configuration

Settings are stored in the `ahg_settings` table (setting_group = 'fuseki'):

| Setting Key | Default | Description |
|-------------|---------|-------------|
| fuseki_endpoint | http://localhost:3030/ric | SPARQL endpoint URL |
| fuseki_username | admin | Authentication username |
| fuseki_password | (empty) | Authentication password |
| fuseki_sync_enabled | 1 | Enable automatic sync |
| fuseki_queue_enabled | 1 | Use queue for sync |

### Docker Deployment

```bash
# Run Fuseki with Docker
docker run -d --name fuseki \
  -p 3030:3030 \
  -e ADMIN_PASSWORD=admin123 \
  -v fuseki-data:/fuseki \
  stain/jena-fuseki

# Create dataset
curl -u admin:admin123 -X POST \
  'http://localhost:3030/$/datasets' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'dbName=ric&dbType=tdb2'
```

### Optimization Script

The `optimize_fuseki.sh` script performs:
- TDB2 compaction
- Statistics regeneration
- Query cache clearing

```bash
./bin/optimize_fuseki.sh
```

---

## Graph Visualization

### Cytoscape.js (2D)

The plugin uses Cytoscape.js for 2D graph rendering with the following node styles:

| Entity Type | Color |
|-------------|-------|
| RecordSet/Record | #17a2b8 (teal) |
| CorporateBody | #ffc107 (yellow) |
| Person/Family | #dc3545 (red) |
| Production/Activity | #6f42c1 (purple) |
| Place | #fd7e14 (orange) |
| Thing (Subject) | #20c997 (green) |

Layout: COSE (Compound Spring Embedder) with node repulsion.

### 3D Force Graph

Uses three.js and 3d-force-graph for immersive exploration:
- Node labels rendered as sprites
- Directional particles on links
- Interactive camera controls

---

## Event Sync System

### RicSyncListener

The listener hooks into AtoM's event dispatcher to capture entity changes:

```
QubitInformationObject.insert.post --> handleSave()
QubitInformationObject.update.post --> handleSave()
QubitInformationObject.delete.pre  --> handleDelete()

QubitActor.insert.post --> handleSave()
QubitActor.update.post --> handleSave()
QubitActor.delete.pre  --> handleDelete()
```

### Syncable Entities

| PHP Class | Entity Type Key |
|-----------|-----------------|
| QubitInformationObject | informationobject |
| QubitActor | actor |
| QubitRepository | repository |
| QubitFunction | function |
| QubitEvent | event |

### Queue Operations

Operations are queued with priority levels:
- Priority 1: Delete operations (process first)
- Priority 3: Move operations
- Priority 5: Create/Update operations

---

## Python Tools

### ric_extractor_v5.py

The main extraction tool converts AtoM MySQL data to RiC-O JSON-LD:

```bash
# List available fonds
python3 ric_extractor_v5.py --list-fonds

# Extract specific fonds
python3 ric_extractor_v5.py --fonds-id 776 --output output.jsonld --pretty

# List standalone records
python3 ric_extractor_v5.py --list-standalone
```

**Environment Variables:**
| Variable | Default | Description |
|----------|---------|-------------|
| ATOM_DB_HOST | localhost | MySQL host |
| ATOM_DB_USER | root | MySQL user |
| ATOM_DB_PASSWORD | - | MySQL password |
| ATOM_DB_NAME | archive | Database name |
| RIC_BASE_URI | https://archives.theahg.co.za/ric | Base URI for minted URIs |
| ATOM_INSTANCE_ID | atom-psis | Instance identifier |

### ric_semantic_search.py

Flask-based semantic search API combining Elasticsearch and SPARQL:

```bash
# Start search API
python3 ric_semantic_search.py

# API endpoints
GET/POST /api/search?q=<query>     # Main search
GET /api/autocomplete?q=<prefix>   # Autocomplete
GET /api/suggest                   # Query suggestions
GET /api/health                    # Service health check
```

**Search Features:**
- Fuzzy text matching via Elasticsearch
- Fallback to SPARQL for RiC-specific queries
- Bilingual support (English/Afrikaans)
- Date range parsing
- Level-of-description filtering

### ric_shacl_validator.py

SHACL validation for RiC-O conformance:

```bash
# Validate data in Fuseki
python3 ric_shacl_validator.py --validate --summary

# Validate JSON-LD file
python3 ric_shacl_validator.py --file output.jsonld --validate

# Generate HTML report
python3 ric_shacl_validator.py --validate --report -o report.html
```

**Dependencies:**
```bash
pip install pyshacl rdflib
```

### ric_sync.sh

Shell script for batch synchronization:

```bash
# Sync all fonds
./bin/ric_sync.sh

# Sync specific fonds
./bin/ric_sync.sh --fonds 776,829

# Clear and resync
./bin/ric_sync.sh --clear

# With validation
./bin/ric_sync.sh --validate

# Show status
./bin/ric_sync.sh --status
```

---

## Admin Dashboard Routes

| Route | URL | Description |
|-------|-----|-------------|
| ric_dashboard_index | /admin/ric | Dashboard home |
| ric_dashboard_sync_status | /admin/ric/sync-status | Entity sync status |
| ric_dashboard_orphans | /admin/ric/orphans | Orphaned triples |
| ric_dashboard_queue | /admin/ric/queue | Sync queue |
| ric_dashboard_logs | /admin/ric/logs | Operation logs |

### AJAX Endpoints

| Endpoint | Purpose |
|----------|---------|
| /admin/ric/ajax/stats | Dashboard statistics |
| /admin/ric/ajax/dashboard | Full dashboard data (cached) |
| /admin/ric/ajax/integrity-check | Run integrity check |
| /admin/ric/ajax/cleanup-orphans | Remove orphaned triples |
| /admin/ric/ajax/resync | Force resync entity |

---

## JSON-LD Output Structure

```json
{
  "@context": {
    "rico": "https://www.ica.org/standards/RiC/ontology#",
    "rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
    "rdfs": "http://www.w3.org/2000/01/rdf-schema#",
    "xsd": "http://www.w3.org/2001/XMLSchema#",
    "spectrum": "https://collectionstrust.org.uk/spectrum#",
    "grap": "https://www.asb.co.za/grap#"
  },
  "@graph": [
    {
      "@id": "https://example.com/ric/instance/recordset/776",
      "@type": "rico:RecordSet",
      "rico:identifier": "F001",
      "rico:title": "Fonds Title",
      "rico:hasCreator": {"@id": "..."},
      "rico:includes": [{"@id": "..."}]
    }
  ],
  "_metadata": {
    "extracted": "2025-01-30T10:00:00Z",
    "source": "AtoM instance: atom-psis",
    "records_count": 150,
    "agents_count": 25,
    "relations_count": 340
  }
}
```

---

## Configuration

### app.yml Settings

```yaml
all:
  ric_explorer:
    sparql_endpoint: 'http://localhost:3030/ric/query'
    base_uri: 'https://your-domain.com/ric/atom'
    explorer_url: '/ric/'
    enabled: true
    show_related_records: true
    related_records_limit: 10
    show_mini_graph: true
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Panel not loading | Check Fuseki endpoint accessibility |
| No graph data | Verify JSON-LD was loaded to Fuseki |
| CORS errors | Configure Fuseki CORS or use PHP proxy |
| Sync queue stuck | Check ric_sync_queue for failed items |
| Orphaned triples | Run integrity check from dashboard |
| 3D graph slow | Reduce node count or use 2D mode |

---

## Security Considerations

- Fuseki credentials stored in ahg_settings (encrypted recommended)
- Dashboard restricted to administrators
- SPARQL queries sanitized to prevent injection
- API endpoints require authentication

---

*Part of the AtoM AHG Framework*
