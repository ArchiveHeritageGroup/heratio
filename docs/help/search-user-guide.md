> Heratio Help Center article. Category: Search / Indexing.

# Search and Indexing

Heratio's global search runs on Elasticsearch. It lets users find archival descriptions, authority records, repositories, and terms from one search box, narrow results with sidebar facets, and lets administrators rebuild the search indices with the `ahg:es-reindex` command.

---

## Overview

Search is provided by the `ahg-search` package. It maintains four Elasticsearch indices, one per entity type, and serves the public search results page, an advanced search form, and an autocomplete endpoint. Administrators get extra tools: a global find-and-replace, a "Description updates" report, and a search-analytics dashboard.

The indices are populated from the Heratio MySQL database. They are independent from any other application on the host - every Heratio index carries the prefix `heratio_`.

| Entity type | Index name | Holds |
|---|---|---|
| Information object | `heratio_qubitinformationobject` | Archival descriptions (titles, scope and content, dates, repository, creators, digital object, library fields) |
| Actor | `heratio_qubitactor` | Authority records (people, organisations, families) |
| Term | `heratio_qubitterm` | Controlled-vocabulary terms (subjects, places, genres) |
| Repository | `heratio_qubitrepository` | Holding institutions, with contact details |

---

## Key features

- **Global search** across all four entity types from a single query box (`/search`).
- **Faceted filtering** in the results sidebar: repository, level of description, date range, has-digital-object, media type, plus language, place, subject, genre, name, and collection facets. Each active filter shows as a removable chip.
- **Advanced search form** (`/search/advanced`) with repository, level, date, digital-object, and media-type fields that submit through to the main results page.
- **Autocomplete** suggestions as you type (minimum two characters).
- **"Did you mean...?"** spelling suggestion, shown when a query returns few results.
- **Sorting** by relevance (default) or other supported sort keys.
- **Multi-tenant scoping** - when a tenant repository is active, information-object results are limited to that repository while authority/term results pass through.
- **Admin tools**: global find-and-replace over description text fields, a "Description updates" report of recently created or modified records, and a search-analytics dashboard (top queries, zero-result queries, click-through).
- **Reindex command** (`ahg:es-reindex`) to build indices from MySQL or clone them from another prefix.

---

## How to use

### Searching

1. Type your query in the search box and submit. You land on the results page at `/search?q=...`.
2. Results are paginated 30 per page.
3. If the query returns very few results, a "Did you mean...?" suggestion may appear above the results - click it to re-run the corrected query.
4. As you type a query of two or more characters, autocomplete offers matching records you can jump to directly.

### Filtering with facets

1. On the results page, use the sidebar facets to narrow your results. Available facets include:
   - Repository
   - Level of description
   - Date range (From / To)
   - Has digital object
   - Media type
   - Language, Place, Subject, Genre, Name, Collection
2. Click a facet value to apply it. The page reloads with that filter applied and the value added as a chip at the top of the results.
3. To remove a filter, click the chip. You can combine several facets at once.
4. Change the sort order using the sort control (relevance is the default).

### Advanced search

1. Go to `/search/advanced`.
2. Fill in any combination of query text, repository, level of description, date range, digital-object, and media-type fields.
3. Submit. The form redirects to the main results page with your criteria applied as URL parameters, so the result is bookmarkable and shareable.

### Administrator: rebuilding the indices

Use the `ahg:es-reindex` artisan command to populate or rebuild the Heratio indices. Run it from the application root. Do not run artisan as root - run as the web user, for example:

```bash
sudo -u www-data php artisan ahg:es-reindex
```

With no options it reindexes all four entity types from MySQL into the `heratio_` indices. Available options:

| Option | Purpose |
|---|---|
| `--index=<name>` | Reindex one entity type only. Valid values: `informationobject`, `actor`, `term`, `repository`. Omit to reindex all. |
| `--id=<n>` | Reindex a single object by its database id. Requires `--index`. |
| `--clone-from=<prefix>` | Clone the mapping and data from an existing index prefix (for example `archive_`) using the Elasticsearch reindex API, instead of building from MySQL. |
| `--drop` | Drop and recreate the target index before reindexing. |
| `--batch=<n>` | Bulk indexing batch size (default `500`). |

Examples:

```bash
# Full rebuild from MySQL, dropping the old indices first
sudo -u www-data php artisan ahg:es-reindex --drop

# Reindex only information objects
sudo -u www-data php artisan ahg:es-reindex --index=informationobject

# Reindex a single information object by id
sudo -u www-data php artisan ahg:es-reindex --index=informationobject --id=12345

# Clone mapping and data from another prefix
sudo -u www-data php artisan ahg:es-reindex --clone-from=archive_ --drop

# Larger batches for a faster bulk load
sudo -u www-data php artisan ahg:es-reindex --batch=1000
```

When a target index does not yet exist, the command clones the mapping from the matching `archive_` index if one is present, otherwise it creates the index with dynamic mapping. It also ensures the information-object index has the `gis` geo-point field (for map results) and a `workKey` field (for grouping editions of the same work), and that the term index maps the `code` field. After it finishes, verify with:

```bash
curl -s http://localhost:9200/_cat/indices?v | grep heratio_
```

### Administrator: other search tools

- **Description updates** (`/search/descriptionUpdates`) - lists recently created or modified records across all entity types, filterable by entity type, date range, publication status, and user. Admin only.
- **Global replace** (`/search/globalReplace`) - find and replace text across a chosen description field (title, scope and content, access conditions, and so on) in the English description records. It previews affected records before you confirm, and supports case-sensitive or case-insensitive matching. Admin only. This writes to the database, so review the preview carefully before confirming. After a bulk replace, reindex so the search results reflect the change.
- **Search analytics** (`/admin/search/analytics`) - top queries, zero-result queries, and click-through data over a configurable number of days. Admin only.

---

## Configuration

Elasticsearch connection settings live in `config/services.php` under the `elasticsearch` key and are driven by environment variables:

| `.env` variable | Config key | Default |
|---|---|---|
| `ELASTICSEARCH_HOST` | `services.elasticsearch.host` | `http://localhost:9200` |
| `ELASTICSEARCH_PREFIX` | `services.elasticsearch.prefix` | `heratio_` |

The prefix is prepended to every index name, giving the four indices listed in the Overview. Keep `ELASTICSEARCH_PREFIX` set to `heratio_` so Heratio's indices stay separate from any other application sharing the same Elasticsearch cluster.

Two optional discovery-API enrichments are defined in `config/ahg-search.php` and default to OFF so the search hot path is never slowed:

| `.env` variable | Effect when enabled |
|---|---|
| `AHG_DISCOVERY_QUERY_EXPANSION` | Runs the query through natural-language query expansion before searching, with a thesaurus fallback and then the raw query on any failure. |
| `AHG_DISCOVERY_HISTORY_RERANK` | Re-ranks the current result page to favour records matching the signed-in user's recent searches. |

Only turn these on per deployment once the backing services have been validated.

---

## Known issues

- **Search reflects only what has been indexed.** New or edited records do not appear in search until the relevant index is rebuilt with `ahg:es-reindex`. After bulk edits (including a global replace), run a reindex.
- **The command requires a reachable Elasticsearch host.** If it cannot connect to `ELASTICSEARCH_HOST` it exits with an error and indexes nothing. Check the host and that the service is running.
- **`--clone-from` needs the source indices to exist.** If a source index for a given entity type is missing, that entity type is skipped with a warning.
- **Global replace operates on English (`en`) description records only** and matches with a SQL `LIKE`, not a regular expression. Always review the preview before confirming, as the change is applied directly to the database.

---

## References

- Source: packages/ahg-search/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/623
