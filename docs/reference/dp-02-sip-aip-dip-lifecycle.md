# SIP / AIP / DIP - the information-package lifecycle in Heratio

**Summary.** OAIS (ISO 14721) moves content through an archive as three kinds of
information package. A *Submission Information Package* (SIP) is what a producer
deposits. An *Archival Information Package* (AIP) is the long-term preservation
copy the archive builds and keeps, complete with fixity and PREMIS provenance. A
*Dissemination Information Package* (DIP) is the access copy a consumer receives,
usually derivatives only with forensic / private metadata stripped. Heratio
implements all three as first-class objects: it builds them in BagIt format,
records them in a preservation package store, and models the SIP -> AIP -> DIP
state transitions with explicit lineage.

## The concept

- **SIP (Submission).** The producer's deposit: master file(s) plus whatever
  descriptive metadata accompanies them. It is the historical record of "what
  arrived" and, per OAIS, is normally left untouched once received.
- **AIP (Archival).** The curated preservation package. It adds what the archive
  needs to keep the content alive: fixity manifests (checksums), PREMIS
  preservation events, technical and rights metadata, and representation
  information. This is the copy that gets replicated and fixity-checked.
- **DIP (Dissemination).** The access package generated on request. It typically
  contains access derivatives rather than masters, and deliberately suppresses
  forensic / private metadata (for example PREMIS digiprovMD and PII) so it is
  safe to hand to the public.

The lifecycle is SIP -> (curation) -> AIP -> (on request) -> DIP. AIPs persist;
DIPs are disposable and regenerable.

## How Heratio addresses this

Heratio treats SIP / AIP / DIP as real, persisted package types:

- **Package builder.** `AhgIngest\Services\OaisPackagerService` builds a package
  for one information object as `sip`, `aip`, or `dip`. Its documented contents
  match OAIS:
  - SIP = payload master(s) + descriptive XML + bag-info
  - AIP = SIP content + PREMIS event export + fixity manifest
  - DIP = access derivatives only (no master) + descriptive metadata
  Packages are written into `preservation_package`,
  `preservation_package_object`, and `preservation_package_event`, and the
  builder emits PREMIS events into `preservation_event`.
- **State machine + lineage.** `AhgPreservation\Services\OaisLifecycleService`
  formalises the transitions: `createSip()` records a producer submission;
  `promoteSipToAip()` is the curatorial step (it calls `BagItService::buildPackage`
  on the SIP's source object and records a new AIP with
  `parent_package_id` pointing back at the SIP, leaving the SIP untouched as the
  record of what arrived); `createDipFromAip()` derives a per-request DIP from an
  AIP, with `parent_package_id` set to the AIP. Lineage is stored in
  `preservation_package.parent_package_id`.
- **Where packages are created.** Two callers build packages automatically: the
  ingest wizard (`IngestService::commit()`, per-batch) and the scanner
  (`ProcessScanFile`, per-file). An on-demand "Build package" admin action also
  exists - `POST /admin/preservation/package/build/{ioId}` (route
  `preservation.package.build`).
- **METS profile awareness of the package type.** The METS serialiser
  (`AhgMetadataExport\Services\Exporters\MetsSerializer`) produces a different
  amdSec per package type - AIP carries the full PREMIS digiprovMD event chain;
  DIP deliberately suppresses PREMIS so no forensic trace leaks into a public
  access copy. See `dp-04-mets-packaging`.
- **Admin view.** Built packages appear under
  `GET /admin/preservation/packages` (route `preservation.packages`), with a
  per-package view at `/admin/preservation/package/{id}`.

## Gaps / not yet

- The SIP -> AIP promotion is a deliberate, curator-driven step; there is no
  fully automatic "every SIP becomes an AIP on a timer" pipeline (by design, the
  curatorial review is the point).
- DIP generation supports filtering and derivative-only packaging, but advanced
  per-consumer shaping (for example bespoke watermarking or selective redaction
  per request) is partial and depends on the rights / redaction modules rather
  than being a single DIP-builder option.
