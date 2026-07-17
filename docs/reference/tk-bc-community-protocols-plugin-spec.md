# TK/BC Community Protocols Plugin - Spec (Heratio #1388 / build #1406)

**Summary:** Technical spec for delivering Traditional Knowledge (TK, cultural) and Biocultural (BC, biodiversity) labels as a **per-region, community-governed plugin** on Heratio's **jurisdiction-neutral core enforcement engine**. Design: #1388; build tracker: #1406. Much of the core was already scaffolded (2026-07-16); this spec is the plugin layer + gaps.

## Already built (do not re-build)
- **`term_protocol`** table (`ahg-term-taxonomy` migration): `term_id, label_family, label_code, access_condition, owner_actor_id, region_module, pid, no_equivalent` - the protocol-bearing-term model (#1388 Principle 3) with per-region, community owner, sovereign PID, and the no-equivalent flag (Principle 2).
- **`TermProtocolService` / `TermProtocolGate`** (`ahg-core`) - wired into DisplayController, RiC `LinkedDataApiController`, OAI-PMH, TermBrowse, portable-export (enforced at retrieval/display/export).
- **`OdrlService::isPermitted(targetType, targetId, researcher, action)`** over `research_rights_policy` (`ahg-research`) - object-level `odrl:use`/`odrl:reproduce`, wired to API/IIIF/download/C2PA.
- **`ahg-term-taxonomy`** SKOS validate + cross-match (sideways/peer mapping, Principle 1).

## Two layers
- **Core (jurisdiction-neutral, built):** `term_protocol` + `TermProtocolGate` + `OdrlService`. Generic evaluation (actor + action view/reproduce/export on term-or-object); never region-specific.
- **Plugin (per-region, to build):** label catalogs, communities + stewards, assignment/governance UI, notices; per-region packs.

## Data model additions
- **`community`**: `name, self_identified_term, region_module, care_statement, pid`. `self_identified_term` = the community's own term; "Indigenous" is never hard-coded.
- **`protocol_label_catalog`**: `label_family (TK|BC), label_code, region_module, name, community_id, definition, default_access_condition, is_local_contexts, icon`. Seeds Local Contexts base labels + community-authored labels.
- **`object_protocol`** (mirror of term_protocol for information_object/digital_object) OR a `research_rights_policy` bridge (`policy_type='tk_protocol'`).
- **`protocol_assignment_log`**: CARE provenance (who assigned/withdrew, when, authority, AhgInferenceReceipt ref if AI-proposed). Append-only.

## Labels vs Notices
- Labels = community-authored + applied, enforceable. Notices = institution-applied advisory placeholders.
- TK (cultural): Attribution, Clan, Family, Community-Voice, Seasonal, Withholding, Secret/Sacred, gender/initiation-restricted, Non-Commercial, Verified, etc.
- BC (biocultural): Provenance, Consent-Verified, Multiple-Community + BC Notices (Nagoya/ABS). Home = natural-history/herbaria/seed collections.

## Enforcement / governance / terminology / AI
- Extend `TermProtocolGate` to **object-level** at the same choke points (show, IIIF, download, API, OAI, RiC/JSON-LD, portable export). Resolution: object > inherited term > default open. `access_condition` -> `odrl:use`/`odrl:reproduce`; Secret/Sacred -> suppress from public (as `EmbeddedMetadataService` does). Admin bypass logged (CARE).
- **Community Steward** role (per community): only actor who creates/assigns/withdraws that community's labels (Principle 4). Staff add Notices + propose labels only.
- **Terminology:** no hard-coded "Indigenous"; render `self_identified_term`; "Indigenous peoples" (UNDRIP) only as a SKOS peer term; `no_equivalent=1` surfaced, not forced (Principles 1/2/7).
- **AI fence (Principle 5):** `TermProtocolGate::isModelEligible(target)` pre-flight in `ahg-ai-services` excludes restricted material from NER/embeddings/summary + the vision/OCR path. AI-proposed labels carry inference receipts; steward disposes.

## Packages
`ahg-community-protocols` (core plugin) + per-region packs `ahg-community-protocols-{za,sadc,wa,...}` (ship catalog + community rows + `region_module` value only; enforcement stays in core). Standard ahg-* layout (ServiceProvider, routes, install.sql, seed_*.sql, auto-seed).

## Build plan (P1-P5, tracked in #1406)
1. Object-level protocols + gate (choke points, resolution order, badges via View::composer into locked blades).
2. Catalog + governance (`protocol_label_catalog`, `community`, Steward role, assign/withdraw UI, `protocol_assignment_log`, CARE).
3. AI fence (`isModelEligible` in ahg-ai-services; AI-proposed labels + receipts + steward disposition).
4. Interop + self-identification (Local Contexts RDF in RiC/OAI/portable export; SKOS peer + no_equivalent).
5. Region packs (ZA first by self-identification, then SADC/WA/etc.).

## Standards
Local Contexts (TK/BC Labels & Notices), CARE Principles, UNDRIP, Nagoya/ABS (BC), SKOS, RiC-O; enforcement via existing ODRL. See `indigenous-tk-bc-plugin-design.md`, `indigenous-knowledge-rights-in-archives.md`.
