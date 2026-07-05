> Heratio Help Center article. Category: AI & Automation / Governance.

# ISO/IEC 42001 and AI Governance in Heratio

Heratio applies AI across records and archives management — named-entity
recognition (NER), handwriting transcription (HTR), summarisation, translation,
condition assessment and generative description. Because that AI touches
catalogue records and the people described in them, Heratio governs it under a
formal **AI Management System (AIMS)** aligned to **ISO/IEC 42001:2023**.

This page explains that alignment, the framework behind it, and where to find
each control in the product.

> **Alignment, not certification.** Heratio's AI governance is *designed and
> mapped to* the controls of ISO/IEC 42001 as a conformance-oriented management
> system. Heratio is **not third-party certified** to ISO/IEC 42001 — this page
> describes alignment with the standard, not a certification claim.

## What ISO/IEC 42001 is

ISO/IEC 42001:2023 is the international management-system standard for
artificial intelligence. It sets out how an organisation should establish,
implement, maintain and continually improve an AI Management System — the
governance, risk management, documentation, monitoring and improvement
processes around the AI it develops or uses. Its management-system clauses
(4–10) cover organisational context, leadership, planning, support, operation,
performance evaluation and improvement.

## The AI-RAM Framework (ISO/IEC 42001-integrated)

Heratio's AI governance is built on the **AI-RAM Framework — AI for Records and
Archives Management (V3)**, AHG's industry-facing framework extracted and
enhanced from the PhD research of Johannes J. Pieterse, *"Utilising Artificial
Intelligence to Enhance Records Accessibility within a State-Owned Company in
South Africa."* It is jurisdiction-neutral, with country-specific law (POPIA,
GDPR, FOIA, HIPAA, …) handled as a pluggable mapping layer.

**Version 3 integrates the ISO/IEC 42001:2023 management-system controls
(Clauses 4–10) directly into the framework**, so it maps essentially
one-to-one onto the standard. The four integrated sections are:

- **AI Risk and Impact Management** — a repeatable risk process (Identify →
  Analyse → Evaluate → Treat → Monitor) with a risk register, plus an **AI
  System Impact Assessment (AISIA)** across four dimensions (individuals,
  affected groups/society, the institution, and the record itself). Mandatory
  for high-risk uses and signed off by the Governance Committee.
- **Internal Audit and Management Review** — a scheduled, risk-based audit
  programme with auditor independence, and a periodic management review with
  defined inputs and recorded decisions.
- **Nonconformity, AI Incidents and Corrective Action** — an AI incident
  register and a six-step corrective-action process (Detect & contain → Record
  → Root-cause → Corrective action → Verify → Close).
- **Competence, Awareness and Training** — role-based, evidenced competence for
  the Governance Committee, Model Owner/Data Steward, Reviewers/Curators and
  Platform Ops, plus a recurring awareness and training programme.

### Core principles

1. **Provenance-first** — every AI-derived assertion carries machine-readable
   provenance (what ran, who/what, when, which model/version, configuration and
   confidence).
2. **Privacy-by-design** — detect and protect PII early; record lawful-basis
   decisions; restrict output accordingly.
3. **Human-in-the-loop** — high-risk actions (disposal, transfer, sensitive
   access) require human validation. AI assists; it does not arbitrate.
4. **Standards-aligned** — mapped to applicable data-protection and
   access-to-information law and to the ISO standards below.
5. **Phased and measurable adoption** — pilots, canary deployments and
   measurable KPIs (precision/recall, time-to-discovery, access-request SLA).

## Where the controls live in the product

Heratio implements the AI-RAM / ISO 42001 controls as working features (the
`ahg-ai-compliance` package):

| ISO 42001 area | In Heratio | Where |
|---|---|---|
| AI inventory & transparency | AI Inventory & Governance dashboard — every model in use, its config, its outputs, and cryptographic verification of each result | **Admin → AI Inventory & Governance** (`/admin/governance`) |
| Risk management (Clause 6 / EU AI Act Art. 9) | AI risk register + incident register, seeded across the LLM, HTR, NER, layout, guardrail and translation services, with severity, likelihood, affected group, mitigation and residual risk | **Admin → AI Compliance → Risk** (`/admin/ai-compliance/risk`) |
| Documentation (Clause 7 / EU AI Act Art. 11, Annex IV) | AI model registry + Annex IV technical-documentation generator (Markdown + PDF per service) with an EU declaration-of-conformity template | **Admin → AI Compliance → Models / Documentation** (`/admin/ai-compliance/models`, `/documentation`) |
| Operation & logging (Clause 8 / EU AI Act Art. 12) | Tamper-evident inference-log chain — every recorded inference is signed over a canonical manifest of its inputs, outputs and model identity | AI Inventory & Governance dashboard |
| Performance & monitoring (Clause 9) | Post-market monitoring digest over the inference log (open incidents, overdue reviews, anomalous volumes) | `ai-compliance:risk-monitor` (weekly) |
| Human oversight (EU AI Act Art. 14) | Human-in-the-loop review queues and sign-off gates | Review workflows |

## Related standards

The AI-RAM Framework aligns to a wider standards set:

- **AI management & assurance:** ISO/IEC 42001 (AI management system),
  ISO/IEC 23894 (AI risk management), ISO/IEC 42005 (AI system impact
  assessment).
- **Records & archives:** ISO 15489, ISO 23081, ISO 16175, ISO 30301.
- **Regulatory:** EU AI Act (Articles 9, 11, 12, 14 implemented); national
  data-protection and access-to-information law via the pluggable mapping layer.

## Further reading

- AI Inventory & Governance dashboard user guide (`/help/article/ai-governance-user-guide`)
- AI compliance reference: EU AI Act Articles 9, 11, 12, 14
- The full AI-RAM Framework V3 (ISO/IEC 42001-integrated) master document

---
*Source-of-truth: `docs/reference/ai-ram-framework.md` and the `ahg-ai-compliance`
package (Heratio v1.93.0+). Feature claims trace to issues #724 (Art. 9),
#725 (Art. 11), and the AI Governance dashboard.*
