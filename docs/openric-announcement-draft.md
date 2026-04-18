# OpenRiC v0.1.0 announcement — draft

**Status:** Draft, not yet sent. Post after `viewer.openric.org` cert goes green.
**Targets:** AtoM users list, Archivematica users list, any EGAD/RiC-O contacts, ICA-adjacent Twitter/Bluesky.
**Author:** Johan Pieterse

---

## Subject line options

1. **"OpenRiC 0.1.0 — an open spec + reference implementation for Records in Contexts"** *(plainest)*
2. **"Records in Contexts is now implementable — introducing OpenRiC 0.1.0"**
3. **"OpenRiC — an IIIF-style spec for serving RiC-CM/RiC-O, with a reference implementation"**

Recommend **#1** — boring, accurate, gets the link-click.

---

## Body

> Hi all,
>
> We've published **OpenRiC 0.1.0** at **[openric.org](https://openric.org)** — an open, implementation-neutral specification for serving ICA's *Records in Contexts* model (RiC-CM / RiC-O) over HTTP.
>
> The deliverable is four short documents, a machine-verifiable conformance pack, and a reference implementation:
>
> - **Mapping spec** — how ISAD(G), ISAAR-CPF, and ISDIAH translate to RiC-O
> - **Viewing API** — a small REST + JSON-LD contract (records, agents, places, rules, activities, instantiations, a subgraph endpoint)
> - **Graph primitives** — node/edge/cluster/drill abstractions so UIs can render RiC from any conformant server
> - **Conformance** — 12 JSON Schemas, SHACL shapes, a 20-case fixture pack, an `openric-validate` CLI
>
> The reference implementation is **[Heratio](https://github.com/ArchiveHeritageGroup/heratio)** — an open-source Laravel archival-management platform (GLAM-focused) that emits RiC-O for every record, agent, and repository. 8 endpoint types currently live-validate against the schemas.
>
> A **standalone 2D/3D graph viewer** (`@openric/viewer`) is published on npm and deployed at **[viewer.openric.org](https://viewer.openric.org)**. The same viewer is driven by two backends in the demo — the Heratio reference server AND an in-browser static fixture server — to prove the decoupling actually holds. Anyone building an OpenRiC-conformant server can drop the viewer in without bundling a UI of their own.
>
> **What we'd love from you**
>
> - **Review.** Spec-level feedback, mapping disagreements, "this doesn't work for our national archive" — all welcome. The spec is a v0.1 *draft*, explicitly open for revision.
> - **A second implementation.** Heratio being the only one is what keeps this at "claim" rather than "proof". If you run AtoM, Archivematica, a national-archive platform, or anything custom, we've written up what it would take: there's a Discussion open at **[github.com/openric/spec/discussions/2](https://github.com/openric/spec/discussions/2)**.
> - **Mapping sanity-check.** Where does ISAD(G) → RiC-O feel wrong? Discussion open at **[github.com/openric/spec/discussions/3](https://github.com/openric/spec/discussions/3)**.
>
> **Links**
>
> - Spec: **[openric.org](https://openric.org)**
> - Repository: **[github.com/openric/spec](https://github.com/openric/spec)**
> - Live demo: **[viewer.openric.org](https://viewer.openric.org)**
> - Viewer on npm: **[@openric/viewer](https://www.npmjs.com/package/@openric/viewer)**
> - Discussions: **[github.com/openric/spec/discussions](https://github.com/openric/spec/discussions)**
>
> The spec is **CC-BY 4.0**, the reference implementation and viewer are **AGPL-3.0**. No product; just a contract anyone can implement.
>
> Thanks,
> Johan Pieterse
> The Archive and Heritage Group / Plain Sailing Information Systems
> johan@theahg.co.za

---

## Suggested mailing lists and venues

| Venue | Why | How |
|---|---|---|
| **AtoM users list** — `ica-atom-users@googlegroups.com` (Google Group) | Closest sibling community; many are the exact readership for "a second implementation" ask | Post via Google Groups web UI |
| **Archivematica users list** — `archivematica@googlegroups.com` | Preservation crowd; will care about the OAIS/PREMIS-adjacent vocabulary once OpenRiC-Preservation comes online | Same |
| **ICA Expert Group on Archival Description (EGAD)** contacts | They authored RiC-CM; our mapping work is directly downstream | Email directly to known EGAD members; see ICA site for roster |
| **code4lib list** — `CODE4LIB@listserv.nd.edu` | Cultural-heritage developers, the crowd likely to prototype a second impl | Subscribe + post |
| **SAA Issues & Advocacy / Electronic Records Section** | North American archivists | SAA microsite contacts |
| **DLF Forum participant lists** | Digital-library crowd | via DLF contacts |
| **Fediverse** — `@archivists@a.gup.pe` group on Mastodon | Broad reach among archivists | Short version (below) with main link |
| **Bluesky** — #archives #linkedopendata | Growing archival community there | Same |
| **LinkedIn** — "ICA Expert Group" + "Archives and Records" groups | Professional audience, more likely to click-through to spec | Reshare |

---

## Short version (Mastodon / Bluesky / LinkedIn)

> **OpenRiC 0.1.0 is out** — an open spec for serving ICA's Records in Contexts over HTTP, plus a reference implementation (Heratio) and a standalone 2D/3D viewer (`@openric/viewer` on npm).
>
> Four short docs, 12 JSON Schemas, a 20-case conformance fixture pack, a validator CLI, and a live demo at [viewer.openric.org](https://viewer.openric.org). Spec is CC-BY; implementation AGPL. Looking for reviewers and a second implementation.
>
> 🔗 [openric.org](https://openric.org) · [github.com/openric/spec](https://github.com/openric/spec)
>
> #archives #linkedopendata #RiC

---

## Before sending — pre-flight checklist

- [ ] `viewer.openric.org` cert green + page loads cleanly with both backends selectable
- [ ] `openric.org` landing page shows the `@openric/viewer` row in the status table
- [ ] `@openric/viewer@0.1.0` visible on npm (not unpublished) — <https://www.npmjs.com/package/@openric/viewer>
- [ ] GitHub Discussions #1/#2/#3 are pinned (at least #1)
- [ ] Kill-switch: commit fresh on openric-spec or viewer if any typo is found during review
