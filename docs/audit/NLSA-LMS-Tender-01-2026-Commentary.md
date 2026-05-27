# NLSA LMS Tender (NLSA 01/2026-2027) — Commentary

**Source:** `docs/tenders/LMS-Tender.pdf`
**Bid closing:** 01 June 2026 at 11h00
**Evaluation:** Two-stage — (1) Pre-qualification (SBD forms + CSD registration) then (2) Mandatory technical criteria then (3) Functional evaluation (minimum 70/100 to pass; 50/100 presentation threshold)
**Price:** 80/20 preference point system (80 price, 20 specific goals — 100% women-owned = 10 pts, 100% youth-owned = 10 pts)
**Contract:** 5 years, 90-day delivery SLA, no upfront payment, 120-day bid validity

---

## 1. Overall Assessment

This is a **major institutional procurement** (National Library of South Africa, 2.5M+ items, Pretoria + Cape Town campuses) for an Integrated Library Management System. The specification is comprehensive but **deliberately platform-agnostic** — it does not name any existing LMS product, which is correct for a competitive tender.

### Key Strengths of the Specification

| Area | Comment |
|------|---------|
| Standards awareness | MARC21, RDA, AACR, ISAD(G), EAD, VRA, Dublin Core, BIBFRAME, FRBR, Z39.50, NISO/KBART, COUNTER/SUSHI — all correctly specified |
| Interoperability | RESTful APIs with auth, encryption, audit logging — well-specified |
| Security | AES-256 at rest, TLS 1.2+ in transit, key rotation, MFA — correctly specified |
| AI/chatbot | Separate specification for NLP chatbot (2.5), AI predictive search (2.11) — forward-looking |
| Accessibility | WCAG-adjacent ("web accessibility standards", assistive technology) — present but vague |
| SA compliance | GRAP 103 & 17 (serials), POPIA implied by encryption/security requirements, legal deposit mandate (s6) |
| ILMS/ILL | ISO 10160/10161 + OCLC Tipasa compatibility — correctly specified for a national library |
| Evaluation | Two-stage gate (pass mandatory technical to reach functionality) is appropriate for this scale |

---

## 2. Critical Weaknesses and Gaps in the Specification

### 2.1 No Digital Preservation or Archival Standards Mentioned

The NLSA collects manuscripts, photographs, and digital images (Background 1.2). The specification covers library materials extensively but **completely omits:**
- OAIS (ISO 14721) — the reference model for digital preservation
- PREMIS (Preservation Metadata) — mandatory for any institutional digital repository
- METS — widely used in archives and libraries for complex digital objects
- Archivematica, Lockss, or any trusted digital repository certification criteria (TRAC/DSACC)
- Any format migration, bit-level preservation, or fixity requirements
- Any handling of born-digital archives vs. digitised surrogates

**Risk:** A commercial LMS vendor will optimise for bibliographic records and serials. The NLSA's mandate to "collect, record, preserve and make available the national documentary heritage" requires an **archival information system** (like Heratio) at its core, not just an LMS. The tender as written could award to a product that handles ISBNs well but cannot ingest EAD finding aids, manage provenance chains, or preserve TIFF files for 100 years.

**Recommendation:** Add a Digital Preservation section — OAIS functional entities (Ingest, Archival Storage, Preservation Planning, Administration, Access), PREMIS events/rights/objects, format migration policy, trusted repository audit criteria.

### 2.2 AI/Chatbot Specification is Extremely Vague

Section 2.5 specifies chatbot capabilities in general terms but:
- No mention of what knowledge base or data sources the chatbot should query
- No AI governance, audit trail of AI decisions, or AI provenance requirements
- No specification for the underlying LLM, hosting model (on-prem vs cloud), or data residency
- No mention of hallucination mitigation, confidence thresholds, or human escalation SLAs
- "Adaptive learning" (2.5.5) implies the system may retain user queries — this requires a POPIA data retention policy to be specified

**Risk:** Any vendor can claim to have a chatbot. The NLSA has no basis to evaluate whether the AI is fit for a national heritage institution.

**Recommendation:** Add an AI Governance appendix specifying: (a) knowledge sources must be NLSA-managed content only; (b) all AI interactions logged with query/response/feedback for audit; (c) AI suggestions must be flagged as AI-generated; (d) human escalation for heritage/accuracy-critical queries.

### 2.3 No Specific Requirements for Non-Roman Script and Multilingual Support

Section 2.2 mentions "correct display of non-Roman scripts and diacritics" and the chatbot requires "multi-language support including local languages" (2.5.4). But:
- Which SA languages? (11 official languages)
- Is full i18n of the UI required or just display?
- Are African scripts (Tifinagh, Arabic for Arabic-language SA documents, etc.) in scope?
- No mention of UNICODE normalization, right-to-left support, or Ndebele/Zulu orthography handling

**Recommendation:** Specify which languages require full UI localisation vs. content display support, and require proof of non-Roman script cataloguing capability (e.g., Zulu authority records, Arabic-script MARC records).

### 2.4 Reading Room / On-Site Research Facilities Not Addressed

The NLSA has a physical reading room (researchers access original materials on-site). The specification covers:
- Booking functionality (3.5 — mentions "booking functionality for library resources")
- ILL (3.3)
- Circulation

But it does not address:
- Physical reading room seat management / researcher registration (cf. Heratio's `research_researcher` + `research_booking` model)
- Archival access restrictions (sensitive materials, access periods, restricted records)
- Researcher identification and vetting (security, reading room rules acknowledgement)
- Material retrieval from stacks (curatorial workflow)
- No mention of the difference between library loan and archival consultation workflows

**Recommendation:** Add an Archival Research Services section modelled on OAIS Access workflow — reading room registration, access decision, material retrieval, researcher audit trail, time-limited access sessions.

### 2.5 Legal Deposit Specifics are Thin

Section 6 (Legal Deposit) specifies:
- "Link with publisher systems" (ONIX ingestion)
- "Metadata information" (brief record creation, audit trail)
- GRAP 103 accommodation (acquisition module)

But does not address:
- What happens when a publisher refuses deposit?
- Integration with the National Library's existing legal deposit legislation (Legal Deposit Act 25 of 1997)
- Handling of e-journals vs physical monographs differently
- ISSN/ISBN management for 2.5M items (some already on system, some not)
- De-duplication against existing catalogue

### 2.6 Interoperability with Heritage/Archive Systems Absent

The tender is for an LMS, but the NLSA is also an **archive**. The specification does not mention:
- EAD (Encoded Archival Description) — the standard for archival finding aids
- ISAD(G) — General International Standard Archival Description
- RiC-O (Records in Contexts) — the new ICA/OAIS standard for record-level description
- Any IIIF (International Image Interoperability Framework) support for image delivery
- Any connection to SA National Library's potential participation in TROVE, WorldCat, or other union catalogues
- Any METS/MODS handling for digital objects with structural metadata

**Risk:** A vendor that has never dealt with archival description (EAD finding aids, hierarchical fonds/collection/series/file/item levels, multiple languages in a single collection) will not surface this gap until after contract award.

### 2.7 No Mention of Linked Data or BIBFRAME Implementation Path

Section 2.8 mentions "Linked data and BIBFRAME for interoperability" as a software compliance practice. But:
- No requirement to produce linked open data
- No mention of SPARQL endpoints or RDF triplestores
- No requirement for BIBFRAME conversion or MARC-to-BIBFRAME migration tooling
- No mention of Schema.org, OAI-PMH (though Z39.50 is mentioned)

For a national library with a mandate to "promote awareness... internationally", a proper linked-data strategy should be a contract deliverable, not a software compliance checkbox.

### 2.8 Digitisation Repository Specification is Minimal

Section 4.2 (Digitisation repository) specifies:
- WAC format web file ingest
- Validation of ingested files
- Metadata creation
- Link related digital objects and metadata (families)
- Quality control and approval before publication
- Discoverability to end users

But omits:
- Image format requirements (TIFF master vs. derivative JPEG; PDF/A for documents)
- IIIF API support for image delivery (International Image Interoperability Framework is now standard for cultural heritage)
- OpenSeadragon or Mirador for deep-zoom viewing of digitised photographs/manuscripts
- OCR/ICR requirements for text documents (scanned books)
- Colour calibration, resolution standards, ICC profiles
- Bulk ingest from external digitisation vendors (BagIt, DPN)
- METS structural metadata for multi-page objects
- IIIF Presentation API for complex objects
- Audio/video digitisation handling

**The absence of IIIF is the most glaring technical omission.** The cultural heritage sector globally has moved to IIIF for image interoperability. A national library that cannot expose its digitised photographs via IIIF is behind the international standard of care.

### 2.9 Backup and Data Recovery Plan Vague

Section 1.6 (Backup and data recovery plan) is marked YES/NO checkbox only in the mandatory criteria. The specification does not:
- Require off-site or geographically separated backup
- Specify RPO (Recovery Point Objective) or RTO (Recovery Time Objective)
- Require documented disaster recovery testing schedule
- Mention data sovereignty (data must remain in SA? Or on SA-owned infrastructure?)
- Require a Business Continuity Plan separate from IT backup

### 2.10 No Vendor Lock-in Mitigation

The 5-year contract (4.1) locks the NLSA in with one vendor. The specification does not require:
- A full data export in standard MARC/XML/EAD formats upon contract termination
- An open data licence for catalogue records
- A schema or API version stability commitment
- The right to transition data to another system without vendor assistance fees
- Any reference to the South African POPIA section 22 data portability requirements

**This is a significant procurement risk.** If the vendor fails or the contract is not renewed, the NLSA needs to be able to exit cleanly.

---

## 3. What the Tender Gets Right

| Item | Why it is correct |
|------|-------------------|
| RESTful APIs with auth + encryption + audit logging (2.2) | Essential for a national library integrating with SABIN, DISA, WorldCat, national catalogue |
| AES-256 + TLS 1.2+ (2.3) | POPIA-compliant at state-of-the-art level |
| 99.5% uptime SLA (2.12) | Appropriate for a national institution; 99.5% = ~3.65 days downtime/year max |
| ISO 10160/10161 + OCLC Tipasa (3.3) | Correct ILMS/ILL standards for a national library doing interlibrary loan |
| GRAP 103 & 17 (8.1) | SA-specific statutory accounting for heritage assets — correctly required |
| FRBR clustering and deduplication (2.2) | Modern catalogue theory — correct to require |
| NISO KBART, COUNTER/SUSHI (2.12) | Correct e-resource usage statistics standards |
| No upfront payment (5.8) | Standard government procurement protection |
| Presentation with live demo (5.7) | Appropriate for a system of this scale |
| Youth + women ownership preference (10, SBD 6.1) | South African procurement policy correctly incorporated |
| 80/20 preference points | Correct for a sub-R50M contract |
| Mandatory technical gate | Correct procurement governance — unresponsive bids eliminated before evaluation |
| 70/100 minimum for functionality | High enough to be meaningful, low enough not to preclude competition |

---

## 4. How This Relates to Heratio / AHG

Heratio's `ahg-research` module has direct overlap with several NLSA requirements:

| NLSA Requirement | Heratio Equivalent |
|---|---|
| Booking functionality for library resources | `research_booking` + `research_reading_room` + `research_equipment_booking` |
| Researcher/user reading room registration | `research_researcher` (encrypted PII, approval workflow, expiry tracking) |
| Chatbot / AI library assistant | `LlmService` + chatbot blade component |
| Usage statistics | `research_analytics` dashboard + `research_activity_log` |
| Multi-format discovery (manuscripts, photographs, monographs, serials) | Heratio multi-format archival description (EAD, ISAD(G), RAD, MODS, MARC) |
| IIIF for digitised images | IIIF/Mirador integration (cf. `docs/audit/FUNCTIONALITY.md`) |
| OpenSeadragon deep zoom | Custom OpenSeadragon plugins (scalebar, magnifier, filter) |
| OCR for digitised text | Tesseract + LLM post-correction pipeline |
| Metadata extraction (EXIF, IPTC, XMP) | `MetadataExtractionService` + ExifTool 12.76 |
| GRAP compliance | `ahg-grap` market module |
| Legal deposit | `ahg-legal-deposit` module |
| POPIA compliance (encryption, access audit) | `EncryptionService` (AES-256-CBC), audit trail tables |
| Research projects + collaboration | `research_project` + `research_project_collaborator` |
| Bibliography management | `research_bibliography` + `research_citation_log` (14 231 rows) |

**However, Heratio is missing:**
- Full MARC21 catalogue management (AtoM/Heratio uses RAD/ISAD(G) archival description, not library MARC)
- Z39.50 server/client (required in this tender)
- COUNTER/SUSHI compliance (e-resource usage statistics)
- ONIX ingestion (legal deposit from publishers)
- Serials prediction pattern management
- Acquisition ordering workflow (the `researcher_submission` module handles researcher submissions, not vendor acquisitions)
- ILMS/ILL module with ISO 10160/10161

**Conclusion:** Heratio is a strong fit for the NLSA's **archival and heritage** requirements (digitised manuscripts, photographs, digital archives, researcher services, reading room). It is not a fit for the NLSA's **library** requirements (MARC cataloguing, Z39.50, serials, ILL, acquisitions). A joint bid — Heratio for the archival/heritage/digital preservation layer + an established library LMS (Koha, Evergreen, Alma, Sierra) for the bibliographic layer — would be the technically correct approach. The tender as written does not anticipate or enable this hybrid architecture.

---

## 5. Actionable Recommendations for AHG

If AHG intends to bid on this tender:

### 5.1 Confirm Bid Type
- As a software vendor (Heratio): full LMS bid
- As a system integrator: Heratio (archival) + licensed LMS partner (library) in a consortium
- As a consultant: implementation partner only (no product)

### 5.2 Critical Missing Sections to Address
Before submitting, AHG would need to add or clarify:
1. **Digital Preservation** — OAIS + PREMIS compliance, format migration policy, trusted repository evidence
2. **Archival Research Services** — reading room registration, access decision workflow, researcher audit trail
3. **EAD / ISAD(G) / RiC-O support** — archival description standard for manuscripts and photographs
4. **IIIF Image API** — deep-zoom, Mirador, IIIF Presentation API for the digitised image collection
5. **Linked Open Data** — BIBFRAME production, SPARQL endpoint, Schema.org exposure
6. **AI Governance** — POPIA-compliant AI audit trail, knowledge source documentation
7. **Data Portability / Exit** — full MARC/XML/EAD export on contract termination
8. **MARC21 catalogue** — the 2.5M items include monographs/serials requiring MARC rather than RAD

### 5.3 Evaluation Scoring Strategy
The functional criteria (100 pts):
- **Bidders Experience (50 pts)** — AHG needs 2 reference letters from national libraries + 2 from academic institutions. This is the hardest gate.
- **Team leader experience (10 pts)** — >10 years = 10 pts, 5-9 = 5 pts. Requires demonstrable CV.
- **Project plan (20 pts)** — All 6 sub-requirements must be fully met to score max.
- **Presentation (20 pts)** — Live demo of all 4 aspects (user-facing, workflows, admin/reports, AI).

Minimum to pass: 70/100.
Minimum to reach presentation: 50/100.

**Key risk:** The 50-pt experience requirement is the primary disqualifier. AHG must have traceable references from TWO national libraries and TWO academic institutions. If fewer than 4 such references exist, bidding is not viable.

### 5.4 Pricing Schedule
- Yearly escalation required per item (Section 8 of tender)
- Unit prices per year over 5 years
- 5-year total including VAT
- AHG should model: (a) licence/subscription fee, (b) implementation/migration, (c) annual support, (d) optional modules (AI, IIIF, etc.)

### 5.5 Closing Date: 01 June 2026
- 1 hard copy + 1 electronic copy (USB or CD, PDF password-protected)
- Contact: kenny.netshiongolwe@nlsa.ac.za / 012 401 3017

---

## 6. Summary Table

| Dimension | Rating | Notes |
|---|---|---|
| Technical completeness | **Medium** | Strong on library standards; weak on archival, digital preservation, IIIF |
| SA regulatory alignment | **Good** | GRAP 103/17, POPIA (implied), Legal Deposit Act |
| AI/chatbot specification | **Weak** | Vague; no governance, no knowledge-source definition |
| Interoperability | **Good** | REST APIs, Z39.50, MARC21, ONIX, ISO ILL — well-specified |
| Security | **Good** | AES-256, TLS 1.2+, MFA, audit logging |
| Digital preservation | **Absent** | No OAIS, PREMIS, METS, IIIF, format migration |
| Archival description | **Absent** | No EAD, ISAD(G), RiC-O, hierarchical description |
| Accessibility | **Vague** | "Web accessibility standards" but no WCAG level specified |
| Vendor lock-in mitigation | **Absent** | No data portability or exit clause |
| Evaluation rigour | **Good** | Two-stage gate, presentation demo, 80/20 preference points |

---

*Commentary prepared as part of Heratio audit series. Document: `docs/audit/NLSA-LMS-Tender-01-2026-Commentary.md`.*
