# OpenRiC - decoupling plan

**Goal:** Address Richard's feedback by turning OpenRiC from a Heratio feature into an implementation-neutral specification, with Heratio as the reference implementation.

**Model:** IIIF. The spec is the neutral ground. Multiple viewers/servers compete on implementation, not on owning the standard.

---

> **📍 Progress note (2026-04-17):** This document is a frozen strategic record. For live tracking of what's done vs outstanding, see [`ric-status-and-plan.md`](ric-status-and-plan.md).
>
> **Phase 0** (repository split) - done. `openric-spec` repo is live at [github.com/openric/spec](https://github.com/openric/spec); site live at [openric.org](https://openric.org).
> **Phase 1** (core spec artifacts) - **partial**. Four draft documents (mapping, viewing API, graph primitives, conformance) are published at openric.org. JSON Schemas, fixture pack, and `openric-validate` CLI are specified in the conformance doc but not yet built.
> **Phases 2–4** - not started. Tracked in `ric-status-and-plan.md` §3.

---

## The split

| Today | After |
|---|---|
| OpenRiC = code inside Heratio | **OpenRiC Spec** = implementation-neutral documents + schemas + test suite |
| | **Heratio-OpenRiC** = reference implementation (server + viewer) |
| One repo | Two repos: `openric/spec` + `heratio` (implementation lives here) |
| AHG-owned feature | Open governance, spec editors from multiple institutions |

---

## Phase 0 - Repository split (1 day)

1. Create `github.com/openric/spec` (done 2026-04-18 - transferred out of `ArchiveHeritageGroup` to the neutral `openric` org).
2. Move the *conceptual* content out of Heratio:
   - Mapping rules (AtoM/ISAD(G)/ISAAR(CPF)/ISDIAH → RiC-CM/RiC-O)
   - Graph abstractions (node/edge/cluster/drill-down)
   - Viewing-API contract
3. Heratio retains only the *implementation* of those concepts.
4. Cross-link both repos. README on each makes the relationship obvious.

---

## Phase 1 - Core spec artifacts (2–3 weeks)

The four documents that make OpenRiC usable by anyone, not just us.

### 1.1 `openric-mapping-spec.md`
How to map source-archival-description schemas to RiC-CM / RiC-O.

- AtoM / ISAD(G) → RiC (Record, RecordSet, RecordPart)
- ISAAR(CPF) / Actor → RiC (Agent, Person, CorporateBody, Family)
- ISDIAH / Repository → RiC (Place, Agent-CorporateBody)
- Relationships → RiC Relations with typed predicates
- Every mapping rule has a **canonical example** (input JSON-ish → output RiC triples)

### 1.2 `openric-viewing-api.md`
REST/JSON-LD contract - analogous to IIIF Presentation API.

```
GET /openric/v1/description/{id}         → RiC entity + first-order neighbours
GET /openric/v1/description/{id}/graph   → subgraph for 2D/3D viewer
GET /openric/v1/description/{id}/drill?depth=N
GET /openric/v1/search?q=...&type=...
```

Response shape: JSON-LD with RiC-O vocabulary, plus OpenRiC-specific viewing hints (node clustering, layout suggestions, edge bundling).

### 1.3 `openric-graph-primitives.md`
Abstract viewing model. Defines terms like:

- **Node** - a RiC entity projected for display
- **Edge** - a typed RiC relation
- **Cluster** - a grouping strategy (by type, by fonds, by date)
- **Drill** - a navigation operation that expands/contracts subgraphs
- **Layout hint** - non-binding suggestion a server can attach to guide viewers

Viewers are free to render 2D, 3D, timeline, matrix - the spec just says what data they'll receive.

### 1.4 `openric-conformance.md` + test suite
- JSON Schema files for every endpoint response.
- A CLI validator: `openric-validate https://myserver/openric/v1/description/abc123`.
- A fixture pack: example inputs + expected OpenRiC outputs, so implementers can self-check.

---

## Phase 2 - Heratio as reference implementation (1–2 weeks after Phase 1)

1. Refactor Heratio's existing RiC code to serve the spec'd endpoints unchanged:
   `GET /openric/v1/description/{id}` etc.
2. Pass the conformance test suite. Badge the README: *"OpenRiC 1.0 reference implementation, 100% conformance."*
3. Publish the 2D/3D viewer JS as a separate npm package (`@openric/viewer`) that talks to any OpenRiC server - not just Heratio.
4. Demo page: point `@openric/viewer` at an AtoM instance (via a thin adapter) to prove the spec works across systems.

---

## Phase 3 - Governance & legitimacy (ongoing)

1. **RFC process.** Spec changes go through public RFCs with a 2-week comment window.
2. **Spec editors group.** Invite Richard, 1–2 EGAD-adjacent reviewers, 1 AtoM community member, 1 Archivematica community member. Not a formal committee yet - an editors' circle.
3. **Versioning.** SemVer on the spec. `openric-1.0.0` frozen after first external implementation passes conformance.
4. **Licence.** Spec under CC-BY 4.0. Reference implementation under AGPL-3.0 (already Heratio's licence). Separation of spec-licence from code-licence matters - IIIF uses CC-BY for the spec for exactly this reason.

---

## Phase 4 - Validation (3–6 months out)

Spec is real when someone else implements it.

- **Target 1:** an AtoM plugin or fork that exposes `/openric/v1/...` from AtoM's data. Proves the mapping spec is tight enough.
- **Target 2:** a second viewer (not ours). Even a simple D3 one-page demo by a student counts.
- **Target 3:** a national archive or university archive runs a pilot.

Each of these is a line in the README. Three external implementations = legitimacy.

---

## What we tell Richard

The reply should offer, concretely:

1. **We accept the reframing.** OpenRiC becomes a spec, Heratio becomes a reference implementation.
2. **Timeline.** Phase 0 + Phase 1 drafts within ~4 weeks.
3. **Invitation.** Would he review the mapping spec and viewing API drafts when ready? And suggest 1–2 others to include in the editors' circle?
4. **Non-commitment.** We don't ask him to speak for EGAD. Personal review only.

This turns his polite suggestion into a commitment without putting him in an awkward position.

---

## What it costs us

- ~4 weeks of extracting-and-documenting work that was always going to be needed if we wanted the RiC view to be maintainable.
- Loss of "Heratio owns the RiC standard" as a marketing line.

## What we gain

- "Heratio is the reference implementation of OpenRiC" - a stronger line, because it's defensible.
- A path to EGAD adoption that doesn't require AHG to be the gatekeeper.
- Other systems implementing OpenRiC becomes a moat for Heratio (first, best, most-complete implementation).
- Removes the commercial-COI objection that would otherwise block institutional uptake.

---

## Success criteria

- [ ] `openric/spec` repo exists with the 4 core documents
- [ ] Heratio passes its own conformance suite
- [ ] `@openric/viewer` published and usable against a non-Heratio backend
- [ ] At least one external implementation (even a prototype)
- [ ] Richard + one other external reviewer listed as spec editors or acknowledged reviewers
- [ ] `openric-1.0.0` tagged and frozen
