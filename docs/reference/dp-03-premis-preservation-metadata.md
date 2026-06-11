# PREMIS preservation metadata in Heratio

**Summary.** PREMIS (PREservation Metadata: Implementation Strategies),
maintained by the Library of Congress and at version 3.0, is the de-facto
international standard for the metadata a repository needs to *preserve* digital
content - as opposed to describing it for discovery. PREMIS models four entity
types: Objects (the files / representations being preserved), Events (everything
that happens to them: ingest, fixity check, virus scan, format identification,
normalisation, migration, replication), Agents (the people, software, and
organisations responsible), and Rights (the permissions governing preservation
actions). Heratio records PREMIS events for every preservation action, exports a
valid PREMIS 3.0 XML document per record, validates against a bundled PREMIS 3.0
schema, and embeds PREMIS inside METS AIPs.

## The concept

PREMIS answers "what do we need to know to keep this file alive and trustworthy
over decades?" Its four entities:

- **Object** - the bytestream / file / representation, with technical
  characteristics (format, size, checksum), significant properties, and
  relationships to other objects (for example "this PDF/A is a normalised version
  of that .doc").
- **Event** - a dated, typed record of an action: `ingestion`, `fixity check`,
  `virus check`, `format identification`, `normalisation`, `replication`,
  `validation`, `migration`. Each event has an outcome (success / failure) and
  links the objects and agents involved.
- **Agent** - a person, a piece of software (for example Siegfried, ClamAV), or
  an organisation that performed or authorised an event.
- **Rights** - the basis (licence, statute, donor agreement) under which a
  preservation action was permitted.

PREMIS is the practical expression of OAIS *Preservation Description
Information* (see `dp-01-oais-reference-model`).

## How Heratio addresses this

- **Event recording.** Every preservation action Heratio runs writes a
  PREMIS-shaped event. Package building writes into `preservation_event` and
  `preservation_package_event`; fixity, format-ID, and virus scans write events
  through the preservation services. The events admin view is
  `GET /admin/preservation/events` (route `preservation.events`).
- **PREMIS 3.0 XML export.** `php artisan premis:export` (the
  `PremisExportCommand` in `ahg-preservation`) exports a PREMIS 3.0 XML document
  for an information object. The serialiser is
  `AhgPreservation\Services\PremisXmlSerializer`.
- **Schema validation.** The package bundles the official PREMIS 3.0 schema at
  `packages/ahg-preservation/resources/schemas/premis-3-0.xsd`, so exported
  documents can be validated against the standard.
- **PREMIS rights.** `AhgPreservation\Services\PremisRightsService` plus the
  `AhgPremisRights` model capture the rights entity (the permission basis for
  preservation actions), distinct from access-control ACLs.
- **PREMIS-in-METS.** AIPs embed the full PREMIS event chain inside the METS
  amdSec / digiprovMD. The METS serialiser
  (`AhgMetadataExport\Services\Exporters\MetsSerializer`, namespace
  `http://www.loc.gov/premis/v3`) emits PREMIS digiprovMD for AIPs and
  deliberately omits it for DIPs. See `dp-04-mets-packaging`.
- **Maturity signal.** The preservation-maturity assessment reads the
  `preservation_event` table as evidence of administrative / provenance metadata
  when scoring the Metadata functional area (see `dp-08-ndsa-levels`).

## Gaps / not yet

- Heratio records the Object, Event, and Rights entities thoroughly; Agent
  capture is present (tool name / version recorded on scans) but is not exposed
  as a separately browsable PREMIS Agent registry in the UI.
- PREMIS export is per-information-object on demand via the CLI; there is no
  bulk "export PREMIS for an entire fonds in one click" button in the admin UI
  yet (the per-record `premis:export` command is the supported path).
