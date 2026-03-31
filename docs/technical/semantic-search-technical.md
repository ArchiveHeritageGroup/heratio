# Semantic Search - Technical Manual

## Overview

The Semantic Search system provides query expansion capabilities for AtoM archives using a thesaurus-based approach. It integrates with Elasticsearch for search optimization and supports multiple data sources including WordNet, Wikidata, and local synonym definitions.

**Namespace:** `AtomFramework\Services\SemanticSearch`
**Database:** Laravel Query Builder (`Illuminate\Database\Capsule\Manager`)
**Location:** `/usr/share/nginx/archive/atom-framework/src/Services/SemanticSearch/`

---

## Architecture

### System Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              CLIENT LAYER                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐     │
│   │   Search Box     │    │   Admin UI       │    │   CLI Commands   │     │
│   │   (_box.php)     │    │(semanticSearch   │    │(ThesaurusCommand)│     │
│   │                  │    │    Admin)        │    │                  │     │
│   └────────┬─────────┘    └────────┬─────────┘    └────────┬─────────┘     │
│            │                       │                       │                │
└────────────┼───────────────────────┼───────────────────────┼────────────────┘
             │                       │                       │
             ▼                       ▼                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                            SERVICE LAYER                                     │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌──────────────────────────────────────────────────────────────────────┐  │
│   │                     SemanticSearchService                             │  │
│   │   - expandSearchQuery()    - getExpansionInfo()                      │  │
│   │   - buildElasticsearchQuery()   - logSearch()                        │  │
│   └──────────────────────────────────────────────────────────────────────┘  │
│                           │                                                  │
│                           ▼                                                  │
│   ┌──────────────────────────────────────────────────────────────────────┐  │
│   │                      ThesaurusService                                 │  │
│   │   - addTerm()          - getSynonyms()      - expandQuery()          │  │
│   │   - importLocalSynonyms()   - exportToElasticsearch()                │  │
│   └──────────────────────────────────────────────────────────────────────┘  │
│              │                    │                    │                     │
│              ▼                    ▼                    ▼                     │
│   ┌────────────────┐   ┌────────────────┐   ┌────────────────┐             │
│   │ WordNetSync    │   │ WikidataSync   │   │ EmbeddingService│            │
│   │ Service        │   │ Service        │   │ (Ollama)       │             │
│   └────────────────┘   └────────────────┘   └────────────────┘             │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
             │                       │                       │
             ▼                       ▼                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                            DATA LAYER                                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐          │
│   │   MySQL DB      │   │  Elasticsearch  │   │  External APIs  │          │
│   │ (ahg_thesaurus  │   │  (synonyms.txt) │   │ - Datamuse      │          │
│   │   _* tables)    │   │                 │   │ - Wikidata      │          │
│   └─────────────────┘   └─────────────────┘   │ - Ollama        │          │
│                                               └─────────────────┘          │
└─────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_e214ad62.png)
```

---

## Entity Relationship Diagram (ERD)

### Database Schema

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          SEMANTIC SEARCH ERD                                 │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────┐
│   ahg_thesaurus_term     │
├──────────────────────────┤
│ PK id            BIGINT  │
│    term          VARCHAR │◄────────────┐
│    source        VARCHAR │             │
│    domain        VARCHAR │             │
│    external_id   VARCHAR │             │
│    created_at    DATETIME│             │
│    updated_at    DATETIME│             │
└──────────────────────────┘             │
           │                             │
           │ 1                           │
           │                             │
           │                             │
           ▼ N                           │
┌──────────────────────────┐             │
│  ahg_thesaurus_synonym   │             │
├──────────────────────────┤             │
│ PK id            BIGINT  │             │
│ FK term_id       BIGINT  │─────────────┘
│    synonym       VARCHAR │
│    relationship  VARCHAR │  (exact, related, broader, narrower)
│    weight        DECIMAL │  (0.0 - 1.0)
│    source        VARCHAR │
│    created_at    DATETIME│
└──────────────────────────┘


┌──────────────────────────┐
│  ahg_thesaurus_embedding │
├──────────────────────────┤
│ PK id            BIGINT  │
│ FK term_id       BIGINT  │─────────────┐
│    model         VARCHAR │             │
│    embedding     BLOB    │             │
│    created_at    DATETIME│             │
└──────────────────────────┘             │
                                         │
           ┌─────────────────────────────┘
           │
           ▼
┌──────────────────────────┐
│   ahg_thesaurus_term     │
│        (reference)       │
└──────────────────────────┘


┌──────────────────────────┐          ┌──────────────────────────┐
│  ahg_thesaurus_sync_log  │          │ahg_semantic_search_log   │
├──────────────────────────┤          ├──────────────────────────┤
│ PK id            BIGINT  │          │ PK id            BIGINT  │
│    source        VARCHAR │          │    original_query VARCHAR│
│    status        VARCHAR │          │    expanded_query TEXT   │
│    terms_synced  INT     │          │    was_expanded  BOOLEAN │
│    started_at    DATETIME│          │    terms_expanded INT    │
│    completed_at  DATETIME│          │    user_id       INT     │
│    error_message TEXT    │          │    ip_address    VARCHAR │
└──────────────────────────┘          │    created_at    DATETIME│
                                      └──────────────────────────┘


┌──────────────────────────┐
│      ahg_settings        │
├──────────────────────────┤
│ PK id            BIGINT  │
│    setting_key   VARCHAR │  (UNIQUE)
│    setting_value TEXT    │
│    setting_type  VARCHAR │  (string, boolean, integer, json)
│    setting_group VARCHAR │  (semantic_search)
│    updated_by    INT     │
│    created_at    DATETIME│
│    updated_at    DATETIME│
└──────────────────────────┘
![wireframe](./images/wireframes/wireframe_840b4fd1.png)
```

### Table Relationships

```
ahg_thesaurus_term (1) ──────< (N) ahg_thesaurus_synonym
                    (1) ──────< (N) ahg_thesaurus_embedding

ahg_settings ──── (filtered by setting_group = 'semantic_search')
```

---

## Database Schema DDL

### Migration File

**Location:** `/usr/share/nginx/archive/atom-framework/database/migrations/2026_01_21_semantic_search_tables.sql`

```sql
-- Thesaurus Terms
CREATE TABLE IF NOT EXISTS ahg_thesaurus_term (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term VARCHAR(255) NOT NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'local',
    domain VARCHAR(100) DEFAULT 'general',
    external_id VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_term_source (term, source),
    INDEX idx_source (source),
    INDEX idx_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Synonyms
CREATE TABLE IF NOT EXISTS ahg_thesaurus_synonym (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id BIGINT UNSIGNED NOT NULL,
    synonym VARCHAR(255) NOT NULL,
    relationship_type VARCHAR(50) DEFAULT 'exact',
    weight DECIMAL(3,2) DEFAULT 0.80,
    source VARCHAR(50) DEFAULT 'local',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (term_id) REFERENCES ahg_thesaurus_term(id) ON DELETE CASCADE,
    UNIQUE KEY idx_term_synonym (term_id, synonym),
    INDEX idx_synonym (synonym),
    INDEX idx_weight (weight)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sync Log
CREATE TABLE IF NOT EXISTS ahg_thesaurus_sync_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'running',
    terms_synced INT DEFAULT 0,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    error_message TEXT NULL,
    INDEX idx_source_status (source, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Embeddings
CREATE TABLE IF NOT EXISTS ahg_thesaurus_embedding (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id BIGINT UNSIGNED NOT NULL,
    model VARCHAR(100) NOT NULL,
    embedding BLOB NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (term_id) REFERENCES ahg_thesaurus_term(id) ON DELETE CASCADE,
    UNIQUE KEY idx_term_model (term_id, model)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Search Log
CREATE TABLE IF NOT EXISTS ahg_semantic_search_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_query VARCHAR(500) NOT NULL,
    expanded_query TEXT NULL,
    was_expanded TINYINT(1) DEFAULT 0,
    terms_expanded INT DEFAULT 0,
    user_id INT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_query (original_query(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Service Classes

### Class Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           CLASS HIERARCHY                                    │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────┐
│                          ThesaurusService                                 │
├──────────────────────────────────────────────────────────────────────────┤
│ - logger: Logger                                                          │
│ - synonymsPath: string                                                    │
├──────────────────────────────────────────────────────────────────────────┤
│ + addTerm(term, source, domain, externalId): int                         │
│ + addSynonym(termId, synonym, type, weight, source): int                 │
│ + getSynonyms(term, minWeight, limit): array                             │
│ + expandQuery(query, limit): array                                        │
│ + importLocalSynonyms(): array                                           │
│ + exportToElasticsearch(): string                                        │
│ + getTermByWord(word): ?object                                           │
│ + startSyncLog(source): int                                              │
│ + completeSyncLog(logId, count): void                                    │
│ + failSyncLog(logId, error): void                                        │
└──────────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ uses
                                    ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                         WordNetSyncService                                │
├──────────────────────────────────────────────────────────────────────────┤
│ - thesaurus: ThesaurusService                                            │
│ - apiUrl: string                                                          │
│ - ARCHIVAL_TERMS: array                                                   │
│ - LIBRARY_TERMS: array                                                    │
│ - MUSEUM_TERMS: array                                                     │
├──────────────────────────────────────────────────────────────────────────┤
│ + syncTerm(term, domain): array                                          │
│ + syncArchivalTerms(): array                                             │
│ + syncLibraryTerms(): array                                              │
│ + syncMuseumTerms(): array                                               │
│ - fetchFromDatamuse(word): array                                         │
│ - mapRelationshipType(datamuseType): string                              │
└──────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────┐
│                        WikidataSyncService                                │
├──────────────────────────────────────────────────────────────────────────┤
│ - thesaurus: ThesaurusService                                            │
│ - sparqlEndpoint: string                                                  │
│ - HERITAGE_CLASSES: array                                                 │
│ - SA_HERITAGE_ITEMS: array                                                │
├──────────────────────────────────────────────────────────────────────────┤
│ + syncHeritageTerms(): array                                             │
│ + syncSouthAfricanTerms(): array                                         │
│ - executeSparqlQuery(query): array                                       │
│ - buildHeritageQuery(): string                                           │
└──────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────┐
│                         EmbeddingService                                  │
├──────────────────────────────────────────────────────────────────────────┤
│ - ollamaEndpoint: string                                                  │
│ - model: string                                                           │
├──────────────────────────────────────────────────────────────────────────┤
│ + generateEmbedding(text): array                                         │
│ + storeEmbedding(termId, embedding): void                                │
│ + findSimilar(text, limit): array                                        │
│ + cosineSimilarity(vec1, vec2): float                                    │
│ - callOllama(prompt): array                                              │
└──────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────┐
│                       SemanticSearchService                               │
├──────────────────────────────────────────────────────────────────────────┤
│ - thesaurus: ThesaurusService                                            │
│ - settings: array                                                         │
├──────────────────────────────────────────────────────────────────────────┤
│ + expandSearchQuery(query): string                                       │
│ + getExpansionInfo(): array                                              │
│ + isEnabled(): bool                                                       │
│ + logSearch(original, expanded, userId): void                            │
└──────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_d903ca59.png)
```

---

## Query Expansion Flow

### Detailed Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        QUERY EXPANSION FLOW                                  │
└─────────────────────────────────────────────────────────────────────────────┘

User Input: "old photographs township"
            │
            ▼
┌───────────────────────────────────────┐
│ 1. TOKENIZATION                       │
│    Split query into terms             │
│    ["old", "photographs", "township"] │
└───────────────────────────────────────┘
            │
            ▼
┌───────────────────────────────────────┐
│ 2. STOPWORD FILTERING                 │
│    Remove common words                │
│    ["photographs", "township"]        │
│    (Note: "old" may be kept)          │
└───────────────────────────────────────┘
            │
            ▼
┌───────────────────────────────────────────────────────────────────────────┐
│ 3. SYNONYM LOOKUP (per term)                                               │
│                                                                            │
│    SELECT synonym, weight                                                  │
│    FROM ahg_thesaurus_synonym s                                           │
│    JOIN ahg_thesaurus_term t ON s.term_id = t.id                          │
│    WHERE t.term = 'photographs'                                           │
│    AND s.weight >= 0.6                                                    │
│    ORDER BY s.weight DESC                                                 │
│    LIMIT 5                                                                │
│                                                                            │
│    Results:                                                                │
│    ┌────────────────┬────────┐   ┌────────────────┬────────┐             │
│    │ photographs    │        │   │ township       │        │             │
│    ├────────────────┼────────┤   ├────────────────┼────────┤             │
│    │ photo          │ 0.95   │   │ location       │ 0.85   │             │
│    │ picture        │ 0.90   │   │ settlement     │ 0.80   │             │
│    │ image          │ 0.85   │   │ informal       │ 0.70   │             │
│    │ snapshot       │ 0.75   │   │ settlement     │        │             │
│    └────────────────┴────────┘   └────────────────┴────────┘             │
└───────────────────────────────────────────────────────────────────────────┘
            │
            ▼
┌───────────────────────────────────────┐
│ 4. QUERY CONSTRUCTION                 │
│                                       │
│    (photographs OR photo OR picture   │
│     OR image OR snapshot)             │
│    AND                                │
│    (township OR location OR           │
│     settlement)                       │
└───────────────────────────────────────┘
            │
            ▼
┌───────────────────────────────────────┐
│ 5. ELASTICSEARCH QUERY                │
│                                       │
│    {                                  │
│      "query": {                       │
│        "bool": {                      │
│          "should": [                  │
│            {"match": {...}},          │
│            {"match": {...}}           │
│          ]                            │
│        }                              │
│      }                                │
│    }                                  │
└───────────────────────────────────────┘
            │
            ▼
┌───────────────────────────────────────┐
│ 6. RESULTS + EXPANSION INFO           │
│                                       │
│    Return search results with         │
│    expansion metadata for UI display  │
└───────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_a3889915.png)
```

---

## Data Sync Flow

### WordNet Sync Process

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         WORDNET SYNC FLOW                                    │
└─────────────────────────────────────────────────────────────────────────────┘

┌────────────────────┐
│ Start Sync         │
│ (CLI or Admin UI)  │
└─────────┬──────────┘
          │
          ▼
┌────────────────────┐
│ Create sync log    │
│ status = 'running' │
└─────────┬──────────┘
          │
          ▼
┌────────────────────┐
│ Load term list     │
│ (ARCHIVAL_TERMS,   │
│  LIBRARY_TERMS,    │
│  MUSEUM_TERMS)     │
└─────────┬──────────┘
          │
          ▼
┌────────────────────────────────────────────────────────────────────────────┐
│ FOR EACH term:                                                              │
│                                                                             │
│   ┌────────────────────┐                                                   │
│   │ Call Datamuse API  │                                                   │
│   │ GET /words?rel_syn │                                                   │
│   │ =term&max=10       │                                                   │
│   └─────────┬──────────┘                                                   │
│             │                                                               │
│             ▼                                                               │
│   ┌────────────────────┐                                                   │
│   │ Rate limit: 100ms  │                                                   │
│   │ between requests   │                                                   │
│   └─────────┬──────────┘                                                   │
│             │                                                               │
│             ▼                                                               │
│   ┌────────────────────┐                                                   │
│   │ Parse response:    │                                                   │
│   │ [{"word":"...",    │                                                   │
│   │   "score":1000}]   │                                                   │
│   └─────────┬──────────┘                                                   │
│             │                                                               │
│             ▼                                                               │
│   ┌────────────────────┐                                                   │
│   │ Normalize score    │                                                   │
│   │ to weight (0-1)    │                                                   │
│   └─────────┬──────────┘                                                   │
│             │                                                               │
│             ▼                                                               │
│   ┌────────────────────┐                                                   │
│   │ Upsert term +      │                                                   │
│   │ synonyms to DB     │                                                   │
│   └─────────┬──────────┘                                                   │
│             │                                                               │
└─────────────┼───────────────────────────────────────────────────────────────┘
              │
              ▼
┌────────────────────┐
│ Update sync log    │
│ status='completed' │
│ terms_synced = N   │
└────────────────────┘
![wireframe](./images/wireframes/wireframe_a98f66bb.png)
```

---

## CLI Commands

### Command Reference

**Location:** `/usr/share/nginx/archive/atom-framework/src/Console/ThesaurusCommand.php`

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          CLI COMMAND REFERENCE                               │
└─────────────────────────────────────────────────────────────────────────────┘

Usage: php bin/atom thesaurus:<command> [options] [arguments]

┌─────────────────────────┬───────────────────────────────────────────────────┐
│ Command                 │ Description                                        │
├─────────────────────────┼───────────────────────────────────────────────────┤
│ thesaurus:import-local  │ Import local JSON synonym files                    │
│ thesaurus:sync-wordnet  │ Sync from Datamuse API (WordNet)                   │
│ thesaurus:sync-wikidata │ Sync from Wikidata SPARQL                          │
│ thesaurus:export-es     │ Export synonyms to Elasticsearch file              │
│ thesaurus:stats         │ Display thesaurus statistics                       │
│ thesaurus:expand        │ Test query expansion                               │
│ thesaurus:embeddings    │ Generate vector embeddings (requires Ollama)       │
└─────────────────────────┴───────────────────────────────────────────────────┘

Examples:
  php bin/atom thesaurus:import-local
  php bin/atom thesaurus:sync-wordnet --archival
  php bin/atom thesaurus:expand "old photographs"
  php bin/atom thesaurus:export-es --path=/etc/elasticsearch/synonyms/
![wireframe](./images/wireframes/wireframe_25ae194b.png)
```

---

## Settings Configuration

### Settings Keys

```
┌────────────────────────────────┬──────────┬─────────────────────────────────┐
│ Key                            │ Type     │ Default                          │
├────────────────────────────────┼──────────┼─────────────────────────────────┤
│ semantic_search_enabled        │ boolean  │ true                             │
│ semantic_expansion_limit       │ integer  │ 5                                │
│ semantic_min_weight            │ float    │ 0.6                              │
│ semantic_show_expansion        │ boolean  │ true                             │
│ semantic_log_searches          │ boolean  │ true                             │
│ semantic_wordnet_enabled       │ boolean  │ true                             │
│ semantic_wikidata_enabled      │ boolean  │ false                            │
│ semantic_local_synonyms        │ boolean  │ true                             │
│ semantic_ollama_enabled        │ boolean  │ false                            │
│ semantic_ollama_endpoint       │ string   │ http://localhost:11434           │
│ semantic_ollama_model          │ string   │ nomic-embed-text                 │
│ semantic_es_synonyms_path      │ string   │ /etc/elasticsearch/synonyms/... │
└────────────────────────────────┴──────────┴─────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_4b355ce3.png)
```

### Accessing Settings

```php
use Illuminate\Database\Capsule\Manager as DB;

// Get single setting
$enabled = DB::table('ahg_settings')
    ->where('setting_key', 'semantic_search_enabled')
    ->value('setting_value');

// Get all semantic search settings
$settings = DB::table('ahg_settings')
    ->where('setting_group', 'semantic_search')
    ->pluck('setting_value', 'setting_key');
```

---

## Elasticsearch Integration

### Synonym File Format

**Location:** `/etc/elasticsearch/synonyms/ahg_synonyms.txt`

```
# Format: term => synonym1, synonym2, synonym3
# Generated by: php bin/atom thesaurus:export-es

archive => repository, depot, record office, holdings
photograph => photo, picture, image, snapshot
manuscript => document, text, codex
township => location, settlement, informal settlement
fonds => collection, papers, records
```

### Elasticsearch Index Configuration

```json
{
  "settings": {
    "analysis": {
      "filter": {
        "ahg_synonyms": {
          "type": "synonym",
          "synonyms_path": "synonyms/ahg_synonyms.txt",
          "updateable": true
        }
      },
      "analyzer": {
        "ahg_search_analyzer": {
          "tokenizer": "standard",
          "filter": [
            "lowercase",
            "ahg_synonyms"
          ]
        }
      }
    }
  },
  "mappings": {
    "properties": {
      "title": {
        "type": "text",
        "analyzer": "standard",
        "search_analyzer": "ahg_search_analyzer"
      }
    }
  }
}
```

---

## Local Synonym Files

### File Structure

**Location:** `/usr/share/nginx/archive/atom-framework/data/synonyms/`

```
data/synonyms/
├── archival.json      # Archival terminology
├── library.json       # Library terminology
├── museum.json        # Museum terminology
└── south_african.json # South African heritage terms
```

### JSON Format

```json
{
  "domain": "archival",
  "terms": [
    {
      "term": "archive",
      "synonyms": [
        {"word": "repository", "weight": 0.95, "type": "exact"},
        {"word": "record office", "weight": 0.90, "type": "exact"},
        {"word": "depot", "weight": 0.85, "type": "exact"},
        {"word": "holdings", "weight": 0.75, "type": "related"}
      ]
    },
    {
      "term": "fonds",
      "synonyms": [
        {"word": "collection", "weight": 0.90, "type": "related"},
        {"word": "papers", "weight": 0.85, "type": "related"},
        {"word": "records", "weight": 0.80, "type": "broader"}
      ]
    }
  ]
}
```

---

## API Reference

### ThesaurusService Methods

```php
/**
 * Add a term to the thesaurus
 * @param string $term The term to add
 * @param string $source Source identifier (local, wordnet, wikidata)
 * @param string $domain Domain category
 * @param string|null $externalId External source ID
 * @return int The term ID
 */
public function addTerm(
    string $term,
    string $source = 'local',
    string $domain = 'general',
    ?string $externalId = null
): int

/**
 * Add a synonym to a term
 * @param int $termId The term ID
 * @param string $synonym The synonym word
 * @param string $relationshipType Relationship type
 * @param float $weight Relevance weight (0.0-1.0)
 * @param string $source Source identifier
 * @return int The synonym ID
 */
public function addSynonym(
    int $termId,
    string $synonym,
    string $relationshipType = 'exact',
    float $weight = 0.8,
    string $source = 'local'
): int

/**
 * Get synonyms for a term
 * @param string $term The term to look up
 * @param float $minWeight Minimum weight threshold
 * @param int $limit Maximum synonyms to return
 * @return array Array of synonym objects
 */
public function getSynonyms(
    string $term,
    float $minWeight = 0.6,
    int $limit = 5
): array

/**
 * Expand a search query with synonyms
 * @param string $query Original search query
 * @param int $limit Max synonyms per term
 * @return array Associative array [term => [synonyms]]
 */
public function expandQuery(string $query, int $limit = 5): array

/**
 * Import local JSON synonym files
 * @return array Stats: ['terms' => N, 'synonyms' => M]
 */
public function importLocalSynonyms(): array

/**
 * Export synonyms to Elasticsearch format
 * @param string|null $path Output file path
 * @return string Path to generated file
 */
public function exportToElasticsearch(?string $path = null): string
```

---

## Admin Module

### Module Structure

```
ahgThemeB5Plugin/modules/semanticSearchAdmin/
├── actions/
│   └── actions.class.php
├── config/
│   └── module.yml
└── templates/
    ├── indexSuccess.php       # Dashboard
    ├── configSuccess.php      # Settings form
    ├── termsSuccess.php       # Term browser
    ├── termAddSuccess.php     # Add term form
    ├── termViewSuccess.php    # View term details
    ├── syncLogsSuccess.php    # Sync history
    └── searchLogsSuccess.php  # Search log viewer
```

### Actions

| Action | Method | Description |
|--------|--------|-------------|
| index | GET | Dashboard with stats |
| config | GET/POST | Settings configuration |
| terms | GET | Browse thesaurus terms |
| termAdd | GET/POST | Add custom term |
| termView | GET | View term details |
| syncLogs | GET | View sync history |
| searchLogs | GET | View search logs |
| runSync | POST | Execute sync (AJAX) |
| testExpand | GET | Test query expansion (AJAX) |

---

## Logging

### Log Configuration

**Location:** `/usr/share/nginx/archive/logs/semantic_search.log`

```php
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

$logger = new Logger('semantic_search');
$logger->pushHandler(new RotatingFileHandler(
    '/usr/share/nginx/archive/logs/semantic_search.log',
    7,
    Logger::INFO
));
```

### Log Levels

| Level | Usage |
|-------|-------|
| INFO | Sync started/completed, terms imported |
| WARNING | Rate limits hit, API timeouts |
| ERROR | Sync failures, database errors |
| DEBUG | Individual term processing (disabled in production) |

---

## Performance Considerations

### Caching

```php
// Static cache for repeated lookups within a request
private static array $synonymCache = [];

public function getSynonyms(string $term): array
{
    if (isset(self::$synonymCache[$term])) {
        return self::$synonymCache[$term];
    }

    // ... fetch from database ...

    self::$synonymCache[$term] = $results;
    return $results;
}
```

### Indexing

Ensure proper indexes on:
- `ahg_thesaurus_term.term` (for lookups)
- `ahg_thesaurus_synonym.synonym` (for reverse lookups)
- `ahg_thesaurus_synonym.weight` (for filtering)

### Rate Limiting

External API calls are rate-limited:
- **Datamuse API:** 100ms between requests
- **Wikidata SPARQL:** 500ms between requests

---

## Security

### Access Control

All admin actions require administrator credentials:

```php
public function preExecute()
{
    if (!$this->context->user->hasCredential('administrator')) {
        $this->forward('admin', 'secure');
    }
}
```

### Input Validation

```php
// Sanitize search input
$term = trim(strtolower($request->getParameter('term', '')));
$term = preg_replace('/[^a-z0-9\s\-]/', '', $term);

// Validate numeric parameters
$weight = max(0, min(1, (float)$request->getParameter('weight', 0.8)));
$limit = max(1, min(20, (int)$request->getParameter('limit', 5)));
```

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| No synonyms returned | Table empty | Run `thesaurus:import-local` |
| Sync fails | API rate limit | Wait and retry |
| Slow queries | Missing indexes | Check database indexes |
| ES synonyms not working | File not reloaded | Restart Elasticsearch |

### Diagnostic Queries

```sql
-- Check term count by source
SELECT source, COUNT(*) as count
FROM ahg_thesaurus_term
GROUP BY source;

-- Check synonym coverage
SELECT t.term, COUNT(s.id) as synonyms
FROM ahg_thesaurus_term t
LEFT JOIN ahg_thesaurus_synonym s ON t.id = s.term_id
GROUP BY t.id
ORDER BY synonyms DESC
LIMIT 20;

-- Check recent syncs
SELECT * FROM ahg_thesaurus_sync_log
ORDER BY started_at DESC
LIMIT 10;
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | Jan 2026 | Initial release |

---

*Document Version: 1.0*
*Last Updated: January 2026*
*Author: The Archive and Heritage Group (Pty) Ltd*
