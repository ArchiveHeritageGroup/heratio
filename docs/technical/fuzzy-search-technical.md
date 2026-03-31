# Fuzzy Search - Technical Documentation

**Plugin:** ahgDisplayPlugin
**Version:** 3.2.23+
**Last Updated:** February 2026

---

## 1. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                         FUZZY SEARCH ARCHITECTURE                                │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌────────────────────────────────────────────────────────────────────────────┐ │
│  │                        LAYER 1: SPELL CORRECTION                           │ │
│  │                                                                            │ │
│  │  FuzzySearchService.php                                                    │ │
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐                       │ │
│  │  │ Levenshtein  │ │   SOUNDEX    │ │  Metaphone   │                       │ │
│  │  │ (edit dist)  │ │  (phonetic)  │ │  (phonetic)  │                       │ │
│  │  └──────────────┘ └──────────────┘ └──────────────┘                       │ │
│  │                                                                            │ │
│  │  Vocabulary Sources:                                                       │ │
│  │  • display_facet_cache (616 terms)                                        │ │
│  │  • ahg_thesaurus_term (2,946 terms, try/catch)                            │ │
│  │  • term_i18n (taxonomy 35/42/78)                                          │ │
│  │  • actor_i18n (creator names)                                             │ │
│  └────────────────────────────────────────────────────────────────────────────┘ │
│                                      │                                           │
│                                      ▼                                           │
│  ┌────────────────────────────────────────────────────────────────────────────┐ │
│  │                     LAYER 2: FULLTEXT SEARCH                               │ │
│  │                                                                            │ │
│  │  MySQL FULLTEXT indexes on i18n tables                                     │ │
│  │  ┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐          │ │
│  │  │ ft_ioi_title     │ │ ft_ioi_scope     │ │ ft_ai_name       │          │ │
│  │  │ (title)          │ │ (scope_and_      │ │ (authorized_     │          │ │
│  │  │                  │ │  content)        │ │  form_of_name)   │          │ │
│  │  └──────────────────┘ └──────────────────┘ └──────────────────┘          │ │
│  │  ┌──────────────────┐                                                     │ │
│  │  │ ft_ti_name       │  Falls back to LIKE %term% if indexes missing      │ │
│  │  │ (term name)      │                                                     │ │
│  │  └──────────────────┘                                                     │ │
│  └────────────────────────────────────────────────────────────────────────────┘ │
│                                      │                                           │
│                                      ▼                                           │
│  ┌────────────────────────────────────────────────────────────────────────────┐ │
│  │                  LAYER 3: ELASTICSEARCH FUZZY FALLBACK                      │ │
│  │                                                                            │ │
│  │  Activated when SQL returns 0 results                                     │ │
│  │  Uses multi_match with fuzziness: AUTO                                    │ │
│  │                                                                            │ │
│  │  Fields searched:                                                          │ │
│  │  • i18n.en.title (boost: 3)                                              │ │
│  │  • i18n.en.scopeAndContent (boost: 1)                                    │ │
│  │  • display.creator (boost: 2)                                             │ │
│  │  • autocomplete (boost: 2)                                                │ │
│  │                                                                            │ │
│  │  Returns up to 200 matching IDs → rebuilds SQL query with whereIn()       │ │
│  └────────────────────────────────────────────────────────────────────────────┘ │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_bff86c89.png)
```

---

## 2. Files

### New Files

| File | Purpose |
|------|---------|
| `ahgDisplayPlugin/lib/Services/FuzzySearchService.php` | Core spell-correction service |
| `ahgDisplayPlugin/database/fulltext_indexes.sql` | FULLTEXT index definitions |
| `ahgDisplayPlugin/lib/task/ahgAddFulltextIndexesTask.class.php` | CLI task to create indexes |

### Modified Files

| File | Changes |
|------|---------|
| `ahgDisplayPlugin/modules/display/actions/actions.class.php` | Fuzzy correction in `executeBrowse()`, FULLTEXT in `applyTextSearchFilter()`, ES fallback, new helper methods |
| `ahgDisplayPlugin/modules/display/templates/browseSuccess.php` | "Did you mean?" and auto-correct alert banners |

---

## 3. FuzzySearchService

### Class Structure

```php
namespace AhgDisplay\Services;

class FuzzySearchService
{
    private array $vocabulary = [];      // normalized => original
    private array $soundexIndex = [];    // soundex_code => [terms]
    private array $metaphoneIndex = [];  // metaphone_code => [terms]

    public function loadVocabulary(): void;
    public function correctQuery(string $query): array;
    private function findLevenshteinMatch(string $word): ?array;
    private function findPhoneticMatch(string $word): ?array;
    private function buildPhoneticIndexes(): void;
}
```

### correctQuery() Return Value

```php
[
    'original'    => string,          // Original query
    'corrected'   => string|null,     // Corrected query (null if no correction)
    'suggestion'  => string|null,     // Same as corrected
    'confidence'  => float,           // 0.0 - 1.0
    'corrections' => array,           // Per-word correction details
    'method'      => string|null      // 'levenshtein', 'soundex', 'metaphone', or null
]
```

### Vocabulary Sources

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  VOCABULARY LOADING (in order)                                                   │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  1. display_facet_cache                                                          │
│     ~616 terms (subjects, places, genres, creators, levels)                     │
│     Always available on any installation                                        │
│                                                                                  │
│  2. ahg_thesaurus_term (try/catch)                                              │
│     ~2,946 terms from ahgSemanticSearchPlugin                                   │
│     Only available if semantic search plugin is installed                        │
│                                                                                  │
│  3. term_i18n                                                                    │
│     Terms from taxonomies 35 (subject), 42 (place), 78 (genre)                 │
│     Core AtoM terms                                                             │
│                                                                                  │
│  4. actor_i18n                                                                   │
│     Creator/authority names                                                      │
│     Enables name correction                                                      │
│                                                                                  │
│  Total: ~5,000 terms                                                            │
│  Performance: PHP levenshtein() is C-implemented → <5ms per word                │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_7a263307.png)
```

### Levenshtein Thresholds

| Word Length | Max Edit Distance | Example |
|-------------|-------------------|---------|
| 1-5 chars | 2 | "musem" → "museum" (distance 1) |
| 6+ chars | 3 | "archieves" → "archives" (distance 2) |

### Confidence Thresholds

| Confidence | Action |
|------------|--------|
| >= 0.9 | Auto-correct (replace query, show "Showing results for X") |
| 0.5 - 0.89 | Suggest ("Did you mean: X?") |
| < 0.5 | No suggestion shown |

---

## 4. FULLTEXT Indexes

### Index Definitions

```sql
CREATE FULLTEXT INDEX ft_ioi_title ON information_object_i18n(title);
CREATE FULLTEXT INDEX ft_ioi_scope ON information_object_i18n(scope_and_content);
CREATE FULLTEXT INDEX ft_ai_name ON actor_i18n(authorized_form_of_name);
CREATE FULLTEXT INDEX ft_ti_name ON term_i18n(name);
```

### Installation

```bash
php symfony ahg:add-fulltext-indexes
```

The task is idempotent - checks if indexes exist before creating.

### Behavior

- **With indexes**: Uses `MATCH(column) AGAINST(? IN NATURAL LANGUAGE MODE)` for relevance-ranked results
- **Without indexes**: Falls back to `LIKE %term%` (exact substring matching)
- Detection is cached per request via `isFulltextAvailable()` static property

---

## 5. Elasticsearch Fuzzy Fallback

### Trigger Condition

ES fuzzy is called only when:
1. The primary SQL query returns 0 results
2. A search query exists
3. `SearchEngineFactory` class is available

### Query Structure

```json
{
  "query": {
    "multi_match": {
      "query": "archieves",
      "fields": [
        "i18n.en.title^3",
        "i18n.en.scopeAndContent",
        "display.creator^2",
        "autocomplete^2"
      ],
      "fuzziness": "AUTO",
      "type": "best_fields"
    }
  },
  "size": 200,
  "_source": false
}
```

### Fuzziness AUTO Behavior

| Term Length | Max Edits Allowed |
|-------------|-------------------|
| 1-2 chars | Exact match only |
| 3-5 chars | 1 edit |
| 6+ chars | 2 edits |

---

## 6. Request Flow

```
executeBrowse()
│
├─ 1. Read query from request: $this->queryFilter
│
├─ 2. FuzzySearchService.correctQuery($this->queryFilter)
│     │
│     ├─ loadVocabulary()
│     ├─ For each word:
│     │   ├─ findLevenshteinMatch()
│     │   └─ findPhoneticMatch() (if no Levenshtein match)
│     │
│     ├─ confidence >= 0.9 → auto-correct ($this->queryFilter = corrected)
│     └─ confidence < 0.9  → set $this->didYouMean
│
├─ 3. applyTextSearchFilter($this->queryFilter)
│     │
│     ├─ isFulltextAvailable()?
│     │   ├─ YES → MATCH ... AGAINST (natural language mode)
│     │   └─ NO  → LIKE %term%
│     │
│     └─ Identifier field always uses LIKE
│
├─ 4. Execute query, get $this->total
│
├─ 5. If $this->total === 0 → tryElasticsearchFuzzy()
│     │
│     ├─ class_exists('SearchEngineFactory')?
│     │   ├─ YES → multi_match with fuzziness:AUTO → get IDs
│     │   │        Rebuild query with whereIn('io.id', $esIds)
│     │   └─ NO  → skip (no ES available)
│     │
│     └─ All wrapped in try/catch (ES failure never breaks browse)
│
└─ 6. Template renders alerts:
      ├─ $this->didYouMean → "Did you mean: X?" info alert
      ├─ $this->correctedQuery → "Showing results for X" success alert
      └─ $this->esAssistedSearch → "Fuzzy matches" warning alert
```

---

## 7. Template Alerts

Three alert types in `browseSuccess.php` (no `<script>` or `<style>` tags, no CSP nonce needed):

| Variable | Alert Type | Content |
|----------|-----------|---------|
| `$didYouMean` | `alert-info` | "Did you mean: [link]?" |
| `$correctedQuery` | `alert-success` | "Showing results for X. Search instead for: [link]" |
| `$esAssistedSearch` | `alert-warning` | "No exact matches. Showing fuzzy matches from search index." |

The "Search instead for" link appends `&noCorrect=1` to bypass correction.

---

## 8. Graceful Degradation

```
┌──────────────────────────────┬──────────────────────────────────────────────┐
│  Scenario                    │  Behavior                                    │
├──────────────────────────────┼──────────────────────────────────────────────┤
│  All layers available        │  Full correction + FULLTEXT + ES fallback   │
│  ES not running              │  Levenshtein + FULLTEXT still work          │
│  No FULLTEXT indexes         │  Falls back to LIKE, Levenshtein + ES work  │
│  No ahgSemanticSearchPlugin  │  Vocabulary from facet_cache + term_i18n    │
│  All layers unavailable      │  Behaves exactly like pre-fuzzy search      │
│                              │  (LIKE %term%)                              │
└──────────────────────────────┴──────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_149b9a9a.png)
```

Every layer is wrapped in try/catch. A failure in any layer never breaks the browse page.

---

## 9. CLI Commands

### Create FULLTEXT Indexes

```bash
php symfony ahg:add-fulltext-indexes
```

Creates the four FULLTEXT indexes. Idempotent (checks existence first). Non-blocking on MySQL 8.0.12+.

---

## 10. Configuration

No configuration is needed. Fuzzy search activates automatically on the GLAM Browse page. The `noCorrect` URL parameter is the only user-facing control.

---

## 11. Dependencies

| Dependency | Required | Purpose |
|------------|----------|---------|
| MySQL 8 InnoDB | Yes | FULLTEXT indexes, Levenshtein vocabulary queries |
| Elasticsearch/OpenSearch | No | Fuzzy fallback (graceful degradation) |
| ahgSemanticSearchPlugin | No | Adds thesaurus vocabulary (graceful degradation) |
| PHP `levenshtein()` | Yes | Built-in PHP function (C implementation) |
| PHP `soundex()` | Yes | Built-in PHP function |
| PHP `metaphone()` | Yes | Built-in PHP function |

---

*Part of the AtoM AHG Framework - ahgDisplayPlugin*
