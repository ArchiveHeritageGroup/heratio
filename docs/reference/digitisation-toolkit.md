# AHG Digitisation Toolkit - archival digitisation reference (specs, procedures, and tender-flaw lessons)

**Summary.** The AHG's reusable reference for archival digitisation across the full lifecycle: how to justify, plan and prove the programme (business case + benefit realisation), how to specify it (tender specification), and how to deliver and prove it (implementation procedures + QA verification), plus the lessons from a real delivery audit that caused defects. Use it to build any digitisation tender, proposal, business case or SOP - it is reference/building-blocks, not a live tender. Full documents (v2.0, four parts) live at `/usr/share/nginx/tender-reference/digitisation/` (Part 1 Tender Specification - now covering paper/graphic, photographic, bound, **audio-visual** and **3D/artefact** capture; Part 2 Implementation Procedures; Part 3 Programme/Business Case/Benefit Realisation; **Part 4 Born-digital records**; plus a worked Flaws-and-Corrections example and the NARSSA process map). Grounded in Metamorfoze, FADGI, ISO 19264-1, ISO/TR 19263-1, ISO/TR 13028, ISO 15489, plus IASA TC-04/05/06 (A/V), CARARE + 3D-ICONS (3D), and OAIS (ISO 14721)/PREMIS/METS/PRONOM (born-digital). The core rule: **all visible information in the original must be visible in the digital surrogate; preservation masters must be use-neutral and lossless.** Lifecycle order: plan/justify (Part 3) -> specify (Part 1) -> deliver/prove (Part 2) -> go-live/realise benefits (Part 3).

## Programme, business case and benefit realisation (Part 3 - the management wrapper)

Parts 1-2 are the technical delivery layer; Part 3 wraps them with the management lifecycle the technical spec omits. **Front end - business case:** the lifecycle context (digitisation sits inside records management and archives, inheriting appraisal/arrangement/retention - do not digitise an un-appraised mass); business needs assessment (state the driver: preservation, access, service delivery, space, legal, business continuity); collections needs assessment and selection (rank candidates on value/significance, demand, preservation risk, rights/access status, arrangement readiness, condition/effort - not everything is digitised, and not first); gap analysis (current vs desired capability); challenges (technical, financial, organisational, legal/IP, quality, preservation trade-off); risk assessment and management (loss/damage to originals, data loss/corruption, unusable output, security/privacy breach, vendor/continuity, budget/schedule, obsolescence - each with likelihood/impact/owner/mitigation/residual, as institutional strategy not project afterthought); benchmarking (plan->collect->analyse->implement->monitor, against standards + peers + own prior performance); business case (need, prioritised collections, benefits tied to metrics, options incl. in-house-vs-vendor and phased-vs-full, once-off + recurrent cost, risks, standards, stakeholders). **Planning:** project proposal (informed by all assessments + institutional strategy); resource identification (financial/human/technological/infrastructural + change management); in-house vs external vendor (cost, control/security, quality, capacity/speed, sustainability - blended models common, require skills transfer, acceptance criteria apply either way); project plan (phases/milestones around the Part 2 workflow, >=25%/quarter common target, weekly/monthly/quarterly reporting); best practices. **Governance and go-live:** governance/monitoring (sponsor, steering, roles, change control, regulatory/ethical + AI human-in-the-loop oversight); go-live (confirm acceptance/reconciliation, publish to access/discovery layer with access control, test real retrieval paths, confirm dark-archive copies before re-filing - avoid "scanned but not usable"). **Back end - prove value:** success metrics (output/progress, quality incl. A1-A14 pass rate, outcome/benefit); benefit realisation (measure post-go-live against the business-case baseline; report achieved/partial/not-achieved honestly - unmeasured benefits cannot be claimed); lessons learned/post-project review (feed back into next business case and spec; the Flaws doc is a worked example). **Digital preservation beyond delivery:** fixity over time, redundancy (2 copies, 1 off-site), format sustainability/migration, born-digital + obsolescence/emulation, digital curation (OAIS) - recurrent funded commitment, belongs in the business case. **Case-study exemplars (SA, public, collection-level):** NARSSA liberation/transition collections (Rivonia dictabelt, CODESA, multiparty negotiations, IEC 1994, TRC), DISA (banned struggle publications, UKZN), NaLHISA (struggle heritage online). **Pluggable legislative/policy module (SA):** the **approved** National Policy on the Digitisation of Arts, Culture and Heritage (obtain the signed copy), NARSSA Act 43/1996, the Constitution, Disaster Management Act, Batho Pele + NDP (mandate/justification), PAIA/POPIA, Preferential Procurement Regulations 2022/SBD suite - other markets substitute equivalents without changing the technical core. Origin: gap analysis against the THC/Alexio Motsi "Workshop on Digitisation of Records" (4 modules: business case, planning, execution, post-project) - our Parts 1-2 already exceeded the workshop's execution/technical layer; Part 3 adds the planning front-end and benefit-realisation back-end it taught that we lacked.

## Toolkit v2.0 additions (2026-08): A/V, 3D, born-digital, legislative framework, NARSSA crosswalk

v2.0 was driven by partner review (Alexio Motsi, now an AHG partner and the NARSSA digitisation process owner - see [[project_ahg_partners]]). Key additions:

- **Materials in scope (Part 1 section 3).** The capture spec differentiates material classes (paper/graphic, photographic, bound, audio-visual, 3D/artefact; born-digital -> Part 4). Audio-visual and 3D cannot be treated as flat paper.
- **Audio-visual capture (Part 1 section 3.3).** IASA TC-04 (audio), TC-05 (handling), TC-06 (video), FADGI A/V. Audio masters WAV/BWF 96kHz/24-bit (min 48/24), real-time one-pass, correct calibration (speed/azimuth/EQ), capture flat; video masters uncompressed or lossless FFV1/MKV through a time-base corrector; film 2K/4K DPX or FFV1. Principles: obsolescence urgency (magnetic media degrading, playback gear disappearing), one-time-transfer risk, signal-not-file (no noise-reduction/enhancement on the master). Criterion A13.
- **3D objects and artefacts (Part 1 section 3.4).** Methods: photogrammetry, structured-light, laser, RTI, multispectral - chosen by object. Capture geometry + colour/texture + true scale with a mandatory colour chart and scale bar. Masters: raw capture set (photogrammetry source images are part of the record) + high-res mesh (PLY/OBJ) + point cloud; access: glTF/GLB. Standards: CARARE + 3D-ICONS + CIDOC-CRM + PREMIS. Conservation-led handling. Criterion A14.
- **Part 4 Born-digital records.** A different discipline (custody not capture; the file IS the record). Lifecycle: accession/transfer (write-blocking, forensic disk imaging, BitCurator, chain of custody) -> appraisal + PII/sensitivity (POPIA) -> characterisation (PRONOM/DROID, Siegfried, JHOVE) -> format policy (retain original + normalise/migrate) -> integrity/fixity (SHA-256, PREMIS events) -> OAIS packaging (SIP/AIP/DIP; METS + PREMIS + Dublin Core; BagIt) -> preservation storage (>=2 copies, fixity + format-obsolescence monitoring, migration/emulation, ISO 16363, DPC) -> access (derivatives, human-reviewed redaction). Acceptance criteria B1-B7.
- **Acceptance criteria now A1-A14** (A12 AI governance, A13 A/V fidelity, A14 3D/artefact fidelity); born-digital uses the B1-B7 series.
- **Pre-capture controls hardened (Part 1 section 4):** inventory (4.1) as the reconciliation baseline; pre-digitisation condition assessment + remedial first-aid (4.2) guiding equipment/handling/sorting; control-sheet/enclosure info captured into metadata and digitised in place (4.3); chain of custody and physical security of material (4.4).
- **Preservation/access separation (Part 2 section 8):** keep preservation masters logically and physically separate from access copies - a dedicated preservation server / digital repository (dark archive) - to protect masters from accidental change/deletion/ransomware. Reporting cadence made duration-driven.
- **Legislative + policy mandate (Part 3 section 8):** the Constitution, NARSSA Act 43/1996, Disaster Management Act, Batho Pele + NDP as public-sector justification; the approved National Policy on Digitisation of Arts, Culture and Heritage (get the signed copy).
- **Public-sector governance crosswalk (Part 3 section 9):** Gate 0-6 mapped to the DSAC/NARSSA process map A6.1-A6.7 (Conceptualization -> Planning -> Approval per DOA -> MOA/MoU + Legal vetting -> Implementation into Strategic Plan/APP -> Payment internal/external -> Monitoring -> Close-out), with DOA, MOA/MoU + legal vetting, and APP alignment as explicit controls.
- **Prioritisation template + AI (Part 3 section 2.3):** a weighted-scoring template so selection is defensible, condition-assessment-fed, with an optional AI-assisted ranking (human-in-the-loop).
- **Risk of NOT digitising (Part 3 section 2.6)** as a first-class risk (loss of deteriorating records/ageing formats; passive preservation of originals), and the honesty that **digitisation is often not cheaper** - justify on preservation/disaster-mitigation/fragile-format-rescue, not cost saving.

## Standards to cite

Metamorfoze (preservation imaging); FADGI still-image guidelines (3-star/4-star); ISO 19264-1 (measurable image-quality analysis, imaged per batch via a target); ISO/TR 19263-1 (best practice for capture of cultural-heritage material); ISO/TR 13028 (implementation guidelines for digitisation of records); ISO 15489 (records management). Conformance must be measurable, not asserted.

## Technical capture specification (by material type)

Minimums; measure effective ppi at original size (not interpolated).

| Material | Min resolution | Bit depth | Preservation master | Access derivative |
|---|---|---|---|---|
| Text (to A0) | 300 ppi (600 fine print) | 8-bit grey or 24-bit colour | TIFF uncompressed/lossless | PDF/A with OCR; JPEG |
| Maps / plans | 300 ppi (600 fine detail) | 24-bit colour | TIFF uncompressed/lossless | JPEG2000 or PDF/A |
| Photographs / negatives | 600 ppi (up to 1200 small originals) | 24-bit colour / 16-bit grey | TIFF uncompressed/lossless | JPEG |
| Bound volumes | 300-400 ppi | 24-bit colour | TIFF uncompressed/lossless | PDF/A with OCR |

**Preservation-master rule (critical):** masters must be uncompressed or losslessly compressed (TIFF, or PDF/A with lossless imagery), min 8-bit greyscale / 24-bit colour. **Lossy or bi-tonal masters (e.g. JBIG2 1-bit PDF) are NOT acceptable preservation masters** - bi-tonal only for born-bitonal text with written client acceptance. Colour management: calibration target per batch, embedded ICC profile, no content/tonal alteration.

## Acceptance criteria (deliverables accepted only against these)

- A1 Completeness - every inventory item accounted for; master listing reconciles to inventory with an exceptions list.
- A2 No duplicates - files unique by checksum (SHA-256; MD5 legacy only); the manifest determines whether an equal-hash pair is an error or an intended copy (do not silently delete).
- A3 Resolution compliance - effective ppi meets the per-type minimum.
- A4 Format/bit-depth compliance - lossless/uncompressed masters; no lossy/bi-tonal masters unless accepted.
- A5 Searchable text - OCR text layer on all textual items (PDF/A).
- A6 Legibility/fidelity - all visible info reproduced; correct colour/order; no missing pages.
- A7 Integrity - no zero-byte/truncated/corrupt files.
- A8 Naming/structure - unique, convention-compliant; multi-page items structured.
- A9 Metadata - descriptive (Dublin Core/ISAD(G)), technical (embedded), structural.
- A10 Indexing and metadata quality - approved indexing level applied; mandatory fields, controlled values, hierarchy links and file relationships validate against the data dictionary.
- A11 Delivery and access - agreed layers/packages, encryption, manifests, checksums, access controls and platform ingestion (where in scope) complete and receipt-verified.
- A12 AI governance (only where AI is used) - every AI-assisted step (OCR/HTR, metadata extraction, classification, PII detection) runs under human-in-the-loop control: confidence-thresholded human review, a per-action provenance record, and no autonomous appraisal/identity/access/disposal decision.

Acceptance = 100% completeness and manifest reconciliation + 100% automated validation of mandatory metadata, filenames, integrity and checksums + risk-based visual and OCR sampling (sampling does not replace reconciliation/automated checks; a failed batch is re-worked).

## Indexing, minimum metadata, and delivery model

**Indexing** inherits the approved fonds -> series -> file -> item hierarchy (never invent an arrangement); choose depth per series: hierarchical, file/item-level, field/key-entry, OCR/HTR full-text, or authority-controlled. A client-approved metadata pilot is a production gate. **Minimum mandatory metadata** (delivered as UTF-8 CSV with a data dictionary): reference code, parent/level, title, date (ISO 8601), creator, description, material type/language, physical location, access status, digital filenames + relationships, extent, capture/technical data, SHA-256 checksum, OCR/HTR status, rights, QA/exception status. Do not silently normalise uncertainty (use agreed codes).

**Delivery and access model** (agree before production; usually layered): (1) structured file-system handover - masters + access copies in an arrangement-based folder tree, named by reference, with manifest + checksums; (2) searchable access derivatives - PDF/A with OCR, folder-sorted; (3) catalogue/repository platform - metadata + derivatives in AtoM/ISAD(G) or a RiC-capable system/EDRMS/search index. **Recommended layered:** preservation layer (lossless TIFF masters + SHA-256, two copies, one off-site, dark archive) + access layer (PDF/A-with-OCR / JPEG) + discovery layer (metadata + full-text in a catalogue, access-controlled). The manifest reconciles all three and traces every result to the physical box. Transfer encrypted (HDD/SSD/NAS/SFTP), verify SHA-256 on receipt with a signed acceptance, apply PAIA/POPIA access control (not obscurity).

## AI-assisted digitisation and human-in-the-loop (where applicable)

AI may assist digitisation but never replaces professional judgement; where used it runs under human-in-the-loop control, specified/priced/evidenced as such (Part 1 section 10, Part 2 sections 4.4 and 5.9, criterion A12). If no AI is used, this does not apply. **Where AI may assist:** OCR/HTR (a supplementary search index - the image stays the authoritative surrogate); metadata assistance (NER + suggested descriptive fields for archivist review); classification (suggested file-plan/record-type coding for human confirmation); quality/integrity (duplicate, resolution, blank/mis-scan detection); privacy (PII detection/redaction assistance flagged for a human decision, never auto-released). **Mandatory controls when AI is used:** (1) human authority - AI recommends, a person decides; no autonomous appraisal/disposal/access/identity decision; AI-suggested metadata is archivist-approved before it is authoritative and stays visibly distinct from human-authored; (2) confidence thresholds and review gates - each output carries a confidence score, below-threshold and high-sensitivity classes route to human review (threshold agreed with client); (3) provenance/audit - log model+version, input, output, confidence, reviewer and decision per action; (4) accuracy and bias - measure accuracy by era/script/language/condition class and screen for uneven accuracy so marginalised material is not under-served; (5) data protection - POPIA-governed processing on approved, ideally in-jurisdiction infrastructure, with a privacy assessment before processing; (6) disclosure of tools and method to the client. Verified per batch under A12 via section 5.9 (provenance present, class-based accuracy/bias sampling, human-review of below-threshold/sensitive outputs, no autonomous decisions). Aligns with the "AI augments, human decides" model in the ANC bias-benchmark pilot and Trust by Design work.

## QA verification procedures (run per batch; automate; keep evidence)

- **De-duplication** - checksum every file; duplicates by hash are returned. (Prevents the "thousands of duplicate surrogates" failure.)
- **Resolution/format verification** - read effective ppi + encoding/bit-depth; flag sub-spec and any lossy/bi-tonal master; report distribution per type.
- **Text-layer verification** - confirm extractable searchable text on every textual item.
- **Integrity check** - every file opens/renders; flag zero-byte/truncated.
- **Completeness reconciliation** - master listing vs inventory; nothing missing or extra.
- **Naming/structure/metadata** checks; **legibility sampling** by a QC officer.

Acceptance instruments: the **master listing/manifest** (one row per item/file: reference, filename, type, pages, ppi, format, checksum, status), an **exceptions report**, a **checksum manifest** (fixity), and a **delivery audit matrix** (A1-A12 pass/fail per batch with evidence; A12 only where AI is used).

## Tender-flaw lessons (from the Freedom Park RFQ-00205 delivery audit)

Spec flaws cause delivery defects - design them out:

| Spec flaw | Delivery defect it caused | Fix |
|---|---|---|
| Permitted 1-bit bi-tonal PDF as a preservation master | 5,506 JBIG2 / 5,507 1-bit masters | One lossless-master rule (TIFF/PDF-A, >=8-bit) |
| No de-duplication/checksum requirement | 2,375 duplicate files of 6,351 | Require uniqueness + checksums + dedup check |
| OCR required but no text-layer acceptance test | 6,342 of 6,351 with no text layer | Text-layer acceptance criterion, verified |
| Resolution minimums with no effective-ppi verification | ~12,552 images below 300 dpi | Measure effective ppi; reject sub-spec |
| No master listing / completeness reconciliation | duplicates + gaps undetected until audited | Require manifest reconciled to inventory |
| Inconsistent naming; contradictory bit-depth wording; Figshare named as archives platform | linking/rework issues | Strict naming convention; clear bit-depth; ISAD(G)/AtoM-compatible platform |

## Evaluation criteria (SA public-sector RFQ, adapt)

Specification & requirements 40; project plan & methodology 25; internal technical resources (proof of certification) 20; contactable references (<5 years) 15. Then the statutory price + specific-goals stage (80/20 or 90/10 preference points, Preferential Procurement Regulations 2022; SBD 6.1) and admin compliance (tax clearance, CSD, B-BBEE, SBD4, 90-day validity).

Related: [[reference_digitisation_estimation]], [[project_dsac_narssa_paper]].
