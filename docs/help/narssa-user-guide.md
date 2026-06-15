> Heratio Help Center article. Category: Plugin Reference.

# Transfer Packaging (NARSSA) - User Guide

## Package Approved Records into a Standards-Based Transfer Bundle

The Transfer Packaging module builds a complete, self-describing transfer package
for sending records to a receiving archive. Each package is a single compressed
archive containing an inventory, a standards-based metadata wrapper, per-item
descriptions, and checksums that let the receiving archive verify everything
arrived intact.

The module supports the transfer regimes used by national archives. It is named
for NARSSA (the National Archives and Records Service of South Africa), but the
package format - METS wrapper, EAD 2002 descriptions, CSV manifest, SHA-256
checksums - is equally suited to NARA (United States), The National Archives
(United Kingdom), and equivalent regimes elsewhere. Heratio is jurisdiction-
neutral, and this is one of its pluggable per-market records-management modules.

---

## Overview

Given a set of records, the module assembles a transfer package containing:

- **`manifest.csv`** - a structured inventory listing each item's archival
  reference, title, retention schedule code, digital-object count, byte size, and
  SHA-256 checksum.
- **`transfer.xml`** - a METS wrapper holding metadata, file locations, and
  checksums for the whole package.
- **Per-item `description.xml`** - an EAD 2002 description for each record.
- **The digital files** themselves, organised one folder per item.

The whole bundle is compressed to a `.tar.gz` file, given an auto-allocated
reference (for example `NARSSA-2026-001`, numbered per calendar year), and
recorded in the database along with its overall SHA-256 hash and a status of
"packaged".

The module integrates with the retention and disposal workflow, so records
approved for transfer through that workflow can be packaged automatically.

---

## Key features

- One compressed transfer package per batch, with a manifest, METS wrapper,
  per-item EAD 2002 descriptions, and the digital files.
- SHA-256 checksums at both the package level and the per-item level for
  integrity verification.
- Auto-allocated, per-year transfer references (for example `NARSSA-2026-001`).
- Package either an explicit list of records or every approved transfer awaiting
  packaging.
- Each packaged transfer is recorded in the database with its item count, total
  size, file path, checksum, and status.
- Integrates with the retention and disposal workflow.

---

## How to use

This module currently runs from the command line. (A web dashboard for browsing,
status, retry, and download is planned for a later phase.)

### Package a specific set of records

Run the transfer-package command with a comma-separated list of record IDs:

```
php artisan narssa:transfer-package --io-ids=5,12,99 --user-id=1 --title="My batch"
```

Options:

- `--io-ids=` - the records to package, by their IDs.
- `--user-id=` - the user initiating the transfer.
- `--title=` - an optional title for the batch.
- `--description=` - an optional description.

### Package every approved transfer

To package all records that the retention and disposal workflow has approved for
transfer and that have not yet been packaged:

```
php artisan narssa:transfer-package --from-approved --user-id=1
```

The command finds the approved transfer actions, packages them, and links each
disposal action to the package it now belongs to.

### What you get back

The command reports the package reference, the item count, the digital-object
count, the total bytes, the package SHA-256 hash, and the file path of the
`.tar.gz`. Packages are written under the configured uploads path.

---

## Configuration

There are no module settings to set. Packages are written under the application's
configured uploads path. Each package is recorded in the transfer tables with its
status, which progresses from "packaged" towards "transmitted" and "accepted" as
the transfer proceeds, and can hold the receipt reference issued by the receiving
archive on acceptance.

---

## Standards used

- **EAD 2002** (Encoded Archival Description) for per-item descriptions.
- **METS** (Metadata Encoding and Transmission Standard) for the package wrapper.
- **CSV** for the inventory manifest.
- **SHA-256** checksums for integrity, at both package and item level.
- **tar.gz** for the package container.

---

## References

- Source package: `packages/ahg-narssa/`
- GitHub issue: [GH #603](https://github.com/ArchiveHeritageGroup/heratio/issues/603)
