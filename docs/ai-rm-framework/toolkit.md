---
title: "AI RAM Framework: Implementation Toolkit"
subtitle: "Fillable worksheets, checklists, matrices and templates - end to end"
---

# AI RAM Framework: Implementation Toolkit

A companion to the **AI RAM Framework** workbook (AI for Records and Archives
Management). These are the working artefacts: copy each
one into your own pilot workspace and fill the bracketed `[ ]` fields and `- [ ]`
checkboxes. Vendor- and jurisdiction-agnostic; replace examples with your own
regimes, numbers and names. Nothing here is a legal opinion - have your own legal
and records authorities confirm the jurisdiction-specific entries.

How the toolkit maps to the journey: **Start -> Govern -> Comply -> Pilot ->
Procure -> Prove -> Operate -> Sustain -> Retire.**

---

## Part A - Start

### A1. Readiness and maturity self-assessment

Score each statement 0 (not at all), 1 (partly), 2 (yes). A total under ~18 means
start with foundations before any AI pilot.

| # | Statement | Score (0-2) |
|---|---|---|
| 1 | We can list our major unstructured stores (shares, email, scans) and their rough size | [ ] |
| 2 | We have an agreed file plan / classification scheme | [ ] |
| 3 | We have documented retention rules and a disposal authority | [ ] |
| 4 | We know which data is personal / special-category and where it sits | [ ] |
| 5 | We have a named owner for records and one for IT/security | [ ] |
| 6 | We can stand up a read-only connector to a source store | [ ] |
| 7 | We have somewhere to store provenance (a database / JSON column) | [ ] |
| 8 | We have staff time for human review of AI output | [ ] |
| 9 | We have a lawful basis position for processing our content | [ ] |
| 10 | Leadership sponsors the work and will sign phase gates | [ ] |
| 11 | We can measure a baseline (e.g. current time-to-find a record) | [ ] |
| 12 | We have a backup / rollback path | [ ] |

**Total: [ ] / 24.** Notes / weakest areas to fix first: `[ ]`

### A2. Business-case / ROI worksheet

- Problem statement (1-2 sentences): `[ ]`
- Affected users / volumes: `[ ]`
- Baseline today (measure before): time-to-find `[ ]`; manual hours/month `[ ]`; access-to-information SLA breaches/year `[ ]`
- Target after pilot: time-to-find `[ ]`; manual-hours reduction `[ ]`%; SLA compliance `[ ]`%
- Costs: people `[ ]`; infrastructure `[ ]`; licences/services `[ ]`; contingency `[ ]`
- Benefits (quantified where possible): `[ ]`
- Risks of doing nothing: `[ ]`
- Decision sought / sponsor: `[ ]`

### A3. Content inventory and pilot-corpus selection worksheet

| Store | Type (share/email/scan) | Rough volume | Formats | Sensitivity (none/PII/special) | In pilot? |
|---|---|---|---|---|---|
| `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` |

Pilot corpus chosen: `[ ]` (recommend a representative 10k-50k subset). Why this set: `[ ]`
Excluded for now (and why): `[ ]`

---

## Part B - Govern

### B1. AI Governance Committee charter (terms of reference)

- Purpose: oversee lawful, accountable use of AI on the records estate.
- Members (role - name): Records Manager `[ ]`; Legal `[ ]`; IT/Security `[ ]`; Risk `[ ]`; Business unit `[ ]`
- Chair: `[ ]`. Secretary: `[ ]`. Quorum: `[ ]`
- Cadence: `[ ]` (e.g. monthly + on-demand for gate decisions)
- Decision rights: approve models for production; sign phase gates; review bias/drift; accept residual risk; authorise disposal at scale.
- Out of scope: `[ ]`
- Escalation path: `[ ]`. Record of decisions kept at: `[ ]`

### B2. AI risk register (template)

| ID | Risk | Category (privacy/security/bias/legal/ops) | Likelihood (L/M/H) | Impact (L/M/H) | Mitigation / control_id | Owner | Status |
|---|---|---|---|---|---|---|---|
| R-1 | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` |

### B3. Phase-gate sign-off sheet

For each phase (0 Preparation, 1 Pilot, 2 Operationalise, 3 Scale):

- Phase: `[ ]`. Entry criteria met? `- [ ]`  Exit criteria met? `- [ ]`
- KPIs at gate (target vs actual): `[ ]`
- Open risks accepted (register IDs): `[ ]`
- Decision: `- [ ] proceed  - [ ] proceed with conditions  - [ ] hold`
- Conditions: `[ ]`
- Committee chair signature / date: `[ ]`

---

## Part C - Comply

### C1. Data Protection Impact Assessment (DPIA) worksheet

Required for large-scale or special-category processing (control C-PRV-03).

- Processing described: `[ ]`
- Purpose and lawful basis (per dataset): `[ ]`
- Data categories, incl. special-category: `[ ]`
- Necessity and proportionality: `[ ]`
- Data subjects affected + volume: `[ ]`
- Risks to individuals (and likelihood/severity): `[ ]`
- Measures to reduce risk (map to control_id): `[ ]`
- Residual risk + who accepts it: `[ ]`
- DPO / adviser consulted? `- [ ]`  Date / outcome: `[ ]`
- Review date: `[ ]`

### C2. Record of Processing Activities (RoPA) entry (template)

- Activity name: `[ ]`
- Controller / processor: `[ ]`
- Purpose(s): `[ ]`  Lawful basis: `[ ]`
- Data categories / subjects: `[ ]`
- Recipients / sharing: `[ ]`
- Cross-border transfers + safeguards: `[ ]`
- Retention period: `[ ]`
- Technical/organisational measures: `[ ]`

### C3. Data residency and transfer decision worksheet (control C-RES-02)

- Where must this data physically reside? `[ ]`
- Permitted regions / prohibited regions: `[ ]`
- Cross-border flows identified: `[ ]`
- Safeguards applied (contractual clauses / adequacy / encryption): `[ ]`
- KMS / key custody (who holds the keys): `[ ]`
- Deployment topology chosen (on-prem / single-region / multi-region / hybrid): `[ ]`

### C4. Access-to-information (FOI) request log + handling SOP (control C-ACC-05)

SOP: receive -> log -> locate (incl. AI-assisted search) -> review/redact -> approve -> respond -> publish non-exempt -> export provenance chain.

| Req ID | Received | Statutory due date | Records located | Redactions | Reviewer | Responded | Exemptions applied |
|---|---|---|---|---|---|---|---|
| `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` |

---

## Part D - Pilot

### D1. Pilot plan / charter

- Objective + success criteria: `[ ]`
- Corpus (from A3): `[ ]`
- Duration: `[ ]` (recommend ~12 weeks active + 4 weeks evaluation)
- Team + roles: `[ ]`
- In scope / out of scope: `[ ]`
- KPIs + targets: `[ ]`
- Risks (register IDs): `[ ]`
- Go/no-go gate date: `[ ]`

### D2. Reviewer standard operating procedure (human-in-the-loop)

- [ ] Items below the confidence threshold or flagged high-risk are queued for review
- [ ] Reviewer compares raw -> canonical in the diff viewer
- [ ] Reviewer accepts / corrects / rejects; high-risk actions (disposal, transfer, sensitive access) always require a human
- [ ] Every decision is written to the provenance log (who, when, what changed)
- [ ] Disagreements escalate to: `[ ]`
- [ ] Target throughput / SLA per reviewer: `[ ]`

### D3. Pilot evaluation report (template)

- Period + corpus: `[ ]`
- KPI results (target vs actual): time-to-discovery `[ ]`; precision/recall per category `[ ]`; SLA `[ ]`; reviewer throughput `[ ]`
- What worked: `[ ]`
- What did not: `[ ]`
- Incidents / surprises: `[ ]`
- Cost actuals vs estimate: `[ ]`
- Recommendation: `- [ ] proceed to Phase 2  - [ ] iterate  - [ ] stop`
- Recommended production steps: `[ ]`

---

## Part E - Procure

(Vendor checklist, acceptance tests and the procurement scoring matrix are in the
framework workbook, sections 7.2-7.3 and the Pilot Procurement annex. Add a
signed vendor data-processing agreement reference here.)

- Vendor: `[ ]`  Contract / DPA reference: `[ ]`  Data-residency commitment: `[ ]`

---

## Part F - Prove (provenance and audit)

### F1. Chain-of-custody manifest (template, control C-AUD-01)

For an export / transfer package:

- Package ID: `[ ]`  Created (ISO 8601 UTC): `[ ]`  Created by: `[ ]`
- Source system + record IDs: `[ ]`
- Item count + total bytes: `[ ]`
- Per-item hash list (sha256) attached? `- [ ]`
- Processing events included (ingest/OCR/NER/redaction/export)? `- [ ]`
- Signature / signing key reference: `[ ]`
- Recipient + handover date: `[ ]`

### F2. Audit-export checklist

- [ ] Provenance events exportable per record (PROV-O / JSON-LD)
- [ ] Audit log is append-only and signed
- [ ] Export carries a chain-of-custody manifest (F1)
- [ ] Packaging format agreed (METS / bagit / JSON)
- [ ] Retention of provenance records meets statutory windows

---

## Part G - Operate (AI ops)

### G1. Model card / registry record (template, control C-GOV-01)

- Model name + version: `[ ]`
- Purpose / task: `[ ]`
- Training-data origin + date: `[ ]`
- Evaluation set(s) + metrics (precision/recall/F1): `[ ]`
- Known limitations / bias notes: `[ ]`
- Confidence-threshold in use: `[ ]`
- Approved for production by / date: `[ ]`
- Retraining trigger + cadence: `[ ]`

### G2. Drift-monitoring log

| Date | Metric watched | Baseline | Current | Drift? | Action (none / investigate / retrain) | By |
|---|---|---|---|---|---|---|
| `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` |

### G3. Operational runbook checklist

- [ ] Model serving + scaling owner and on-call defined
- [ ] Backups scheduled + restore tested
- [ ] Monitoring + alerts on pipeline stages
- [ ] Canary / rollback procedure documented
- [ ] Secrets in a vault; keys rotated on schedule
- [ ] TLS/HSTS/CSP enforced on all web interfaces

---

## Part H - Sustain

### H1. Annual review / AI-management-system conformance checklist (toward ISO/IEC 42001)

- [ ] Governance committee met per cadence; decisions logged
- [ ] Risk register reviewed and current
- [ ] Every production model has a current model card + recent evaluation
- [ ] Drift monitored; retraining done where triggered
- [ ] DPIAs reviewed for material changes
- [ ] Access-to-information SLA performance reviewed
- [ ] Audit logs intact and exportable
- [ ] Improvement actions captured for next cycle: `[ ]`

### H2. Incident-response sheet

- Incident ID + date/time detected: `[ ]`
- Type (data breach / model failure / bias / availability): `[ ]`
- Affected records / individuals: `[ ]`
- Containment actions + time: `[ ]`
- Notification required? to whom, by when (statutory window): `[ ]`
- Root cause: `[ ]`  Corrective actions: `[ ]`
- Closed by / date: `[ ]`

---

## Part I - Retire

### I1. Retention and disposal sign-off (human-acknowledged)

- Records / set: `[ ]`  Retention rule + authority: `[ ]`
- Disposal action (destroy / transfer to archive / review): `[ ]`
- AI recommendation (if any) + confidence: `[ ]`
- Legal hold checked clear? `- [ ]`
- Human authoriser (name/role) + date: `[ ]`  (disposal is never automatic)
- Disposal certificate / evidence reference: `[ ]`

---

## Part J - Enhancements

Optional artefacts that round out the toolkit beyond the core journey.

### J1. Glossary (plain-language)

- **RAM** - Records and Archives Management.
- **Provenance** - the machine-readable record of what produced an output, who/what
  ran it, when, with which model/version and how confident.
- **PROV-O** - the W3C standard vocabulary for expressing provenance.
- **DPIA** - Data Protection Impact Assessment: a documented risk assessment for
  higher-risk processing of personal data.
- **RoPA** - Record of Processing Activities: the register of what personal data is
  processed, why, and under what basis.
- **NER** - Named-Entity Recognition: automatically finding people, places,
  organisations etc. in text.
- **Confidence threshold** - the score above which AI output is auto-accepted and
  below which it is sent for human review.
- **Drift** - a change over time in the data a model sees, which can erode accuracy.
- **Human-in-the-loop** - a person validates high-risk AI decisions; AI assists, it
  does not arbitrate.
- **Chain of custody** - an unbroken, evidenced record of who held a record and what
  was done to it.
- Add local terms: `[ ]`

### J2. Stakeholder / RACI matrix

R = Responsible, A = Accountable, C = Consulted, I = Informed.

| Activity | Records Mgr | Legal | IT/Security | Risk | Model Owner | Reviewers | Exec sponsor |
|---|---|---|---|---|---|---|---|
| Approve a model for production | C | C | C | C | R | I | A |
| Sign a phase gate | C | C | C | C | C | I | A/R |
| Run a DPIA | C | A/R | C | C | C | I | I |
| Resolve review queues | A | I | I | I | C | R | I |
| Authorise disposal at scale | A/R | C | I | C | I | C | I |
| Incident response | C | C | R | A | C | I | I |

(Adjust to your structure.)

### J3. Bias / fairness assessment worksheet

- Model + version assessed: `[ ]`
- Decisions it influences: `[ ]`
- Groups that could be disadvantaged (by language, region, era, format, community): `[ ]`
- Evidence checked (per-group precision/recall, error patterns): `[ ]`
- Disparities found: `[ ]`
- Mitigations (re-balance data, threshold per class, human review): `[ ]`
- Residual concern + who accepts it: `[ ]`  Re-assess date: `[ ]`

### J4. Change-management / training and communications plan

- [ ] Audiences identified (curators, IT, leadership, the public)
- [ ] Key messages per audience: `[ ]`
- [ ] Training for reviewers on the human-in-the-loop SOP
- [ ] "What the AI does and does not do" explainer published
- [ ] Feedback channel for staff: `[ ]`
- [ ] Go-live comms + FAQ ready
- [ ] Adoption measure (e.g. % staff trained, queue uptake): `[ ]`

### J5. Model retirement / sunset checklist

- [ ] Reason for retirement (replaced / drifted / risk / deprecated dependency): `[ ]`
- [ ] Replacement model + cutover plan: `[ ]`
- [ ] Provenance of past inferences preserved (do not delete history)
- [ ] Model card archived with a retired status + date
- [ ] Endpoints / jobs disabled; keys revoked
- [ ] Stakeholders informed: `[ ]`  Retired by / date: `[ ]`

### J6. Vendor security / due-diligence questionnaire

- Certifications held (ISO 27001 / SOC 2 / other): `[ ]`
- Data residency + sub-processors: `[ ]`
- Encryption in transit and at rest: `[ ]`
- Access control + admin audit logging: `[ ]`
- Breach-notification commitment + window: `[ ]`
- Penetration-test cadence + last date: `[ ]`
- Data-deletion / return on exit: `[ ]`
- Model-training use of our data (allowed? opt-out?): `[ ]`
- Signed DPA / confidentiality in place? `- [ ]`

---

*Cross-references: controls C-PRV-01..C-AUD-01 are defined in the AI RAM Framework
workbook, section 8 (the compliance control catalog) and visualised in Figure 10.*
