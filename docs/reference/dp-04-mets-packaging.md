# METS structural / packaging metadata in Heratio

**Summary.** METS (Metadata Encoding and Transmission Standard), maintained by
the Library of Congress, is an XML container that wraps the several kinds of
metadata an information package needs into one structured document. A METS file
has distinct sections: a header (`metsHdr`), one or more descriptive-metadata
sections (`dmdSec`, holding Dublin Core / MODS / EAD / etc.), an
administrative-metadata section (`amdSec`, holding technical, rights, source, and
*digital-provenance* metadata - the last of which is where PREMIS lives), a file
inventory (`fileSec` with file groups), and a structural map (`structMap`) that
says how the files fit together. METS is the standard "wrapper" format for AIPs
and DIPs and is what tools like Archivematica produce. Heratio both *exports*
METS (with package-type-aware profiles) and *ingests* Archivematica-style METS.

## The concept

METS does not invent its own metadata vocabularies; it is a *container* that
references or embeds them:

- `dmdSec` - descriptive metadata (what the thing is): Dublin Core, MODS, EAD.
- `amdSec` - administrative metadata, split into techMD (technical
  characteristics), rightsMD (rights), sourceMD (source / analog origin), and
  digiprovMD (digital provenance - typically PREMIS events).
- `fileSec` - the inventory of actual files, grouped (for example master vs
  access derivative).
- `structMap` - the structural hierarchy tying files to intellectual units.

The same METS structure can describe a SIP, an AIP, or a DIP; the difference is
*which sections are populated*. An AIP carries the full forensic story; a DIP is
deliberately lean and public-safe.

## How Heratio addresses this

- **METS export with package-aware profiles.**
  `AhgMetadataExport\Services\Exporters\MetsSerializer` produces METS and changes
  its profile by package type:
  - **SIP** - amdSec only, original `digital_object` only in fileSec, a structMap.
  - **AIP** - full DC dmdSec, full amdSec including PREMIS digiprovMD (the per-event
    chain), and all file groups (master + derivatives).
  - **DIP** - full DC dmdSec, rightsMD only in amdSec, *no* PREMIS digiprovMD, so
    no forensic trace or PII leaks into a public dissemination copy.
  The serialiser declares the PREMIS namespace (`http://www.loc.gov/premis/v3`)
  for the digiprovMD it embeds. This is the concrete realisation of
  "PREMIS-in-METS" described in `dp-03-premis-preservation-metadata`.
- **METS ingest (Archivematica interoperability).** The scanner can recognise and
  transform METS on the way in: `ahg-scan` ships an XSL transform
  `packages/ahg-scan/resources/transforms/mets-to-heratio.xsl`, and
  `AhgScan\Services\AlternateFormatTransformer` detects a METS wrapper
  (matching the `loc.gov/METS` namespace) and routes it through that transform.
  METS can carry DC / MODS inside its dmdSec, so descriptive metadata survives the
  round trip. This is how Heratio accepts Archivematica-produced AIP METS.
- **Where it sits.** METS export rides the metadata-export package alongside the
  other standards exporters (EAD 2002/3/4, MARCXML, MODS, DACS, DublinCore,
  CIDOC-CRM, EAC-CPF) under `/admin/metadata-export`.

## Gaps / not yet

- The METS *export* is driven by the OAIS package builder (it is produced as part
  of SIP / AIP / DIP construction) rather than offered as a standalone
  "download METS for this record" button on the metadata-export download menu
  (which currently lists dcterms / mods / rad / dacs and CIDOC-CRM).
- METS ingest covers the common Archivematica-style profile; exotic METS profiles
  (deeply nested structMaps, custom extension schemas) may not map cleanly through
  the single `mets-to-heratio.xsl` transform.
