> Heratio Help Center article. Category: Digital Preservation.

# OAIS packages: SIP, AIP, DIP

## The idea

The OAIS model (the international reference model for archives, ISO 14721) moves
content through your archive as three kinds of package:

- **SIP - Submission Information Package.** What a depositor hands you. Heratio
  keeps it as the record of "what arrived".
- **AIP - Archival Information Package.** The long-term preservation copy Heratio
  builds and keeps. It adds checksums (fixity), the PREMIS preservation history,
  and technical and rights metadata. This is the copy that gets replicated and
  fixity-checked.
- **DIP - Dissemination Information Package.** The access copy a user receives -
  usually derivatives only, with forensic and private metadata deliberately
  stripped, so it is safe to share publicly.

The flow is: SIP -> (you curate it) -> AIP -> (on request) -> DIP. AIPs are
permanent; DIPs are made on demand and can be regenerated.

## How Heratio builds them

Heratio creates these packages in **BagIt** format (an international packaging
standard that bundles files with their checksums). Packages are created:

- automatically during **ingest** (per batch) and **scanner capture** (per file),
  and
- on demand from a **"Build package"** action on a record.

Each package's contents follow OAIS:

- SIP = master file(s) + descriptive metadata
- AIP = SIP content + PREMIS events + fixity manifest
- DIP = access derivatives only + descriptive metadata

## Doing it in Heratio

- See and manage packages at `/admin/preservation/packages`.
- Open a package to **validate** it (re-verify every checksum) or view its
  contents.
- Heratio records the lineage between packages (a SIP, the AIP built from it, and
  any DIPs derived from that AIP), so you can always trace an access copy back to
  what was originally deposited.

## See also

- Digital preservation overview (help)
- Fixity and checksums (help)
- Format identification (help)
