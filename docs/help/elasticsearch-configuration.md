> Heratio Help Center article. Category: Admin & Settings / Search.

# Elasticsearch Configuration

## Overview

Heratio uses Elasticsearch (ES) to power full-text search across archival descriptions, actors, terms, and repositories. Heratio maintains its own set of indices with the `heratio_` prefix, ensuring complete separation from any other application sharing the same Elasticsearch cluster.

This guide covers initial configuration, index management, reindexing, and troubleshooting.

---

## Environment Configuration

All Elasticsearch settings are configured in your `.env` file:

```
# Elasticsearch connection
ELASTICSEARCH_HOST=localhost:9200
ELASTICSEARCH_PREFIX=heratio_

# Storage paths (also used by ES index management)
HERATIO_STORAGE_PATH=/mnt/nas/heratio/archive
```

### Configuration Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `ELASTICSEARCH_HOST` | `localhost:9200` | Hostname and port of the Elasticsearch server |
| `ELASTICSEARCH_PREFIX` | `heratio_` | Prefix for all Heratio index names, ensuring isolation from other applications |
| `HERATIO_STORAGE_PATH` | `{app}/uploads` | Base directory for file storage (used by some indexing operations) |

The prefix ensures that Heratio indices (e.g., `heratio_qubitinformationobject`) never collide with indices from other applications on the same cluster.

---

## Index Structure

Heratio maintains four Elasticsearch indices:

| Index Name | Contents | Source Table(s) |
|------------|----------|-----------------|
| `heratio_qubitinformationobject` | Archival descriptions (fonds, series, files, items) | `information_object`, `information_object_i18n` |
| `heratio_qubitactor` | Authority records (persons, corporate bodies, families) | `actor`, `actor_i18n` |
| `heratio_qubitterm` | Taxonomy terms (subjects, places, genres, etc.) | `term`, `term_i18n` |
| `heratio_qubitrepository` | Repositories (archival institutions) | `repository`, `repository_i18n`, `actor_i18n` |

Each index stores the full-text searchable fields for its entity type, including all i18n translations, hierarchical data, and related metadata.

---

## Reindex Command

The primary tool for managing indices is the `ahg:es-reindex` Artisan command:

```bash
php artisan ahg:es-reindex
```

This rebuilds all four indices from the database. The command supports several options:

### Options

| Option | Description | Example |
|--------|-------------|---------|
| `--index=NAME` | Reindex only a specific index | `--index=qubitinformationobject` |
| `--clone-from=PREFIX` | Clone mappings and settings from an existing index with a different prefix | `--clone-from=atom_` |
| `--drop` | Drop and recreate indices before reindexing (destructive) | `--drop` |
| `--batch=N` | Number of records per batch (default: 500) | `--batch=1000` |

### Examples

**Full reindex of all indices:**
```bash
php artisan ahg:es-reindex
```

**Reindex only archival descriptions:**
```bash
php artisan ahg:es-reindex --index=qubitinformationobject
```

**Drop and rebuild all indices (fresh start):**
```bash
php artisan ahg:es-reindex --drop
```

**Clone index structure from an existing application, then populate:**
```bash
php artisan ahg:es-reindex --clone-from=atom_ --drop
```

**Use larger batches for faster indexing on powerful hardware:**
```bash
php artisan ahg:es-reindex --batch=2000
```

---

## Initial Setup

When setting up Heratio for the first time:

1. Ensure Elasticsearch 7.x or 8.x is installed and running
2. Confirm connectivity: `curl http://localhost:9200` should return cluster information
3. Set `ELASTICSEARCH_HOST` and `ELASTICSEARCH_PREFIX` in `.env`
4. Run the initial index build: `php artisan ahg:es-reindex --drop`
5. Verify indices exist: `curl http://localhost:9200/_cat/indices?v | grep heratio_`

---

## Index Maintenance

### When to Reindex

- After initial installation
- After restoring a database backup
- After bulk imports (CSV, EAD, etc.)
- If search results appear stale or incomplete
- After upgrading Heratio to a new version that changes index mappings

### Monitoring Index Health

Check index status from the command line:

```bash
# List all Heratio indices with document counts
curl "http://localhost:9200/_cat/indices/heratio_*?v&h=index,docs.count,store.size"

# Check cluster health
curl "http://localhost:9200/_cluster/health?pretty"
```

Or view index statistics in **Admin > Settings > Search** within Heratio.

### Partial Updates

Heratio automatically updates individual documents in Elasticsearch when records are created, edited, or deleted through the web interface. Full reindexing is only needed for bulk operations or recovery scenarios.

---

## Troubleshooting

| Symptom | Cause | Solution |
|---------|-------|----------|
| Search returns no results | Indices not built | Run `php artisan ahg:es-reindex --drop` |
| Connection refused errors | ES not running or wrong host | Check `ELASTICSEARCH_HOST` in `.env`, verify ES is running |
| Partial search results | Index out of date | Run `php artisan ahg:es-reindex` |
| Out of memory during reindex | Batch size too large | Use `--batch=100` for smaller batches |
| Index name conflicts | Prefix collision | Change `ELASTICSEARCH_PREFIX` to a unique value |
| Slow indexing | Large dataset | Increase `--batch` size, ensure adequate server resources |

---

## Security Considerations

- Elasticsearch should not be exposed to the public internet
- Use a firewall to restrict access to `localhost` or trusted IPs only
- If using Elasticsearch with authentication (X-Pack), configure credentials in `.env`
- The `heratio_` prefix prevents accidental cross-application data leakage

---

*Part of the Heratio AHG Framework*
