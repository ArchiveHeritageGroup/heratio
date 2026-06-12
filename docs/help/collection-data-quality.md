# Collection data-quality report

The Collection data-quality report (Administration, `/admin/data-quality`) is an archivist-facing dashboard showing how complete the catalogue's descriptions are against the core elements of ISAD(G), the international standard for archival description. It is read-only - it measures, it never changes a record.

## What it measures

Across the published catalogue, the report counts how many records are missing each of the core ISAD(G) descriptive elements:

- Title
- Reference code / identifier
- Date(s) - recorded as a linked event
- Creator
- Scope and content
- Extent and medium
- Level of description (fonds, series, file, item)
- Repository

Each element shows the number and share of records that are missing it, with a progress bar so the biggest gaps stand out.

## Completeness score

A single headline score shows the share of published records that carry all of the core elements - a quick read on overall descriptive quality. A short "top gaps" summary highlights which element is most often missing, so cataloguing effort can be aimed where it matters most.

## How to use it

Use it as a worklist: the elements with the largest "missing" counts are where description is weakest. Work through them, re-run the report, and watch the completeness score climb. Where a browse filter can isolate the affected records, the report links straight to that filtered list.

## Notes

- The report is read-only and never edits a record.
- It is built from cheap aggregate counts, so it is safe to open on a large catalogue.
- ISAD(G) is the international standard; the report makes no jurisdiction-specific assumptions.
- A fresh catalogue with nothing to measure shows a calm empty state rather than an error.
