# AI for Records & Archives - the Workbook

> A working reference for introducing AI/ML into records and archives management at
> scale (shared drives, file shares, email stores), with governance, privacy, legal
> defensibility, and operational adoption built in. Vendor- and jurisdiction-agnostic.
> Read the sections, then copy the **tick-sheets** and fill them in for your own pilot.

**Audience:** AI specialists and non-specialist records, legal, and executive readers.
**How to use:** sections 1-6 are the reference; section 7 is the fillable workbook;
section 8 is the compliance control catalog; section 9 links the supporting reading.

---

## 1. Core principles

1. **Provenance-first.** Every AI-derived assertion carries machine-readable provenance:
   what, who/what ran it, when, which model/version, configuration, and confidence.
2. **Privacy-by-design.** Detect and protect personal data early; record the lawful-basis
   decision; restrict output accordingly.
3. **Human-in-the-loop.** High-risk actions (disposal, transfer, sensitive access) require
   human validation. AI is an assistant, not an arbiter.
4. **Standards-aligned.** Map outputs to the applicable data-protection and
   access-to-information laws, national archival legislation, and ISO standards
   (15489 / 23081 / 16175 / 30301 / ISO/IEC 23894 / 42001) so they are auditable across
   jurisdictions.
5. **Phased and measurable.** Start with pilots and canary deployments behind measurable
   KPIs (precision/recall, time-to-discovery, access-to-information SLA compliance).

## 2. Architecture

A layered, minimal-dependence architecture suitable for hybrid deployments - see
**Figure 2** (`diagrams.md`). Policy and control flow down the layers; evidence and
provenance flow back up.

- **Regulatory & policy** - obligations, file-plan, retention authorities, classification.
  The source of truth for what the law and the institution require; every layer above must
  honour these constraints.
- **Governance** - committee, risk register, model registry, performance gates. Where people
  hold the AI to account: the committee approves models for production, and the gates keep an
  unproven model away from live records.
- **Ingest & provenance** - crawlers, snapshot store (file hash + URI), OCR, token-level
  provenance, ingest manifest, the `queue_for_ingest` tick-box. The controlled front door:
  content comes in without altering the originals, and each step is stamped with provenance.
- **Intelligence** - NER, classification, sensitivity detectors, dedupe, embeddings /
  semantic search, model serving. The machine work of understanding the content; deliberately
  swappable, so models can be upgraded without disturbing the layers around it.
- **Control & lifecycle** - ECM/EDRMS integration, retention enforcement (human-acknowledged),
  secure transfer, ACLs. Turns decisions into action inside the systems of record, and
  controls who can see what.
- **Human UX & review** - provenance timeline, diff viewer, review queues, audit exports.
  Where people work with the system: it makes the AI's reasoning visible and produces the
  audit evidence regulators and auditors ask for.

## 3. The pipeline

The document journey end to end - see **Figure 3**. A provenance event is emitted at
every stage, so any later assertion can be traced back to its source.

Source stores → crawler/ingest → snapshot + hash → OCR → NER/classification →
sensitivity/PII detection → confidence/risk gate → (auto-accept or human review) →
ECM/retention → access & export.

## 4. Key components

- **Crawler & ingest** - reads from the source stores without modifying them, under
  least-privilege access, picking up only what is new or changed on each pass; an uploader
  `queue_for_ingest` tick-box gives a single auditable point of entry; a snapshot store
  records each file's hash + object-store URI so any result can be reproduced and verified.
- **OCR & text extraction** - layout-aware OCR that understands page structure (columns,
  tables, headings) and records where each character sits (page + offset), so every word
  traces back to its place on the page; raw OCR is kept as a first-class artefact, never
  written over the original.
- **NER / classification** - entity recognition and classifiers trained on the
  organisation's own material and file plan, not a generic vocabulary; configurable
  confidence thresholds route uncertain items to a human; a model registry records each
  model's training-data provenance, evaluation sets and metrics.
- **Sensitivity & privacy** - detectors for personal and special-category data (names, IDs,
  health, financial) run early, before content is indexed; policy then decides whether to
  index, limit, redact or withhold - see **Figure 8**.
- **Provenance store** - append-only events (processing jobs, reviews, policy decisions)
  that are never edited or deleted, stored as structured JSON so any record's full history
  can be audited and replayed.
- **Human review & workflows** - low-confidence or high-risk items are surfaced to a curator
  rather than actioned automatically; a diff viewer shows raw vs canonical text and reviewer
  edits so corrections stay transparent - see **Figure 5**.
- **APIs & integration** - ingest, provenance, review, search, audit, and access-request
  endpoints, plus adapters to the systems records already live in (common ECM/EDRMS), so the
  pipeline augments the estate rather than replacing it.

## 5. Provenance and evidence

The spine of trust - see **Figure 4** (PROV-O). Store provenance at event granularity
(ingest, transform, classification, redaction, export). Each event: timestamp (ISO 8601
UTC), agent id, action type, inputs, outputs, optional signature, job id. Sign logs with a
server key and support a chain-of-custody export. A worked schema is in section 10.

## 6. Governance and roles

See **Figure 6**.

- **AI Governance Committee** - Records Manager, Legal, IT/Security, Risk, Business Unit.
  Approves models for production, reviews bias/drift, signs phase gates.
- **Model Owner / Data Steward** - dataset curation, labelled evaluation sets, performance.
- **Reviewers / Curators** - resolve review queues, authoritatively approve retention and
  transfers.
- **Platform Ops / SRE** - model serving, scaling, backups, incident response.

The roadmap (Phases 0-3) is **Figure 7**; the model governance loop is **Figure 11**.

---

## 7. The workbook (fillable tick-sheets)

Copy each list into your pilot workspace and tick as you go. Replace every bracketed
placeholder with your own value.

### 7.1 Minimum viable deployment

- [ ] Legal sign-off on the pilot (data-protection + access-to-information mapping)
- [ ] Defined pilot dataset and success criteria (accuracy target, manual-hours reduction, access-to-information SLA target)
- [ ] Provenance JSON schema and database column (JSONB) agreed
- [ ] APIs available for ingest, provenance, and review
- [ ] Human-review dashboard with assign / claim / complete actions
- [ ] Backup and immutable logs for auditability

### 7.2 Vendor / procurement checklist

- [ ] Technical design maps to the layered architecture (section 2)
- [ ] Model governance and retraining plan provided
- [ ] Deployment + rollback plan and a maintenance SLA
- [ ] Evidence of data-protection and access-to-information compliance controls for the target jurisdiction(s)
- [ ] References to prior deployments of similar scale (if any)

### 7.3 Acceptance tests (demonstrate during the pilot)

- [ ] **Ingest manifest** - upload 1000 files with `queue_for_ingest` set; all queued, provenance entries created
- [ ] **OCR fidelity** - OCR 50 scanned documents; measure character accuracy; provenance links output to source pages
- [ ] **Classification accuracy** - vendor evaluation CSV meets precision/recall thresholds for target categories
- [ ] **Review workflow** - 200 low-confidence jobs; reviewers resolve 95% in the UI with exportable audit reports
- [ ] **Access-to-information scenario** - locate 20 named-record requests within SLA; export the provenance chain for each

### 7.4 Regime-to-control mapping checklist

For each regime in scope, record the obligation and the control it maps to (section 8).

- [ ] Target jurisdiction(s) identified
- [ ] Each obligation recorded (processing, retention, access, residency, disclosure)
- [ ] Each obligation mapped to a `control_id` with recommended configuration
- [ ] Data residency and cross-border transfer constraints recorded and enforced
- [ ] DPIAs / risk assessments required where applicable and linked to ingestion
- [ ] Breach-notification timers and contact roles configured
- [ ] Vendor contractual requirements (data processing addendum) recorded

### 7.5 Quick compliance checklist

- [ ] Every dataset mapped to at least one regime and control
- [ ] Redaction / release workflow for access-to-information requests, with an auditor role
- [ ] Lawful-basis recorded per dataset
- [ ] Append-only, signed audit logs in place

---

## 8. Compliance control catalog

Vendor- and jurisdiction-agnostic. Regulatory obligations map to controls; controls carry
recommended configuration. This catalog is also a live, queryable feature
(`/admin/privacy/control-catalog` and `…/control-catalog.json`). Visualise it as
**Figure 10**.

| Control | Name | Category | Recommended configuration |
|---|---|---|---|
| C-PRV-01 | Lawful Basis & Purpose Limitation | privacy | lawful_basis per dataset; record RoPA; purpose labels at ingest |
| C-PRV-02 | Data Subject Rights Handling | privacy | subject-request workflow with SLA timers; link to records |
| C-PRV-03 | Privacy Impact Assessment | privacy | DPIA for qualifying datasets; linked to the pipeline; residual-risk sign-off |
| C-RES-02 | Data Residency & Transfer Constraint | residency | region flag; encrypt in transit/at rest; transfer safeguards |
| C-ACC-05 | Access-to-Information Request Handling | access | request workflow + redaction queue + SLA timers + audit-proof export |
| C-SEC-03 | Special-Category Data Safeguards | security | special-category tagging; processor agreements; breach timers |
| C-GOV-01 | AI Provenance & Model Governance | governance | append-only provenance events; model registry + drift monitoring |
| C-GOV-02 | Human-in-the-Loop Review | governance | confidence-threshold queues; reviewer roles; recorded decisions |
| C-AUD-01 | Tamper-Evident Audit & Chain-of-Custody | audit | append-only signed log store; chain-of-custody export (METS / bagit) |

**Example regime mappings** (interchangeable - add your own):

| Regime | Obligation | Control |
|---|---|---|
| General data-protection regulation | Lawful basis + purpose limitation | C-PRV-01 |
| General data-protection regulation | Data-subject rights | C-PRV-02 |
| General data-protection regulation | Cross-border transfer restrictions | C-RES-02 |
| Access-to-information statute | Publication / response within statutory timeline | C-ACC-05 |
| Sectoral health-data regime | Special-category handling + breach notification | C-SEC-03 |

## 9. Metrics & KPIs

- **Classification precision and recall, per file-plan category** - precision is how often
  a category is assigned correctly, recall is how much of each category is actually found;
  broken down per category so weak spots are visible rather than hidden in an average.
- **Time-to-discovery** - the median time to locate a record, compared before and after the
  pilot to show the real-world speed gain in plain terms.
- **Access-to-information response SLA achievement rate** - the share of requests answered
  within their statutory deadline; the headline measure of legal responsiveness.
- **Review backlog size and reviewer throughput** - how much is waiting for human review and
  how fast reviewers clear it, so the human-in-the-loop step is resourced before it becomes a
  bottleneck.
- **Model drift indicators and retraining frequency** - shifts over time in the data the
  model sees, and how often retraining is triggered, so accuracy is monitored rather than
  assumed to hold.

## 10. Worked provenance JSON (schema fragment)

```json
{
  "record_id": "",
  "source_file": "",
  "file_hash": "sha256:",
  "ingest_timestamp": "2026-06-20T08:00:00Z",
  "ingested_by": "uploader-username",
  "processing_jobs": [
    { "job_id": "ocr-uuid", "job_type": "ocr", "processor": "tesseract-4.1.1",
      "timestamp": "2026-06-20T08:10:00Z", "notes": "layout-aware OCR run" },
    { "job_id": "ner-uuid", "job_type": "ner", "model_name": "ner-archive-v1",
      "model_version": "2026-05-12", "confidence_threshold": 0.6, "human_review_required": true }
  ],
  "ai": { "model_registry_id": "ner-archive-v1", "model_version": "2026-05-12",
          "inference_config": { "temperature": 0.0 }, "inference_hash": "sha256:" },
  "human_review": { "status": "pending", "reviewer_id": null, "review_timestamp": null }
}
```

---

## 11. Supporting reading (the article hub)

The published articles are the narrative spokes; this workbook is the hub. Group them
into a reading path:

**Why it matters (the problem)**
- The Crisis of Records Management: From Shared Drives to EDRMS Solutions
- How Records Management Lost Its Way in the 90s and 00s
- Why Your Fancy Records Management System Sits Empty (And What to Do About It) - *carries a tick-sheet*
- The Digital Preservation Paradox

**The AI approach**
- Implementing AI in Records Management - *carries a template/attachment*
- AI Records Management: IT Implementation Guide
- The LLM Paradox: Why "What Model Do You Use?" Is the Wrong First Question
- Perfect Is the Enemy of Accessible: Archives in the Age of AI-Assisted Processing

**Trust, governance and compliance**
- What Proper Authority Resolution Looks Like in Archival AI  (→ section 5, Figure 4)
- What the EU AI Act Means for Galleries, Libraries, Archives and Museums - *carries a tick-sheet* (→ section 8)
- In 2026, data-protection law stopped moving in one direction (→ section 8)
- When the System Goes Silent: An Estimated R5 Million Lesson in IT Disaster Preparedness

**Standards and the future**
- OpenRiC: An IIIF-Style Contract for Archival Linked Data (→ section 1, standards-aligned)
- The Archive of the Future Is Not a Building. It Is Not Even a Database.
- A Culture You Can Talk To

**Product**
- Why Heratio's Digital Twin Concept Matters for GLAM and Heritage
- Your Digital Assets Are More Valuable Than You Think

> Note: where an article already carries a template or tick-sheet, this workbook links to
> it rather than duplicating it. The workbook's own tick-sheets (section 7) are the
> consolidated, jurisdiction-neutral set.
