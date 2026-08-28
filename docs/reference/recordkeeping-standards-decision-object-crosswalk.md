# Recordkeeping standards crosswalk for decision, provenance and audit architectures

**Summary.** Any system that claims to make a decision reconstructable after the fact is building a record of a transaction, and international recordkeeping standards have specified that problem since the 1990s. This note is the crosswalk. It maps the components such systems typically invent (signals, context, objectives, evidence, constraints, alternatives, rationale, decision, action, outcome) onto the ISO 23081-2 five-entity metadata model, records the four things the archival literature has settled that decision-logging designs routinely leave open, and identifies the three places where AI decision systems genuinely exceed what the standards cover. Written 28 August 2026 while reviewing an external working paper on "Decision Objects", but the mapping is general and applies to Heratio's own provenance, ODRL and audit layers.

## Why this matters for AHG work

Two recurring situations.

First, when an auditor, a regulator or a funder asks why Heratio's provenance and audit design should be trusted, the strongest answer is not that the design is sensible. It is that it conforms to a named international standard with a conformance literature behind it. The mapping below is what turns "we log a lot" into "this is an ISO 23081-2 compatible recordkeeping structure".

Second, the AI governance field is currently inventing decision-logging frameworks from scratch, without knowing that records science already standardised most of the model. That is a positioning advantage for an archival practice: we can read those frameworks natively and tell their authors what they have re-derived. It is also a warning, because a framework that does not know its lineage will omit fixity, access rights, retention and disposition, and those omissions surface at the worst possible time.

## The five-entity model is the anchor

ISO 23081-2 specifies a multiple-entity metadata model, descended from the Australian Recordkeeping Metadata Schema of 1999:

- **Record.** The fixed evidence of the transaction.
- **Agent.** Anyone or anything that contributed, owned, authorised or executed, with role-typed relationships carrying validity periods.
- **Business.** The function and activity the transaction belongs to.
- **Mandate.** The legal or policy instrument authorising and bounding the activity, held as an entity that can be cited rather than as a value copied into a field.
- **Relationship.** Typed, dated, directional links between all of the above, and between records.

Most decision-logging designs flatten this into a single record with many fields. The flattening is where they lose capability, in three specific ways worth remembering:

- Mandate as an entity is what lets a system state the instrument under which an action was permitted. A constraint stored as an opaque string cannot be audited against the policy version in force at the time.
- Agent with typed roles and validity periods is what separates contribution from ownership from authority from execution. Flat "user_id" columns cannot express that a person had authority on the date of the act and lost it later.
- Relationship as an entity is what makes the decision graph first-class. Under the archival bond, a record's evidential value derives in part from its relationship to others in the same aggregation, so a decision severed from what triggered it or escalated to it has already lost some of its evidential capacity. A graph is not an optional extension to this kind of model.

## Crosswalk

| Typical decision-object component | Recordkeeping entity | What the standard supplies that the invented version usually lacks |
|---|---|---|
| Decision, Action | Record | Fixity at capture; identity and integrity as separate properties; disposition authority |
| Context, Objectives | Business | Function and activity classification, situating the act in the work that produced it |
| Constraints | Mandate | The authorising instrument as a citable entity, version-resolvable at the time of the act |
| Authority, Owner, Contributor, Executor | Agent | Role-typed relationships with validity periods |
| Signals, Evidence | Relationship and linked records | Typed, dated, directional links, including to superseded evidence |
| Decision graph | Relationship | First-class rather than deferred; the archival bond makes it constitutive |
| Rationale | No equivalent | Genuine gap. See below |
| Alternatives considered and rejected | No equivalent | Genuine gap. See below |
| Outcome | Subsequent records in the same aggregation | Bound by the archival bond rather than by a schema slot |

## Four things the archival literature has already settled

**Identity is not integrity.** InterPARES separates a record's identity, the attributes that distinguish it uniquely and situate it in context, from its integrity, its wholeness and freedom from alteration. Authenticity is the presumption supported by both together. An identifier plus a status field establishes neither. Designs that conflate the two cannot answer an auditor asking whether the object in front of them is unaltered, or by what mechanism that would be shown.

**OAIS gives the operational checklist.** The Open Archival Information System reference model (ISO 14721) requires Reference, Provenance, Context, Fixity and Access Rights as Preservation Description Information for anything meant to remain trustworthy over time. Invented decision schemas reliably have the first three and reliably omit Fixity and Access Rights. Both omissions are fatal in an adversarial setting, and both are cheap to add early and expensive to retrofit.

**ISO 15489 characteristics beat completeness scores.** A record must be authentic, reliable, have integrity and be usable. These are defined against the capture process, not against the presence of fields, so they cannot be satisfied by populating slots. Any "completeness" metric defined as the fraction of populated fields measures documentation compliance rather than evidential quality, and will be optimised against the moment it is reported on. If such a metric is used, declare explicitly that it is a schema-completeness measure and not an epistemic one.

**Appraisal answers the cost objection.** Every decision-capture design eventually concedes that capturing everything is too expensive, and then invents an ad-hoc two-tier scheme. That is the appraisal problem, theorised since Jenkinson and Schellenberg and given a functional turn by Cook's macro-appraisal, which appraises the business function rather than the individual record. Appraisal theory supplies a principled basis for what is captured, at what fidelity, and for how long, and it brings retention and disposition with it. Regulators ask about retention first.

## Lifecycle versus continuum

Decision frameworks almost always adopt a linear lifecycle: initiation, evidence, evaluation, governance, commitment, execution, outcome, feedback. In records science that is the lifecycle position, contested since the mid-1990s by the records continuum (Upward, McKemmish), which holds that a record is simultaneously evidence of an act, of an organisation and of a society, and that its evidential value is constituted by its relationships across those dimensions rather than by its passage through stages.

The dispute is unresolved and either side is defensible. The point to carry is that adopting a lifecycle diagram is taking a position, and the continuum position is the one that handles multi-agent and distributed decisions natively, where local admissibility does not aggregate to systemic admissibility.

## Three places AI decision systems genuinely exceed the standards

These are the additions worth making to a recordkeeping model rather than borrowing from it.

**Rationale as first-class captured content.** Recordkeeping metadata captures what was done, by whom, when, under which mandate and in what business context. It does not require a structured representation of why one alternative was selected over others. ISO 23081 has no rationale entity. Diplomatics has the narratio, but that is a rhetorical form of a document, not a structured evidential object. Preserving supporting, contradicting and uncertain evidence at decision time, with direction, timing and version, goes beyond any recordkeeping metadata standard.

**Rejected alternatives as mandatory content.** Records document the act, very rarely what was considered and set aside. That absence is a long-standing archival complaint about silences in the record. Capturing the rejected alternative set is a genuine advance, and it is what an administrative-law challenge actually needs in order to test whether relevant considerations were taken into account.

**Prospective gating.** Recordkeeping is overwhelmingly retrospective: the record is a trace left by the transaction. A gate that blocks commitment until evidence, constraint, risk and authority conditions are satisfied makes an adequate record a precondition of the transaction instead. No recordkeeping standard does this. The nearest analogues are maker-checker controls in finance and documentation requirements in clinical practice, neither theorised as a general mechanism.

## Caveat on rationale capture

Recording a rationale does not make it the actual reason. Where the rationale is generated by a model, the recorded explanation may be post-hoc narrative rather than the causal process, which is the unfaithful-reasoning problem in the interpretability literature. A traceability layer that preserves an unfaithful rationale delivers auditable fiction with a provenance chain attached, and is arguably worse than no record, because it manufactures confidence that is not warranted. Any design capturing model-generated rationale should treat faithfulness as an open risk to be tested, not a property to be assumed. Capturing the alternative set and the evidence available at the time is a partial mitigation, since it lets a reviewer check the stated reason against what was actually on hand.

## Standards worth citing by number

- ISO 15489-1:2016. Records management, concepts and principles. Characteristics of an authoritative record.
- ISO 23081-1:2017 and ISO 23081-2:2021. Managing metadata for records. The multiple-entity model.
- ISO 14721:2012. OAIS reference model. Preservation Description Information.
- ISO 16175-1:2020. Functional requirements for software managing records.
- ISO/IEC 42001:2023. AI management systems.
- MoReq2010, DLM Forum Foundation. Modular requirements for records systems, with an immutable audit trail as a core service and a conformance test framework.
- PREMIS Data Dictionary v3.0. Preservation metadata with Object, Event, Agent and Rights entities.
- W3C PROV-DM and PROV-O. Entity, Activity, Agent with derivation and association.
- OMG Decision Model and Notation v1.4. The decision as the unit of modelling, with decision requirements diagrams.

## Related

Applies directly to Heratio's provenance chains, ODRL policy evaluation at point of access, ACL gates on read and reproduction, and per-action audit logging. The ODRL evaluation is an admissibility gate in this vocabulary and the provenance chain is a dated directional evidence chain, which means Heratio is closer to a conformant decision-record architecture than most purpose-built AI governance tooling.
