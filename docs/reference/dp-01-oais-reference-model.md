# OAIS reference model (ISO 14721) in Heratio

**Summary.** The Open Archival Information System (OAIS) reference model,
standardised as ISO 14721, is the foundational conceptual framework for digital
preservation. It defines an archive as an organisation of people and systems
that takes responsibility for preserving information and making it available to a
designated community. OAIS gives the field its shared vocabulary: the three
information packages (SIP, AIP, DIP), the six functional entities (Ingest,
Archival Storage, Data Management, Administration, Preservation Planning, and
Access), and the idea that preserved content must carry enough representation
information to stay understandable over time. Heratio implements the OAIS package
lifecycle concretely through its ingest pipeline and preservation package store,
and self-assesses against OAIS-aligned maturity frameworks.

## The concept

OAIS is a *reference model*, not a piece of software. It describes:

- **Information packages.** Content does not move through an archive as loose
  files; it moves as packages. A *Submission Information Package* (SIP) is what a
  producer hands over. An *Archival Information Package* (AIP) is what the archive
  stores for the long term. A *Dissemination Information Package* (DIP) is what a
  consumer receives. See `dp-02-sip-aip-dip-lifecycle`.
- **Functional entities.** Ingest accepts SIPs and prepares AIPs. Archival
  Storage holds AIPs and runs fixity and error checking. Data Management runs the
  catalogue / descriptive database. Administration runs day-to-day operations.
  Preservation Planning watches formats and the designated community and triggers
  migration. Access serves DIPs and finding aids.
- **Information model.** Each package bundles *Content Information* (the digital
  object plus its representation information) with *Preservation Description
  Information* (provenance, context, reference, fixity, and access rights). This
  is what PREMIS metadata captures in practice - see `dp-03-premis-preservation-metadata`.
- **Designated community.** The specific audience the archive commits to keeping
  the content understandable for. Format and migration decisions are made
  relative to that community.

## How Heratio addresses this

Heratio realises the OAIS lifecycle, not just the vocabulary:

- **Ingest** is the `ahg-ingest` package (the data-ingest wizard:
  configure -> upload -> map -> validate -> preview -> commit) and the `ahg-scan`
  capture pipeline (watched folders, Scan API). On commit, the ingest service can
  build OAIS packages via `AhgIngest\Services\OaisPackagerService`, which
  explicitly constructs SIP / AIP / DIP packages in BagIt format and records them
  into `preservation_package`.
- **Archival Storage + the AIP store** is the `ahg-preservation` package. The
  admin dashboard at `GET /admin/preservation` (route `preservation.index`)
  surfaces packages (`preservation.packages`), fixity (`preservation.fixity-log`),
  PREMIS events (`preservation.events`), formats (`preservation.formats`), and
  the OAIS lifecycle service `AhgPreservation\Services\OaisLifecycleService`,
  which models the SIP -> AIP -> DIP state machine with lineage in
  `preservation_package.parent_package_id`.
- **Data Management** is the core catalogue (information objects, actors,
  repositories, terms) plus Elasticsearch indices.
- **Preservation Planning** is expressed through the format risk registry and the
  preservation policies view (`preservation.policies`), plus the format
  identification described in `dp-06-pronom-format-identification`.
- **Access** is the public GLAM browse / show surfaces and the metadata export
  package (`ahg-metadata-export`), which serialises DIPs and finding aids.
- **Self-assessment.** Heratio scores the running instance against OAIS-aligned
  maturity frameworks: the NDSA Levels self-assessment at
  `GET /admin/preservation-maturity` (see `dp-08-ndsa-levels`).

## Gaps / not yet

- Heratio implements the OAIS *package lifecycle* and *storage / fixity*
  functions well, but Preservation Planning is partly manual: format-risk
  monitoring and migration are driven by the format registry and operator
  decisions rather than a fully automated "watch the designated community and
  auto-trigger migration" loop.
- There is no single screen that draws the full OAIS functional-entity diagram
  and maps each entity to its Heratio module; this knowledge layer is the closest
  thing to that map today.
