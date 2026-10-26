> Heratio Help Center article. Category: Import/Export.

# Data Migration

## User Guide

**Last Updated:** 2026-10-26

The Data Migration wizard brings rows from a spreadsheet into Heratio. You upload a CSV or Excel file, match its columns to Heratio fields, and the import runs in the background while a progress page shows how far it has got. It is the tool for moving catalogue data out of another system, provided that system can export a spreadsheet with one row per record and a header row.

It does not read any other system's native format. There are no presets for particular products, and it does not import XML, Preservica OPEX or PAX packages, or digital object files. What the source system was does not matter, as long as its export reaches you as a CSV or Excel file.

## Contents

1. [Opening the tool](#1-opening-the-tool)
2. [What it imports](#2-what-it-imports)
3. [Running an import](#3-running-an-import)
4. [Target fields](#4-target-fields)
5. [Import types](#5-import-types)
6. [Following a job](#6-following-a-job)
7. [Exports](#7-exports)
8. [Sector imports from the command line](#8-sector-imports-from-the-command-line)
9. [Troubleshooting](#9-troubleshooting)

## 1. Opening the tool

Go to **Admin > Data Migration**, or `/admin/data-migration`. You need the `import` permission; administrators have it.

## 2. What it imports

| | |
| --- | --- |
| File types | CSV (`.csv`, `.txt`) and Excel (`.xlsx`, `.xls`), up to 100 MB |
| Header row | Required. The first row names the columns. |
| Delimiter | Comma, semicolon, tab or pipe; detected automatically, or chosen on the upload page |
| Encoding | UTF-8, ISO-8859-1 or Windows-1252; detected automatically, or chosen on the upload page |
| Excel | One sheet per import; pick it on the upload page |
| Record types | Archival descriptions (ISAD(G)), accessions, authority records (ISAAR) and repositories (ISDIAH) |

: What the Data Migration wizard accepts

Library, museum, gallery and digital-asset records, and imports that bring digital object files with them, go through the **Ingest** wizard (`/ingest`) instead. It creates the sector records and attaches the files.

## 3. Running an import

**Upload.** Choose the file, then the record type and the import type. For an Excel file, choose the sheet. The file is converted on the server to a UTF-8 comma-separated copy, and every later step reads that copy.

**Map.** The map page lists your columns, with the first five rows as a sample. Pick a Heratio field for each column you want to import; columns left blank are ignored. You can save the mapping under a name and load it again for the next file with the same layout. Saved mappings can also be exported and imported as files from the map page.

**Preview.** **Preview Transformed Data** shows the sample rows as they will be written.

**Execute.** **Execute Import** records a job and hands it to the queue worker. You are taken to the job's page straight away; the import runs in the background, so a large file does not tie up your browser.

New archival descriptions are created as **drafts**. Publish them once you have checked them.

## 4. Target fields

For archival descriptions the main fields are:

| Field | Meaning |
| --- | --- |
| `identifier` | Reference code. Also the match key for update and replace. |
| `title` | Title |
| `scope_and_content` | Scope and content |
| `extent_and_medium` | Extent and medium |
| `level_of_description_id` | Level of description (term id) |
| `repository_id` | Repository (record id) |
| `parent_id` | Parent record (record id) |
| `legacyId` | The row's id in the source system |
| `parentId` | The parent row's `legacyId` |

: Main target fields for archival descriptions

The map page lists every field for the chosen record type, including the other ISAD(G) areas.

To build a hierarchy, give each row a `legacyId` and point children at their parent with `parentId`. Parents must come before their children in the file, because a child can only find a parent that has already been imported.

```csv
legacyId,parentId,identifier,title
F001,,F001,Municipal Archives
S001,F001,F001/S1,Council Minutes
I001,S001,F001/S1/1,Minutes 1950-1960
```

## 5. Import types

| Type | What it does |
| --- | --- |
| Create new records | Every row creates a new record. |
| Match and update existing | Rows are matched on `identifier` (authority records: identifier, then name). Non-blank cells are written; blank cells leave the existing value alone. Rows that match nothing are created. |
| Replace in place | Matched the same way, but every mapped field is overwritten, so a blank cell clears the value. The record keeps its id, links and children. |

: Import types

## 6. Following a job

**Admin > Data Migration > Jobs** lists every import. A job's page shows the counts so far and, when it finishes, the rows that failed with the reason for each.

| Status | Meaning |
| --- | --- |
| `pending` | Recorded, waiting for the queue worker |
| `processing` | Running; the counts update every 25 rows |
| `completed` | Finished. Some rows may still have failed; see the error list. |
| `failed` | Nothing could be imported, or the job stopped. The message says why. |
| `cancelled` | Cancelled before it started |

: Job statuses

Each row is written in its own transaction, so a bad row is reported and skipped without undoing the rows before it. A job runs once: it is never retried automatically, because a second run of a create import would duplicate the rows the first run had already written. Cancelling only stops a job that has not started yet.

## 7. Exports

**Export Records** downloads archival descriptions, authority records, repositories or accessions as CSV, filtered by repository (for descriptions) and by creation date. The file downloads straight away.

**Batch Export** and the per-sector exports download records as CSV in an archives, museum, library, gallery or digital-asset column layout.

For EAD, Dublin Core or EAC-CPF XML, use the **Export** pages (`/export`).

## 8. Sector imports from the command line

Server administrators can import sector CSV files with validation built in:

```bash
php artisan sector:archives-csv-import /path/to/file.csv --validate-only
php artisan sector:museum-csv-import /path/to/file.csv --repository=SLUG
php artisan sector:library-csv-import /path/to/file.csv --mapping=ID
php artisan sector:gallery-csv-import /path/to/file.csv --update=identifier
php artisan sector:dam-csv-import /path/to/file.csv
```

Drop `--validate-only` to import. Run `php artisan help sector:archives-csv-import` for the full option list.

## 9. Troubleshooting

**The map page shows no columns.** The file has no header row, or its first row is empty. Add a header row and upload it again.

**Accented letters come out garbled.** The encoding was detected wrongly. Upload again and choose the encoding on the upload page.

**The job stays on `pending`.** The queue worker is not running. On the server, check it with `systemctl status 'heratio-queue-worker@*'` and start it if it is stopped. The job starts as soon as a worker picks it up.

**Children were imported at the top level.** Their `parentId` did not match a `legacyId` earlier in the file. Check the spelling, and put parents before children.

**Need help?** Contact support@theahg.co.za.
