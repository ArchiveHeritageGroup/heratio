# Content Authenticity in Heratio

**A technical and architectural writeup**

Author: Plain Sailing Information Systems
Date: 30 June 2026
Scope: How the Heratio platform establishes, signs, records, and verifies the authenticity and integrity of digital content across its lifecycle.

---

## 1. Executive Summary

Heratio treats "content authenticity" not as a single feature but as a layered chain of evidence that runs from the moment a file is captured or ingested, through every transformation, to the point a member of the public verifies it. No single mechanism is trusted on its own; instead the platform combines seven reinforcing layers:

1. **Fixity** - cryptographic checksums (SHA-256) computed at baseline and re-verified on a schedule, so silent corruption or tampering is detected.
2. **PREMIS preservation events** - a structured, standards-based event log (ingest, format identification, fixity, virus scan, normalization, replication) that records what happened to an object, when, by which agent, and with what outcome.
3. **PRONOM format identification** - every object is identified against the PRONOM registry (PUID, version, risk level) so authenticity can be asserted about a known, characterised format.
4. **Normalization (Format Policy Registry)** - Archivematica-style preservation and access derivatives, each independently checksummed and linked back to its source.
5. **Provenance and chain of custody** - governance headers and a time-ordered custody chain per record, plus per-digital-object provenance records.
6. **C2PA 2.1 Content Credentials** - cryptographically signed (Ed25519) content provenance manifests, emitted as sidecars or embedded in the media itself, with public verification. This is the layer that makes content tamper-evident to anyone, anywhere.
7. **Tamper-evident audit trail** - a hash-chained who-did-what log where each entry cryptographically links to the previous one, so the audit record itself cannot be silently altered.

Underpinning these are two storage-level integrity systems: **OCFL** (Oxford Common File Layout) for versioned, content-addressed object storage with manifest fixity, and **offsite backup replication** with SHA-256-verified ledgers.

The standout differentiator is the **C2PA package** (`ahg-c2pa`): Heratio does not merely record metadata about authenticity, it produces industry-standard, cryptographically signed Content Credentials that travel with the asset and can be independently verified - the same standard adopted across the imaging and AI industry for provenance.

---

## 2. The Authenticity Model

Authenticity in an archival context means being able to answer, defensibly, three questions about any digital object:

- **Is it intact?** (Has the bitstream changed since we received it?) -> Fixity.
- **What is it, and where did it come from?** (Format, origin, custody, transformations) -> PRONOM + PREMIS + Provenance.
- **Can a third party trust it without trusting us?** (Cryptographic, independently verifiable proof) -> C2PA Content Credentials + hash-chained audit.

Heratio answers all three. The first two are the traditional digital-preservation contract (OAIS / PREMIS / PRONOM). The third - independent, cryptographic verifiability - is what most archival systems lack and what Heratio adds through C2PA and the chained audit log.

The model maps cleanly onto recognised standards: **PREMIS v3** preservation vocabulary, the **PRONOM** PUID registry, **C2PA 2.1** content credentials, **OCFL v1.1** object layout, and **RFC 8493 BagIt** for transfer integrity. Maturity against **NDSA Levels of Digital Preservation** and **DPC RAM v2.0** is self-assessable inside the platform.

---

## 3. Layer 1 - Fixity and Checksums

Fixity is the bedrock: a cryptographic digest of the bitstream taken at a known-good moment, then re-computed and compared over time.

| Concern | Implementation | Storage |
|---|---|---|
| Checksum generation | `ahg-preservation` - `PreservationService::generateChecksum()` (SHA-256, with algorithm, file size, and timestamp recorded) | `preservation_checksum` |
| Fixity verification | `ahg-preservation` - `PreservationService` compares stored digest against the current file hash and logs pass/fail with duration | `preservation_fixity_check` |
| Scheduled sweeps | `ahg-preservation` - `RunFixitySchedulesCommand` cron-orchestrates re-verification of stale objects (not checked in N days) | `preservation_fixity_check` |
| Coverage reporting | `ahg-core` - `FixityService` produces an NDSA-Levels coverage report: baseline count, verified count, algorithms in use, last sweep | `digital_object`, `preservation_fixity_check` |
| Admin dashboard | `ahg-core` - `FixityController` surfaces a read-only fixity dashboard | - |

Why it matters for authenticity: a checksum mismatch is the canonical signal that a file has been corrupted, swapped, or tampered with. Because checks are scheduled (not just on-ingest), Heratio catches "bit rot" and unauthorised changes that occur in storage long after ingest.

---

## 4. Layer 2 - PREMIS Preservation Events

Every meaningful action against an object is recorded as a PREMIS-compliant event. This is the audit spine of the preservation world: it answers "what was done to this object, when, by whom, and did it succeed?"

- **Event emission:** `ahg-scan` - `PremisEventService::emit()` writes structured events with `event_type`, `event_datetime`, `event_outcome`, `linking_agent_type`, and an optional JSON `outcome_detail`.
- **Event types:** virus check, format identification, fixity, ingestion, derivation, replication.
- **Outcomes:** success, warning, failure.
- **Logging wrapper:** `ahg-preservation` - `PreservationService::logEvent()` inserts the PREMIS row and simultaneously captures an audit entry under the agent `heratio-preservation`.
- **Format and scan events:** `FixityScanService::writeIdentifyEvent()` and `writeScanEvent()` record format identification (name, version, MIME, PUID) and virus-scan outcomes (scanner version, threats found).
- **Export:** `PremisExportCommand` exports events as PREMIS XML; `ahg-metadata-export` includes a `PremisSerializer` and a `PremisInMetsBuilder` for PREMIS-in-METS packaging.

Primary table: `preservation_event`. This log is what an auditor or depositor reads to confirm that the institution actually performed (and passed) its preservation obligations.

---

## 5. Layer 3 - PRONOM Format Identification

Authenticity claims are only meaningful about a known, characterised format. Heratio identifies every object against the PRONOM registry.

- **Pure-PHP identifier:** `ahg-preservation` - `PronomIdentificationService` uses magic-byte signatures (~70 formats: PDF, TIFF, JPEG, PNG, WAV, FLAC, MP3, MP4, the Office OOXML family, ZIP, glTF, PLY, STL, and more), with extension and MIME fallback. Returns PUID, format name, version, MIME, confidence, basis, and a risk level (low / medium / high / unknown).
- **Siegfried integration:** `SiegfriedTool` shells out to `sf -json` for authoritative PRONOM matching, filtering out extension-only guesses.
- **Format registry:** maintained per PUID+version in `preservation_format`, storing `risk_level`, an `is_preservation_format` flag, and a `preservation_action` (retain or monitor).
- **Per-object identification:** stored in `preservation_object_format`; `batchIdentify()` bulk-identifies objects lacking a row; `riskDistribution()` aggregates objects by risk for dashboards.
- **Scan wrapper:** `ahg-scan` - `FormatIdService` tries Siegfried, falls back to the `file` command, and flags obsolete formats.

Why it matters: format risk is an authenticity and longevity signal. A high-risk or obsolete format triggers normalization (Layer 4), and the PUID is one of the identity assertions baked into the C2PA manifest (Layer 6).

---

## 6. Layer 4 - Normalization (Format Policy Registry)

Heratio runs Archivematica-style normalization to produce preservation and access derivatives, each one independently verifiable and provably linked to its source.

- **Engine:** `ahg-preservation` - `NormalizationService`. Matches a Format Policy Registry (FPR) rule by PRONOM PUID or MIME, executes the appropriate tool (ImageMagick, FFmpeg, Ghostscript, LibreOffice), SHA-256s the output, attaches it as a child `digital_object` with usage "Preservation Master", and emits a PREMIS normalization event.
- **Rules:** `preservation_normalization_rule` holds the FPR - highest-priority active rule per source format, specifying target format, MIME, tool, options (preset, quality, compression), and purpose (preservation or access).
- **Conversion ledger:** `preservation_format_conversion` records source format/MIME/SHA-256, target format/MIME, tool, options, status, source and output size, output checksum, and duration.
- **Backfill:** `NormalizeExistingCommand` retroactively normalizes already-ingested objects.

Authenticity angle: every derivative carries its own checksum and a recorded, PREMIS-logged derivation relationship to its parent. There is no orphan derivative - you can always prove which original a preservation or access copy came from, and that the conversion completed without silent corruption.

---

## 7. Layer 5 - Provenance and Chain of Custody

Provenance answers "where did this come from, and through whose hands?" Heratio tracks it at two levels.

**Record / archival-description level** (`ahg-information-object-manage`):

- `ProvenanceService` maintains a per-record governance header in `provenance_overview`: current status, custody type, acquisition type and date, certainty level, gap flags and descriptions, research status, Nazi-era provenance check, cultural-property status, completeness, and publication flag.
- A time-ordered custody chain is held in `provenance_entry`.

**Digital-object level** (`ahg-c2pa`):

- `RecordDigitalObjectProvenance` (event listener) captures provenance whenever a digital object is mutated or signed.
- `ProvenanceRecordService` persists and queries per-object provenance (who, what, when) in `ahg_c2pa_provenance_record`.
- `ProvenanceTraceService` aggregates every digital object's provenance within a single archival record into one chronological trace and computes a record-level authenticity summary (verified / partially verified / unsigned / invalid / none), classifying events as capture, edit, AI, or signature.

This dual model means authenticity can be asserted both about the intellectual record (custody, acquisition, gaps) and about each individual file beneath it (capture, edits, AI involvement, signatures).

---

## 8. Layer 6 - C2PA 2.1 Content Credentials (the cryptographic core)

This is Heratio's distinguishing authenticity capability. The `ahg-c2pa` package builds, signs, and verifies **C2PA 2.1** content provenance manifests - the same Content Credentials standard used across the imaging and AI industry. It is specifically tuned for AI-touched archival content, so any AI-generated suggestion, OCR, or derivative carries a tamper-evident, cryptographically signed record of how it was produced.

**How a manifest is built and signed:**

- `ManifestBuilder` assembles an unsigned C2PA 2.1 manifest store (per spec section 11): manifest label, assertions, claim, and claim signature. Fluent API: `withTitle()`, `withFormat()`, `withAssetHash()`, `withAssetFile()`, `withAssetString()`.
- `Assertion` carries individual assertions such as `c2pa.actions` and `c2pa.ai_meta`; `Claim` carries the instance index, generated time, creator statement, and assertion list.
- `C2paSigner` signs the claim with **Ed25519** over SHA-256(JCS(claim)), returning a signed-manifest struct (`alg=Ed25519`, key id, signature, pad) with a matching verification method.
- `CborEncoder` encodes manifests to CBOR for media embedding.

**How it is attached and persisted:**

- `C2paService` is the orchestration entry point: `manifestForAiSuggestion()`, `signManifest()`, `sidecar()`, `embed()`, `embedInJpeg()`. It persists **every** manifest to `ahg_c2pa_manifest` for audit and reissue, resolves the `c2patool` binary, and degrades gracefully if the binary is absent.
- `WriteC2paSidecar` (listener) writes a `.c2pa.json` sidecar next to the media after signing.
- `C2paEmbedCommand` wraps `c2patool` to embed a manifest as JUMBF inside JPEG / PNG / TIFF / MP4.

**How it is verified - including by the public:**

- `PublicCheckController` exposes an anonymous endpoint that verifies and displays a manifest for any public asset.
- `InjectContentCredentialsBadge` (middleware) injects an HTML "Content Credentials" badge linking to that public check whenever an asset carries a manifest.
- `C2paReverifyCommand` re-verifies all signed manifests against their public keys; `C2paSmokeCommand` runs an end-to-end diagnostic; `C2paProvenanceBackfillCommand` backfills provenance for legacy objects.

**Surfaces:** authenticity dashboards, content-credentials views, coverage reports, a transparency report, a trust dashboard, a trust dossier per record, verified-records listings, and public verify pages (`verify/check`, `verify/object`, `verify/trace`).

Why this is the keystone: fixity and PREMIS prove integrity *to the institution*. C2PA proves it *to everyone else*. Because the manifest is cryptographically signed and can be embedded in the file itself, a downstream user - a researcher, a court, a journalist, another repository - can verify the asset's provenance and detect tampering without any access to, or trust in, Heratio's internal database.

Tables: `ahg_c2pa_manifest` (signed manifests), `ahg_c2pa_provenance_record` (per-object provenance).

---

## 9. Layer 7 - Tamper-Evident Audit Trail

The audit log records who did what - but an audit log is only trustworthy if it cannot itself be silently rewritten. Heratio's `ahg-audit-trail` package makes the log tamper-evident.

- **Audit service:** `AuditService::log()` records action, category, object id and type, user id and name, JSON details, IP address, and timestamp; it respects the `audit_enabled` setting and supports IP anonymization (masking the last octet).
- **Action vocabulary:** create, update, delete, view, download, export, import, login, logout, approve, reject, publish, unpublish.
- **Hash chaining:** `ChainedAuditWriter` maintains a cryptographic chain where each row's hash links to the previous row - a blockchain-style construction. Any retroactive edit or deletion breaks the chain.
- **Chain verification:** `VerifyChainCommand` validates chain integrity on demand.
- **Lifecycle:** `ReportCommand` (compliance reports), `PruneCommand` and the `ahg-core` retention/purge commands (retention policy), plus an `AuditableCommand` trait and an ORM-level `AuditLog` facade (`captureMutation()`, `captureEdit()`) for before/after state capture.

Primary table: `security_audit_log`. The hash chain means an attacker who gains write access cannot quietly cover their tracks - altering history is detectable.

---

## 10. Storage-Level Integrity

Two further systems protect authenticity at the storage tier, beneath the application logic above.

**OCFL - Oxford Common File Layout (`ahg-ocfl`):**

- `StorageRoot` maintains an OCFL v1.1 root (`0=ocfl_1.1` namaste + `ocfl_layout.json`), resolves object ids to paths, reads/writes `inventory.json`, and verifies object fixity.
- `ContentAddressing` computes SHA-512 (or SHA-256 / MD5) for content-addressable storage; `Inventory` holds the per-version manifest (content hash -> path/size/state).
- `OcflVerifyCommand` re-hashes all content against the inventory manifest; `OcflInitCommand`, `OcflIngestCommand`, and `OcflExportCommand` manage the lifecycle.
- Heratio extensions embed EXIF, extracted text, and AI metadata into the inventory, with a PII gate that strips GPS/location fields when a PII finding exists.

OCFL gives versioned, content-addressed storage where every version is fixity-checkable and the layout is recoverable without the application - a strong authenticity guarantee at rest.

**Offsite backup replication (`ahg-backup`):**

- `OffsiteReplicator` resolves S3 / rsync / local-filesystem drivers and wraps outbound data in GPG AES-256 if a passphrase is configured.
- Each driver records SHA-256, status, and timestamps in `ahg_backup_replication`.
- `VerifyBackupIntegrityCommand` re-verifies remote backups against the recorded SHA-256 and exits non-zero on failure.

This extends fixity beyond the primary store: copies are independently hashed and re-verified, satisfying the geographic-replication and fixity dimensions of NDSA Levels.

---

## 11. Transfer Integrity at Ingest (BagIt)

Authenticity starts at the door. The `ahg-scan` package's `BagItIngestService` implements RFC 8493 BagIt:

- Detects directory-form or zipped bags (`isBag()`).
- Validates `bagit.txt` and the payload manifests, verifying every file's checksum (`manifest-sha256.txt` / `manifest-md5.txt`) **before** ingest.
- Parses `bag-info.txt` (Source-Organization, Contact-Email, External-Identifier) into provenance.

The `ProcessScanFile` job then identifies format, scans for malware, and logs PREMIS events. The result: nothing enters the repository without its transfer integrity being proven, and the act of proving it is itself recorded.

---

## 12. Standards and Maturity Alignment

| Standard | Where Heratio implements it |
|---|---|
| PREMIS v3 (preservation events, agents, rights) | `preservation_event`, `PremisEventService`, `PremisSerializer`, PREMIS-in-METS export |
| PRONOM (format registry / PUIDs) | `PronomIdentificationService`, Siegfried, `preservation_format` |
| C2PA 2.1 (Content Credentials) | `ahg-c2pa` - `ManifestBuilder`, `C2paSigner` (Ed25519), `C2paService` |
| OCFL v1.1 (object layout, fixity) | `ahg-ocfl` - `StorageRoot`, `Inventory`, `OcflVerifyCommand` |
| RFC 8493 BagIt (transfer integrity) | `ahg-scan` - `BagItIngestService` |
| NDSA Levels of Digital Preservation | `FixityService` coverage report + self-assessment |
| DPC RAM v2.0 | Preservation maturity self-assessment (`ahg-core`) |

Heratio also ships an in-platform **preservation maturity self-assessment** (`PreservationMaturityService`, `PreservationSelfAssessmentService`) that scores the live instance against NDSA Levels and DPC RAM v2.0 by reading what the system actually does - fixity coverage, format identification, replication - rather than relying on a paper questionnaire.

---

## 13. End-to-End: the Authenticity Lifecycle of One File

To see how the layers compose, follow a single TIFF from arrival to public verification:

1. **Arrival** - delivered in a BagIt bag. `BagItIngestService` verifies the manifest checksums before anything is written. Transfer integrity proven. (Layer / BagIt)
2. **Ingest** - `ProcessScanFile` identifies the format against PRONOM, runs a virus scan, and emits PREMIS `ingestion`, `format_identification`, and `virus_check` events. (Layers 2, 3)
3. **Baseline fixity** - a SHA-256 baseline is recorded in `preservation_checksum`. (Layer 1)
4. **Normalization** - if the format policy requires it, a preservation master is generated, checksummed, attached as a child object, and a PREMIS `derivation` event is logged. (Layer 4)
5. **Provenance** - custody and acquisition are recorded at the record level; per-object provenance records capture capture/edit/AI events. (Layer 5)
6. **Content Credential** - a C2PA 2.1 manifest is built, signed with Ed25519, persisted to `ahg_c2pa_manifest`, and written as a sidecar (or embedded as JUMBF). Any AI involvement is declared in `c2pa.ai_meta`. (Layer 6)
7. **Every action above** is written to the hash-chained `security_audit_log`, and key actions also to `preservation_event`. (Layers 2, 7)
8. **At rest** - the object lives in an OCFL store (content-addressed, manifest fixity) and is replicated offsite with SHA-256 verification. (Storage layers)
9. **Ongoing** - scheduled fixity sweeps and C2PA re-verification confirm nothing has changed. (Layers 1, 6)
10. **Public verification** - a member of the public opens the asset, sees the Content Credentials badge, and verifies the signed manifest through the anonymous public-check endpoint - without trusting Heratio at all. (Layer 6)

At every step the evidence is recorded, standards-based, and - for the C2PA and audit-chain layers - cryptographically tamper-evident.

---

## 14. Summary Table - Mechanisms, Packages, Tables

| Layer | Mechanism | Package | Key tables |
|---|---|---|---|
| 1 | Fixity / checksums | ahg-preservation, ahg-core | `preservation_checksum`, `preservation_fixity_check` |
| 2 | PREMIS events | ahg-scan, ahg-preservation | `preservation_event` |
| 3 | PRONOM format ID | ahg-preservation, ahg-scan | `preservation_format`, `preservation_object_format` |
| 4 | Normalization (FPR) | ahg-preservation | `preservation_normalization_rule`, `preservation_format_conversion` |
| 5 | Provenance / custody | ahg-information-object-manage, ahg-c2pa | `provenance_overview`, `provenance_entry`, `ahg_c2pa_provenance_record` |
| 6 | C2PA Content Credentials | ahg-c2pa | `ahg_c2pa_manifest`, `ahg_c2pa_provenance_record` |
| 7 | Tamper-evident audit | ahg-audit-trail, ahg-core | `security_audit_log` |
| Storage | OCFL | ahg-ocfl | OCFL `inventory.json` (filesystem) |
| Storage | Offsite backup integrity | ahg-backup | `ahg_backup_replication` |
| Ingest | BagIt transfer integrity | ahg-scan | `ingest_file`, `scan_folder` |

---

## 15. What Sets Heratio Apart

Most archival and DAM systems stop at fixity and PREMIS - integrity that the institution can attest to internally. Heratio adds the two layers that make authenticity provable to outsiders and resistant to insider tampering:

- **C2PA 2.1 Content Credentials with Ed25519 signatures**, embeddable in the asset and publicly verifiable, purpose-built to declare AI involvement in archival content.
- **A hash-chained audit log** whose own integrity is cryptographically verifiable.

Combined with PRONOM-driven format identification, FPR normalization, OCFL content-addressed storage, and SHA-256-verified offsite replication - and self-scored against NDSA Levels and DPC RAM v2.0 - Heratio delivers a complete, standards-aligned content-authenticity chain from the moment of capture to the moment of public verification.
