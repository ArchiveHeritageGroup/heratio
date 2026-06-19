# Ontology Governance Pin (Heratio / AtoM3 RiC stack)

**Status:** v1 governance pin, 2026-06-19. Owner: Johan Pieterse (The Archive and Heritage Group).
**Purpose:** the single, citable record of *which* ontologies Heratio commits to, at *which versions*, under *what change process*, and with *what conformance gate*. Agents, the KM RAG, downstream consumers and any AtoM3/Foundation alignment read this file as the canonical pin. If an ontology version, namespace, or export format is not listed here, it is not sanctioned.

This is deliberately one page. It governs the meaning layer; it does not re-specify the model (that lives in the OpenRiC spec and `ahg-ric`).

## 1. Pinned standards and versions

| Layer | Standard | Pinned version | Role |
|---|---|---|---|
| Records model | RiC-O (Records in Contexts Ontology) | **1.0.2** | Primary records-centric ontology. Canonical entity/relationship target. |
| Thesauri / controlled vocab | SKOS | W3C Rec (2009) | `term`, `subject`, `place`, authority vocabularies. |
| Provenance | PROV-O | W3C Rec (2013) | "Who asserted this, when, how, on what basis." Backs `ahg-provenance`. |
| AI inference provenance | PROV-O + `AhgInferenceReceipt` profile | local profile v1 | Records model, prompt, confidence, human-override for AI-asserted edges. `ahg-provenance-ai`. |
| Museum bridge | CIDOC-CRM | **7.1.3** | Cross-walk for museum/object data via `ahg-ric/src/Crm` (`CrmSerializer`, `RicToCrmMapper`; `CrmExportController` emits `X-CRM-Version: 7.1.3`). |

**Rule:** RiC-O is version-pinned at 1.0.2. RiC-O is still maturing and version-churns; a bump is a governed change (section 4), never an automatic `git pull`.

## 2. Canonical namespaces / IRI policy

- Use the upstream namespace IRIs unchanged: `rico:` = `https://www.ica.org/standards/RiC/ontology#`, `prov:`, `skos:`, `crm:` = `http://www.cidoc-crm.org/cidoc-crm/` (CIDOC-CRM 7.1.3).
- **AHG-local extension namespace (single, canonical):** `openric:` = `https://openric.org/ns/v1#`. This is the public OpenRiC community namespace and MUST resolve to this IRI in every serialisation (JSON-LD and Turtle alike). AHG extension predicates/classes live here, documented in the OpenRiC spec, never minted ad hoc in code. (Superseded 2026-06-19: the AHG-hosted `http://openric.theahg.co.za/ns/v0.2/openric#` is deprecated.)
- **Public entity-IRI base (single, canonical, environment-independent):** `https://ric.theahg.co.za/ric/<type>/<slug>`, sourced from `config('ric.base_uri')` for all PUBLIC/export minting (`RicSerializationService`, `LinkedDataApiController`, `CrmSerializer`; JSON-LD / Turtle / SPARQL export). Dereferenceable - the host equals the published SPARQL/REST host. (Superseded 2026-06-19: `config('app.url')`-based minting - which produced `http://localhost/...` in CI/dev and a Heratio-host IRI in prod - plus `https://archives.theahg.co.za/ric` and the dead `https://heratio.theahg.co.za/ric/`.)
- **Internal live-graph node IRIs (two-layer model):** the live Fuseki graph deliberately uses `urn:ahg:ric:<type>:<id>` (id-based) as node / named-graph identifiers, which partition the dataset one named graph per entity (`FusekiInstanceLoadCommand`, `CrmGraphSyncService`, `FusekiIntegrityCheckCommand`, `FusekiOrphanCleanupCommand`). These are internal and NOT dereferenceable; the public `https://ric.theahg.co.za/ric/...` IRIs above are emitted at export/serialisation time. The semantic-layer (Qdrant) join into the graph is `object_id -> urn:ahg:ric:<type>:<id>`; the public IRI rides alongside as the external identity.
- Entity IRIs are minted from stable identifiers (authority ids), never from mutable labels. An IRI, once published, is permanent; superseded entities/IRIs are `owl:deprecated`, not deleted (mirrors the relational read-only-base rule).
- **Operational hosts are not ontology namespaces:** `https://ric.theahg.co.za` (REST/SPARQL endpoint host) is a service base URL, not a vocabulary IRI. The SHACL shapes graph lives under `https://ric.theahg.co.za/ric/shapes#`.

## 3. Source of truth and the derived graph

- The relational store (AtoM/Heratio DB) remains the **source of truth**. The RDF graph in Fuseki is a **derived, regenerable projection** (`FusekiSyncService`, backfill + near-real-time sync). No dual-write.
- The graph can be dropped and rebuilt from the relational source at any time. Nothing of record originates only in the triplestore.

## 4. Change process (the actual governance)

1. **Proposal** - any ontology-affecting change (version bump, new predicate, namespace, mapping change) is raised as a GitHub issue against `ahg-ric` / `openric-spec` with the before/after mapping.
2. **Review** - editorial owner signs off. RiC-O/CRM version bumps require an explicit migration note.
3. **Conformance gate** - the change must pass **SHACL validation** (`ShaclValidationService`) against the published shapes before merge. SHACL is the gate, not a guideline. Wire this into CI so a non-conformant graph fails the build.
4. **Versioned release** - bump the ontology-artifact version (semver: major = breaking IRI/semantics, minor = additive, patch = labels/docs), update the change log, regenerate the SHACL shapes and the SPARQL/JSON-LD fixtures.
5. **Change log** - every released change is appended to the OpenRiC spec change log with date, version, and migration impact. Agents and consumers diff against this.

## 5. Conformance and export (anti-lock-in)

- **Serialisations:** Turtle and JSON-LD are first-class and must always round-trip. RDF/XML on request. Portability is a guarantee, not a feature.
- **Endpoints:** a read-only SPARQL endpoint and JSON-LD export are published and documented. No proprietary-only access path.
- **Lock-in stance:** standard serialisations + published APIs + a full export path mean the ontology stays portable even if tooling changes. Where AtoM3 / the AtoM Foundation align, the ontology layer is contributed as a community standard, not a Heratio-proprietary model.

## 6. AI provenance requirement (archives-specific)

Every AI-asserted edge (NER link, suggested relationship, OCR-derived field) carries an `AhgInferenceReceipt`: the model, the prompt/version, a confidence value, the timestamp, and whether a human confirmed or overrode it. This is a legal/evidentiary requirement under POPIA/PAIA and equivalents, not a nicety. AI assertions are visibly distinguishable from human-curated ones in the graph and in any export.

## 7. What this pin does NOT cover

- The data model itself (OpenRiC spec + `ahg-ric` mapping docs).
- AI service governance (see `docs/ai-governance-issue.md`).
- Trademark/branding (AtoM3 naming) - that is a Foundation/Artefactual agreement, separate from this technical pin.

## Change log

- 2026-06-19 - v1. Initial pin. RiC-O 1.0.2, SKOS, PROV-O, CIDOC-CRM bridge, SHACL conformance gate, change process and export guarantees recorded.
- 2026-06-19 - v1.1. Namespace consolidation (#1319). Pinned CIDOC-CRM 7.1.3. Consolidated ALL RiC entity-IRI minting onto the single canonical base `https://ric.theahg.co.za/ric` via `config('ric.base_uri')` - superseding the environment-dependent `config('app.url')` minting (which produced `http://localhost/...` in CI/dev), `https://archives.theahg.co.za/ric`, and the dead `https://heratio.theahg.co.za/ric`. Fixed the `openric:` extension namespace to the single public `https://openric.org/ns/v1#` in both JSON-LD and Turtle (was format-dependent: v1 in JSON-LD, AHG-hosted v0.2 in Turtle - a round-trip hazard). SHACL shapes graph moved to `https://ric.theahg.co.za/ric/shapes#`. Migration: Fuseki is a derived projection (section 3) and must be rebuilt under the canonical IRIs; superseded IRIs are `owl:deprecated`, not deleted.
