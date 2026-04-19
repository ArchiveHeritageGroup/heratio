# OpenRiC Future Direction and Phased Roadmap

## Strategic Direction

The right long-term direction is to position **OpenRiC** as an **open-source, API-first RiC interoperability project**, with **Heratio as one consumer and producer**, not the centre of the model.

That means the clearest structure is:

- **OpenRiC** = the open specification, profiles, contracts, fixtures, mappings, and conformance model.
- **Reference API** = one implementation of the OpenRiC contract.
- **Heratio** = one operational platform that consumes and publishes through OpenRiC.
- **Other consumers** = future viewers, catalogs, validators, connectors, and repository systems.

This is the strongest position because it prevents OpenRiC from being seen merely as “Heratio’s RiC feature” and instead makes it a reusable interoperability layer.

---

## Core Strategic Recommendation

The strongest guiding principle should be:

**Make OpenRiC the RiC interoperability contract project, not the RiC application project.**

That means the most important long-term assets of OpenRiC should be:

- the specification,
- implementation profiles,
- conformance suite,
- test fixtures,
- mapping packs,
- validators,
- reference implementation,
- service discovery metadata,
- and federation patterns.

In this model, **Heratio wins by being the first serious consumer and producer**, rather than by defining the project itself.

---

## Future Suggestions

## 1. Keep OpenRiC explicitly spec-first

OpenRiC should remain clearly separated from any one application.

### Recommended model

- **OpenRiC** = specification and interoperability contract.
- **Reference API** = one implementation of that contract.
- **Heratio** = one consuming and publishing platform.
- **Future systems** = additional consumers and producers.

### Why this matters

This keeps OpenRiC reusable, credible, and attractive to a wider ecosystem.

---

## 2. Build around profiles, not one universal implementation target

RiC is broad and complex. Adoption is more likely if OpenRiC defines named implementation profiles rather than expecting every implementation to support the full semantic range immediately.

### Possible profiles

- **Core discovery profile**
- **Authority and context profile**
- **Provenance and event profile**
- **Digital object linkage profile**
- **Export-only profile**
- **Round-trip editing profile**

### Why this matters

Profiles make adoption more realistic and allow institutions to enter the ecosystem incrementally.

---

## 3. Treat conformance as one of the core products

The conformance model may become more important than the code itself.

### Core assets to build

- conformance fixtures,
- sample datasets,
- expected API responses,
- profile test packs,
- SHACL validation packs,
- public conformance reports or badges.

### Why this matters

That is how OpenRiC becomes a true interoperability layer instead of just another implementation.

---

## 4. Formalise a three-layer contract stack

A strong technical contract model would be:

- **OpenAPI** for the HTTP contract,
- **JSON Schema** for payload structure,
- **SHACL** for graph-level semantic validation.

### Why this matters

This gives OpenRiC a bridge between normal API engineering and semantic-web correctness.

---

## 5. Add a catalog and discovery layer early

Each OpenRiC deployment should be able to advertise:

- service metadata,
- supported profiles,
- conformance claims,
- dataset scope,
- licensing,
- endpoint descriptions,
- and example queries.

### Why this matters

This creates the basis for a future federated ecosystem rather than isolated endpoints.

---

## 6. Plan for federation from the beginning

OpenRiC should not only serve individual repositories. Over time it should support a wider ecosystem where multiple compliant deployments can be discovered and queried.

### Early federation pattern

Start with:
- endpoint registry,
- profile registry,
- harvested summaries,
- cross-site authority matching,
- federated search over normalized summaries.

### Why this matters

Federation becomes a major differentiator if implemented well.

---

## 7. Separate canonical graph from delivery views

The internal semantic model should remain distinct from public-facing representations.

### Example delivery views

- graph-native responses,
- flattened search results,
- browse or tree views,
- map or timeline views,
- editing payloads,
- lightweight institutional summaries.

### Why this matters

This keeps the specification flexible and allows different consumers to use the same semantic core in different ways.

---

## 8. Make round-tripping a serious design goal

OpenRiC should aim not only to expose RiC-aligned outputs, but eventually to support safe and controlled round-tripping between operational systems and the RiC layer.

### This includes

- export from legacy systems,
- consumption by external tools,
- controlled edits,
- reconciliation,
- provenance-aware write-back rules.

### Why this matters

Many projects can publish linked data. Fewer can support meaningful interoperability with safe re-ingestion and editing workflows.

---

## 9. Invest in authority and identity resolution

OpenRiC should treat identity and reconciliation as a core capability, not an optional enhancement.

### Important identity domains

- people,
- organizations,
- places,
- functions,
- mandates,
- events,
- record sets,
- digital objects.

### Why this matters

RiC becomes much more valuable when contextual entities are well governed and linked consistently.

---

## 10. Create a low-barrier adoption path for non-RiC-native systems

Most systems will not become fully RiC-native quickly. OpenRiC should therefore support staged adoption.

### Suggested levels

- **Level 1**: basic read-only mapped endpoints.
- **Level 2**: linked contextual entities and validation.
- **Level 3**: graph-aware creation, editing, and federation.

### Why this matters

This makes ecosystem growth much more realistic.

---

## 11. Publish reference mappings from common archival models

OpenRiC should offer practical mapping packs and reference patterns for common archival and records description structures.

### Useful mapping packs

- ISAD(G)-style structures,
- EAD and EAC derived exports,
- AtoM-like relational structures,
- CSV and tabular imports,
- authority record structures.

### Why this matters

Practical mappings reduce adoption friction and make the project immediately useful.

---

## 12. Define governance early

Open-source specification projects need visible governance.

### Governance areas to define

- specification governance,
- profile governance,
- change proposal process,
- compatibility policy,
- deprecation policy,
- version support policy.

### Why this matters

Implementers need confidence that the ecosystem will evolve in a stable and predictable way.

---

# Phased Roadmap

## Phase 1: Foundation

### Aim

Establish OpenRiC as a clear, stable, API-first interoperability contract.

### Main outcomes

- Define the core mission and scope.
- Separate OpenRiC clearly from Heratio.
- Publish the baseline specification.
- Publish the reference API.
- Establish the contract stack:
  - OpenAPI,
  - JSON Schema,
  - SHACL.
- Define a canonical versioning approach.
- Publish initial sample datasets and fixtures.
- Publish initial developer documentation.

### Key deliverables

- specification v1 baseline,
- reference API repository,
- schema repository,
- validation repository or package,
- example requests and responses,
- initial conformance checks,
- service metadata format.

### Success indicators

- An external developer can understand the contract.
- Heratio can consume and publish through the contract.
- A second consumer can be built against the same model.

---

## Phase 2: Adoption

### Aim

Lower the barrier for institutions and systems to implement OpenRiC incrementally.

### Main outcomes

- Introduce named implementation profiles.
- Define staged adoption levels.
- Publish reference mappings from common archival models.
- Expand implementation guidance.
- Improve onboarding for non-RiC-native systems.

### Key deliverables

- core discovery profile,
- authority/context profile,
- export-only profile,
- implementation guide,
- mapping packs,
- migration guidance,
- example consumer integrations.

### Success indicators

- Institutions can identify an entry-level implementation path.
- Multiple systems can expose OpenRiC-compliant outputs without full semantic maturity.
- OpenRiC becomes practically adoptable beyond Heratio.

---

## Phase 3: Conformance and Trust

### Aim

Make OpenRiC credible as a real interoperability layer through verifiable conformance.

### Main outcomes

- Expand conformance fixtures and tests.
- Publish profile-based conformance packs.
- Add machine-readable service claims.
- Publish public conformance reporting patterns.
- Improve validation tooling.

### Key deliverables

- conformance suite,
- test fixtures,
- profile validation packs,
- service declaration format,
- conformance report templates,
- badge or claim structure.

### Success indicators

- An implementation can prove what parts of OpenRiC it supports.
- Conformance becomes comparable across different systems.
- External evaluators can assess quality objectively.

---

## Phase 4: Ecosystem Growth

### Aim

Move from one reference implementation to a broader ecosystem of tools and consumers.

### Main outcomes

- Encourage external viewers and clients.
- Support additional repository and archival systems.
- Publish reusable SDKs or client libraries.
- Improve documentation for third-party developers.
- Add ecosystem showcases and examples.

### Key deliverables

- client libraries,
- integration examples,
- viewer examples,
- third-party implementation guides,
- showcase page,
- external contribution guide.

### Success indicators

- More than one serious consumer exists.
- OpenRiC is seen as reusable beyond its originating environment.
- The project begins attracting external experimentation.

---

## Phase 5: Federation

### Aim

Enable coordinated discovery and use across multiple OpenRiC deployments.

### Main outcomes

- Add endpoint registry patterns.
- Add profile registry patterns.
- Publish dataset and service metadata guidance.
- Support harvested summaries and cross-site search.
- Develop authority reconciliation across installations.

### Key deliverables

- registry specification,
- dataset/service metadata model,
- cross-instance discovery guide,
- federated search pattern,
- identity reconciliation pattern.

### Success indicators

- Separate OpenRiC deployments can be discovered and described consistently.
- Cross-instance search and contextual linkage become possible.
- The project starts functioning as a network, not just a single deployment model.

---

## Phase 6: Advanced Interoperability and Round-Trip Exchange

### Aim

Support richer interoperability where systems can not only publish but safely exchange, reconcile, and potentially write back information.

### Main outcomes

- Define controlled editing models.
- Define reconciliation workflows.
- Establish provenance-aware write-back rules.
- Improve handling of conflicting assertions.
- Support higher-trust exchange scenarios.

### Key deliverables

- round-trip editing profile,
- reconciliation workflow guide,
- provenance handling model,
- conflict management guidance,
- trust and exchange policy patterns.

### Success indicators

- OpenRiC supports more than publishing and search.
- External tools can participate in carefully governed editing workflows.
- The project becomes materially more valuable than a one-way linked-data export layer.

---

# Suggested Immediate Priorities

The strongest short-term sequence would be:

1. Finalise the spec-first positioning.
2. Cleanly separate OpenRiC from Heratio in all public messaging.
3. Define the first implementation profiles.
4. Strengthen conformance as a first-class deliverable.
5. Publish reference mappings from common archival models.
6. Add service metadata and discovery patterns.
7. Prepare for federation conceptually, even if not fully implemented yet.

---

# Final Conclusion

OpenRiC has the potential to occupy a valuable position in the archival and records interoperability space if it remains:

- open,
- API-first,
- profile-driven,
- validation-aware,
- and not dependent on a single platform identity.

The key is to ensure that **OpenRiC becomes the contract**, while platforms such as **Heratio become consumers and producers within that contract**.

That is the direction most likely to produce long-term ecosystem value, broader adoption, and architectural durability.
