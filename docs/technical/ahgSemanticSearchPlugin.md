# ahgSemanticSearchPlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Search
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

AI-powered semantic search plugin that enhances AtoM search capabilities through thesaurus-based query expansion, vector embeddings, and integration with external knowledge sources (WordNet/Datamuse, Wikidata). Provides intelligent synonym matching, multilingual support, and Elasticsearch integration for improved search relevance in GLAM institutions.

---

## Architecture

```
+---------------------------------------------------------------------+
|                    ahgSemanticSearchPlugin                          |
+---------------------------------------------------------------------+
|                                                                     |
|  +-------------------------+    +----------------------------+      |
|  |    ThesaurusService     |    |   SemanticSearchService    |      |
|  |  - Term management      |    |  - Query expansion         |      |
|  |  - Synonym relationships|    |  - ES query building       |      |
|  |  - ES export            |    |  - Search logging          |      |
|  +-------------------------+    +----------------------------+      |
|            |                              |                         |
|            v                              v                         |
|  +-------------------------+    +----------------------------+      |
|  |  WordNetSyncService     |    |    EmbeddingService        |      |
|  |  - Datamuse API sync    |    |  - Ollama integration      |      |
|  |  - Domain vocabularies  |    |  - Vector generation       |      |
|  |  - Archival/Library/    |    |  - Cosine similarity       |      |
|  |    Museum terms         |    |  - Semantic clustering     |      |
|  +-------------------------+    +----------------------------+      |
|            |                              |                         |
|            v                              v                         |
|  +-------------------------+    +----------------------------+      |
|  |  WikidataSyncService    |    |      Cron Scheduler        |      |
|  |  - SPARQL queries       |    |  - Weekly full sync        |      |
|  |  - Heritage classes     |    |  - Daily embeddings        |      |
|  |  - Multilingual labels  |    |  - ES export               |      |
|  +-------------------------+    +----------------------------+      |
|                                                                     |
|                          |                                          |
|                          v                                          |
|  +-----------------------------------------------------------+     |
|  |                   Database Tables                          |     |
|  |  ahg_thesaurus_term | ahg_thesaurus_synonym                |     |
|  |  ahg_thesaurus_embedding | ahg_semantic_search_log         |     |
|  |  ahg_thesaurus_sync_log | ahg_semantic_search_settings     |     |
|  +-----------------------------------------------------------+     |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+---------------------------+       +---------------------------+
|   ahg_thesaurus_term      |       |   ahg_thesaurus_synonym   |
+---------------------------+       +---------------------------+
| PK id BIGINT              |<------| FK term_id BIGINT         |
|    term VARCHAR(255)      |       | FK synonym_term_id BIGINT |
|    normalized_term        |       |    synonym_text VARCHAR   |
|    language VARCHAR(10)   |       |    relationship_type      |
|    source VARCHAR(50)     |       |    weight DECIMAL(3,2)    |
|    source_id VARCHAR(255) |       |    source VARCHAR(50)     |
|    definition TEXT        |       |    is_bidirectional       |
|    pos VARCHAR(20)        |       |    is_active TINYINT      |
|    domain VARCHAR(100)    |       |    created_at TIMESTAMP   |
|    frequency INT          |       |    updated_at TIMESTAMP   |
|    is_preferred TINYINT   |       +---------------------------+
|    is_active TINYINT      |
|    created_at TIMESTAMP   |       +---------------------------+
|    updated_at TIMESTAMP   |       |  ahg_thesaurus_embedding  |
+---------------------------+       +---------------------------+
            |                       | PK id BIGINT              |
            |                       | FK term_id BIGINT         |----+
            +---------------------->|    model VARCHAR(100)     |    |
                                    |    embedding LONGBLOB     |    |
                                    |    embedding_dimension    |    |
                                    |    created_at TIMESTAMP   |    |
                                    |    updated_at TIMESTAMP   |    |
                                    +---------------------------+    |
                                                                     |
+---------------------------+       +---------------------------+    |
| ahg_semantic_search_log   |       | ahg_thesaurus_sync_log    |    |
+---------------------------+       +---------------------------+    |
| PK id BIGINT              |       | PK id BIGINT              |    |
|    original_query VARCHAR |       |    source VARCHAR(50)     |    |
|    expanded_query TEXT    |       |    sync_type VARCHAR(50)  |    |
|    expansion_terms TEXT   |       |    status VARCHAR(20)     |    |
|    result_count INT       |       |    terms_processed INT    |    |
|    search_time_ms INT     |       |    terms_added INT        |    |
|    user_id INT            |       |    terms_updated INT      |    |
|    session_id VARCHAR     |       |    synonyms_added INT     |    |
|    created_at TIMESTAMP   |       |    errors TEXT            |    |
+---------------------------+       |    started_at TIMESTAMP   |    |
                                    |    completed_at TIMESTAMP |    |
                                    +---------------------------+    |
                                                                     |
+---------------------------+                                        |
|ahg_semantic_search_settings|                                       |
+---------------------------+                                        |
| PK id BIGINT              |                                        |
|    setting_key VARCHAR    |                                        |
|    setting_value TEXT     |                                        |
|    setting_type VARCHAR   |                                        |
|    description TEXT       |                                        |
|    created_at TIMESTAMP   |                                        |
|    updated_at TIMESTAMP   |                                        |
+---------------------------+                                        |
```

### SQL Schema

```sql
-- Main thesaurus terms table
CREATE TABLE ahg_thesaurus_term (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term VARCHAR(255) NOT NULL,
    normalized_term VARCHAR(255) NOT NULL,
    language VARCHAR(10) DEFAULT 'en',
    source VARCHAR(50) NOT NULL,        -- wordnet, wikidata, local
    source_id VARCHAR(255) NULL,        -- External ID
    definition TEXT NULL,
    pos VARCHAR(20) NULL,               -- Part of speech
    domain VARCHAR(100) NULL,           -- archival, library, museum, general
    frequency INT DEFAULT 0,
    is_preferred TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_term_source (normalized_term, source, language),
    INDEX idx_term (term),
    INDEX idx_normalized (normalized_term),
    INDEX idx_domain (domain),
    INDEX idx_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Synonym relationships
CREATE TABLE ahg_thesaurus_synonym (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id BIGINT UNSIGNED NOT NULL,
    synonym_term_id BIGINT UNSIGNED NULL,
    synonym_text VARCHAR(255) NOT NULL,
    relationship_type VARCHAR(50) DEFAULT 'synonym',  -- synonym, broader, narrower, related, use_for
    weight DECIMAL(3,2) DEFAULT 1.00,
    source VARCHAR(50) NOT NULL,
    is_bidirectional TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (term_id) REFERENCES ahg_thesaurus_term(id) ON DELETE CASCADE,
    UNIQUE KEY uk_term_synonym (term_id, synonym_text, relationship_type),
    INDEX idx_synonym_text (synonym_text),
    INDEX idx_weight (weight)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vector embeddings for semantic similarity
CREATE TABLE ahg_thesaurus_embedding (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id BIGINT UNSIGNED NOT NULL,
    model VARCHAR(100) NOT NULL,        -- Ollama model name
    embedding LONGBLOB NOT NULL,        -- Serialized vector
    embedding_dimension INT NOT NULL,   -- e.g., 768, 1536
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (term_id) REFERENCES ahg_thesaurus_term(id) ON DELETE CASCADE,
    UNIQUE KEY uk_term_model (term_id, model)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Relationship Types

| Type | Direction | Description |
|------|-----------|-------------|
| synonym | Bidirectional | Equivalent meaning (archive = repository) |
| broader | Hierarchical | Parent concept (document > letter) |
| narrower | Hierarchical | Child concept (letter < document) |
| related | Bidirectional | Associated concepts (archive ~ preservation) |
| use_for | Unidirectional | Preferred term mapping (fonds USE collection) |

---

## Service Methods

### ThesaurusService

```php
namespace AtomFramework\Services\SemanticSearch;

class ThesaurusService
{
    // Term management
    public function addTerm(string $term, string $source, string $language, array $options): ?int
    public function getTerm(int $id): ?object
    public function findTerm(string $term, ?string $source, string $language): ?object
    public function searchTerms(string $query, int $limit = 20): array
    public function normalizeTerm(string $term): string

    // Synonym management
    public function addSynonym(int $termId, string $synonymText, string $source, string $type, float $weight): ?int
    public function getSynonyms(int $termId, ?string $type, ?float $minWeight, int $limit): array
    public function getSynonymsForText(string $term, string $language = 'en'): array

    // Query expansion
    public function expandQuery(string $query, string $language = 'en'): array

    // Elasticsearch export
    public function exportToElasticsearch(?string $outputPath = null): string
    public function getElasticsearchConfig(): array

    // Local import
    public function importLocalSynonyms(?string $domain = null): array

    // Settings
    public function getSetting(string $key, $default = null)
    public function setSetting(string $key, $value): bool

    // Statistics
    public function getStats(): array
}
```

### SemanticSearchService

```php
class SemanticSearchService
{
    // Search with expansion
    public function search(string $query, array $options = []): array
    public function buildElasticsearchQuery(string $query, ?array $expansion, array $options): array

    // Expansion info
    public function getExpansionInfo(string $query, string $language = 'en'): array

    // Suggestions
    public function getSuggestions(string $prefix, int $limit = 10): array
    public function getDidYouMean(string $query): array

    // Configuration
    public function isEnabled(): bool
    public function enable(): void
    public function disable(): void

    // Analytics
    public function getPopularSearches(int $limit = 20, ?string $period = null): array
    public function getExpansionStats(): array
}
```

### EmbeddingService

```php
class EmbeddingService
{
    // Embedding models
    public const MODEL_NOMIC = 'nomic-embed-text';
    public const MODEL_MXBAI = 'mxbai-embed-large';
    public const MODEL_ALL_MINILM = 'all-minilm';

    // Availability
    public function isAvailable(): bool
    public function getAvailableModels(): array

    // Embedding generation
    public function getEmbedding(string $text, ?string $model = null): ?array
    public function getEmbeddings(array $texts, ?string $model = null): array

    // Term embeddings
    public function generateTermEmbedding(int $termId, ?string $model = null): bool
    public function getTermEmbedding(int $termId, ?string $model = null): ?array
    public function generateAllEmbeddings(?string $model = null): array

    // Similarity search
    public function cosineSimilarity(array $a, array $b): float
    public function findSimilarTerms(string $query, int $limit = 10, float $minSimilarity = 0.7): array
    public function findRelatedTerms(int $termId, int $limit = 10): array

    // Statistics
    public function getStats(): array
}
```

### WordNetSyncService

```php
class WordNetSyncService
{
    // Domain sync methods
    public function syncArchivalTerms(): array    // ~150 terms
    public function syncLibraryTerms(): array     // ~55 terms
    public function syncMuseumTerms(): array      // ~65 terms
    public function syncGeneralTerms(): array     // ~300 terms
    public function syncSouthAfricanTerms(): array // ~120 terms
    public function syncHistoricalTerms(): array   // ~40 terms
    public function syncAllDomains(): array       // All 730+ terms

    // Custom sync
    public function syncTerms(array $terms, string $domain): array
    public function syncCustomTerms(array $terms, string $domain): array
    public function syncDomain(string $domain, int $limit = 0): array

    // Datamuse API
    public function fetchSynonyms(string $word): array
    public function fetchRelatedWords(string $word): array
    public function fetchDefinitions(string $word): array
    public function fetchSoundsLike(string $word): array
    public function fetchSpelledLike(string $word): array
}
```

### WikidataSyncService

```php
class WikidataSyncService
{
    // Sync operations
    public function syncHeritageTerms(): array
    public function syncSouthAfricanTerms(): array
    public function syncArchivalTerms(int $limit = 0): array
    public function syncClassAndSubclasses(string $qid, string $domain): array

    // SPARQL queries
    public function fetchItem(string $qid): ?array
    public function fetchSubclasses(string $parentQid, int $limit = null): array
    public function fetchArchiveTerms(): array
    public function fetchSouthAfricanHeritage(): array
}
```

---

## Configuration

### Settings Table (ahg_semantic_search_settings)

| Setting Key | Default | Type | Description |
|-------------|---------|------|-------------|
| semantic_search_enabled | true | bool | Enable semantic search |
| default_expansion_limit | 5 | int | Max synonyms per term |
| min_synonym_weight | 0.6 | string | Minimum weight threshold |
| datamuse_rate_limit_ms | 100 | int | Datamuse API rate limit |
| wikidata_rate_limit_ms | 500 | int | Wikidata API rate limit |
| ollama_endpoint | http://localhost:11434 | string | Ollama API endpoint |
| ollama_model | nomic-embed-text | string | Embedding model |
| elasticsearch_synonyms_path | /etc/elasticsearch/synonyms/ahg_synonyms.txt | string | ES synonyms file path |
| show_expansion_info | true | bool | Show expansion to users |
| cache_ttl_seconds | 86400 | int | Cache TTL (24 hours) |
| last_cron_sync | 0 | int | Last sync timestamp |

---

## Query Expansion

### How It Works

```
User Query: "historical documents"
     |
     v
+------------------+
| Tokenize Query   |
| ["historical",   |
|  "documents"]    |
+------------------+
     |
     v
+------------------+
| Find Synonyms    |
| historical ->    |
|   [ancient,      |
|    archival,     |
|    heritage]     |
| documents ->     |
|   [records,      |
|    papers,       |
|    files]        |
+------------------+
     |
     v
+------------------+
| Expanded Query   |
| "historical      |
|  documents       |
|  ancient         |
|  archival        |
|  heritage        |
|  records         |
|  papers files"   |
+------------------+
     |
     v
+------------------+
| ES Query Builder |
| - must: original |
| - should: syns   |
| - boost weights  |
+------------------+
```

### Expansion Result Structure

```php
[
    'original_query' => 'historical documents',
    'expanded_query' => 'historical documents ancient archival heritage records papers files',
    'expanded_terms' => [
        'historical' => ['ancient', 'archival', 'heritage'],
        'documents' => ['records', 'papers', 'files'],
    ],
    'expansions' => [
        ['text' => 'ancient', 'weight' => 0.85, 'type' => 'synonym', 'source' => 'wordnet'],
        ['text' => 'archival', 'weight' => 0.92, 'type' => 'related', 'source' => 'local'],
        // ...
    ],
    'expansion_count' => 6,
]
```

---

## Vector Embeddings

### Ollama Integration

The plugin uses Ollama for local vector embedding generation, supporting multiple models:

| Model | Dimensions | Use Case |
|-------|------------|----------|
| nomic-embed-text | 768 | General purpose, fast |
| mxbai-embed-large | 1024 | High accuracy |
| all-minilm | 384 | Lightweight, fast |

### Embedding Generation Flow

```
Term "archive"
     |
     v
+----------------------+
| Get term + definition|
| "archive: a place    |
|  where historical    |
|  records are kept"   |
+----------------------+
     |
     v
+----------------------+
| Ollama API Request   |
| POST /api/embeddings |
| model: nomic-embed   |
| prompt: text         |
+----------------------+
     |
     v
+----------------------+
| Response             |
| [0.012, -0.089, ...] |
| (768 dimensions)     |
+----------------------+
     |
     v
+----------------------+
| Store in DB          |
| ahg_thesaurus_       |
| embedding table      |
+----------------------+
```

### Cosine Similarity Search

```php
// Find semantically similar terms
$similar = $embeddingService->findSimilarTerms('archive', 10, 0.7);

// Returns:
[
    ['term' => 'repository', 'similarity' => 0.92],
    ['term' => 'collection', 'similarity' => 0.88],
    ['term' => 'depot', 'similarity' => 0.85],
    // ...
]
```

---

## External Data Sources

### WordNet/Datamuse API

**Endpoint:** https://api.datamuse.com

| API Path | Purpose |
|----------|---------|
| /words?rel_syn=X | Get synonyms |
| /words?rel_trg=X | Get triggered/related words |
| /words?sp=X&md=d | Get definitions |
| /words?sl=X | Get phonetically similar words |

### Wikidata SPARQL

**Endpoint:** https://query.wikidata.org/sparql

**Heritage Classes:**
- Q210272: cultural heritage
- Q2668072: archive
- Q7075: library
- Q33506: museum
- Q234460: historical document

### Domain Term Coverage

| Domain | Terms | Source |
|--------|-------|--------|
| Archival | ~150 | WordNet |
| Library | ~55 | WordNet |
| Museum | ~65 | WordNet |
| General | ~300 | WordNet |
| South African | ~120 | WordNet |
| Historical | ~40 | WordNet |
| Heritage | Variable | Wikidata |

---

## CLI Commands

### ThesaurusCommand

```bash
# Show statistics
php bin/atom thesaurus:stats

# WordNet sync
php bin/atom thesaurus:sync-wordnet --archival
php bin/atom thesaurus:sync-wordnet --library
php bin/atom thesaurus:sync-wordnet --museum
php bin/atom thesaurus:sync-wordnet --general
php bin/atom thesaurus:sync-wordnet --south-african
php bin/atom thesaurus:sync-wordnet --historical
php bin/atom thesaurus:sync-wordnet --all    # All 730+ terms

# Custom terms
php bin/atom thesaurus:sync-wordnet archive document manuscript

# Wikidata sync
php bin/atom thesaurus:sync-wikidata --heritage
php bin/atom thesaurus:sync-wikidata --south-african

# Local import
php bin/atom thesaurus:import-local archival

# Elasticsearch export
php bin/atom thesaurus:export-elasticsearch

# Query expansion test
php bin/atom thesaurus:expand "historical documents"

# Search
php bin/atom thesaurus:search archive

# Vector embeddings
php bin/atom thesaurus:embeddings archive
php bin/atom thesaurus:embeddings --generate-all
```

### Cron Script

```bash
# Full sync (all tasks)
php bin/semantic-search-cron.php all

# Individual tasks
php bin/semantic-search-cron.php sync-wordnet
php bin/semantic-search-cron.php sync-wikidata
php bin/semantic-search-cron.php update-embeddings
php bin/semantic-search-cron.php export-es
php bin/semantic-search-cron.php cleanup

# Options
--domain=archival    # Filter by domain
--limit=500          # Limit terms processed
--force              # Force sync even if recent
--dry-run            # Show what would happen
--quiet              # Suppress output
```

---

## Scheduled Tasks (Cron)

```
# Weekly full sync (Sunday 2:00 AM)
0 2 * * 0 www-data php /path/to/bin/semantic-search-cron.php all --quiet

# Daily embedding updates (3:00 AM)
0 3 * * * www-data php /path/to/bin/semantic-search-cron.php update-embeddings --limit=500

# Daily Elasticsearch export (4:00 AM)
0 4 * * * www-data php /path/to/bin/semantic-search-cron.php export-es

# Monthly cleanup (1st of month, 1:00 AM)
0 1 1 * * www-data php /path/to/bin/semantic-search-cron.php cleanup
```

### Installation

```bash
sudo cp /usr/share/nginx/archive/plugins/ahgSemanticSearchPlugin/config/cron.d/ahg-semantic-search /etc/cron.d/
```

---

## Elasticsearch Integration

### Synonym File Format

```
# AtoM Semantic Search Synonyms
# Generated: 2026-01-30 10:00:00
# Format: term => synonym1, synonym2, synonym3

archive => repository, depot, collection
document => record, paper, file
photograph => photo, picture, image
manuscript => ms, handwritten document
```

### Elasticsearch Configuration

```json
{
  "analysis": {
    "filter": {
      "ahg_synonyms": {
        "type": "synonym",
        "synonyms_path": "/etc/elasticsearch/synonyms/ahg_synonyms.txt",
        "updateable": true
      }
    },
    "analyzer": {
      "ahg_semantic_analyzer": {
        "tokenizer": "standard",
        "filter": [
          "lowercase",
          "ahg_synonyms",
          "snowball"
        ]
      }
    }
  }
}
```

---

## Admin Interface

### Routes

| Route | Action | Description |
|-------|--------|-------------|
| /admin/semantic-search | index | Dashboard |
| /admin/semantic-search/config | config | Settings |
| /admin/semantic-search/terms | terms | Term browser |
| /admin/semantic-search/term/:id | termView | Term details |
| /admin/semantic-search/term/add | termAdd | Add custom term |
| /admin/semantic-search/sync-logs | syncLogs | Sync history |
| /admin/semantic-search/search-logs | searchLogs | Search analytics |

### AJAX Endpoints

| Route | Action | Description |
|-------|--------|-------------|
| /semanticSearchAdmin/runSync | runSync | Trigger sync |
| /semanticSearchAdmin/testExpand | testExpand | Test query expansion |

---

## Python Integration

The plugin can optionally integrate with Python services for advanced NLP:

**Location:** `/usr/share/nginx/archive/atom-ahg-python/src/atom_ahg/resources/`

| Script | Purpose |
|--------|---------|
| embeddings.py | Sentence transformer embeddings |
| similarity.py | Semantic similarity computation |
| clustering.py | Term clustering |

---

## Ollama Setup

### Installation

```bash
# Install Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Pull embedding model
ollama pull nomic-embed-text

# Start Ollama service
sudo systemctl enable ollama
sudo systemctl start ollama

# Verify
curl http://localhost:11434/api/tags
```

### Configuration

```php
// Settings table
ollama_endpoint = 'http://localhost:11434'
ollama_model = 'nomic-embed-text'
```

---

## Performance Considerations

### Rate Limiting

| API | Rate Limit | Purpose |
|-----|------------|---------|
| Datamuse | 100ms | Prevent API abuse |
| Wikidata | 500ms | SPARQL query limits |
| Ollama | 100ms | Local resource management |

### Batch Processing

- Embeddings: Process in batches of 10
- Sync: Maximum 1000 terms per run
- Cleanup: Removes entries older than 90 days

### Caching

- Settings cached in memory during request
- API responses cached for 24 hours
- Embeddings stored permanently until term update

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Ollama not available | Check `systemctl status ollama`, verify endpoint |
| Datamuse timeouts | Increase rate limit, check network |
| Empty expansions | Run sync to populate thesaurus |
| ES synonyms not loading | Verify file path, check ES logs |
| Slow embedding generation | Reduce batch size, check Ollama resources |

---

## File Structure

```
ahgSemanticSearchPlugin/
+-- bin/
|   +-- semantic-search-cron.php      # Cron job handler
+-- config/
|   +-- ahgSemanticSearchPluginConfiguration.class.php
|   +-- routing.yml
|   +-- cron.d/
|       +-- ahg-semantic-search       # Cron file
+-- database/
|   +-- install.sql                   # Initial schema
|   +-- migrations/
|       +-- 2026_01_21_semantic_search_tables.sql
+-- lib/
|   +-- Commands/
|   |   +-- ThesaurusCommand.php      # CLI command
|   +-- Services/
|       +-- ThesaurusService.php      # Core thesaurus
|       +-- SemanticSearchService.php # Search integration
|       +-- EmbeddingService.php      # Vector embeddings
|       +-- WordNetSyncService.php    # Datamuse API
|       +-- WikidataSyncService.php   # Wikidata SPARQL
+-- modules/
|   +-- semanticSearchAdmin/
|   |   +-- actions/
|   |   |   +-- actions.class.php
|   |   +-- config/
|   |   |   +-- module.yml
|   |   +-- templates/
|   |       +-- indexSuccess.php
|   |       +-- configSuccess.php
|   |       +-- termsSuccess.php
|   |       +-- termViewSuccess.php
|   |       +-- termAddSuccess.php
|   |       +-- syncLogsSuccess.php
|   |       +-- searchLogsSuccess.php
|   +-- searchEnhancement/
|       +-- actions/
|       |   +-- actions.class.php
|       +-- config/
|       |   +-- routing.yml
|       +-- templates/
|           +-- savedSearchesSuccess.php
|           +-- historySuccess.php
|           +-- adminTemplatesSuccess.php
|           +-- adminTemplateEditSuccess.php
+-- extension.json
```

---

*Part of the AtoM AHG Framework*
