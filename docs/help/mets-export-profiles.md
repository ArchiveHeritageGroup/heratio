> Heratio Help Center article. Category: Reference.

# METS Export Profiles (SIP / AIP / DIP)

## Overview

Heratio's per-record METS download supports three OAIS-standard profile
shapes from the same exporter. The profile controls how much metadata
and which file groups end up in the downloaded XML.

| Profile | Use case | What it contains |
|---|---|---|
| SIP | Submission Information Package - hand a record off to an external preservation system or partner archive | Minimal Dublin Core, rights + source administrative metadata, original master file only |
| AIP | Archival Information Package - the internal preservation master with full forensic trail | Full Dublin Core, full PREMIS event chain, master plus preservation plus access files |
| DIP | Dissemination Information Package - sanitised public download | Full Dublin Core, rights only (PREMIS suppressed), access copies only |

The PROFILE attribute on the root `<mets>` element changes per profile so
downstream consumers (Archivematica, RODA, dArceo) can tell at a glance
which package shape they received.

## How to download a specific profile

1. Open the archival description show page.
2. In the right-hand action bar, choose **METS export** under the
   Linked Data heading.
3. The default download is AIP. To grab SIP or DIP instead, append
   `?profile=sip` or `?profile=dip` to the download URL:

```
/informationobject/{slug}/export/mets             - AIP (default)
/informationobject/{slug}/export/mets?profile=sip - SIP
/informationobject/{slug}/export/mets?profile=dip - DIP
```

## What each profile leaves out

### SIP

- Subjects, places, languages, creators - the SIP descriptive metadata
  stays deliberately minimal so downstream systems can re-derive their
  own descriptive layer.
- PREMIS event history - submission packages predate the preservation
  chain.

### AIP

- Nothing. The AIP is the complete picture and should never be exposed
  publicly without a separate access decision.

### DIP

- The PREMIS event history (`digiprovMD` entries). Forensic data,
  staff agent identities, and migration trails are removed.
- The `sourceMD` administrative section, for the same reason.
- Master and preservation files - only access copies are referenced.

## Use cases

- **Hand-off to Archivematica or RODA** - SIP.
- **Internal preservation snapshot or archival backup** - AIP.
- **Public data dump / open access download** - DIP.

## Related

- See `docs/reference/mets-phase-4-profiles-sparql.md` for the developer
  reference (API signature, PROFILE URIs, file layout).
- The PROV-O JSON export (`/informationobject/{slug}/export/provo`) is
  the JSON-LD companion to the PREMIS chain inside the AIP.
- The SPARQL endpoint at `/admin/sparql` lets you query the PROV-O
  graph for a single record - see the developer reference for query
  examples.
