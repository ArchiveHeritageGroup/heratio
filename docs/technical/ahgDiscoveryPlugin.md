# ahgDiscoveryPlugin - Technical Documentation

**Version:** 0.2.0
**Category:** Search / AI
**Dependencies:** atom-framework, ahgCorePlugin
**Optional Dependencies:** ahgAIPlugin, ahgSemanticSearchPlugin, ahgSecurityClearancePlugin

---

## Overview

Natural language discovery search across archival collections. Parses free-text queries into structured search terms, executes three parallel search strategies (keyword, NER entity, hierarchical), merges and ranks results, then enriches with metadata. No AI calls at runtime — uses existing OpenSearch index and NER data.

### Key Features

| Feature | Description |
|---------|-------------|
| Query Expansion | Stop-word removal, date range detection, phrase extraction, synonym lookup |
| Keyword Search | OpenSearch multi_match with field boosts, fuzzy matching, highlighting |
| Entity Search | NER entity table matching (persons, orgs, places) |
| Hierarchical Walk | Sibling/child discovery from top results |
| Result Merging | Weighted scoring (keyword 35%, entity 40%, hierarchy 25%) |
| Result Enrichment | Batch metadata: titles, dates, creators, thumbnails, slugs |
| Collection Grouping | Results grouped by root fonds/collection |
| Result Caching | Query hash-based cache with configurable TTL |
| Search Analytics | Query logging, click tracking, popular topics |

---

## Architecture

```
+-------------------------------------------------------------------------+
|                         ahgDiscoveryPlugin                               |
+-------------------------------------------------------------------------+
|                                                                          |
|  +--------------------------------------------------------------------+  |
|  |                       Web Interface Layer                           |  |
|  |  +--------------------+  +--------------------+                     |  |
|  |  | discoveryActions   |  | indexSuccess.php    |                     |  |
|  |  | - executeIndex     |  | - Search input      |                     |  |
|  |  | - executeSearch    |  | - Results (AJAX)    |                     |  |
|  |  | - executeRelated   |  | - Grouped/flat view |                     |  |
|  |  | - executeClick     |  | - Pagination        |                     |  |
|  |  | - executePopular   |  | - Popular topics    |                     |  |
|  |  +--------------------+  +--------------------+                     |  |
|  +--------------------------------------------------------------------+  |
|                                 |                                        |
|                                 v                                        |
|  +--------------------------------------------------------------------+  |
|  |                  4-Step Search Pipeline                             |  |
|  |                                                                     |  |
|  |  Step 1: Query Expansion                                           |  |
|  |  +------------------------------------------------------------+    |  |
|  |  | QueryExpander                                               |    |  |
|  |  | - extractDateRange() → decade, century, year, before/after  |    |  |
|  |  | - extractPhrases()   → quoted + multi-word proper nouns     |    |  |
|  |  | - extractKeywords()  → tokenize, remove stop words          |    |  |
|  |  | - identifyEntityTerms() → capitalized words/phrases         |    |  |
|  |  | - lookupSynonyms()   → ahg_thesaurus_synonym table          |    |  |
|  |  +------------------------------------------------------------+    |  |
|  |                          |                                          |  |
|  |                          v                                          |  |
|  |  Step 2: Three-Strategy Search                                     |  |
|  |  +------------------+ +------------------+ +--------------------+   |  |
|  |  | 2A: Keyword      | | 2B: Entity       | | 2C: Hierarchical   |   |  |
|  |  | KeywordSearch     | | EntitySearch      | | HierarchicalStrat. |   |  |
|  |  | Strategy          | | Strategy          | |                    |   |  |
|  |  |                  | |                    | |                    |   |  |
|  |  | OpenSearch       | | ahg_ner_entity   | | information_object |   |  |
|  |  | multi_match +    | | LIKE match on    | | parent_id walk:    |   |  |
|  |  | field boosts +   | | entity_value     | | siblings + children|   |  |
|  |  | fuzzy + date     | | GROUP BY obj_id  | | of top results     |   |  |
|  |  | range filter     | | with match_count | |                    |   |  |
|  |  +------------------+ +------------------+ +--------------------+   |  |
|  |          |                     |                     |              |  |
|  |          v                     v                     v              |  |
|  |  Step 3: Merge & Rank                                              |  |
|  |  +------------------------------------------------------------+    |  |
|  |  | ResultMerger                                                |    |  |
|  |  | - buildResultMap() → deduplicate across strategies          |    |  |
|  |  | - calculateScores() → weighted: KW 35% + ENT 40% + HI 25%  |    |  |
|  |  | - groupByFonds() → walk to root fonds/collection            |    |  |
|  |  | - multi-strategy bonus: +10% per additional source          |    |  |
|  |  +------------------------------------------------------------+    |  |
|  |                          |                                          |  |
|  |                          v                                          |  |
|  |  Step 4: Enrich                                                    |  |
|  |  +------------------------------------------------------------+    |  |
|  |  | ResultEnricher                                              |    |  |
|  |  | - fetchTitles()     → information_object_i18n              |    |  |
|  |  | - fetchEntities()   → ahg_ner_entity                      |    |  |
|  |  | - fetchMetadata()   → level, dates, extent, creator, repo  |    |  |
|  |  | - fetchThumbnails() → digital_object (THUMBNAIL_ID)        |    |  |
|  |  | - fetchSlugs()      → slug table                           |    |  |
|  |  +------------------------------------------------------------+    |  |
|  +--------------------------------------------------------------------+  |
|                                 |                                        |
|                                 v                                        |
|  +--------------------------------------------------------------------+  |
|  |                    Data Layer                                       |  |
|  |  +------------------+  +------------------+                         |  |
|  |  | ahg_discovery_   |  | ahg_discovery_   |                         |  |
|  |  | cache            |  | log              |                         |  |
|  |  | - query_hash     |  | - user_id        |                         |  |
|  |  | - result_json    |  | - query_text     |                         |  |
|  |  | - expires_at     |  | - expanded_terms |                         |  |
|  |  | (1-hour TTL)     |  | - clicked_object |                         |  |
|  |  +------------------+  +------------------+                         |  |
|  +--------------------------------------------------------------------+  |
|                                                                          |
+-------------------------------------------------------------------------+
              |                    |                     |
              v                    v                     v
    +------------------+  +------------------+  +------------------+
    | OpenSearch       |  | MySQL (Laravel   |  | ahg_ner_entity   |
    | archive_qubit-   |  | Query Builder)   |  | (from            |
    | informationobj.  |  | information_obj. |  |  ahgAIPlugin)    |
    +------------------+  +------------------+  +------------------+
```

---

## File Structure

```
ahgDiscoveryPlugin/
├── config/
│   └── ahgDiscoveryPluginConfiguration.class.php   # Plugin config, autoloader, routes
├── database/
│   └── install.sql                                  # DB schema (2 tables)
├── extension.json                                   # Plugin manifest v0.2.0
├── lib/
│   └── Services/
│       ├── QueryExpander.php                        # Step 1: NL query → structured terms
│       ├── KeywordSearchStrategy.php                # Step 2A: OpenSearch BM25 query
│       ├── EntitySearchStrategy.php                 # Step 2B: NER entity table match
│       ├── HierarchicalStrategy.php                 # Step 2C: Sibling/child walk
│       ├── ResultMerger.php                         # Step 3: Weighted merge + fonds grouping
│       └── ResultEnricher.php                       # Step 4: Batch metadata fetch
└── modules/
    └── discovery/
        ├── actions/
        │   └── actions.class.php                    # 5 actions (index/search/related/click/popular)
        ├── config/
        │   ├── module.yml                           # Module config
        │   └── security.yml                         # No auth required
        └── templates/
            └── indexSuccess.php                      # Full UI (BS5, inline CSS/JS, CSP nonces)
```

---

## Database Tables

### ahg_discovery_cache

Result cache to avoid re-running identical queries.

```sql
CREATE TABLE IF NOT EXISTS ahg_discovery_cache (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    query_hash      VARCHAR(64) NOT NULL,       -- MD5 of query + culture + page + limit
    query_text      TEXT NOT NULL,
    expanded_json   TEXT NULL,
    result_json     LONGTEXT NOT NULL,           -- Full JSON response
    result_count    INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at      TIMESTAMP NOT NULL,          -- Default: 1 hour TTL
    UNIQUE KEY uq_query_hash (query_hash),
    INDEX idx_expires (expires_at)
);
```

### ahg_discovery_log

Search analytics — what people ask and what they click.

```sql
CREATE TABLE IF NOT EXISTS ahg_discovery_log (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NULL,                    -- NULL for anonymous
    query_text      TEXT NOT NULL,
    expanded_terms  TEXT NULL,                    -- JSON of expanded keywords/synonyms/entities
    result_count    INT NOT NULL DEFAULT 0,
    clicked_object  INT NULL,                    -- Set by click tracking endpoint
    response_ms     INT NULL,                    -- Response time in milliseconds
    session_id      VARCHAR(64) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
);
```

---

## Routes

Registered via `RouteLoader('discovery')` in plugin configuration.

| Route Name | URL | Method | Action | Description |
|------------|-----|--------|--------|-------------|
| `discovery_index` | `/discovery` | GET | `executeIndex` | Main discovery page |
| `discovery_search` | `/discovery/search` | GET | `executeSearch` | AJAX search endpoint |
| `discovery_related` | `/discovery/related/:id` | GET | `executeRelated` | Related content for record sidebar |
| `discovery_click` | `/discovery/click` | POST | `executeClick` | Click tracking (sendBeacon) |
| `discovery_popular` | `/discovery/popular` | GET | `executePopular` | Popular search topics |

---

## API Reference

### GET /discovery/search

Full 4-step search pipeline.

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `q` | string | (required) | Natural language query |
| `page` | int | 1 | Page number |
| `limit` | int | 20 | Results per page (max 50) |

**Response:**

```json
{
  "success": true,
  "total": 100,
  "page": 1,
  "limit": 20,
  "pages": 5,
  "collections": [
    {
      "fonds_id": 123,
      "fonds_title": "ANC Archives",
      "fonds_slug": "anc-archives",
      "records": [
        {
          "object_id": 456,
          "score": 0.85,
          "match_reasons": ["KEYWORD", "ENTITY:Nelson Mandela"],
          "highlights": {
            "i18n.en.scopeAndContent": ["...about <mark>education</mark> policy..."]
          },
          "slug": "anc-education-policy-1960",
          "title": "ANC Education Policy",
          "scope_and_content": "First two sentences of scope...",
          "entities": [
            {"type": "PERSON", "value": "Nelson Mandela"},
            {"type": "ORG", "value": "ANC"}
          ],
          "level_of_description": "File",
          "date_range": "1960–1969",
          "extent": "3 folders",
          "creator": "African National Congress",
          "repository": "PSIS Archive",
          "thumbnail_url": "/uploads/r/path/to/thumb.jpg"
        }
      ]
    }
  ],
  "results": [ /* same records as flat array */ ],
  "expanded": {
    "keywords": ["anc", "education", "policy"],
    "phrases": [],
    "synonyms": ["schooling", "training"],
    "dateRange": {"start": 1960, "end": 1969, "label": "1960s"},
    "entityTerms": ["ANC"]
  }
}
```

### GET /discovery/related/:id

Find records sharing NER entities with a given record.

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | int | (required) | Object ID (in URL) |
| `limit` | int | 8 | Max related results (max 20) |

**Response:**

```json
{
  "success": true,
  "results": [
    {
      "object_id": 789,
      "score": 3,
      "title": "Related Record Title",
      "slug": "related-record",
      "shared_entities": ["Nelson Mandela", "ANC"]
    }
  ]
}
```

### POST /discovery/click

Track user clicks on search results. Called via `navigator.sendBeacon()`.

**Parameters (form-encoded):**

| Parameter | Type | Description |
|-----------|------|-------------|
| `query` | string | The search query |
| `object_id` | int | Clicked record ID |
| `session_id` | string | Client session identifier |

### GET /discovery/popular

Frequently searched topics from the last 30 days.

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | int | 8 | Max topics (max 20) |

**Response:**

```json
{
  "success": true,
  "topics": [
    {"query": "photographs", "count": 15, "avg_results": 42}
  ]
}
```

---

## Service Details

### QueryExpander

Parses natural language queries into structured search terms.

| Method | Input | Output |
|--------|-------|--------|
| `expand($query)` | Raw string | `{original, keywords, phrases, synonyms, dateRange, entityTerms}` |

**Date detection patterns:** `1960s` (decade), `19th century` (century word or ordinal), `1960-1969` (range), `before 1900` / `after 1950` (open-ended), standalone `1960` (single year).

**Synonym lookup:** Queries `ahg_thesaurus_term` + `ahg_thesaurus_synonym` tables (from ahgSemanticSearchPlugin). Bidirectional lookups supported. Degrades gracefully if tables don't exist.

**Stop words:** 100+ English stop words plus discovery-specific fillers (`anything`, `related`, `regarding`, `concerning`, `records`, `documents`, `materials`, `collections`, `information`, `details`).

### KeywordSearchStrategy

OpenSearch BM25 bool query with field boosts.

**Field boosts (culture-aware):**

| Field | Boost |
|-------|-------|
| `i18n.{culture}.title` | 3.0 |
| `i18n.{culture}.scopeAndContent` | 2.0 |
| `i18n.{culture}.archivalHistory` | 1.5 |
| `subjects.i18n.{culture}.name` | 2.0 |
| `places.i18n.{culture}.name` | 2.0 |
| `creators.i18n.{culture}.authorizedFormOfName` | 1.5 |
| `names.i18n.{culture}.authorizedFormOfName` | 1.0 |

**Query construction:**
- `must`: multi_match on keywords + phrases (fuzziness: AUTO, minimum_should_match: 50%)
- `should`: synonym boosting (boost: 0.7), entity phrase boosting (boost: 1.5)
- `filter`: date range on `dates.startDate` / `dates.endDate`
- `must_not`: exclude ROOT_ID (id=1)

**Index name resolution:** Uses `QubitSearch::getInstance()->config['index']['name']` + `_qubitinformationobject`. Fallback: `sfConfig::get('app_elasticsearch_index', 'atom')` + `_qubitinformationobject`.

### EntitySearchStrategy

Searches `ahg_ner_entity` table for records containing matching entities.

| Method | Description |
|--------|-------------|
| `search($expanded, $limit)` | Find records by entity LIKE match, grouped by object_id |
| `findRelated($objectId, $limit)` | Find records sharing entities with a specific record |

### HierarchicalStrategy

Walks the `information_object` MPTT hierarchy from top results.

| Method | Description |
|--------|-------------|
| `search($topResults, $alreadyFound, $topN)` | Find siblings + children of top N results |
| `findRootFonds($objectId)` | Walk up to root fonds/collection (static, cached) |

**High-level detection:** Resolves term IDs for Fonds, Sub-fonds, Series, Collection, Sub-series from `term` + `term_i18n` tables. Fallback IDs: `[227, 228, 229, 231]`.

### ResultMerger

Combines results from all three strategies.

**Scoring formula:**

```
finalScore = (keywordNorm × 0.35) + (entityNorm × 0.40) + (hierarchyNorm × 0.25)

// Multi-strategy bonus:
if (sourceCount > 1) finalScore *= (1 + (sourceCount - 1) × 0.1)

// Where:
keywordNorm  = es_score / maxEsScore           (0–1)
entityNorm   = match_count / maxMatchCount      (0–1)
hierarchyNorm = 0.5 (sibling) or 0.3 (child)   (fixed)
```

### ResultEnricher

Batch-fetches metadata for paginated results. All queries use Laravel Query Builder.

| Fetch | Table(s) | Notes |
|-------|----------|-------|
| Titles + scope | `information_object_i18n` | Culture-filtered, scope trimmed to 2 sentences |
| NER entities | `ahg_ner_entity` | Max 10 per record, approved/pending status |
| Level of description | `information_object` + `term_i18n` | Via taxonomy join |
| Dates | `event` + `event_i18n` | Creation events, year extraction |
| Creator | `event` + `actor_i18n` | Creation event actor |
| Repository | `information_object` + `actor_i18n` | Repository extends actor |
| Thumbnails | `digital_object` | `usage_id = THUMBNAIL_ID` |
| Slugs | `slug` | `object_id` lookup |

---

## Caching

- **Cache key:** `MD5(query + culture + page + limit)`
- **TTL:** 3600 seconds (1 hour)
- **Storage:** `ahg_discovery_cache` table
- **Strategy:** `updateOrInsert` on query_hash
- **Expiry:** Checked via `WHERE expires_at > NOW()`

---

## Plugin Configuration

### ahgDiscoveryPluginConfiguration

- **PSR-4 autoloader:** Maps `AhgDiscovery\` namespace to `lib/` directory
- **Module enabling:** Adds `discovery` to `sf_enabled_modules`
- **Route registration:** Via `routing.load_configuration` event with `RouteLoader('discovery')`
- **Install method:** Executes `database/install.sql` via Propel connection

---

## Dependencies

| Dependency | Required | Purpose |
|------------|----------|---------|
| atom-framework | Yes | AhgController, RouteLoader, Laravel DB |
| ahgCorePlugin | Yes | Core framework integration |
| ahgAIPlugin | No | NER entities for entity search + related content |
| ahgSemanticSearchPlugin | No | Thesaurus synonyms for query expansion |
| ahgSecurityClearancePlugin | No | Future: security-filtered results |
| OpenSearch / Elasticsearch | Yes | Keyword search strategy |

All optional dependencies degrade gracefully — table existence is checked with `SHOW TABLES LIKE` before each query.

---

## Installation

```bash
# Install and enable
php atom-framework/bin/atom extension:install ahgDiscoveryPlugin

# Clear cache
rm -rf cache/* && php symfony cc

# Verify
curl -sk https://your-site.com/discovery/search?q=test
```

---

## Performance Considerations

- **Batch queries:** ResultEnricher fetches all metadata in 6 batch queries (not N+1)
- **Pagination:** Only enriches the current page slice (default 20 records)
- **Caching:** Identical queries served from cache for 1 hour
- **HierarchicalStrategy:** Only walks top 20 results, limits siblings to 5 and children to 10 per node
- **Root fonds resolution:** Static cache prevents repeated hierarchy walks
- **Entity search:** Limited to 200 results before merge
- **Keyword search:** Limited to 100 results from OpenSearch
