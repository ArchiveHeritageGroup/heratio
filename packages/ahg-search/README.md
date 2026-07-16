# AhgSearch

Elasticsearch-backed full-text search, faceting, and type-ahead autocomplete for
Heratio records (archival descriptions, authority records, repositories, terms).
Falls back to a MySQL query path when Elasticsearch is unavailable.

## Overview

- **Search** (`/search`) - multi-field query over titles, names, identifiers,
  ISBN/call numbers, creators, with facets and geo filtering.
- **Autocomplete** (`/search/autocomplete`) - `match_phrase_prefix` type-ahead
  across the information-object, actor, repository and term indices.
- **Guest visibility** - both paths are **published-only**: archival descriptions
  must carry `publicationStatusId = 160`, and authority records must additionally
  not be embargoed (`embargoUntil` in the future is excluded). Docs without a
  `publicationStatusId` field (e.g. repositories/terms) pass through. This mirrors
  the DB-side gate in `AhgCore\Services\AclService` (Part B authority draft/embargo).

## Structure

- `src/Controllers/SearchController.php` - `/search` + `/search/autocomplete` endpoints.
- `src/Services/ElasticsearchService.php` - query builders (`search()`, `autocomplete()`),
  filters, and the MySQL fallback (`advancedSearchDb`).
- `src/Commands/EsReindexCommand.php` - `ahg:es-reindex`, populates the indices
  from MySQL (or clones from another prefix). Ensures required mappings before
  bulk-indexing (the indices are `dynamic: strict`, so new fields must be PUT to
  the mapping first).
- `resources/views/` - result partials (`_search-result.blade.php`, etc.).

## Configuration

Environment / settings:

- `ELASTICSEARCH_HOST` - ES base URL (e.g. `http://localhost:9200`).
- `ELASTICSEARCH_PREFIX` - per-tenant index prefix (e.g. `heratio_`).

Indices (prefixed): `qubitinformationobject`, `qubitactor`, `qubitrepository`,
`qubitterm`.

## Usage

Reindex from MySQL:

```bash
# all indices
php artisan ahg:es-reindex
# a single index
php artisan ahg:es-reindex --index=actor      # informationobject | actor | term | repository
# clone mapping + data from another prefix
php artisan ahg:es-reindex --clone-from=archive_
```

The autocomplete/search filters are applied automatically per request; no flags
are needed to enforce published-only visibility.

## Testing

```bash
php artisan test packages/ahg-search/tests
```

Tests skip cleanly when Elasticsearch or the schema is unavailable (CI without ES
exercises the MySQL fallback path).
