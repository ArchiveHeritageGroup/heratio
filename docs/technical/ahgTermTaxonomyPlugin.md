# ahgTermTaxonomyPlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Browse
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

High-performance replacement for base AtoM's taxonomy browse (`/taxonomy/:id`) and term browse (`/term/:slug`). Eliminates N+1 query patterns by using batch count queries and direct Elasticsearch HTTP requests instead of Elastica ORM objects.

Replaces and retires `ahgTermBrowsePlugin`.

---

## Architecture

```
+-----------------------------------------------------------------------+
|                      ahgTermTaxonomyPlugin                             |
+-----------------------------------------------------------------------+
|                                                                        |
|  +---------------------------+      +----------------------------+    |
|  |    Plugin Configuration   |      |     Route Loading          |    |
|  | ahgTermTaxonomyPlugin     |      |  * routing.load_config     |    |
|  |   Configuration           |      |  * prependRoute()          |    |
|  | * PSR-4 autoloader        |      |                            |    |
|  | * Module registration     |      +----------------------------+    |
|  +---------------------------+                 |                       |
|               |                                |                       |
|               V                                V                       |
|  +------------------------------------------------------------+       |
|  |                  termTaxonomy Module                        |       |
|  +------------------------------------------------------------+       |
|  |                                                            |       |
|  |  executeIndex()              executeTaxonomyIndex()        |       |
|  |  /term/:slug                 /taxonomy/:id                 |       |
|  |  Term browse (IOs)           Taxonomy listing (terms)      |       |
|  |                                                            |       |
|  +------------------------------------------------------------+       |
|               |                          |                             |
|               V                          V                             |
|  +------------------------------------------------------------+       |
|  |              TermTaxonomyService                            |       |
|  +------------------------------------------------------------+       |
|  |                         |                       |          |       |
|  |  browse()               |  browseTaxonomy()     |  batch   |       |
|  |  * IO search for term   |  * Term search in     |  counts  |       |
|  |  * Facets + aggs        |    taxonomy            |          |       |
|  |  * Direct count         |  * Text search         |  batch   |       |
|  |                         |  * Sort + paginate     |  Count   |       |
|  |  loadListTerms()        |                       |  Related |       |
|  |  * Sidebar tree         |                       |  IOs()   |       |
|  |                         |                       |          |       |
|  |                         |                       |  batch   |       |
|  |                         |                       |  Count   |       |
|  |                         |                       |  Related |       |
|  |                         |                       |  Actors()|       |
|  +------------------------------------------------------------+       |
|               |                          |                             |
|               V                          V                             |
|  +---------------------------+  +----------------------------+        |
|  |   Elasticsearch (curl)    |  |  Laravel Query Builder     |        |
|  |   * IO index search       |  |  * object_term_relation    |        |
|  |   * Term index search     |  |  * term_i18n (names)       |        |
|  |   * Aggregations          |  |  * Batch GROUP BY counts   |        |
|  +---------------------------+  +----------------------------+        |
|                                                                        |
+-----------------------------------------------------------------------+
```

---

## Routes

| Route Name | URL Pattern | Action | Description |
|-----------|-------------|--------|-------------|
| `term_browse_override` | `/term/:slug` | `termTaxonomy/index` | Individual term page with related IOs |
| `taxonomy_browse_override` | `/taxonomy/:id` | `termTaxonomy/taxonomyIndex` | Taxonomy listing (list of terms) |

Both routes are prepended via `routing.load_configuration` event, overriding base AtoM's default `/:module/:action` catch-all.

---

## File Structure

```
ahgTermTaxonomyPlugin/
├── config/
│   ├── ahgTermTaxonomyPluginConfiguration.class.php
│   └── routing.yml
├── extension.json
├── database/
│   └── install.sql                     (empty — no custom tables)
├── modules/
│   └── termTaxonomy/
│       ├── actions/
│       │   └── actions.class.php       (executeIndex + executeTaxonomyIndex)
│       ├── config/
│       │   └── module.yml
│       └── templates/
│           ├── indexSuccess.php         (term browse — 3-col layout)
│           └── taxonomyIndexSuccess.php (taxonomy browse — 2-col layout)
└── lib/
    ├── Services/
    │   └── TermTaxonomyService.php     (all business logic)
    ├── SearchHit.php                   (ES doc wrapper)
    └── SimplePager.php                 (lightweight pager)
```

---

## Service: TermTaxonomyService

**Namespace:** `AhgTermTaxonomy\Services`

### Constructor

```php
__construct(string $culture = 'en')
```

Initialises Elasticsearch connection settings from `sfConfig`:
- `app_opensearch_host` (default: `localhost`)
- `app_opensearch_port` (default: `9200`)
- `app_opensearch_index_name` (or falls back to DB name)

Computes index names: `{name}_qubitinformationobject` and `{name}_qubitterm`.

### Methods

| Method | Purpose | Returns |
|--------|---------|---------|
| `browse(int $termId, int $taxonomyId, array $params)` | Search IOs for a given term with facets, pagination, sorting | `{hits, total, aggs, direct, page, limit, filters}` |
| `browseTaxonomy(int $taxonomyId, array $params)` | Search terms within a taxonomy with text search, sorting | `{hits, total, page, limit}` |
| `loadListTerms(int $taxonomyId, array $params)` | Load term list for sidebar treeview | `{hits, total, page, limit}` |
| `batchCountRelatedIOs(array $termIds)` | Count IOs per term in one query | `[termId => count]` |
| `batchCountRelatedActors(array $termIds)` | Count actors per term in one query | `[termId => count]` |
| `resolveTermNames(array $ids)` | Batch resolve term IDs to display names | `[id => name]` |
| `extractI18nField(array $doc, string $field)` | Extract i18n value with culture fallback | `string` |

---

## Key Optimisation: Batch Counts

### Problem (Base AtoM)

The base taxonomy template calls `countRelatedInformationObjects()` and `TermNavigateRelatedComponent::getEsDocsRelatedToTermCount()` **per row**, creating N+1 queries:

```
Page with 30 terms:
  30 × countRelatedInformationObjects()   = 30 DB queries
  30 × getEsDocsRelatedToTermCount()      = 30 DB queries
  Total: 60+ queries per page load
```

### Solution (This Plugin)

Batch all term IDs from the current page, run two GROUP BY queries:

```
Page with 30 terms:
  1 × batchCountRelatedIOs([id1, id2, ...id30])    = 1 DB query
  1 × batchCountRelatedActors([id1, id2, ...id30])  = 1 DB query
  Total: 2 queries per page load
```

### SQL Pattern

```sql
-- batchCountRelatedIOs
SELECT otr.term_id, COUNT(*) as cnt
FROM object_term_relation otr
JOIN object o ON otr.object_id = o.id
WHERE otr.term_id IN (?, ?, ...)
AND o.class_name = 'QubitInformationObject'
GROUP BY otr.term_id;

-- batchCountRelatedActors
SELECT otr.term_id, COUNT(*) as cnt
FROM object_term_relation otr
JOIN object o ON otr.object_id = o.id
WHERE otr.term_id IN (?, ?, ...)
AND o.class_name = 'QubitActor'
GROUP BY otr.term_id;
```

---

## Elasticsearch Queries

### Taxonomy Browse (browseTaxonomy)

Queries the term index (`_qubitterm`) with:

```json
{
  "query": {
    "bool": {
      "must": [
        { "term": { "taxonomyId": 35 } },
        {
          "query_string": {
            "query": "user search text",
            "fields": ["i18n.en.name^5", "useFor.i18n.en.name"],
            "default_operator": "AND"
          }
        }
      ]
    }
  },
  "sort": [{ "i18n.en.name.alphasort": { "order": "asc" } }],
  "_source": ["slug", "i18n", "taxonomyId", "numberOfDescendants",
              "isProtected", "useFor", "scopeNotes", "updatedAt"]
}
```

### Term Browse (browse)

Queries the IO index (`_qubitinformationobject`) with:

```json
{
  "query": {
    "bool": {
      "filter": [
        { "terms": { "subjects.id": [12345] } }
      ],
      "must_not": [
        { "term": { "publicationStatusId": 159 } }
      ]
    }
  },
  "aggs": {
    "languages": { "terms": { "field": "i18n.languages", "size": 10 } },
    "places":    { "terms": { "field": "places.id", "size": 10 } },
    "subjects":  { "terms": { "field": "subjects.id", "size": 10 } },
    "genres":    { "terms": { "field": "genres.id", "size": 10 } },
    "direct":    { "filter": { "terms": { "directSubjects": [12345] } } }
  }
}
```

---

## Actions

### executeIndex (Term Browse)

```
Request: GET /term/:slug

1. Resolve QubitTerm from slug (QubitResourceRoute)
2. Validate: must be QubitTerm with parent, not locked taxonomy
3. Set culture, page title, error schema (duplicate name check)
4. Determine if browse elements needed (Places/Subjects/Genres only)
5. XHR? → handleTermXhrRequest() → JSON for treeview
6. Call service->browse() → search IOs for this term
7. Wrap hits as SearchHit[], build SimplePager
8. Populate aggregation display names (batch)
9. Load sidebar term list via service->loadListTerms()
10. Render indexSuccess.php (3-column layout)
```

### executeTaxonomyIndex (Taxonomy Browse)

```
Request: GET /taxonomy/:id

1. Resolve QubitTaxonomy by ID
2. Set resource on sf_route (for treeView component)
3. ACL: locked → 403; restricted → editor/admin required
4. Set per-taxonomy: icon, title, count column flags
5. Pagination guard (max_result_window)
6. Sort defaults (authenticated vs anonymous)
7. XHR? → handleTaxonomyXhrRequest() → JSON for autocomplete
8. Call service->browseTaxonomy() → search terms in taxonomy
9. Wrap hits as SearchHit[], collect term IDs
10. Build SimplePager
11. Batch counts: batchCountRelatedIOs() + batchCountRelatedActors()
12. Render taxonomyIndexSuccess.php (2-column layout)
```

---

## Templates

### taxonomyIndexSuccess.php (Taxonomy Browse)

Layout: `layout_2col`

| Slot | Content |
|------|---------|
| sidebar | `term/treeView` component |
| title | Multiline header: icon + "Showing X results" + taxonomy name |
| before-content | Inline search (field selector) + sort pickers |
| content | Table: term name, scope note, IO count, actor count |
| after-content | Pager + "Add new" button (ACL-gated) |

Count columns use pre-computed `$ioCounts` and `$actorCounts` arrays:
```php
<td><?php echo $ioCounts[(int) $hit->getId()] ?? 0; ?></td>
```

### indexSuccess.php (Term Browse)

Layout: `layout_3col`

| Slot | Content |
|------|---------|
| sidebar | Term sidebar: treeview + aggregation facets |
| title | Term title + breadcrumb + translation links |
| context-menu | Navigation + related item counts |
| content | Elements area + actions + IO browse results |
| after-content | Pager |

---

## Helper Classes

### SearchHit

Lightweight wrapper around ES document arrays. Provides `getData()` and `getId()` compatible with `Elastica\Result` so theme partials work without changes.

```php
$hit = new SearchHit(['_id' => '12345', 'slug' => 'photography', ...]);
$hit->getId();    // "12345"
$hit->getData();  // ['slug' => 'photography', ...]
```

### SimplePager

Lightweight pager compatible with theme's `default/pager` partial. Implements same interface as `QubitSearchPager`:

| Method | Description |
|--------|-------------|
| `getPage()` | Current page number |
| `getLastPage()` | Last page number |
| `getNbResults()` | Total result count |
| `getResults()` | Array of SearchHit for current page |
| `haveToPaginate()` | Whether pagination is needed |
| `getLinks($nb)` | Array of page numbers for pager links |
| `getFirstIndice()` / `getLastIndice()` | Display range (e.g. "1 to 30") |

---

## XHR / JSON Responses

Both actions detect `$request->isXmlHttpRequest()` and return JSON.

### Taxonomy XHR (autocomplete)

```json
{
  "results": [
    { "url": "/term/photography", "title": "Photography", "identifier": "", "level": "" },
    { "url": "/term/portraits", "title": "Portraits", "identifier": "", "level": "" }
  ],
  "more": "<div class=\"more\"><a href=\"...\">Browse all terms</a></div>"
}
```

### Term XHR (treeview pagination)

```json
{
  "results": [
    { "url": "/term/photography", "title": "Photography" },
    { "url": "/term/portraits", "title": "Portraits" }
  ],
  "more": "<section>...<div class=\"pager\">...</div></section>"
}
```

---

## Access Control

| Taxonomy | Anonymous | Editor/Admin |
|----------|-----------|--------------|
| Places (42) | Allowed | Allowed |
| Subjects (35) | Allowed | Allowed |
| Genres (78) | Allowed | Allowed |
| Other taxonomies | 403 | Allowed |
| Locked taxonomies | 403 | 403 |

"Add new" button: visible only if `AclService::check($resource, 'createTerm')` passes.

---

## Taxonomy Field Mapping

| Taxonomy ID | ES IO Field | Direct Field | Icon |
|-------------|------------|--------------|------|
| 42 (Places) | `places.id` | `directPlaces` | `map-marker-alt` |
| 35 (Subjects) | `subjects.id` | `directSubjects` | `tag` |
| 78 (Genres) | `genres.id` | `directGenres` | (none) |

---

## Configuration

No custom `app.yml` settings. Uses standard AtoM/framework settings:

| Setting | Default | Purpose |
|---------|---------|---------|
| `app_opensearch_host` | `localhost` | ES host |
| `app_opensearch_port` | `9200` | ES port |
| `app_opensearch_index_name` | (DB name) | ES index prefix |
| `app_opensearch_max_result_window` | `10000` | Max pagination depth |
| `app_hits_per_page` | `30` | Default page size |
| `app_sort_browser_user` | `lastUpdated` | Default sort (authenticated) |
| `app_sort_browser_anonymous` | `lastUpdated` | Default sort (anonymous) |

---

## Database Dependencies

No custom tables. Uses existing AtoM tables (read-only):

| Table | Usage |
|-------|-------|
| `object_term_relation` | Count relationships (batch GROUP BY) |
| `object` | Join for class_name filter |
| `term_i18n` | Resolve term IDs to display names |

---

## Migration from ahgTermBrowsePlugin

This plugin replaces `ahgTermBrowsePlugin`. Migration steps:

```bash
# Remove old symlink
rm plugins/ahgTermBrowsePlugin

# Create new symlink
ln -s ../atom-ahg-plugins/ahgTermTaxonomyPlugin plugins/ahgTermTaxonomyPlugin

# Disable old, enable new
php bin/atom extension:disable ahgTermBrowsePlugin
php bin/atom extension:enable ahgTermTaxonomyPlugin

# Clear cache
rm -rf cache/* && php symfony cc
sudo systemctl restart php8.3-fpm
```

The old plugin source files remain in `atom-ahg-plugins/ahgTermBrowsePlugin/` but are disabled.

---

## Verification Checklist

| Test | Expected |
|------|----------|
| `/taxonomy/35` (Subjects) | 200 — table with IO/actor counts |
| `/taxonomy/42` (Places) | 200 — map-marker-alt icon |
| `/taxonomy/78` (Genres) | 200 — table with IO/actor counts |
| `/taxonomy/36` (restricted) | 403 for anonymous |
| `/term/photography` | 200 — term page with IO results |
| Sort: `?sort=alphabetic` | Alphabetical ordering |
| Search: `?subquery=test` | Filtered results |
| Pagination: `?page=2` | Second page of results |
| XHR to `/taxonomy/35` | JSON with results array |
| Sidebar treeview | Renders term navigation |
| "Add new" button | Visible for editors/admins only |
