> Heratio Help Center article. Category: Technical.

# Data Migration

## Technical Notes

**Last Updated:** 2026-10-26

**Package:** `ahg-data-migration`

- **Wizard:** `DataMigrationController` (upload, map, preview, execute) under `/admin/data-migration`, behind `auth` and `acl:import`.
- **Upload:** CSV/TXT/XLS/XLSX only. `DataMigrationService::normaliseUpload()` converts every upload to a UTF-8 comma-separated CSV (chosen Excel sheet; delimiter and encoding detected or chosen), stored under `storage/app/data-migration/uploads/`. Every later step reads that file by absolute path.
- **Jobs:** `DataMigrationRepository::queueImportJob()` writes an `atom_migration_job` row (status `pending`, `source_file`, `mapping_snapshot`, `import_options`) and dispatches `AhgDataMigration\Jobs\RunMigrationImport` on the default queue. The job has one try and a 1800 s timeout, under the database queue's `retry_after`. `DataMigrationService::executeImport()` claims the row atomically (`pending` to `processing`), so a job runs once.
- **Writes:** one transaction per row; errors go to `error_log` with the row number. New archival descriptions are drafts.
- **Not implemented:** Preservica OPEX/PAX import and export (the routes say so and queue nothing), XML import, source-system presets, digital object attachment.

User documentation: [Data Migration - User Guide](/help/article/data-migration-user-guide).
