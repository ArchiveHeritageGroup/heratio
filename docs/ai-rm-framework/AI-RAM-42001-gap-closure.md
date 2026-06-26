# AI-RAM Framework - ISO/IEC 42001 Gap-Closure Addenda

Author: Johannes J. Pieterse
Companion to: *AI for Records and Archives Management* (AI-RAM Framework)

## Purpose

When the AI-RAM framework is mapped clause-by-clause against ISO/IEC 42001:2023, it covers the AI-specific controls (Annex A) strongly and is lighter on four of the management-system requirements in Clauses 4-10. These four addenda close those gaps at the framework level, so the framework maps essentially one-to-one onto ISO/IEC 42001. They are written to be read alongside the main framework and are jurisdiction- and vendor-agnostic.

The four gaps closed here are: (1) a formal AI risk and impact-assessment methodology; (2) internal audit and management review; (3) nonconformity and corrective action; (4) competence, awareness and training.

# AI Risk and Impact Management

*(Closes ISO 42001 Clause 6.1 / 8.2-8.4 and Annex A.5; aligns ISO/IEC 23894 and ISO/IEC 42005.)*

The legal-mapping annex tells you which laws apply. This section tells you how risk and impact are formally assessed, treated and recorded before any model touches live records, and on every significant change.

## AI risk management process

A repeatable cycle, owned by the Model Owner and reviewed by the Governance Committee, mirroring ISO 31000 / 23894:

1. **Identify** - for each AI use (classification, sensitivity detection, NER, disposal/transfer recommendation, semantic search), list what could go wrong: misclassification, sensitivity miss, wrongful disposal, biased treatment of a group, provenance loss, erroneous assertion acted upon.
2. **Analyse** - rate each risk on likelihood and consequence using a published scale, where consequence explicitly includes harm to data subjects, harm to affected groups, legal/statutory breach, and loss of evidential value of the record.
3. **Evaluate** - compare against documented risk-acceptance criteria. Anything above the threshold cannot go to production without treatment and sign-off.
4. **Treat** - choose avoid / reduce (e.g. raise the confidence threshold, add a deterministic detector, force human review) / transfer (contractual) / accept (with recorded justification and an accountable owner).
5. **Monitor** - residual risks carry an owner, a review date, and a trigger (drift, incident, legal change) that reopens them.

A **risk register** records each risk: id, description, use/system, likelihood, consequence, owner, treatment, residual rating, review date.

## AI system impact assessment (AISIA)

A documented assessment - broader than a privacy DPIA, which becomes one input to it - performed before deployment and on significant change (new model, new data source, new automated decision, expanded scope). It assesses impact across four dimensions specific to records and archives:

- **Individuals** - privacy, data-subject rights, fairness of any decision affecting a person's access or record.
- **Affected groups / society** - bias and exclusion (does sensitivity or classification systematically misfire for a language, community or record type), accessibility, and disproportionate suppression or exposure.
- **The institution** - legal/statutory exposure (access-to-information, archival statute), reputational and litigation risk.
- **The record itself** - authenticity, integrity and evidential value; risk of wrongful disposal or transfer; provenance completeness.

**Triggers, outputs and sign-off:** an AISIA is mandatory for any high-risk use (automated disposal/transfer, sensitivity-driven access decisions, processing of special-category data at scale). Its output is a recorded assessment with identified impacts, treatments and residual acceptance, signed off by the Governance Committee before go-live and retained as an audit artefact.

# Internal Audit and Management Review

*(Closes ISO 42001 Clause 9.2 and 9.3.)*

Monitoring (KPIs, drift) tells you how the models perform. This section adds the independent check and the governance review that turn a set of controls into a managed system.

## Internal audit programme

- A scheduled, risk-based audit programme (at least annually; high-risk uses more often) audits the AI controls against this framework, the AI policy, applicable law, and ISO 42001.
- **Auditor independence:** auditors do not audit their own work - a reviewer cannot audit the review queue they operate; a model owner cannot audit their own model governance.
- Each audit produces findings (conformity, nonconformity, observation), each finding routes into the corrective-action process below, and results are reported to the Governance Committee. Audit plans, findings and outcomes are retained.

## Management review

The Governance Committee conducts a periodic management review (recommended quarterly, with a formal annual review) against a fixed agenda.

**Inputs:** status of actions from the previous review; changes in legal, regulatory or institutional context; AI performance metrics and drift; risk-register and AISIA outcomes; internal-audit results; incidents and nonconformities; reviewer and stakeholder feedback; adequacy of resources and competence; improvement opportunities.

**Outputs (recorded as decisions):** changes to the AIMS, models, thresholds or policy; risk-acceptance decisions; resource and training actions; improvement initiatives. Minutes and decisions are retained as evidence.

# Nonconformity, AI Incidents and Corrective Action

*(Closes ISO 42001 Clause 10.2.)*

Provenance and human-in-the-loop make failures detectable and traceable; this section defines what happens when one occurs.

## What counts as an AI nonconformity / incident

A model breaching a performance gate; drift past its threshold; a sensitivity miss that exposes personal or special-category data; a wrongful or near-miss automated disposal/transfer; a missing or broken provenance chain; an erroneous or hallucinated AI assertion that was relied upon. Each is logged in an AI incident register with a severity rating and an owner.

## Corrective-action process

1. **Detect and contain** - take immediate correction: quarantine or withdraw the affected output, restrict access, revert the action, place a hold. Containment first, root cause second.
2. **Record** - capture the nonconformity, its effect, and the containment in the incident register, linked to the append-only provenance events that evidence what happened.
3. **Root-cause analysis** - determine why (model, data, threshold, procedure, human step, infrastructure).
4. **Corrective action** - act on the cause, not the symptom: retrain, adjust thresholds, add a detector, change a procedure, retire a model via the registry.
5. **Verify effectiveness** - confirm the action worked and did not introduce new risk; update the risk register and model registry.
6. **Close** - record closure; escalate systemic or high-severity incidents to the Governance Committee and into the next management review.

# Competence, Awareness and Training

*(Closes ISO 42001 Clause 7.2 and 7.3.)*

The controls are only as good as the people operating them. This section defines the human capability the framework assumes.

## Competence (role-based, evidenced)

Required competencies are defined per role and evidenced by training records:

- **Governance Committee** - AI-risk literacy; the legal and archival obligations; how to read risk and impact assessments and authorise or refuse a model.
- **Model Owner / Data Steward** - dataset curation, labelling discipline, evaluation-set maintenance, drift interpretation.
- **Reviewers / Curators** - the file plan and sensitivity criteria, how to read a provenance timeline and diff view, and when and how to escalate rather than wave an item through.
- **Platform Ops / SRE** - secure model serving, monitoring, backup and incident response.

## Awareness

Everyone who relies on AI output is made aware of the AI policy, the principle that AI is an assistant and not an arbiter, the limits and known failure modes of the models, the transparency and provenance obligations, and the route to challenge or report an output.

## Training programme

Role-based training at onboarding and on a recurring cycle, refreshed whenever a model, threshold or policy materially changes, with attendance and competence recorded as documented information.

# Coverage after these addenda

With these four sections added, the AI-RAM framework maps essentially one-to-one onto ISO/IEC 42001:2023 - Annex A (the AI controls) was already strong, and Clauses 4-10 (the management-system requirements) are now complete in description. This closes the gap at the document level only; it describes a conformant framework and says nothing yet about an operating management system or the evidence such a system would generate, which is a separate exercise.
