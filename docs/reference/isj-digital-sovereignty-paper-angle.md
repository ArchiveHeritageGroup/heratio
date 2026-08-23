# ISJ Digital Sovereignty - paper angle (revised, authoritative) + critique

**Summary.** The revised, fact-checked concept note for a paper targeting the **Information Systems Journal (ISJ) Special Issue on Digital Sovereignty** (full-paper deadline **31 March 2027, hard/non-extendable**; coordinating senior editor Petter Nielsen, UiO). Author Dr Johan Pieterse, with a senior IS co-author to be identified. This revised version (16 Aug 2026) **supersedes the first abstract** (which is in the Workbench conference working_root and was emailed 16 Aug). Working title: *Sovereignty by Design: Jurisdiction-Neutral Cores and Pluggable Sovereignty in Heritage and Records Infrastructure.* The revised docx is at `heratio/stuff/ISJ_Digital_Sovereignty_Paper_Angle_Revised.docx`. Registered in the Workbench Conferences project (status: tracking).

## Core construct and thesis

- **Pluggable sovereignty**: the modular separation of a shared, standards-based infrastructure core from replaceable governance modules that enact jurisdictional or community-specific rules (data residency, privacy, procurement, AI processing, access, reporting, culturally sensitive knowledge).
- **Theoretical proposition:** open standards do not surrender sovereignty; they can *relocate* it from the shared core to governable boundary modules. **The boundary is reached when a community's authority legitimately requires the refusal of openness, reuse or interoperability.**
- **Research question:** How can heritage-and-records infrastructures be designed to preserve interoperability while enabling actors to make and enforce jurisdictional and community-specific sovereign choices?

## Positioning against the call (areas are lettered A-E, not "1-5")

- **B. Architecture, governance, emerging technologies - PRIMARY (the mechanism):** sovereignty-by-design, modularity, standards, interoperability, API governance.
- **D. Rights, justice and contested sovereignties - PRIMARY boundary case (the distinctive edge):** indigenous data sovereignty tests the limits of modularity and universal interoperability.
- A (foundations/sovereignty paradox), C (public-sector procurement/residency/privacy), E (strategy) - secondary/supporting.

## Revised abstract (~300 words)

Digital sovereignty is commonly framed as a trade-off between dependence and autonomy. For galleries, libraries, archives, museums and public-records bodies, sovereignty cannot mean isolation: these institutions must preserve interoperability with international descriptive and preservation standards while enforcing national law, public-sector controls and community-specific authority over culturally sensitive knowledge. This paper develops "pluggable sovereignty" as a design construct that separates a jurisdiction-neutral, standards-based core from replaceable sovereignty modules governing data residency, privacy, procurement, AI processing, access and community protocols. Empirically, the study draws on the design and multi-jurisdiction deployment of a standards-based heritage-and-records platform across South African public-sector, archival and community settings, supplemented by the author's doctoral case of AI-enabled transformation of unstructured records in a state-owned enterprise. Using design science combined with interpretive comparison across deployments, it traces how sovereignty requirements were translated into architectural boundaries, governance rules and operational controls, and where modular convergence failed. Indigenous data sovereignty provides the critical boundary case: the CARE Principles and Traditional Knowledge and Biocultural Labels show that community authority cannot be reduced to statutory compliance or treated as another interchangeable jurisdictional rule; in some settings sovereignty requires the legitimate refusal of openness, reuse or interoperability. The paper contributes a design theory of sovereignty by design - open standards can reduce vendor dependence and enable shared infrastructure while modular governance preserves situated authority - specifying constructs, design principles, mechanisms and boundary conditions, and reframing digital sovereignty as a socio-technical capability enacted through architecture and governance rather than isolation or policy rhetoric.

## Method and contribution

- **Method:** design science + interpretive multi-case. The platform is the designed artefact; deployments are the cases that stress-test the construct and expose boundary conditions. Show a traceable chain: requirement -> design decision -> implemented control -> observed consequence -> theoretical inference.
- **Contribution:** (1) the construct of pluggable sovereignty; (2) design principles for separating shared standards-based functions from locally governable modules; (3) mechanisms by which modular boundaries redistribute control; (4) boundary conditions where modular convergence is inadequate or illegitimate (indigenous/community authority); (5) a falsifiable design theory linking architecture, governance and outcomes.

## Corrections applied (do not revert)

- "Senior Scholars' Basket of 8" -> **AIS College of Senior Scholars' List of Premier Journals** (AIS renamed/expanded the list in 2023). Or just "a premier IS journal".
- Heratio/AHG "open" platform -> "**standards-based** heritage-and-records platform" unless open-source status is publicly verifiable (avoid promotional tone).
- OpenRiC as an example -> retain **only if public, citable and empirically used** in the study.
- TK/BC labels -> **governance mechanisms and a boundary case, NOT interchangeable compliance plug-ins.** Community authority is not reducible to statutory/technical compliance.
- Call areas are **A-E**, not "Areas 1-5".

## Make-or-break risks (Claude's critique, 16 Aug 2026)

1. **Empirical traceability is the gate.** The contribution rests on genuinely *different* jurisdictional deployments with an auditable requirement->design->control->outcome record. One platform installed in similar places is not cross-case variation. Verify this exists before committing.
2. **Indigenous-data ethics/consent is critical-path, not step 6.** The Area-D boundary case (the paper's distinctiveness) requires a real community partner, consent, provenance and research-ethics clearance (CARE: authority to control = community must be *in* it, per participation, not studied). Ethics board + community relationship can eat months; back-plan that from 31 Mar 2027 first. Without it, the boundary case is rhetoric and the paper loses its edge.
3. **Lead with D, not two primaries.** B (modularity/open standards) is crowded in IS; the unique payoff is the *legitimate refusal* (D). Lead the theory with D, use B as the mechanism, to avoid the "another modularity paper" reflex.
4. **Differentiate "pluggable sovereignty"** from adjacent IS constructs (Tiwana platform/module governance, boundary resources, digital-infrastructure/installed-base) or it reads as a rebadge.
5. **Community-embedded co-author is close to mandatory** for Area-D credibility and CARE participation - a stronger selection criterion than design-theory strength alone, and far above guest-editor proximity.
6. Verify the Wiley CFP URL (`si-2026-000456`) resolves before relying on it.

## Likely reviewer objections to pre-empt

System-description risk (every feature must serve a construct/mechanism/principle/boundary); single-artefact generalisation (needs a cases x sovereignty-demands x design-choices x consequences table showing variation); indigenous sovereignty used decoratively (needs community-authorised evidence + ethics + attribution); method incoherence (integrate design-science cycles with case analysis explicitly); commercial/confidentiality (anonymisation, COI disclosure, double-blind from the outset).

## Sources

Official UiO call: https://www.mn.uio.no/ifi/english/research/groups/is/special-issue-on-digital-sovereignty.html · Wiley/ISJ CFP (verify): https://onlinelibrary.wiley.com/page/journal/13652575/homepage/call-for-papers/si-2026-000456 · AIS Premier Journals list: https://aisnet.org/research/seniorscholarsbasket/ · GIDA CARE Principles: https://www.gida-global.org/careprinciples · Local Contexts TK/BC Labels: https://localcontexts.org/labels/traditional-knowledge-labels/

Related: [[project_thought_leadership_blog]] (the "Sovereign by Default" article #30), [[project_ik_tk_bc_plugin_1388]] (TK/BC labels plugin), [[reference_afcfta_ik_ip_source]], [[project_conferences_2026]], [[feedback_international_positioning]].
