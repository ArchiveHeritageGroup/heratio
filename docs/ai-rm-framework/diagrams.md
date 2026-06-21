# AI RAM Framework: Diagram Set

Eleven figures as renderable Mermaid source. They render natively on GitHub and in most
markdown viewers, and can be exported (SVG/PNG) or restyled for slides and articles.
Numbers match the placeholders in `workbook.md` and the .docx. Each figure serves both
the specialist and the lay reader; captions carry the plain-language reading.

---

## Figure 1 - The problem and the outcome

*Conceptual before/after. For: lay readers and executives.*

```mermaid
flowchart LR
  subgraph BEFORE["Today: scattered and unaccountable"]
    A1[Shared drives]
    A2[Email stores]
    A3[Scanned boxes]
  end
  subgraph AFTER["With the framework: governed and searchable"]
    B1[Single described archive]
    B2[Provenance on every item]
    B3[Lawful, auditable access]
  end
  BEFORE ==> AFTER
```

---

## Figure 2 - Layered reference architecture

*Stacked layers. Policy/control flows down; evidence/provenance flows up.*

```mermaid
flowchart TB
  L1["Regulatory & policy - obligations, file-plan, retention authorities"]
  L2["Governance - committee, risk register, model registry, gates"]
  L3["Ingest & provenance - crawlers, snapshot+hash, OCR, provenance capture"]
  L4["Intelligence - NER, classification, sensitivity, embeddings, model serving"]
  L5["Control & lifecycle - ECM/EDRMS, retention enforcement, secure transfer, ACLs"]
  L6["Human UX & review - provenance timeline, diff viewer, review queues, audit export"]
  L1 --> L2 --> L3 --> L4 --> L5 --> L6
  L6 -. "evidence up" .-> L1
```

---

## Figure 3 - Ingest-to-access pipeline

*Left-to-right data flow; a provenance event is emitted at every stage.*

```mermaid
flowchart LR
  S[Source stores] --> C["Crawler / ingest (queue_for_ingest)"]
  C --> H["Snapshot + file hash"]
  H --> O[OCR]
  O --> N["NER / classification"]
  N --> P["Sensitivity / PII detection"]
  P --> R{"Confidence / risk gate"}
  R -->|high| E["ECM / retention"]
  R -->|low or high-risk| Q[Human review]
  Q --> E
  E --> X[Access & export]
  classDef prov fill:#eef,stroke:#88a;
  C:::prov
  O:::prov
  N:::prov
  P:::prov
```

---

## Figure 4 - Provenance event model (PROV-O)

*W3C PROV triad. Plain reading: what produced this, who or what ran it, when, how confident.*

```mermaid
graph LR
  SRC(["Entity: source file"]) -->|used by| ACT["Activity: OCR / NER job"]
  ACT -->|associated with| AG{{"Agent: model + human reviewer"}}
  ACT -->|generated| OUT(["Entity: derived assertion + confidence"])
  AG -.-> OUT
```

---

## Figure 5 - Human-in-the-loop review workflow

*Where a human stays in control of high-risk actions.*

```mermaid
flowchart TD
  AIO[AI output] --> G{Confidence threshold}
  G -->|above| AUTO[Auto-accept]
  G -->|below or high-risk| QUEUE[Review queue]
  QUEUE --> DEC{Reviewer decision}
  DEC -->|accept| LOG[Write decision to provenance]
  DEC -->|correct| LOG
  DEC -->|reject| LOG
  AUTO --> LOG
```

---

## Figure 6 - Governance roles and decision gates

*Who owns which gate.*

```mermaid
flowchart TB
  COM["AI Governance Committee"]
  MO["Model Owner / Data Steward"]
  REV["Reviewers / Curators"]
  OPS["Platform Ops / SRE"]
  COM -->|approves model to production| GATE1{{Production gate}}
  COM -->|signs off| GATE2{{Phase gate}}
  MO -->|eval sets, drift| COM
  REV -->|resolve queues, approve retention| GATE2
  OPS -->|serving, backups, incidents| GATE1
```

---

## Figure 7 - Phased implementation roadmap

*Timeline with deliverables. For: executives.*

```mermaid
gantt
  dateFormat  YYYY-MM
  axisFormat  %b %Y
  title Implementation roadmap (illustrative durations)
  section Phase 0 Preparation
  Governance + KPIs + pilot corpus            :p0, 2026-01, 2M
  section Phase 1 Pilot MVP
  Crawler + OCR + NER + provenance + review    :p1, after p0, 4M
  section Phase 2 Operationalise
  ECM retention + model registry + canary      :p2, after p1, 6M
  section Phase 3 Scale & Sustain
  Full scale + drift detection + ISO 42001     :p3, after p2, 12M
```

---

## Figure 8 - Sensitivity and PII handling decision flow

*Makes privacy-by-design concrete.*

```mermaid
flowchart TD
  D[Detect PII / special-category] --> POL{Policy decision}
  POL -->|index| IDX[Index normally]
  POL -->|limited| LIM[Limited indexing]
  POL -->|redact| RED[Redact output]
  IDX --> OUT[Output + lawful-basis record]
  LIM --> OUT
  RED --> OUT
```

---

## Figure 9 - Deployment topologies and data residency

*Where the data physically lives and who holds the keys.*

```mermaid
flowchart TB
  subgraph ONPREM[On-prem]
    OP[Local object store + KMS]
  end
  subgraph SINGLE[Cloud single-region]
    CR[Managed services + provider KMS/CMK]
  end
  subgraph MULTI[Cloud multi-region]
    M1[Region A] -. async encrypted replication .-> M2[Region B]
  end
  subgraph HYBRID[Hybrid]
    EDGE[Edge ingest] --> CENTRAL[Central analytics]
  end
```

---

## Figure 10 - Regime-to-control mapping matrix

*The legal-mapping annex made visual. Mermaid is weak at matrices, so this figure is a
table; a designer can render it as a heatmap.*

| Obligation \ Control | C-PRV-01 | C-PRV-02 | C-PRV-03 | C-RES-02 | C-ACC-05 | C-SEC-03 |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| General data-protection regulation | x | x | x | x | | |
| Access-to-information statute | | | | | x | |
| Sectoral health-data regime | | x | | | | x |

---

## Figure 11 - Model governance lifecycle

*How the AI is kept accurate and accountable over time.*

```mermaid
flowchart LR
  C[Curate data] --> T[Train]
  T --> R["Register (version + eval metrics)"]
  R --> CAN[Canary deploy]
  CAN --> MON[Monitor for drift]
  MON -->|drift detected| C
  MON -->|stable| CAN
```
