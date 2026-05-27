# Data migration exports

Heratio's data-migration module ships six export and inspection actions that
together give you complete parity with the PSIS reference implementation. Use
them in the order the wizard suggests, or call any of them directly via the
REST endpoints listed at the bottom of this page.

## Where they live

All six actions are admin-only and sit under `/admin/data-migration/`. They are
wired through `AhgDataMigration\Controllers\DataMigrationController` and rely
on `AhgDataMigration\Services\DataMigrationService` for the heavy lifting.

| Action          | Route name                              | HTTP method  | Output             |
| --------------- | --------------------------------------- | ------------ | ------------------ |
| Export EAD      | `data-migration.export-ead`             | GET / POST   | `application/xml`  |
| Export AHG CSV  | `data-migration.export-ahg-csv`         | GET / POST   | `text/csv`         |
| Sector export   | `data-migration.sector-export-new`      | GET / POST   | `text/csv` or view |
| Detect sheets   | `data-migration.detect-sheets`          | POST         | `application/json` |
| Rename mapping  | `data-migration.rename-mapping`         | POST         | `application/json` |
| Get preview     | `data-migration.get-preview`            | GET / POST   | `application/json` |

Each action also has a `/dataMigration/...` legacy camelCase alias for PSIS
parity (e.g. `/dataMigration/exportEad`).

## 1. Export EAD

Generates a fully-formed EAD 2002 XML download from the current session's
mapping. Records are emitted as `<c level="...">` components inside a single
`<dsc>` block; control-access points (subject, place, name) are pipe-split and
appear in the standard EAD `<controlaccess>` block.

The output filename is the source filename with `.ead.xml` appended.

## 2. Export AHG extended CSV

Produces a CSV with the standard ISAD-G column set plus the AHG sidecar
columns for security classification, rights, provenance and condition. Native
`ahg*` source columns are passed through unchanged; the `_digitalObject*`
internal fields are promoted to their public export names
(`digitalObjectPath`, `digitalObjectFilename`, `allFilenames`).

A UTF-8 BOM is prepended so Excel renders accented characters correctly.

## 3. Sector export

A sector-aware CSV exporter. The sector picker is bound to the
`data_migration_sector` taxonomy in `ahg_dropdown` and accepts `archive`,
`museum`, `library`, `gallery` and `dam` out of the box. The column resolver
returns a sector-specific column / label map - for example the museum sector
uses Spectrum semantics (Object number, Materials, Technique) while the
library sector uses MARC / RDA semantics (Call number, Author, ISBN).

GET renders the picker view; POST streams the CSV.

## 4. Detect sheets

A JSON probe for an uploaded XLS / XLSX / CSV / TSV file. Returns
`{ success, sheets: [{index, name, rows, headers}], count }`. PhpSpreadsheet
handles the binary Excel formats; plain delimited files get a single
synthetic `Sheet1` entry so the UI can render uniformly.

## 5. Rename mapping

A small JSON endpoint that updates `atom_data_mapping.name` for a saved
mapping. The body must include `id` and `name`. Returns
`{ success: true|false }`.

## 6. Get preview

Returns the first N rows of the uploaded source file projected through the
current mapping. Useful for the live preview pane on the mapping screen.
Response shape:

```json
{
  "success": true,
  "headers": ["Identifier", "Title", "..."],
  "rows": [ { "identifier": "...", "title": "..." } ],
  "raw":  [ { "Identifier": "...", "Title": "..." } ],
  "count": 10
}
```

`rows` is the projected (post-mapping) view; `raw` is the source slice.

## Dropdown taxonomies

The exports pages are driven by three `ahg_dropdown` taxonomies, seeded on
first boot from `packages/ahg-data-migration/database/seed_dropdowns.sql`:

* `export_format` - csv, ead, sector, json
* `sheet_type` - records, authority, taxonomy, relations, unknown
* `data_migration_sector` - archive, museum, library, gallery, dam

Re-seed by truncating the relevant taxonomy rows and bouncing php-fpm, or by
running `mysql heratio < packages/ahg-data-migration/database/seed_dropdowns.sql`.

## Related

* PSIS twin issue: `atom-ahg-plugins#86`
* Heratio issue: `#740`
* Service: `packages/ahg-data-migration/src/Services/DataMigrationService.php`
* Controller: `packages/ahg-data-migration/src/Controllers/DataMigrationController.php`
* Tests: `tests/Feature/DataMigrationExportsTest.php`
