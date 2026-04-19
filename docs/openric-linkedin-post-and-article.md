# OpenRiC — LinkedIn post + article

**Status:** Draft, ready for review. Covers the v0.2.0 frozen + v0.3-draft (Proof + Profiles) state as of 2026-04-19.
**Author:** Johan Pieterse
**Venues:** LinkedIn personal feed (post), LinkedIn Publish / Articles (long-form).

---

## 1. LinkedIn post — short feed post

*Target length: ~180 words, reads well on a phone, one clear call to action.*

> **An open specification for archival linked data — and anyone can implement it.**
>
> Over the past month we've taken OpenRiC from "idea with a reference implementation" to a four-surface open standard:
>
> 📖 [openric.org](https://openric.org) — four normative documents, 19 JSON Schemas, SHACL shapes, a 27-case conformance fixture pack, and an OpenAPI 3.0 contract. CC-BY 4.0.
> 🔌 [ric.theahg.co.za](https://ric.theahg.co.za) — a live reference API backed by real archival data (713 records, 402 agents, 14 repositories, 1,281 instantiations).
> 🗺 [viewer.openric.org](https://viewer.openric.org) — 2D+3D graph viewer that drives any conformant server.
> ✍ [capture.openric.org](https://capture.openric.org) — pure-browser data-entry client.
>
> Shipped this week: a **[Proof-of-implementation](https://openric.org/proof) page** with end-to-end use cases and the ISAD(G) → RiC-O mapping laid out explicitly — and a **[Profiles framework](https://openric.org/profiles)** so institutions can adopt the spec incrementally, starting with Core Discovery.
>
> Not a product. A contract anyone can implement.
>
> Feedback, critique, second implementers — all welcome at <https://github.com/ArchiveHeritageGroup/openric-spec/discussions>.
>
> #archives #linkedopendata #RiC #digitalpreservation #GLAM #ICA

---

## 2. LinkedIn article — long-form

**Suggested title:** *OpenRiC — an IIIF-style contract for archival linked data, and why "profiles" matter*

**Alternative titles:**
- *Making Records in Contexts implementable — a progress update on OpenRiC*
- *Why the archival world needs a contract layer, not another product*
- *From spec to ecosystem in four weeks — OpenRiC v0.2 and the profiles framework*

**Recommended cover image:** the OpenRiC architecture diagram (from `openric.org/architecture`) or a screenshot of the viewer rendering the Egyptian Boat fonds.

---

### The problem

The International Council on Archives spent most of a decade developing **Records in Contexts** — a conceptual model and ontology (RiC-CM and RiC-O) that finally gives archival description a linked-data foundation. The model is ambitious, technically sound, and published. But adoption has been slow for a familiar reason: there is no standard contract for how a RiC-based system exposes its data over HTTP. Each implementer who has tried has invented their own endpoint shapes, their own mapping conventions, their own conformance targets. The result is a shelf of incompatible "RiC-based" catalogues that cannot read each other's data.

The image-sharing world faced the same problem fifteen years ago, and solved it with IIIF — a specification that separates the protocol from the viewer. A Mirador viewer works against a Universal Viewer server; an OpenSeadragon client works against a Cantaloupe backend. Eight hundred institutions now run IIIF-compliant image servers, because the spec made interoperability the default rather than the exception.

OpenRiC is the archival equivalent. We are building it in the open, with CC-BY 4.0 licensing on the spec and AGPL-3.0 on the reference implementation, for the simple reason that a standard owned by one product is not a standard.

### What shipped over the past four weeks

The project now has four independently-hosted public surfaces — each replaceable by a third-party implementation without touching the others:

- **The specification** — `openric.org` — four normative documents (mapping, viewing API, graph primitives, conformance), 19 JSON Schemas, SHACL shapes, a 27-case fixture pack, a validator CLI, and an OpenAPI 3.0 contract. Currently frozen at **v0.2.0**.
- **The reference API** — `ric.theahg.co.za` — a Laravel service implementing the OpenRiC contract against a real archival database. Around 40 endpoints across read, write, and OAI-PMH v2.0. Stress-tested with real holdings: 713 records, 402 agents, 14 repositories, 183 places, 1,281 instantiations, six languages.
- **The viewer** — `viewer.openric.org` — a 2D/3D graph rendering client that drives any conformant server. Demonstrably generic: the live page switches between two independent backends to prove the decoupling.
- **The capture client** — `capture.openric.org` — a pure-browser data-entry tool. Paste a server URL and an API key; create Places, Rules, Activities, Instantiations, and relations against any OpenRiC-compliant backend.

On top of those, the specification site now hosts a **[Proof-of-implementation page](https://openric.org/proof)** with concrete evidence that the contract holds — live numbers, an end-to-end use case following one record from cataloguing to OAI harvest, an ISAD(G) → RiC-O mapping table, and one live example of every RiC-O type.

### Why the Profiles framework is the biggest move this week

The single most important decision in any standards project is **scope**. Demand too much and institutions walk away. Demand too little and the standard means nothing. IIIF solved this with levels: a server can implement Presentation API Level 0 (static images, no dynamic tiles) or Level 2 (full cropping, rotation, multi-page) or anything in between. The claim is bounded, verifiable, and public.

OpenRiC is doing the same thing, but shaped around capability *axes* rather than strict progression. We are calling them **profiles**, and we shipped the framework this week at [openric.org/profiles](https://openric.org/profiles). Six profiles are planned:

1. **Core Discovery** — read-only Records, Agents, Repositories, vocabulary, autocomplete. The minimum "I can be queried" claim. *(Defined this week, v0.3.0-draft.)*
2. **Authority & Context** — Places, Rules, Activities as first-class entities. *(Planned.)*
3. **Provenance & Event** — Activity subclasses with the full event model. *(Planned.)*
4. **Digital Object Linkage** — Instantiations with checksum, MIME, IIIF manifest pointers. *(Planned.)*
5. **Export-Only** — OAI-PMH harvest plus JSON-LD dumps. *(Planned.)*
6. **Round-Trip Editing** — full write surface with provenance-aware rules. *(Planned.)*

A server declares which profile(s) it supports in its service description. A conformance probe verifies the claim. An institution with a legacy catalogue can expose Core Discovery today, get listed, and grow into Authority & Context next year without ever claiming more than it delivers.

We deliberately wrote the Core Discovery Profile first, with eight explicit open design questions flagged for community review. The remaining five profiles will be defined one at a time — only when at least one independent implementer is ready to target them. Defining six profiles up front and having five go unimplemented is exactly how standards lose credibility. The IIIF path is the better one: ship the minimum, let it mature, define the next one against what you learned.

### Why this matters strategically

Three stakeholder groups benefit differently:

- **Institutions with legacy catalogues** get a low-ceremony adoption path. Expose Core Discovery, be discoverable, grow over time.
- **Aggregators and national portals** get a predictable harvest target. Any OpenRiC server that claims Export-Only responds to the same OAI-PMH verbs.
- **Software vendors** (commercial or open-source) get a contract to build against. Competing on viewer UX, discovery UX, and search quality — not on owning the standard.

And for the ICA Expert Group on Archival Description (EGAD) — the authors of RiC-CM and RiC-O — OpenRiC offers something they cannot easily build themselves: an implementation-neutral HTTP layer that lets the conceptual model they wrote actually move between systems.

### What we are looking for

- **Critique on the spec.** Every design decision has a rationale, and none are irreversible while we're pre-v1.0. Feedback at [github.com/ArchiveHeritageGroup/openric-spec/discussions](https://github.com/ArchiveHeritageGroup/openric-spec/discussions).
- **A second implementation.** AtoM, Archivematica, a national archive platform, or an experimental build — if you have thought about emitting RiC-O over HTTP, we would love a conversation. The [getting-started guide](https://openric.org/guides/getting-started.html) is 15 minutes end-to-end.
- **Mapping sanity checks.** Some of the ISAD(G) → RiC-O mapping rows (acquisition provenance, access restrictions) involved judgment calls. Pushback welcome.
- **Profile scope review.** The eight open questions in the Core Discovery draft are live. The community decides the answers, not one editor.

### Where to start

- Read the spec: **[openric.org/spec](https://openric.org/spec)**
- See the evidence: **[openric.org/proof](https://openric.org/proof)**
- Understand the profiles framework: **[openric.org/profiles](https://openric.org/profiles)**
- Try the reference API: **[ric.theahg.co.za/api/ric/v1/](https://ric.theahg.co.za/api/ric/v1/)**
- Run the probe against any server: **[openric.org/conformance](https://openric.org/conformance)**
- Join the conversation: **[Discussions](https://github.com/ArchiveHeritageGroup/openric-spec/discussions)**

The IIIF analogy is well-worn but exact. If OpenRiC does for archival linked data what IIIF did for images, the entire field benefits.

Not a product. A contract anyone can implement.

---

*Johan Pieterse is the editor of OpenRiC. He runs The Archive and Heritage Group and Plain Sailing Information Systems. Reach him at <johan@plainsailingisystems.co.za>.*

---

## 3. Posting notes

- **Post first, article second.** Post gets the click-through; the article is for the reader who clicks. Schedule the post at ~09:00 SAST on a Tuesday or Wednesday for best LinkedIn feed reach.
- **Tag considerations.** Ian Milligan, William Kilbride, Tim Hutchinson, Victoria Lemieux — archival/records professionals with RiC-adjacent visibility. Only if they have engaged with prior OpenRiC work; cold-tagging reads as spam.
- **Group reshare targets.** "ICA Experts Group Archival Description" (LinkedIn), "Archives and Records Association", "Digital Preservation Coalition".
- **Hashtags** — already in the post. Do not add more; LinkedIn penalises >5.
- **Do not include screenshots in the post.** LinkedIn's algorithm favours link-free posts with images, but for this audience the external-link click-through is the goal — keep the link visible.
- **Article cover image.** Recommend the architecture diagram, 1200×627px. If using the viewer screenshot, crop to a single fonds graph with visible rico:* edge labels.
