# Indigenous TK/BC Labels as a Per-Region Plugin + Terminology (Heratio #1388)

**Summary:** Design decision for Heratio (issue #1388, "governance-and-access model for African taxonomies"): Traditional Knowledge (TK) and Biocultural (BC) labels should be delivered as **per-region, community-governed plugins** on a **jurisdiction-neutral core enforcement engine**, and the term "Indigenous" should **not be hard-coded** - communities self-identify. Sharpens #1388 Principles 1 (peer vocabularies), 3 (protocol-bearing terms), 6 (pluggable per-region). Comment: github.com/ArchiveHeritageGroup/heratio/issues/1388#issuecomment-4995644650.

## TK/BC as a plugin (two layers)

Same pattern as compliance modules (GRAP/POPIA/GDPR/NAZ pluggable per-market beside a jurisdiction-neutral core):

- **Core (jurisdiction-neutral):** the *protocol-enforcement engine* - a term/object carries a label that gates `odrl:use` / `odrl:reproduce` / export, enforced at **retrieval, display, and export** (#1388 Principle 3). Reuses the existing ODRL + provenance layer. Enforcement must not differ by region, so it stays in core.
- **Plugin (per-region, community-governed):** the actual **label sets + the communities who own them**. This is what varies - Southern vs West vs East vs North African vocabularies, categories, and governance differ; one community's protocols are not another's. Each region ships as a community-administered module (#1388 Principle 6).

**Two distinct label families (not one):**
- **TK Labels (Traditional Knowledge)** - cultural heritage/records: clan, sacred/secret, seasonal, gendered, attribution, non-commercial, community-voice. The archival/GLAM core case.
- **BC Labels (Biocultural)** - biological/biodiversity collections: provenance of genetic/biocultural material, Nagoya Protocol / ABS. Home = natural-history museums, herbaria, seed/plant knowledge - a different domain than TK.

**Proposal:** one "community protocols / Local Contexts" plugin family, per region, exposing both TK and BC label sets, riding the shared core enforcement engine.

## Terminology: "Indigenous peoples" vs "original inhabitants"

- Internationally-correct term is **"Indigenous peoples"** (capital I, plural *peoples*) per **UNDRIP** - the plural signals distinct nations with collective self-determination, not a demographic. "Original inhabitants" is descriptive, not the rights-bearing term.
- **African context is contested:** clean for settler-colonial cases (San/Khoi, Amazigh), but a common critique is that all Africans are indigenous to Africa, so under the **African Commission (ACHPR)** framing the term is reserved for specific marginalised, self-identifying groups (hunter-gatherers, pastoralists, forest peoples). It is a political/self-identification category, not simply "who was here first."
- **Design rule:** do NOT hard-code "Indigenous" as a fixed label. Under the **CARE Principles** and the right to self-identification, the community names itself and chooses its own term (their language, their category names). "Indigenous peoples" is one internationally-crosswalkable **peer** term via sideways SKOS mapping - never the canonical spine (#1388 Principle 1, anti-subordination).

## Ties
Extends the IK-rights-machine-enforceable thesis (`indigenous-knowledge-rights-in-archives.md`) and the AfCFTA IP + IK source (Mukwevho/Ndlovu). Companion conference paper for #1388: `stuff/docs/ica-conference/ica-african-taxonomies-paper.docx` ("Beyond Translation"). Theory base: CARE Principles for Indigenous Data Governance.
