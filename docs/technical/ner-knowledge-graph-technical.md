# NER Integration with Heritage Discovery & Knowledge Graph

**Issue:** #92 - Integrate NER plugin with Heritage discovery pipeline
**Session:** 13 - AI Workbench
**Status:** Complete

---

## Overview

This implementation integrates the ahgAIPlugin's Named Entity Recognition (NER) output with the Heritage Platform discovery system. It includes:

1. **Entity Cache Sync** - Syncs approved NER entities to the heritage discovery cache
2. **Knowledge Graph** - Visualizes entity relationships using D3.js
3. **Filter Integration** - Adds NER-based filter facets to search
4. **CLI Commands** - Batch processing tools for sync and graph building

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        NER → HERITAGE INTEGRATION                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────────┐         ┌──────────────────┐                          │
│  │   ahgAIPlugin    │         │  ahgHeritagePlugin│                          │
│  │                  │         │                   │                          │
│  │ ahg_ner_entity   │────────▶│ heritage_entity_  │                          │
│  │ ahg_ner_extraction│  SYNC  │ cache             │                          │
│  └──────────────────┘         └─────────┬─────────┘                          │
│                                         │                                    │
│           ┌─────────────────────────────┼─────────────────────────────┐     │
│           │                             │                             │     │
│           ▼                             ▼                             ▼     │
│  ┌──────────────────┐      ┌──────────────────┐      ┌──────────────────┐  │
│  │  KNOWLEDGE GRAPH │      │  SEARCH FILTERS  │      │  FILTER FACETS   │  │
│  │                  │      │                  │      │                  │  │
│  │ heritage_entity_ │      │ SearchOrchestrator│     │ FilterService    │  │
│  │ graph_node       │      │ applyCondition() │      │ uses entity_cache│  │
│  │ heritage_entity_ │      │                  │      │                  │  │
│  │ graph_edge       │      │ authority/place  │      │ Landing page     │  │
│  │                  │      │ entity_cache     │      │ filter cards     │  │
│  └──────────────────┘      └──────────────────┘      └──────────────────┘  │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_98ddfe42.png)
```

---

## Components

### 1. Entity Cache Sync Service

**File:** `atom-framework/src/Heritage/Services/EntityCacheSyncService.php`

Syncs approved NER entities from `ahg_ner_entity` to `heritage_entity_cache`.

#### Key Methods

| Method | Description |
|--------|-------------|
| `syncFromNer(int $objectId)` | Sync entities for a single object |
| `syncAllApproved(int $limit, ?int $sinceId, bool $dryRun)` | Batch sync all approved entities |
| `getStats()` | Get sync statistics |
| `cleanOrphaned()` | Remove orphaned cache entries |
| `getPendingSync(int $limit)` | Get entities that need syncing |

#### Entity Type Mapping

| NER Type | Heritage Type |
|----------|---------------|
| PERSON, PER | person |
| ORG | organization |
| GPE, LOC | place |
| DATE, TIME | date |
| EVENT | event |
| WORK_OF_ART, PRODUCT | work |

---

### 2. Knowledge Graph

#### Database Tables

**File:** `ahgHeritagePlugin/data/knowledge_graph.sql`

| Table | Purpose |
|-------|---------|
| `heritage_entity_graph_node` | Canonical entity nodes |
| `heritage_entity_graph_edge` | Relationships between entities |
| `heritage_entity_graph_object` | Object-to-node mapping |
| `heritage_graph_build_log` | Build history tracking |

#### Graph Service

**File:** `atom-framework/src/Heritage/Services/KnowledgeGraphService.php`

| Method | Description |
|--------|-------------|
| `addEntity(array $entity)` | Add entity to graph |
| `buildCoOccurrenceEdges(int $objectId)` | Build edges from co-occurrences |
| `buildFromCache(int $limit, ?int $sinceId)` | Build graph from entity cache |
| `getRelatedEntities(int $nodeId, int $depth)` | Get related entities |
| `getGraphData(array $filters, int $limit)` | Get D3.js visualization data |
| `findNode(string $entityType, string $value)` | Find entity node |
| `getStats()` | Get graph statistics |

#### Relationship Types

- `co_occurrence` - Entities appear in same document
- `mentioned_with` - Mentioned together in text
- `associated_with` - General association
- `employed_by` - Person → Organization
- `located_in` - Entity → Place
- `occurred_at` - Event → Date/Place
- `related_to` - Generic relation
- `same_as` - Duplicate/alias

---

### 3. Routes

**File:** `ahgHeritagePlugin/config/routing.yml`

| Route | URL | Description |
|-------|-----|-------------|
| `heritage_graph` | `/heritage/graph` | Knowledge Graph visualization |
| `heritage_graph_data` | `/heritage/graph/data` | Graph JSON API |
| `heritage_entity` | `/heritage/entity/:type/:value` | Entity detail page |
| `heritage_api_entity` | `/heritage/api/entity/:type/:value` | Entity JSON API |
| `heritage_api_entity_related` | `/heritage/api/entity/:id/related` | Related entities API |
| `heritage_api_entity_search` | `/heritage/api/entity/search` | Entity search API |
| `heritage_api_graph_stats` | `/heritage/api/graph/stats` | Graph statistics API |

---

### 4. Filter Integration

#### Filter Types

| Code | Name | Source Type | Source Reference |
|------|------|-------------|------------------|
| `place` | Place | authority | place |
| `creator` | Creator | authority | actor |
| `ner_person` | People (AI) | entity_cache | person |
| `ner_organization` | Organizations (AI) | entity_cache | organization |
| `ner_place` | Places (AI) | entity_cache | place |
| `ner_date` | Dates (AI) | entity_cache | date |

#### Search Filter Conditions

**File:** `atom-framework/src/Heritage/Discovery/SearchOrchestrator.php`

The `applyCondition()` method handles:

- **taxonomy** - Term-based filters via `object_term_relation`
- **authority** - Place (taxonomy 42) and Actor filters
- **field** - Direct field filters (repository, date)
- **date_range** - Date range filters via event table
- **entity_cache** - NER entity filters via `heritage_entity_cache`

---

## CLI Commands

### Entity Cache Sync

```bash
# Sync all approved NER entities
php symfony ai:sync-entity-cache

# Sync with limit
php symfony ai:sync-entity-cache --limit=500

# Sync specific object
php symfony ai:sync-entity-cache --object-id=12345

# Dry run (show what would be synced)
php symfony ai:sync-entity-cache --dry-run

# Show statistics
php symfony ai:sync-entity-cache --stats
```

### Knowledge Graph Build

```bash
# Build graph from entity cache
php symfony heritage:build-graph

# Rebuild entire graph
php symfony heritage:build-graph --rebuild

# Build with limit
php symfony heritage:build-graph --limit=1000

# Show statistics only
php symfony heritage:build-graph --stats
```

---

## UI Components

### Knowledge Graph Visualization

**URL:** `/heritage/graph`

**File:** `ahgHeritagePlugin/modules/heritage/templates/graphSuccess.php`

Features:
- D3.js force-directed graph
- Node colors by entity type (person=blue, org=green, place=red, date=purple)
- Node size by occurrence count
- Filter by entity type
- Click node to view entity details
- Zoom and pan controls
- Legend showing entity types

### Entity Detail Page

**URL:** `/heritage/entity/:type/:value`

**File:** `ahgHeritagePlugin/modules/heritage/templates/entitySuccess.php`

Shows:
- Entity name, type, and confidence badge
- Occurrence count
- Related entities
- Associated records
- External links (Wikidata, VIAF if available)
- Actions: Search records, View in graph

### Landing Page Link

The Knowledge Graph is accessible from the Heritage landing page via the "Explore By" section:
- Icon: `fa-project-diagram`
- Label: "Knowledge Graph"

---

## Event Integration

When entities are approved in ahgAIPlugin, an event is dispatched:

```php
$this->dispatcher->notify(new sfEvent($this, 'ner.entity_approved', [
    'entity_id' => $entityId,
    'object_id' => $objectId,
    'entity_type' => $entityType,
    'entity_value' => $entityValue,
    'confidence' => $confidence,
]));
```

This can be used to trigger automatic sync to the entity cache.

---

## Cron Jobs

Add to system crontab for automated sync:

```bash
# Sync NER entities every 15 minutes
*/15 * * * * cd /usr/share/nginx/archive && php symfony ai:sync-entity-cache --limit=500

# Rebuild knowledge graph daily at 3am
0 3 * * * cd /usr/share/nginx/archive && php symfony heritage:build-graph --limit=5000
```

---

## Helper Functions

**File:** `ahgHeritagePlugin/lib/helper/HeritageHelper.php`

| Function | Description |
|----------|-------------|
| `heritage_entity_color(string $type)` | Get color for entity type |
| `heritage_entity_icon(string $type)` | Get Bootstrap icon for entity type |
| `heritage_confidence_percent(?float $confidence)` | Format confidence as percentage |
| `heritage_confidence_badge(?float $confidence)` | Get badge class for confidence level |
| `heritage_truncate(?string $text, int $length)` | Truncate text |
| `heritage_count_label(int $count, string $single, string $plural)` | Format count with label |
| `heritage_json_attr(array $data)` | Generate JSON for HTML attribute |
| `heritage_relative_time(?string $datetime)` | Format relative time |

---

## Entity Type Colors

| Type | Color | Hex |
|------|-------|-----|
| person | Blue | #4e79a7 |
| organization | Green | #59a14f |
| place | Red | #e15759 |
| date | Purple | #b07aa1 |
| event | Teal | #76b7b2 |
| work | Pink | #ff9da7 |
| concept | Yellow | #edc949 |

---

## Statistics (Sample)

After initial sync and graph build:

| Metric | Value |
|--------|-------|
| Entities synced | 1,355 |
| Graph nodes | 584 |
| Graph edges | 31,181 |
| Avg connections/node | 54.8 |
| Persons | 385 |
| Organizations | 273 |
| Places | 468 |
| Dates | 229 |

---

## Verification

### 1. Verify Entity Cache Sync

```bash
php symfony ai:sync-entity-cache --stats
```

### 2. Verify Knowledge Graph

```bash
php symfony heritage:build-graph --stats
```

### 3. Test Graph API

```bash
curl -s "https://your-site/heritage/graph/data?limit=10"
```

### 4. Test Place Filter

```bash
curl -s "https://your-site/heritage/search?place[]=France"
```

---

## Troubleshooting

### Graph not loading

1. Check JavaScript console for errors
2. Verify `/heritage/graph/data` returns JSON
3. Clear Symfony cache: `rm -rf cache/* && php symfony cc`

### Filter not working

1. Check filter type exists in `heritage_filter_type` table
2. Verify `source_type` matches (taxonomy, authority, entity_cache)
3. Check SearchOrchestrator `applyCondition()` handles the type

### Sync not running

1. Verify entities are approved in `ahg_ner_entity` (status = 'linked' or 'approved')
2. Check confidence threshold (default 0.70)
3. Run with `--dry-run` to see what would be synced

---

## Related Files

| File | Purpose |
|------|---------|
| `atom-framework/src/Heritage/Services/EntityCacheSyncService.php` | Entity sync service |
| `atom-framework/src/Heritage/Services/KnowledgeGraphService.php` | Graph operations |
| `atom-framework/src/Heritage/Discovery/SearchOrchestrator.php` | Search with filters |
| `atom-framework/src/Heritage/Filters/FilterService.php` | Filter condition building |
| `ahgHeritagePlugin/data/knowledge_graph.sql` | Graph database schema |
| `ahgHeritagePlugin/modules/heritage/templates/graphSuccess.php` | Graph visualization |
| `ahgHeritagePlugin/modules/heritage/templates/entitySuccess.php` | Entity detail page |
| `ahgHeritagePlugin/lib/helper/HeritageHelper.php` | Template helpers |
| `ahgAIPlugin/lib/task/aiSyncEntityCacheTask.class.php` | Sync CLI task |
| `ahgHeritagePlugin/lib/task/heritageBuildGraphTask.class.php` | Graph build CLI task |
