Dear Richard,

Thank you — this is exactly the kind of feedback that makes an initiative stronger, not weaker, and I'm grateful you took the time to write at that length. The IIIF analogy landed hard, and I think you are right.

Answering your three questions directly:

1. **Yes** — OpenRiC is intended as an independent software layer. Heratio is currently the only system that speaks it, but nothing in its design requires Heratio.

2. **Yes, correctly observed.** Today it is an automated translation from the AtoM-style archival description into RiC, surfaced through the 2D / 3D graph views. There is no UI yet for adding RiC assertions directly — neither on top of a translated description nor from scratch.

3. **Yes, this is temporary.** The roadmap already has direct RiC editing, triple-store persistence, and an eventual RiC-primary view across several phases. The current translation-only state is the first milestone, not the destination.

On your larger suggestion — standardisation at a level not tied to any one product — I agree, and I would like to act on it concretely rather than just nod along. My proposal:

**Split OpenRiC into two artifacts.**

- **`openric-spec`** — an implementation-neutral specification. Four documents: a mapping spec (AtoM / ISAD(G) / ISAAR(CPF) / ISDIAH → RiC-CM / RiC-O), a viewing API (REST + JSON-LD, in the spirit of IIIF Presentation API), a graph-primitives document defining abstract concepts like node, edge, cluster, drill, and a conformance test suite with JSON Schemas and fixture data. Spec licensed CC-BY 4.0.

- **`heratio-openric`** — Heratio becomes the reference implementation. The 2D / 3D viewer JS gets extracted as a standalone `@openric/viewer` package that can point at any OpenRiC-conformant server, not just Heratio. We prove the split is real by demoing the viewer against a non-Heratio backend (an AtoM adapter, most likely).

This reframes Heratio from "the product that owns the standard" to "the first and most complete implementation of an open standard" — which is a stronger position anyway, and a more honest one.

I would be grateful if, when the first drafts of the mapping spec and viewing API are ready (aiming ~4 weeks), you would be willing to read them and push back where they are wrong or over-fitted to Heratio. Entirely in a personal capacity — I'm not asking you to speak for EGAD, and I recognise the distinction. If you can also suggest one or two others whose eyes would improve the drafts, even better.

Regardless of how the spec work unfolds, thank you for the encouragement on the graph views. That part was genuinely fun to build, and knowing it resonates with people thinking about what a RiC-native interface could feel like is a real lift.

With warm thanks,
Johan
