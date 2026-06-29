# Automatic digital preservation on ingest

> Audience: archivist, digital preservation officer, sysadmin

When you commit a Data Ingest session, every digital object it creates is
automatically run through a digital-preservation baseline - the same idea as
an Archivematica transfer running its micro-services. You do not have to switch
anything on: it happens on every ingest so your objects are preserved and
auditable from the moment they land.

## What happens to each ingested object

- **Fixity checksum** (SHA-256) is generated and stored. This is the baseline
  that future fixity checks compare against to detect bit-rot or tampering.
- **Format identification** records the file format (PRONOM) in the format
  registry, so obsolete or at-risk formats show up in preservation reports.
- **Virus scan** runs when a scanner is available.
- A **PREMIS "ingestion" event** is logged for the object, so there is a
  permanent, standards-based record that it was ingested, when, and by which
  process.

These steps are best-effort: if one of them cannot run (for example a scanning
tool is offline), the object is still ingested and the failure is logged for
follow-up - your ingest never fails because of a preservation step.

## Where to see the results

- **Central Dashboard → Data Ingest card** shows live ingest counts and a
  link confirming "Ingested objects are auto-preserved".
- **Preservation Dashboard → PREMIS Events** lists the ingestion event for
  each object.
- **Preservation Dashboard → Fixity Verification** lists the objects that now
  carry a checksum, and lets you run/verify fixity over time.
- **Preservation Dashboard → Formats / At-Risk Formats** shows the identified
  formats and flags ones that need migration.

## Requirements

- The Digital Preservation module must be installed (its tables must exist).
  If it is not installed, ingest still works normally - the preservation
  baseline is simply skipped.

## Related

- PREMIS XML export
- Preservation packages (BagIt / AIP)
- Data Ingest user guide
