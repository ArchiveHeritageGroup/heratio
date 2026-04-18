# OpenRiC announcement — send when ready

**Status:** Ready to send. All four public surfaces live as of 2026-04-18.
**Targets:** AtoM users list, Archivematica users list, any EGAD/RiC-O contacts, ICA-adjacent Mastodon/Bluesky/LinkedIn.
**Author:** Johan Pieterse

### Pre-flight — all green
- [x] openric.org serves v0.1.0 spec + status table
- [x] viewer.openric.org — TLS live, help panel + hover tooltip, Heratio + static-fixture backends both render
- [x] capture.openric.org — TLS live, pure-browser client against any OpenRiC server
- [x] ric.theahg.co.za/api/ric/v1 — reference API service, `ric:verify-split` 15/15
- [x] heratio.theahg.co.za now consumes the API as an external client (split is real, not theatre)
- [x] Three seed Discussions at github.com/openric/spec/discussions + a v0.1.1 progress-update thread
- [ ] Pin Discussion #1 (trivial — one UI click)

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
> We've released **OpenRiC** — an open, implementation-neutral specification for serving ICA's *Records in Contexts* model (RiC-CM / RiC-O) over HTTP, plus the tools around it.
>
> ### What's in it
>
> - **Spec** — [openric.org](https://openric.org) — four documents (mapping, viewing API, graph primitives, conformance), 12 JSON Schemas, SHACL shapes, a 20-case fixture pack, and an `openric-validate` CLI. Version 0.1.0 frozen, CC-BY 4.0.
> - **Graph viewer** — [`@openric/viewer`](https://www.npmjs.com/package/@openric/viewer) on npm — a standalone 2D/3D graph client for any OpenRiC-conformant server. Demo at [viewer.openric.org](https://viewer.openric.org) driving two independent backends to prove the decoupling (a real Heratio server *and* an in-browser static fixture replay).
> - **Capture client** — [capture.openric.org](https://capture.openric.org) — a pure-browser data-entry tool. Paste a server URL and an API key; create Places, Rules, Activities, Instantiations, and relations against any OpenRiC-conformant backend. No dependency on the reference implementation.
> - **Reference API** — [ric.theahg.co.za/api/ric/v1/health](https://ric.theahg.co.za/api/ric/v1/health) — a live, public endpoint serving a real archival database, backed by [Heratio](https://github.com/ArchiveHeritageGroup/heratio) (open-source GLAM platform).
>
> ### Why this is noteworthy
>
> Heratio — the reference implementation — is itself now a *consumer* of the public API. Every mutating admin action in Heratio goes out to `ric.theahg.co.za/api/ric/v1/*` with a bearer key, same surface as any external client. Which means the API isn't a "nice-to-have alongside an internal model"; it **is** the model. There's no privileged shortcut.
>
> That matters for the spec's credibility: if the reference implementation is a pure consumer, a second implementation becomes a comparably-shaped client-of-the-same-contract, not a stranger trying to match a hidden internal shape.
>
> ### What we'd love from here
>
> - **Spec feedback.** It's v0.1 draft — open for revision. Issues + Discussions at [github.com/openric/spec](https://github.com/openric/spec).
> - **A second implementation.** AtoM, Archivematica, a national-archive stack, a custom system — if you've thought about emitting RiC-O over HTTP, [Discussion #2](https://github.com/openric/spec/discussions/2) asks the detailed questions.
> - **Mapping disagreements.** Where does ISAD(G) → RiC-O feel wrong? [Discussion #3](https://github.com/openric/spec/discussions/3).
>
> ### Links
>
> - Spec: [openric.org](https://openric.org) · [github.com/openric/spec](https://github.com/openric/spec)
> - Viewer: [viewer.openric.org](https://viewer.openric.org) · [npm](https://www.npmjs.com/package/@openric/viewer) · [github.com/openric/viewer](https://github.com/openric/viewer)
> - Capture: [capture.openric.org](https://capture.openric.org) · [github.com/openric/capture](https://github.com/openric/capture)
> - Reference implementation (operational GLAM platform): [heratio.theahg.co.za](https://heratio.theahg.co.za) · [github.com/ArchiveHeritageGroup/heratio](https://github.com/ArchiveHeritageGroup/heratio)
> - Discussions: [github.com/openric/spec/discussions](https://github.com/openric/spec/discussions)
>
> Spec is **CC-BY 4.0**, the implementation code is **AGPL-3.0**. No product; a contract anyone can implement.
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

> **OpenRiC is out** — open spec for serving ICA's Records in Contexts over HTTP, plus:
>
> 🗺 viewer — [viewer.openric.org](https://viewer.openric.org) — 2D+3D graph for any OpenRiC server
> ✍ capture — [capture.openric.org](https://capture.openric.org) — pure-browser RiC data entry
> 🔌 reference API — [ric.theahg.co.za/api/ric/v1](https://ric.theahg.co.za/api/ric/v1/health) — live, real archival data
>
> Spec CC-BY, code AGPL. The reference implementation (Heratio) consumes its own public API — no privileged shortcut.
>
> 🔗 [openric.org](https://openric.org) · [github.com/openric/spec](https://github.com/openric/spec/discussions)
>
> #archives #linkedopendata #RiC #RDF

---

## Before sending — pre-flight checklist

- [ ] `viewer.openric.org` cert green + page loads cleanly with both backends selectable
- [ ] `openric.org` landing page shows the `@openric/viewer` row in the status table
- [ ] `@openric/viewer@0.1.0` visible on npm (not unpublished) — <https://www.npmjs.com/package/@openric/viewer>
- [ ] GitHub Discussions #1/#2/#3 are pinned (at least #1)
- [ ] Kill-switch: commit fresh on openric-spec or viewer if any typo is found during review
