---
title: "AI for Records & Archives - Implementation Toolkit"
subtitle: "Fillable worksheets, checklists, matrices and templates - end to end"
---

# AI for Records & Archives: Implementation Toolkit

A companion to the framework workbook. These are the working artefacts: copy each
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

*Cross-references: controls C-PRV-01..C-AUD-01 are defined in the framework
workbook, section 8 (the compliance control catalog) and visualised in Figure 10.*
