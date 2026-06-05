# From Blueprint to Build: A Working GLAM Digital Twin as an Implementation of the Wessels (2025) Digital Twin Information Hub

**Draft working paper / collaboration brief.** Prepared as the basis for a joint
publication between the Heratio team (J. Pieterse, Plain Sailing Information Systems /
The Archive and Heritage Group) and the thesis authorship line (Dr Lizette Wessels and
Prof Lorette Jacobs, University of South Africa). This document maps the thesis blueprint
onto the as-built Heratio exhibition digital twin, states what is novel, what is shared,
and what remains for future work, so the three parties can agree scope and authorship.

---

## 1. Why this paper

Wessels (2025), *Digital Twin Information Hub: Possibilities for the Future of Information
Sharing in Metaversities* (DPhil Information Science, UNISA; supervisor Prof L. Jacobs),
develops a conceptual **blueprint** (Table 7.1) for an AI-enhanced digital twin
information hub for South African academic libraries / metaversities. It is, by the
author's own framing, a **conceptual, non-human study**: the thesis is explicit that the
**design, development, testing and implementation** of the hub *"falls outside the scope
of this study"* and is named as a *"potential avenue for further research"* (Ch 8,
Limitations; Phase 6 - implementation - "not addressed in this study"). The thesis
recommends *"constructing prototypes for trial periods to obtain feedback."*

Independently, the Heratio platform built a working **exhibition / collection digital
twin** (a GLAM artefact, not a library hub) that, as of June 2026, instantiates the
thesis's technical-architecture and content-management layers in running software,
including the **real-time data link, simulation, prediction, analytics and AI
recommendation** that the thesis names as the defining functionalities of a digital twin.

The paper therefore writes itself as **theory meeting implementation**: the thesis supplies
the validated blueprint and the metaversity vision; Heratio supplies the prototype the
thesis calls for, plus empirical lessons from building it. This is a genuine
complement, not a competition - the build does not exist without the blueprint's
conceptual scaffolding, and the blueprint gains an existence proof.

## 2. The thesis blueprint (Table 7.1), in brief

The blueprint is organised in layers:

1. **Vision / Mission / Objectives** - a dynamic, equitable, collaborative information
   ecosystem; six objectives (twin ecosystem; information sharing and access; digital
   learning; digital twin information services; security and privacy; continuous
   improvement and sustainability).
2. **Digital leadership** - business model, digital transformation, digital culture,
   staff up/reskilling, digital footprint.
3. **Functionalities of the ecosystem** - stakeholder involvement, partnerships and
   consortium networks, digital learning and research support.
4. **Technical architecture** - 6.1 infrastructure; 6.2 immersive UI with real-time
   engagement (LMS-integrated); 6.3 security and privacy; 6.4 supporting technologies
   (AI, ML, blockchain, analytics, dashboards, AI discovery and recommendation); **6.5
   the digital-twinning process** - a virtual representation linked to a physical entity
   through real-time data and simulation.
5. **Digital information content management** - policies, accessibility, digitisation and
   curation, data quality and governance, interoperable sharing and data-flow.

The operative definition (section 6.5): *a digital twin is a virtual representation
bidirectionally linked to a physical entity through real-time data, enabling monitoring,
simulation and prediction.*

## 3. What Heratio built (the implementation)

Heratio's `ahg-exhibition` package provides:

- **Authoring**: a drag-and-drop builder; a building/plan editor (multi-room buildings,
  polygon room footprints, manual + auto doorways, per-room ceilings/walls/heights/
  rotation, interior dividers with two hangable faces, corridor objects, blueprint
  tracing); objects bound to their archival records (ISAD/RiC) and DAM derivatives.
- **Immersive UI (4.6.2)**: a first-person 3D walkthrough (Three.js) on desktop and
  mobile, with object models / images / PDFs, wayfinding labels, a building minimap,
  variable eye height (crouch/stand), and default architectural finishes (plaster
  ceilings + cornices).
- **The digital-twinning process (6.5)** - the defining loop:
  - **Live data link** (#1146): a readings store and API for lux, temperature, humidity
    and visitor counts per room; per-room conservation status (lux vs target; temp
    16-24C; RH 40-60%); a 3D Live overlay that colour-tints each room and reads out the
    current room. This is the bidirectional model->twin link.
  - **Simulation and prediction** (#1147): a light-budget conservation forecast (30-day
    average lux projected to annual dose against ICOM exposure tiers, with days-to-budget
    and risk banding) and a visitor what-if simulator.
  - **Analytics / continuous improvement** (#1148, thesis Objective 6): a per-room
    time-series dashboard (Chart.js) over the readings, with period selection and summary
    statistics.
  - **AI discovery and recommendation** (6.4): in-twin "you might also like" object
    suggestions (title-token similarity with optional gateway-LLM reasoning), presented
    as chips that fly the visitor to the suggested object.
- **Supporting technologies (6.4)**: an institutional AI gateway (HTR, NER, condition
  assessment) and a retrieval-augmented knowledge base (KM), all called through one
  governed endpoint.
- **Content management (layer 7)**: ISAD(G)/RiC description, Spectrum procedures, IIIF
  deep-zoom, a media pipeline, ODRL rights, provenance, PII redaction, and pluggable
  per-market privacy modules (POPIA/GDPR).

Against the section-6.5 test, the system now **monitors** (live overlay), **simulates**
(visitor/conservation what-if) and **predicts** (light-dose forecast) from real-time
data - i.e. it qualifies as a digital twin, not merely a virtual model.

## 4. Blueprint-to-build crosswalk

| Thesis blueprint layer | Heratio implementation | Status |
|---|---|---|
| 1-3 Vision / Mission / Objectives | GLAM reframing: a Collection / Exhibition twin serving curation, access, learning and conservation | Reframed (library -> GLAM) |
| 4 Digital leadership | Organisational, outside the software artefact | Out of build scope |
| 5 Functionalities (stakeholders, consortia, learning support) | F3 federation foundations; in-app help; record-linked tours | Partial |
| 6.1 Infrastructure | Laravel 12 + Three.js + institutional AI gateway (host/cloud) | Built |
| 6.2 Immersive UI + real-time engagement | 3D walkthrough (desktop + mobile), builder, plan editor | Built (XR/multi-user pending) |
| 6.3 Security and privacy | ACL, ODRL, provenance, redaction, POPIA/GDPR modules | Built (encryption/cyber hardening pending) |
| 6.4 AI / ML / analytics / recommendation | Gateway (HTR/NER/condition), KM RAG, analytics dashboard, in-twin recommendation | Built (blockchain not adopted) |
| **6.5 Digital-twinning process (real-time + simulation + prediction)** | Live readings overlay + conservation/visitor simulation + light-dose prediction | **Built (the defining loop)** |
| 7 Content management (policy, access, curation, governance, interoperable sharing) | ISAD/RiC/Spectrum/IIIF/media pipeline, data governance | Built (twin-level FAIR federation pending) |

## 5. What remains outstanding (future work)

Buildable gaps still open, in priority order:

1. **Multi-user / metaverse presence** (#1150) - shared sessions, avatars, a live
   docent, synchronous tours. This is the largest conceptual gap: a metaversity is by
   definition social and multi-user, and the current twin is single-user.
2. **Immersive XR** (#1152) - WebXR so the existing scene runs in VR/AR headsets,
   satisfying the thesis's "fully immersive, multi-sensory" criterion.
3. **Interoperability / federation of the twin** (#1151) - expose spaces and 3D via
   standards (IIIF 3D / glTF, linked data, OAI) and share across institutions/consortia
   (thesis Functionalities 5.2 and Content Management 7.5).
4. **Encryption / cyber hardening** - data anonymisation, access limits and
   encryption-at-rest for the twin (thesis Objective 5; STEEP 6.4.2.3).
5. **Engineering scale** - WebGPU renderer (#1153) and server-GPU pixel-streaming
   (#1154) for heavy scenes, addressing the "massive server infrastructure" precondition
   the thesis flags.
6. **Blockchain trust layer** - listed in Table 7.1 (6.4) but deliberately not adopted;
   Heratio uses AI-inference provenance instead. A point for joint discussion.
7. **Digital learning support in-twin** - interactive tutorials / skills demonstrations
   inside the walkthrough (thesis Objectives 3-4); currently served via the help system,
   not the twin.

## 6. Contributions and novelty (for the paper)

- **An existence proof** for the Wessels (2025) blueprint: a running system that
  instantiates layers 6 and 7, closing the implementation gap the thesis defers to
  future research.
- **A GLAM transfer**: the blueprint was framed for academic libraries / metaversities;
  the paper shows it transfers, almost 1:1 at the architecture layer, to galleries,
  libraries, archives and museums when reframed as a *collection / exhibition* twin.
- **A conservation-first digital-twinning loop**: the real-time link, simulation and
  prediction are anchored in a concrete, high-value use case - preventive conservation
  (cumulative light-dose budgeting against ICOM tiers) and visitor-load management -
  rather than generic monitoring.
- **Governed institutional AI**: all AI calls route through a single metered, scoped
  gateway, addressing the thesis's data-privacy and supporting-technology concerns in a
  reproducible architecture.
- **Empirical build lessons**: practical findings on authoring spaces, rendering on
  client GPUs, and binding twin state to archival records - the kind of feedback the
  thesis asks prototypes to surface.

## 7. Proposed structure of the joint paper

1. Introduction - the digital-twin-for-information-sharing opportunity; the
   theory/implementation gap.
2. Background - the Wessels (2025) blueprint and definition; metaversities; digital twins
   in GLAM/HE.
3. From library hub to GLAM twin - the reframing argument.
4. System design - the Heratio exhibition twin (architecture, the 6.5 loop,
   conservation/analytics/recommendation).
5. Blueprint-to-build crosswalk (Section 4 here) - layer-by-layer evaluation.
6. Discussion - what implementation validates, complicates or extends in the blueprint.
7. Future work - the outstanding agenda (Section 5), aligned to the thesis's own further-
   research recommendations (multi-user metaverse, XR, federation).
8. Conclusion - blueprint + build as complementary halves of one programme.

## 8. Suggested authorship and venues

- **Authorship (to confirm with all parties)**: Wessels and Jacobs (blueprint, theory,
  metaversity framing) with Pieterse (implementation, system design, results). Order and
  corresponding author to be agreed.
- **Candidate venues**: SAMAB (museums), LIASA / SA Jnl of Libraries and Information
  Science, *South African Journal of Information Management*, UNISA-aligned info-science
  outlets, or a digital-heritage / metaverse-in-education conference. (Cross-reference the
  in-flight SAMAB 2026 and UNILISA 2027 commitments already on the Heratio calendar.)

## 9. Sources

- Wessels, L. (2025). *Digital Twin Information Hub: Possibilities for the Future of
  Information Sharing in Metaversities.* DPhil Information Science, University of South
  Africa. (Blueprint: Table 7.1; definition: section 6.5; future-research and limitations:
  Chapter 8.)
- Heratio exhibition digital twin: `packages/ahg-exhibition/`; companion notes
  `docs/reference/digital-twin-gap-analysis.md` and
  `docs/reference/exhibition-live-data-link.md`; roadmap issues #1145-#1154.
