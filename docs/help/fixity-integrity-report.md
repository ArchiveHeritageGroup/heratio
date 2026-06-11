# Fixity / integrity report

How Heratio verifies that stored files have not changed since ingest, and where
to read the results.

## What fixity is

Fixity is the assurance that a digital file is bit-for-bit identical to the file
that was ingested. Heratio records a **checksum** (a short fingerprint) and the
**algorithm** used (typically SHA-256) for each digital object when it is loaded.
A fixity check re-computes the file's checksum on disk and compares it to that
stored baseline. A match means the file is intact; a mismatch means the bytes
have changed and the file needs attention. This is the actionable "Integrity"
functional area of the NDSA Levels of Digital Preservation.

## The report: /admin/fixity

The admin report at **Admin → Fixity / integrity** (`/admin/fixity`) is
read-only. It shows:

- **Digital objects** in scope (local files only; remote references are excluded).
- **With checksum baseline** — how many objects carry a stored checksum and
  algorithm, so they can be verified, shown as a coverage bar.
- **Without baseline** — objects that cannot be verified until a checksum is
  recorded.
- **Never verified** — objects with a baseline that have not yet been checked.
- **Last sweep** — when the most recent verification ran and what it found.
- **Latest result per object** — a roll-up of match / mismatch / missing file /
  skipped (oversize) / error, as labelled CSS bars.
- **Recent checks** — the most recent individual results.

The page never runs a verification itself, so it always loads quickly.

## Running a sweep

Verification is done out-of-band by a bounded command:

```
php artisan ahg:fixity-sweep            # verify up to 100 objects
php artisan ahg:fixity-sweep --limit=500
php artisan ahg:fixity-sweep --json     # machine-readable summary
```

The sweep is deliberately **bounded** (default 100, hard-capped at 1000 per run)
so it can never launch an unbounded hash of the whole collection, and
**resilient**: a missing or unreadable file is recorded as `missing_file` /
`error` rather than stopping the run, and files above a size cap are recorded as
`skipped_oversize` so a single very large master cannot make a sweep run
unreasonably long. It prefers objects that have never been verified, so repeated
runs make steady forward progress across the collection.

A **daily scheduled sweep** runs automatically (off-peak, in the background), so
the report stays current and integrity drift is caught early without manual
action. Each result is written to an append-only log; nothing in the catalogue
is ever changed.

## Reading the results

| Result | Meaning |
|---|---|
| Match | The file is intact — its checksum matches the baseline. |
| Mismatch | The file has changed since ingest. Investigate (restore from a known-good copy). |
| Missing file | No file was found on disk for the object's stored path. |
| No baseline | The object has no stored checksum to verify against yet. |
| Skipped (oversize) | The file is above the sweep size cap; verify it out-of-band. |
| Error | The file was present but could not be read or hashed. |

A non-zero count of **Mismatch** is the signal that warrants action.
