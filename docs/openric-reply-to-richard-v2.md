Dear Richard,

A short progress note on what you nudged me toward a couple of weeks ago.

**The split happened.** OpenRiC is now a separate specification repo (CC-BY 4.0) at `github.com/ArchiveHeritageGroup/openric-spec`, published at [openric.org](https://openric.org). Heratio is still behind the scenes as a data source, but it is no longer "the product that owns the spec" - the reference API runs on its own host (`ric.theahg.co.za`), licensed separately (AGPL), and the viewer is on its way to a standalone npm package so it can drive any conformant server.

The spec currently frozen is `v0.2.0`. It has four documents (viewing API, mapping, graph primitives, conformance), twelve JSON Schemas, a 27-case fixture pack pinned to live responses, SHACL shapes (core + cross-graph), an OpenAPI 3.0 contract, and a bash-based conformance probe. There is a live API Explorer with Swagger UI, and a `For institutions` page that deliberately speaks to decision-makers rather than implementers.

**What you pushed us toward that wasn't obvious at the time.** The IIIF comparison isn't only about spec/implementation decoupling - it's about *what IIIF did next*, which was levels and profiles. Presentation API Level 0 / Level 1 / Level 2 let servers opt in at their current capability without claiming more than they can deliver. That is the direction we are going with OpenRiC, and I would value your thinking before we commit spec-shaped text to it.

The emerging shape - six **named profiles** rather than a single monolithic conformance target:

- **Core Discovery** - read-only Records, Agents, Repositories, vocabulary, autocomplete. The minimum "I can be queried" claim.
- **Authority & Context** - Places, Rules, Activities as first-class entities with reconciliation-friendly identifiers.
- **Provenance & Event** - Activity subclasses (Production, Accumulation, etc.) with the full event model.
- **Digital Object Linkage** - Instantiation entities with checksum, MIME, IIIF manifest pointers.
- **Export-Only** - OAI-PMH harvest plus one-shot JSON-LD dumps, no interactive discovery.
- **Round-Trip Editing** - POST/PATCH/DELETE with provenance-aware write-back rules (this is the hardest, and not for the first pass).

My intention is to define **Core Discovery** formally first, ship it, and only then design the next profile against what we learned, rather than defining all six up front and having most of them go unimplemented. That is also the IIIF sequence.

Two things I would value your opinion on:

1. **Naming.** "Profile" vs "Level" - does Core Discovery / Authority / Provenance read better than Level 0 / Level 1 / Level 2? IIIF used levels for the Image API and profiles for Presentation, which suggests the right answer is "whichever matches how implementers think about the boundary." I am leaning profile, because each of ours is a distinct capability axis rather than a strict superset, but I would not push back if you thought otherwise.

2. **Scope for Core Discovery.** I am cutting graph traversal, relations, activities, places, rules, and all write endpoints from this first profile. That is a more minimal definition than I initially wanted, but I think it is the right one - a server that exposes *only* records, agents, and repositories should still count as "an OpenRiC server." Does that cut feel right to you?

There is no rush on either - I can get you a draft of the profile doc in the next couple of weeks, and your response can come with that in hand rather than from this note. I mention it now only because the direction decision is load-bearing: if we define profiles badly, every future spec revision has to work around them.

Separately, the [proof-of-implementation page](https://openric.org/proof) went live today - one concrete example of every RiC-O type, with the ISAD(G) → RiC-O mapping table laid out explicitly. It was partly in response to reviewer feedback that said "I believe you, but I want to see it." If you happen to look at it, pushback on the mapping rows would be especially welcome - some of them (3.2.4 acquisition provenance, 3.4.1 access rules) involved judgement calls that I am not fully sure about.

Thank you again. The fact that you told us to think bigger has shaped every structural decision since.

With warm thanks,
Johan
