# Granular Restore

Sometimes you only need to put back one record - a single archival description that was deleted by accident, or one reference table that got corrupted by a bad import. Rolling back the entire database with PITR would undo every legitimate edit that happened since the last backup. Granular restore is the surgical alternative: it pulls just the rows you ask for out of a full backup and writes them back into the live database.

## When granular restore is the right tool

Use it when:

- One information_object (archival description) was deleted, modified incorrectly, or lost its i18n content.
- A small enumerated table (dropdowns, taxonomy terms) was overwritten and you want it back.
- A specific row in any table needs to come back from a backup.

Do NOT use it when:

- The affected rows reference other rows that no longer exist in the live database. Granular restore does NOT chase the foreign-key graph; it can leave dangling references.
- The wrong-ness covers many records across many tables - PITR is faster and safer.
- You need to restore a digital file (TIFF, JP2, PDF). Granular restore is database-only; files are pulled from the uploads tarball.

## Restoring one archival description

```
php artisan backup:restore-io 42 /mnt/nas/heratio/backups/database_heratio_2026-05-25_030000.sql.gz
```

This restores the `information_object` row with `id = 42` together with its `information_object_i18n` translations. Other tables (digital_object, relation, properties) are untouched - the IDs in those tables are likely still valid against the live database.

You will be prompted to confirm before any changes are applied. Pass `--yes` to skip the prompt for automation.

## Restoring one table (or a filtered subset)

```
# Whole table
php artisan backup:restore-table ahg_dropdown /path/to/backup.sql.gz

# Filtered with a WHERE clause
php artisan backup:restore-table actor_i18n /path/to/backup.sql.gz --where="id BETWEEN 100 AND 200"
```

The `--where` clause uses standard MySQL syntax. BETWEEN, LIKE, IS NULL, comparison operators - all work as you'd expect. The clause is rejected if it contains a semicolon.

## What happens under the covers

1. The command opens the backup file (gzip-decompressing on the fly), finds the section for the requested table, and reads the column list and the row data.
2. For row-level restores, the WHERE filter (if any) is applied to each tuple. Heratio asks MySQL itself to evaluate each row against the filter so the semantics match exactly what you would expect from a normal `SELECT ... WHERE` query.
3. Surviving rows are applied via `INSERT ... ON DUPLICATE KEY UPDATE` - new rows are inserted, existing rows are overwritten.
4. The whole operation runs inside a database transaction. If anything fails partway through (a parse error, a constraint violation, an FK clash) the entire restore is rolled back and the live database is untouched.

## Referential-integrity warning

Granular restore intentionally does NOT follow foreign keys. If you restore an information_object whose parent collection was deleted in the meantime, the restored row will point at a `parent_id` that no longer exists. This is a feature - chasing the dependency graph would often mean restoring much more than you wanted - but you must check the result yourself before declaring the recovery complete.

Recommended post-restore steps:

1. Open the restored record in the UI and verify it renders without errors.
2. Run the audit-trail integrity check (`php artisan ahg:audit-trail-verify`) if the table you touched is audited.
3. If the table is indexed in Elasticsearch (information_object, actor, repository), reindex: `php artisan ahg:es-reindex --index=informationobject`.

## Best practice: test in dev first

Before running granular restore against production:

1. Copy the same backup file to your dev environment.
2. Run the exact command there.
3. Confirm the resulting record looks right and that no FK warnings appear in the MySQL error log.

Only then run it against production. This adds ten minutes; it has saved entire days for operators who skipped the step.

## Troubleshooting

**"No matching rows found in backup."** - The id (or WHERE filter) didn't match any row in the dump. Confirm the record existed at backup time, and that you're looking at the right backup file.

**"Could not parse columns for table X in backup."** - The backup file was hand-edited or produced by a non-standard tool. Granular restore expects the output format produced by `mysqldump`. Try a different backup or fall back to PITR.

**Constraint violation on apply** - The restored row points at something that no longer exists. Either restore the parent record first, or accept the dangling reference and clean it up manually.
