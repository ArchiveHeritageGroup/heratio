# Response notes — Richard's feedback on OpenRiC

**From:** Richard
**Date received:** 2026-04-17
**Context:** Reply to Johan's note introducing the OpenRiC initiative and Heratio's RiC view.

---

## What Richard is actually proposing

Not criticism. He is suggesting we reframe **OpenRiC** from *"a RiC layer for Heratio"* into *"a specification for going from archival description → visualisable RiC, plus an abstract viewing API"* — with Heratio as one reference implementation.

His **IIIF analogy** is the tell:

> IIIF succeeded because the spec (Image API, Presentation API) was decoupled from any viewer. Mirador, Universal Viewer, OpenSeadragon all compete on implementation; the spec is the neutral ground.

He wants the same shape for RiC.

---

## Why this matters strategically

- **EGAD signal.** Richard speaking "for myself, not EGAD" is diplomatic, but he is still the channel through which institutional acceptance would flow. Engaging on his terms = legitimacy.
- **Ceiling.** A product-tied standard has a ceiling. A neutral spec could be adopted by AtoM-next, Archivematica, national archive platforms — and Heratio remains the best implementation.
- **Conflict-of-interest objection.** Removes the commercial COI that would otherwise dog an AHG-owned standard.

---

## Honest answers to his three questions

| # | Question | Answer |
|---|---|---|
| 1 | OpenRiC as independent software layer combinable with Heratio? | **Yes.** |
| 2 | Currently just automated AtoM → RiC translation, no GUI for adding RiC directly? | **Correct.** Be straight about this. |
| 3 | Current state is temporary — intending direct RiC editing, RiC-primary view eventually? | **Yes.** Per the existing Heratio + RiC roadmap: dual-view, triple persistence, 6 phases, moving toward RiC-primary. |

---

## What to propose back

A concrete next step, not just agreement.

**Split the initiative into two artifacts:**

1. **`openric-spec`** — the abstract model
   - Node, edge, 2D / 3D graph primitives
   - AtoM → RiC mapping rules
   - Viewing-API abstractions (analogous to IIIF Presentation API)
   - Implementation-neutral

2. **`heratio-openric`** — the reference implementation
   - Lives inside Heratio
   - First to implement the spec
   - Competes on UX, not on owning the standard

**Invite Richard (and EGAD-adjacent reviewers) to review the spec draft.** Turns his polite suggestion into a collaboration.

---

## Tone for the reply

Match his — warm, not defensive.

He explicitly said *"don't let my ramblings get in the way"*; the worst response is to treat it as a threat to the initiative. The best is to say:

> **"Yes, and here's the first concrete step toward that."**

---

## Summary

Richard is handing us a gift: a path from *product feature* to *industry standard*, modeled on the most successful archival/cultural-heritage interop spec of the last fifteen years (IIIF). The right response is to accept it concretely — propose the spec/implementation split, and invite him in.
