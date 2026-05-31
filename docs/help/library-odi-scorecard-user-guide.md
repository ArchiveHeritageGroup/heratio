# ODI Conformance Scorecard

The ODI Scorecard reports how well the library's data and feeds conform to Open Data Interoperability expectations — the metadata-quality and standards-conformance checks that underpin discovery, data exchange and BIBFRAME/KBART interoperability.

Open it from **Library Management → ODI Scorecard**.

## What it shows

The scorecard runs a set of conformance checks across the library data and presents a per-check result — pass / warning / fail with a count of affected records — so you can see at a glance where metadata is complete and standards-compliant and where it needs attention. Checks cover areas such as:

- **BIBFRAME conformance** — whether catalogue records carry the elements needed for clean BIBFRAME mapping.
- **KBART feed conformance** — whether electronic-resource holdings feeds meet KBART formatting and required-field rules.
- **Core metadata completeness** — presence of identifiers, titles, dates and other required descriptive fields.

## Refreshing the scorecard

The scorecard is computed from the current data. Use **Refresh** to re-run the checks after you have edited records or imported a batch, so the figures reflect the latest state. Refreshing can take a moment on large collections because it scans the catalogue.

## Using the results

- Treat **fail** rows as a worklist — drill into the affected records and complete or correct the flagged metadata.
- **Warnings** are non-blocking but improve data quality and downstream interoperability when resolved.
- Re-run **Refresh** after a remediation pass to confirm the score has improved.

## Related

- **BIBFRAME ODI conformance** and **KBART ODI** reference material describe the underlying rules each check applies.
