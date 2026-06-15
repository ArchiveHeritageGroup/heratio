> Heratio Help Center article. Category: Plugin Reference.

# Export - User Guide

## Get Your Records Out in Standard Archival Formats

The Export module lets administrators export catalogue data in widely-used,
standards-based formats so it can be shared, archived, or moved into another
system. You can export archival descriptions, authority records, repositories,
and accessions, choosing the right format for each.

Everything starts from one dashboard, with a dedicated page for each data type
and format.

---

## Overview

The Export dashboard groups your data into four areas - Archival Descriptions,
Authority Records, Repositories, and Accessions - and offers the appropriate
export formats for each:

| Format | Full name | Used for |
|--------|-----------|----------|
| CSV | Comma-separated values | Bulk export of any data type, spreadsheet-friendly |
| EAD 2002 | Encoded Archival Description | Archival descriptions, with the full hierarchy |
| Dublin Core | Dublin Core XML | Archival descriptions, simple metadata |
| EAC-CPF | Encoded Archival Context (Corporate bodies, Persons, Families) | Authority records |

Each export page lets you narrow what is exported - by repository, by level of
description, by date range, or by a record limit - before you download.

---

## Key features

- Central dashboard listing every export type and format.
- CSV bulk export for archival descriptions, authorities, repositories, and accessions.
- EAD 2002 export of a fonds or collection, optionally including all descendants.
- Dublin Core XML export of archival descriptions.
- EAC-CPF XML export of authority records (corporate bodies, persons, families).
- Filters for repository, level of description, parent record, and date range.
- Record-limit options for large exports.
- Round-trip support: accession CSV exports can be re-imported.

---

## How to use

All export pages live under the **Export** area and require an administrator
login.

### Open the dashboard

Go to **`/export`**. The dashboard shows four cards - Archival Descriptions,
Authority Records, Repositories, and Accessions - each with buttons for the
formats it supports.

### Export archival descriptions

- **CSV (bulk):** go to **`/export/csv`**. Choose a repository (or all), pick one
  or more levels of description, optionally limit to a parent record by slug, and
  tick **Include descendants** to pull in child records. Then export.
- **EAD 2002:** go to **`/export/ead`**. Select a top-level fonds or collection
  from the list and choose whether to include descendants. The export includes
  identifiers, titles, dates, scope, arrangement, access restrictions, custodial
  history, access points, and the hierarchy.
- **Multi-format page:** go to **`/export/archival`** to pick CSV, EAD 2002, or
  Dublin Core, filter by repository, and set a record limit (100, 500, 1,000,
  5,000, 10,000, or all).

### Export authority records

Go to **`/export/authority`**. Choose **EAC-CPF** (XML) or **CSV**, optionally
filter by entity type, and set a record limit. The page shows the total number
of authority records available.

### Export repositories

Go to **`/export/repository`**. Repositories export as CSV. Set a record limit if
needed. The export includes name, identifier, history, collecting policies,
holdings, opening times, access conditions, contact information, and GPS
coordinates.

### Export accessions

Go to **`/export/accession-csv`**. Choose a repository (or all) and optionally
set an acquisition date range. Download the CSV. The export carries the full
accession record, including donor details, event types and dates, alternative
identifiers, intake notes, and the source culture.

---

## Configuration

The Export module has no separate settings screen. The choices that shape an
export are all made on each export page:

- **Repository** - export everything or limit to one repository.
- **Level of description** - for CSV archival exports.
- **Parent record and descendants** - scope a CSV or EAD export to one branch of
  the hierarchy.
- **Record limit** - cap the number of rows for large exports.
- **Date range** - for accession exports.

### Bulk export and re-import from the command line

For very large or scheduled jobs, a bulk export command is available
(`php artisan export:bulk`). Accession CSV files exported here can be brought
back in through the Ingest wizard, or with the accession import command
(`php artisan csv:accession-import filename.csv`), so a CSV round-trip is
supported.

---

## References

- Source package: `packages/ahg-export/`
- GitHub issue: [GH #567](https://github.com/ArchiveHeritageGroup/heratio/issues/567)
