# METS download (per archival description)

> Heratio Help Center article. Category: Metadata / Export. Slug: `export-mets`.

## Overview

Every archival-description show page now exposes a **METS 1.12 XML** download alongside the existing Dublin Core / EAD / MODS / MARC / PROV-O exports. METS is the Library of Congress "Metadata Encoding and Transmission Standard" — a single XML envelope that bundles descriptive, administrative, file, and structural metadata for one logical archival object. It is the dissemination format Archivematica, Islandora, and most digital-preservation pipelines consume.

## Where to find it

1. Open any archival description (any URL of the form `/{slug}`).
2. In the right-hand sidebar look for the **METS / PROV-O** card.
3. Click **METS 1.12 XML**.

The browser will download `{slug}.mets.xml`.

## What the document contains

Each METS file produced by Heratio includes the four canonical METS sections:

- `<metsHdr>` — creation timestamp, last-modified timestamp, and a CREATOR `<agent>` row identifying the Heratio instance that produced the file.
- `<dmdSec>` — Dublin Core 1.1 descriptive metadata. The element list mirrors the per-record DC download byte-for-byte at the element level (title, creator, subject, description, publisher, date, type, format, identifier, source, language, coverage, rights).
- `<amdSec>` — administrative metadata. One `<digiprovMD>` child per row in `preservation_event` for this IO (or any digital object it owns), wrapping a PREMIS 3 `<event>` element with eventType, eventDateTime, eventOutcome, eventDetail, and linkingAgentIdentifier.
- `<fileSec>` — `<fileGrp USE="master">`, `<fileGrp USE="preservation">`, and `<fileGrp USE="access">` over the IO's `digital_object` rows. Each `<file>` carries MIMETYPE, SIZE, CHECKSUM (SHA-256), and an `<FLocat xlink:href>` pointing at the on-disk path.
- `<structMap TYPE="logical">` — one `<div>` per direct child IO so downstream tools (Archivematica, IIIF presentation builders) can walk the hierarchy.

The root `<mets>` element carries `PROFILE="https://heratio.theahg.co.za/profiles/mets/io-v1"`. Implementations that key off PROFILE attributes can rely on this URL to opt into Heratio-specific extensions in future revisions of the profile.

## When to use it

- Submitting an Archivematica transfer that needs a single bundled descriptive + preservation envelope.
- Sharing a complete preservation audit trail (PREMIS events) alongside the description in one file.
- Round-tripping through a digital-preservation pipeline that expects `mets.xml` at the root of a bag/SIP.

For the per-event audit trail in JSON form (W3C PROV-O), use the **PROV-JSON** download from the same card.

## Limitations (Phase 1)

- The structMap is flat — only direct children appear in the logical map. Multi-level hierarchies are still discoverable via the `<dc:source>` URL and the children's own METS files.
- METS Schematron / XSD validation is not performed server-side; the output targets METS 1.12 but downstream consumers should validate before ingest if strict conformance is required.
- File checksums are emitted with `CHECKSUMTYPE="SHA-256"` only. Multi-algorithm digests are tracked in the roadmap but not in Phase 1.

Issue: [#658 METS + PROV-O audit](https://github.com/ArchiveHeritageGroup/heratio/issues/658).
