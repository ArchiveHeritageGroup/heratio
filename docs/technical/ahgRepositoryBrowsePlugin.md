# ahgRepositoryBrowsePlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Browse
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

Framework migration of base AtoM's archival institution browse (`/repository/browse`). Replaces Elastica ORM with direct ES HTTP (curl) queries and Laravel Query Builder for aggregation population and advanced filter data. Fully compatible with the existing theme templates and DisplayModeService.

---

## Architecture

```
+-----------------------------------------------------------------------+
|                    ahgRepositoryBrowsePlugin                           |
+-----------------------------------------------------------------------+
|                                                                        |
|  +---------------------------+      +----------------------------+    |
|  |    Plugin Configuration   |      |     Route Loading          |    |
|  | ahgRepositoryBrowsePlugin |      |  * routing.load_config     |    |
|  |   Configuration           |      |  * prependRoute()          |    |
|  | * PSR-4 autoloader        |      |                            |    |
|  | * Module registration     |      +----------------------------+    |
|  +---------------------------+                 |                       |
|               |                                |                       |
|               V                                V                       |
|  +------------------------------------------------------------+       |
|  |              repositoryBrowse Module                        |       |
|  +------------------------------------------------------------+       |
|  |                                                            |       |
|  |  executeBrowse()                                           |       |
|  |  /repository/browse                                        |       |
|  |  * Collect params (search, facets, sort, pagination)       |       |
|  |  * Call service->browse()                                  |       |
|  |  * Build SimplePager                                       |       |
|  |  * Load advanced filter dropdown data                      |       |
|  |  * Batch resolve thematic area names                       |       |
|  |                                                            |       |
|  +------------------------------------------------------------+       |
|               |                                                        |
|               V                                                        |
|  +------------------------------------------------------------+       |
|  |            RepositoryBrowseService                          |       |
|  +------------------------------------------------------------+       |
|  |                         |                       |          |       |
|  |  browse()               |  getAdvancedFilter    |  resolve |       |
|  |  * ES search repos      |    Terms()            |  helpers |       |
|  |  * Facets + aggs        |  * getTermsByTaxonomy |          |       |
|  |  * Sort + paginate      |  * getUniqueRegions   |          |       |
|  |  * Populate aggs        |                       |          |       |
|  +------------------------------------------------------------+       |
|               |                          |                             |
|               V                          V                             |
|  +---------------------------+  +----------------------------+        |
|  |   Elasticsearch (curl)    |  |  Laravel Query Builder     |        |
|  |   * Repository index      |  |  * term / term_i18n        |        |
|  |   * Aggregations          |  |  * contact_information     |        |
|  +---------------------------+  +----------------------------+        |
|                                                                        |
+-----------------------------------------------------------------------+
```

---

## Route

| Route Name | URL Pattern | Action | Description |
|-----------|-------------|--------|-------------|
| `repository_browse_override` | `/repository/browse` | `repositoryBrowse/browse` | Institution browse listing |

Prepended via `routing.load_configuration` event, overriding base AtoM's default route.

---

## File Structure

```
ahgRepositoryBrowsePlugin/
├── config/
│   ├── ahgRepositoryBrowsePluginConfiguration.class.php
│   └── routing.yml
├── extension.json
├── database/
│   └── install.sql                      (empty — no custom tables)
├── modules/
│   └── repositoryBrowse/
│       ├── actions/
│       │   └── actions.class.php        (executeBrowse)
│       ├── config/
│       │   └── module.yml
│       └── templates/
│           ├── browseSuccess.php        (main layout — 2-col)
│           ├── _browseCardView.php      (card/grid view partial)
│           ├── _browseTableView.php     (table view partial)
│           └── _advancedFilters.php     (advanced filter form)
└── lib/
    ├── Services/
    │   └── RepositoryBrowseService.php  (all business logic)
    └── SimplePager.php                  (lightweight pager)
```

---

## Service: RepositoryBrowseService

**Namespace:** `AhgRepositoryBrowse\Services`

### Methods

| Method | Purpose | Returns |
|--------|---------|---------|
| `browse(array $params)` | Main search orchestrator — ES query + aggregations | `{hits, total, aggs, page, limit, filters}` |
| `getAdvancedFilterTerms()` | Load dropdown data for advanced filters | `{thematicAreas, repositoryTypes, regions}` |
| `getTermsByTaxonomy(int $taxonomyId)` | Get terms for a taxonomy via Laravel QB | `[id => name]` |
| `getUniqueRegions()` | Get distinct regions from contact info | `string[]` |
| `resolveTermNames(array $ids)` | Batch resolve term IDs to names | `[id => name]` |
| `extractI18nField(array $doc, string $field)` | Extract i18n value with culture fallback | `string` |
| `extractContactField(array $doc, string $field)` | Extract contact i18n value | `string` |

---

## Aggregations

Six facets matching base AtoM's `RepositoryBrowseAction::$AGGS`:

| Aggregation | ES Field | Population |
|-------------|----------|------------|
| languages | `i18n.languages` | Language code → name (sfCultureInfo) |
| types | `types` | Term ID → name (batch DB query) |
| regions | `contactInformations.i18n.{c}.region.untouched` | Key = display value |
| geographicSubregions | `geographicSubregions` | Term ID → name (batch DB query) |
| locality | `contactInformations.i18n.{c}.city.untouched` | Key = display value |
| thematicAreas | `thematicAreas` | Term ID → name (batch DB query) |

Aggregation population follows a two-pass batch pattern:
1. Collect all term IDs from buckets
2. Single `SELECT` query to resolve all names

---

## Elasticsearch Query

```json
{
  "query": {
    "bool": {
      "must": [
        { "match_all": {} }
      ],
      "filter": [
        { "term": { "types": 1234 } }
      ]
    }
  },
  "sort": [{ "updatedAt": { "order": "desc" } }],
  "aggs": {
    "languages": { "terms": { "field": "i18n.languages", "size": 10 } },
    "types": { "terms": { "field": "types", "size": 10 } },
    "regions": { "terms": { "field": "contactInformations.i18n.en.region.untouched", "size": 10 } },
    "geographicSubregions": { "terms": { "field": "geographicSubregions", "size": 10 } },
    "locality": { "terms": { "field": "contactInformations.i18n.en.city.untouched", "size": 10 } },
    "thematicAreas": { "terms": { "field": "thematicAreas", "size": 10 } }
  },
  "_source": ["slug", "identifier", "i18n", "logoPath", "contactInformations",
              "thematicAreas", "types", "geographicSubregions", "updatedAt"]
}
```

---

## Sort Options

| Sort Value | ES Sort |
|------------|---------|
| `nameUp` | `i18n.{c}.authorizedFormOfName.alphasort` asc |
| `nameDown` | `i18n.{c}.authorizedFormOfName.alphasort` desc |
| `regionUp` | `i18n.{c}.region.untouched` asc |
| `regionDown` | `i18n.{c}.region.untouched` desc |
| `localityUp` | `i18n.{c}.city.untouched` asc |
| `localityDown` | `i18n.{c}.city.untouched` desc |
| `identifier` | `identifier.untouched` + `authorizedFormOfName.alphasort` |
| `alphabetic` | `i18n.{c}.authorizedFormOfName.alphasort` |
| `lastUpdated` (default) | `updatedAt` desc |

---

## Templates

### browseSuccess.php (Main Layout)

Layout: `layout_2col`

| Slot | Content |
|------|---------|
| title | University icon + "Showing X results" + label |
| sidebar | Collapsible facet panel (6 aggregations) |
| before-content | Inline search + advanced filters accordion + display mode toggle + sort pickers |
| content | Card view or table view (based on DisplayModeService) |
| after-content | Pager |

### _browseCardView.php

Masonry grid of repository cards:
- Repository logo (from `/uploads/r/{slug}/conf/logo.png`)
- Name link
- Clipboard button
- 3-column responsive grid

### _browseTableView.php

Sortable table with columns:
- Name (40%, sortable with logo)
- Region (20%, sortable)
- Locality (20%, sortable)
- Thematic area (20%, batch-resolved names)
- Clipboard

### _advancedFilters.php

Three-column form:
- Thematic area dropdown (populated via `getTermsByTaxonomy`)
- Archive type dropdown (populated via `getTermsByTaxonomy`)
- Region dropdown (populated via `getUniqueRegions`)

---

## DisplayModeService Integration

The template integrates with `AtomExtensions\Services\DisplayModeService` for view mode switching:

| Display Mode | View Rendered |
|--------------|---------------|
| grid (default) | `_browseCardView.php` |
| list | `_browseTableView.php` |
| tree | `_browseTableView.php` |
| history | `_browseCardView.php` |

Mode toggle buttons use Font Awesome icons converted from Bootstrap Icons.

---

## Symfony Output Escaping

Templates use `sfOutputEscaper::unescape($doc)` to unwrap raw arrays from Symfony's automatic output escaping, since the pager returns plain arrays (not Elastica objects) that get wrapped by `sfOutputEscaperArrayDecorator`.

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
| `app_default_repository_browse_view` | `card` | Default view mode |
| `app_ui_label_repository` | `Archival institution` | UI label |
| `app_enable_institutional_scoping` | `false` | Institutional scoping feature |

---

## Database Dependencies

No custom tables. Uses existing AtoM tables (read-only via Laravel QB):

| Table | Usage |
|-------|-------|
| `term` / `term_i18n` | Resolve thematic area, type, subregion names |
| `contact_information` / `contact_information_i18n` | Get unique regions |

---

## Verification Checklist

| Test | Expected |
|------|----------|
| `/repository/browse` | 200 — card or table view with results |
| Sort: `?sort=alphabetic` | Alphabetical ordering |
| Sort: `?sort=nameUp` | Name ascending (table view) |
| Search: `?subquery=archive` | Filtered results |
| Advanced filters | Dropdown populated, "Set filters" works |
| Display mode toggle | Grid/List/Tree/History buttons |
| Facet sidebar | 6 aggregation panels |
| Card view | Repository logos and names in grid |
| Table view | Sortable Name/Region/Locality columns |
| Thematic area column | Names (not IDs) via batch resolution |
| Clipboard buttons | Present on each result |
| Pagination | Pager renders for multi-page results |
