# Preserving AI-Generated Metadata as First-Class PREMIS/OAIS Records (iPRES paper thesis)

**Summary:** The AHG's iPRES paper argues that AI-generated metadata (transcriptions, entity extractions, classifications, summaries, confidence scores) is itself a first-class digital-preservation object, and is *more* endangered than the content it describes because the generating model is ephemeral. No new infrastructure is needed: an AI action maps cleanly onto PREMIS and OAIS. This is the digital-preservation-community framing of the AHG AI-RAM / epistemic-transparency line - companion to the preservation article (Heratio blog #27), "The model was never the hard part" (blog #22), and the KARMA 2026 governance paper.

Paper title: "Preserving the Machine's Interpretation: Treating AI-Generated Metadata as First-Class, Provenance-Bearing Records in OAIS/PREMIS Repositories". Author: Dr Johan Pieterse, The Archive and Heritage Digital Commons Group (Pty) Ltd (The AHG). Target: iPRES 2027 (Quebec; hybrid/remote-friendly). Full draft + docx in `/usr/share/nginx/conferences/ipres/`.

## Core claim

When a model transcribes a page, tags an entity, or drafts a summary it creates new metadata that enters the archival record and carries the institution's authority - but current practice writes it into a description field stripped of provenance, model identity/version, reported confidence, and any record of human verification. That metadata must be treated as a preservation object of record.

## Why AI metadata is a fragile preservation object

Three failure modes without preservation discipline:
1. **Authority flattening** - machine description sits in the same field, same visual authority, as human judgement; reader cannot tell machine from human, draft from verified.
2. **Uncertainty erasure** - models emit probabilistic output with confidence; repositories keep the answer and discard the confidence, turning a hedge into a fact.
3. **Provenance loss** - which model, version, input, date? Without it, an erroneous/biased output cannot be traced, corrected at scale, audited, or defended.

Preservation twist: AI metadata is MORE endangered than the content. A TIFF migrated to JP2 stays reconstructible from open specs for decades; the generating model is deprecated within a year or two and is effectively unreproducible ("we used the 2026 model" is not recoverable). If provenance + exact output are not captured *at ingest*, they are lost permanently. Ingest is the only accountability window.

## Mapping onto PREMIS / OAIS (no new infrastructure)

- **Object** - the input (e.g. page image) and the output (e.g. transcript), each first-class with fixity.
- **Event** - the generation: type (transcription / entity-extraction / classification), timestamp, outcome, links to input + output.
- **Agent** - the model as a software Agent identified by name AND version, plus the humans/orgs who commission and later verify.
- **Rights** - what may be done with the output and on what basis.
- **Significant properties** of an AI-output Object extend beyond text to include source (which Agent), confidence (model probability), basis (input + method), and verification state. Fixity computed at generation time anchors the claim.

Turns "the title is X" into "on date D, Agent M (v1.2) produced X at 0.71 confidence from input I; archivist A corrected it to Y on D+2" - a defensible PREMIS record.

## Four mechanisms wired into OAIS ingest

1. **Provenance capture** - every AI action writes a PREMIS Event (Agent+version, input, method, timestamp, output). Nothing enters without a traceable origin.
2. **Confidence preservation** - confidence kept as a significant property; low-confidence flagged, not silently trusted.
3. **Human-verification state** - for assertions above a risk threshold (rights, disposition, access, or a confidence floor) ingest is gated until a professional confirms/corrects; reviewer + outcome recorded as a further Event.
4. **Immutable audit trail** - every generation, confirmation, correction appended to a tamper-evident Events history.

## Reference implementation (Heratio / The AHG)

Platform already models provenance, PREMIS-style events, fixity, digital-object representations, role-based audit; the AI-metadata pattern reuses that rather than a parallel system. AI services (OCR/HTR, NER, summarisation) route through a governed gateway that returns model identity, version and confidence per result, so provenance + confidence are captured at generation, not reconstructed. Rights on AI-touched objects expressed as machine-enforceable ODRL policies. Costs to be honest about: human-verification gating consumes scarce professional time (target by risk, not uniform); confidence + per-Event provenance grows metadata volume (negligible vs content); model-version identifiers depend on vendor disclosure (opacity is itself a preservation risk worth recording); significant-property definitions for AI outputs are not yet standardised (community work opportunity under PREMIS).

## Key standards (verified editions, 2026-07)

- **OAIS** - current edition is **ISO 14721:2025** (= CCSDS 650.0-M-3, 3rd edn, Magenta Book, published Dec 2024). This supersedes the 2012 second edition (ISO 14721:2012 / CCSDS 650.0-M-2). Cite the 2025/CCSDS-650.0-M-3 edition. Three information packages (SIP/AIP/DIP); six functional entities (Ingest, Archival Storage, Data Management, Administration, Preservation Planning, Access).
- **PREMIS** - **Data Dictionary v3.0**, Library of Congress, 10 June 2015 (current). Five entities: Intellectual Entities, Objects, Events, Agents, Rights.
- **ISO/IEC 42001:2023** - AI management system standard (documented, auditable, human-overseen AI).
- **Colavizza, Blanke, Jeurgens & Noordegraaf (2022)** 'Archives and AI: an overview of current debates and future perspectives', *Journal on Computing and Cultural Heritage*, 15(1), Article 4, doi:10.1145/3479010.

See [[project_cfp_watch_2027]], and the companion docs `preservation-in-ai-archives.md` and `indigenous-knowledge-rights-in-archives.md`.
