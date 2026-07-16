# "The Ontology Was Never the Hard Part" (thought-leadership article)

**Summary:** Public thought-leadership companion to the technical `ontology-governance-pin.md`. Argues that teams over-invest in *modelling* an ontology (the class diagram) and under-invest in *governing* it (versions, namespaces, permanent identifiers, conformance, provenance), and that AI - which writes assertions straight into the knowledge graph - makes that governance urgent. Heratio blog #28 by Dr Johan Pieterse (The AHG), group "Framework". Continues the "___ was never the hard part / governance" series: #22 "The Model Was Never the Hard Part" (AI-RAM), #27 "Preservation", now #28 ontology.

Public URL: https://heratio.theahg.co.za/articles/the-ontology-was-never-the-hard-part

## Core thesis

An ontology is a promise, not a diagram - a long-lived commitment about how meaning is expressed, identified and preserved across many people, systems and years. It is worthless without a process to keep it. When AI starts asserting relationships into the graph, the ontology stops being a modelling exercise and becomes a governance problem.

## The six governance moves (the article's spine)

1. **Version-pin it, don't "latest" it** - RiC-O pinned at 1.0.2 (also CIDOC-CRM 7.1.3, SKOS, PROV-O); a bump is a governed change with a migration note, never an auto `git pull`.
2. **One extension namespace, never ad hoc in code** - a single canonical `openric:` namespace, documented in the spec; nothing minted inline (avoids three URIs for one concept).
3. **Permanent identifiers - deprecate, never delete** - IRIs minted from stable keys not labels; superseded entities `owl:deprecated`, never removed (someone already cited them).
4. **The graph is a projection, not the source of truth** - the relational DB is authoritative; the RDF graph is a derived, regenerable projection (no dual-write). Enables drop-and-rebuild under new IRIs/versions; makes triplestore cleanup a non-event.
5. **A conformance gate, not a guideline** - SHACL shapes validated in CI; non-conformant changes fail the build. The gate, not a style guide.
6. **AI assertions carry receipts** - every AI-asserted edge carries provenance (model, version, confidence, timestamp, human-confirmed/overridden); machine claims visibly distinct from human ones, in the graph and every export. POPIA/PAIA evidentiary requirement; same epistemic-transparency principle as the KARMA/iPRES/MISTRA line.

## The artifact

A single, citable one-page **ontology governance pin** (see `ontology-governance-pin.md`) declaring pinned versions, canonical namespaces + IRI policy, the source-of-truth rule, the change process, the conformance gate + export guarantees (Turtle/JSON-LD round-trip; read-only SPARQL; no proprietary-only path), and the AI-provenance requirement. Closing move: govern the ontology like public infrastructure and contribute extensions back to a community standard (OpenRiC), not a proprietary model.

See [[project_thought_leadership_blog]]; technical detail in `ontology-governance-pin.md`; and the companion `preservation-in-ai-archives.md`, `ipres-ai-metadata-preservation-paper.md`.
