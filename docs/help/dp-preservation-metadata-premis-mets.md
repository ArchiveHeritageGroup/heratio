> Heratio Help Center article. Category: Digital Preservation.

# Preservation metadata: PREMIS and METS

## Why preservation metadata matters

Descriptive metadata tells people *what* a record is so they can find it.
*Preservation* metadata tells the archive *what it needs to keep the file alive
and prove it is authentic* - its format, its checksum, and the full history of
everything done to it. Two international standards do this work in Heratio:
PREMIS and METS.

## PREMIS - the preservation history

PREMIS (version 3.0) records four things about preserved content:

- **Objects** - the files, with their format, size, and checksum.
- **Events** - every action taken: ingest, fixity check, virus scan, format
  identification, normalisation, migration, replication. Each has a date and an
  outcome.
- **Agents** - the people and software (for example the format-identification
  tool) responsible.
- **Rights** - the permission basis for preservation actions.

In Heratio, every preservation action you run is written as a PREMIS event. You
can browse the full event history at `/admin/preservation/events`, and an
administrator can export a PREMIS 3.0 XML document for any record.

## METS - the package wrapper

METS is an XML container that bundles all the metadata for a package into one
structured file: descriptive metadata, technical and rights metadata, the
**PREMIS preservation history**, the list of files, and the structure tying them
together. Heratio produces METS as part of building OAIS packages, and tailors it
to the package type:

- An **AIP** (archival copy) carries the full PREMIS history.
- A **DIP** (public access copy) deliberately leaves the PREMIS history out, so no
  forensic detail or private information is exposed.

Heratio can also *read* Archivematica-style METS on ingest, so packages produced
by other preservation systems can be brought in.

## See also

- Digital preservation overview (help)
- OAIS packages: SIP, AIP, DIP (help)
- Fixity and checksums (help)
