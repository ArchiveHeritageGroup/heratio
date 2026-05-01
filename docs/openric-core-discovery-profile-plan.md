# OpenRiC Core Discovery Profile - implementation plan

**Status:** Draft 2026-04-19. Not yet ratified. For planning only.
**Target spec version:** OpenRiC v0.3.0
**Repo affected:** `openric-spec` (primary), `heratio/packages/ahg-ric` (reference impl update)
**Est. effort:** 2–3 focused sessions to first-draft; 1 session for reference-impl claim update.

---

## Why profiles

Today OpenRiC is a single monolithic conformance target. A server either implements "OpenRiC" (all ~40 endpoints, all entity types, all write verbs) or it doesn't. That is a high bar, and institutional reviewers keep asking the same question in different words: *"what is the smallest thing I can say my system does, and still call itself OpenRiC-compliant?"*

Named profiles answer that. Each profile is a bounded conformance target with a defined endpoint set, field set, and SHACL shape set. A server declares which profile(s) it supports; consumers know exactly what to expect. This is the pattern IIIF Presentation API used (Level 0/1/2) that turned IIIF from "an aspirational ideal" into "a thing 800 institutions actually run."

The six profiles sketched in the strategic roadmap (`docs/openric_future_direction_and_phased_roadmap.md` §2):

1. **Core Discovery** - read-only Records, Agents, Repositories, vocabulary, autocomplete
2. **Authority & Context** - Places, Rules, Activities as first-class
3. **Provenance & Event** - Activity subclasses with the full event model
4. **Digital Object Linkage** - Instantiations with checksum, MIME, IIIF pointers
5. **Export-Only** - OAI-PMH + one-shot JSON-LD dumps
6. **Round-Trip Editing** - full write surface with provenance-aware rules

**This plan is for #1 only.** Other profiles follow after Core Discovery has shipped, been implemented by the reference, and been reviewed.

---

## Scope - what Core Discovery is and isn't

### IN scope (must implement to claim conformance)

| Endpoint | Purpose | Notes |
|---|---|---|
| `GET /` | Service description | Must include `openric_conformance.profiles` array |
| `GET /health` | Liveness | Returns `{"status":"ok"}` |
| `GET /vocabulary` | rico:* term catalog | Used by clients to render type labels |
| `GET /records` | List records, search by `q` | Pagination required |
| `GET /records/{slug}` | Single record | Slug- or id-addressable |
| `GET /agents` | List agents | Pagination required |
| `GET /agents/{slug}` | Single agent | Must return at minimum `@id`, `@type`, a name |
| `GET /repositories` | List repositories | |
| `GET /repositories/{slug}` | Single repository | |
| `GET /autocomplete?q=&types=` | Cross-entity prefix search | Supports `types=record,agent,repository` filter |

### OUT of scope for this profile (belong in other profiles)

- `/graph`, `/relations`, `/relations-for`, `/hierarchy` → Graph Traversal profile (later)
- `/activities`, `/places`, `/rules`, `/functions`, `/instantiations` → Authority/Context, Provenance, Digital Object profiles
- `/oai` → Export-Only profile
- `/sparql` → (stays experimental; not in any profile yet)
- `/validate` → Conformance profile (may be admin-side only, not implementer-facing)
- All `POST`/`PATCH`/`DELETE` → Round-Trip Editing profile

### Minimum-field requirement per entity

A Core Discovery response MUST include the fields marked *required*; MAY include *optional*; MUST NOT include fields from other profiles without also claiming those profiles.

**rico:Record (single):**
- Required: `@id`, `@type`, `rico:title` (or `rico:hasName`), `rico:identifier`
- Optional: `rico:description`, `rico:hasBeginningDate`, `rico:hasEndDate`, `rico:heldBy` (embedded), `rico:hasCreator` (embedded)
- Forbidden without Graph profile: `rico:isOrWasSubjectOf`, `rico:hasOrHadSubject`, full traversable edges

**rico:Agent (single):**
- Required: `@id`, `@type` (`rico:Person|CorporateBody|Family|Agent`), `rico:name` or `rico:hasAgentName → AgentName`
- Optional: `rico:history`, `rico:hasBeginningDate`, `rico:hasEndDate`

**rico:CorporateBody (Repository):**
- Required: `@id`, `@type`, `rico:name`
- Optional: `rico:history`, a single contact-point object

**List responses:**
- Envelope: `{"total": N, "limit": L, "offset": O, "items": [...]}`
- Each item: minimum = `@id`, `@type`, a name/title
- `Link` header for pagination (RFC 5988) - `rel="next"`, `rel="prev"`

### Required HTTP behaviours

- Content-negotiation: `Accept: application/ld+json` → JSON-LD; `Accept: application/json` → same content, trimmed `@context` allowed
- CORS: `Access-Control-Allow-Origin: *` on all GETs (reads are public)
- Rate limiting: optional, but if applied must return RFC 6585 `429` + `Retry-After`
- Error envelope: `{"success":false, "error":"...", "message":"...", "timestamp":"..."}`
- `Vary: Accept` on all responses that content-negotiate

---

## Conformance declaration

Servers declare profile support at `GET /`:

```json
{
  "name": "Example Archive Catalogue",
  "version": "1.0",
  "openric_conformance": {
    "spec_version": "0.3.0",
    "profiles": [
      {
        "id": "core-discovery",
        "version": "0.3.0",
        "conformance_level": "full"
      }
    ]
  }
}
```

Profile versions track the spec version that introduced the profile (if Core Discovery v0.3 is unchanged in spec v0.4, the profile stays v0.3; when changed, it bumps to match). `conformance_level` is `"full"` or `"partial"` - partial requires a `"notes"` field explaining deviations.

---

## Deliverables

### A. Spec documents (openric-spec repo)

| File | Purpose | Status |
|---|---|---|
| `spec/profiles/index.md` | Profile concept: what they are, how they compose, how versioning works | New |
| `spec/profiles/core-discovery.md` | Normative Core Discovery definition - tables above, in spec prose | New |
| `spec/conformance.md` | Updated to reference profile-based conformance claims | Edit |
| `spec/viewing-api.md` | Tag every endpoint with its profile membership | Edit |

### B. Machine-readable artefacts

| File | Purpose | Status |
|---|---|---|
| `openapi/openric.yaml` | Add `tags: [core-discovery]` to operations; add `profiles` field to service-description schema | Edit |
| `schemas/service-description.schema.json` | Add `openric_conformance.profiles` array shape | Edit |
| `shapes/profiles/core-discovery.shacl.ttl` | SHACL subset - only rules that apply to Core Discovery responses | New |
| `fixtures/profiles/core-discovery/manifest.json` | Lists which of the 27 existing fixtures belong to Core Discovery (probably: fonds-minimal, fonds-with-children, agent-person-simple, agent-corporate-body, agent-family, record-list, record-multilingual, service-description, vocabulary, autocomplete-egypt, error-not-found - 11 fixtures) | New |

### C. Conformance probe

| File | Purpose | Status |
|---|---|---|
| `conformance/probe.sh` | Add `--profile core-discovery` flag: runs only the Core Discovery subset of tests, produces a pass/fail badge-friendly JSON | Edit |
| `conformance/badge/core-discovery.svg` | Generated badge (template + generator script) | New |

### D. Reference implementation (heratio `packages/ahg-ric`)

| File | Purpose | Status |
|---|---|---|
| `src/Http/Controllers/LinkedDataApiController.php` → `serviceDescription()` | Add `openric_conformance.profiles` to GET / response | Edit |
| `src/Support/OpenApiSpec.php` | Tag operations with profile membership | Edit |

### E. Site (openric-spec repo)

| File | Purpose | Status |
|---|---|---|
| `index.md` | Add a "Profiles" surface-card | Edit |
| `profiles.md` | New index page listing all profiles with status (defined/planned) and linking to each | New |
| `_layouts/default.html` | Add "Profiles" to nav | Edit |

---

## Implementation order

Each numbered step is a natural session boundary.

### Session 1 - spec prose (2–3 hours)

1. Draft `spec/profiles/index.md` - concept, versioning, composition rules, declaration format
2. Draft `spec/profiles/core-discovery.md` - the normative tables above in spec prose
3. Draft `profiles.md` site index + nav update + homepage card
4. Open questions flagged inline as `<!-- TK: ... -->` comments for later decision
5. **Deliverable:** a PR-ready spec draft the user can read, edit, and hand to Richard for review

### Session 2 - machine artefacts (2 hours)

1. SHACL subset at `shapes/profiles/core-discovery.shacl.ttl` - extract only the shapes that apply to Record/Agent/Repository
2. Update `schemas/service-description.schema.json` to include profiles array
3. Add OpenAPI `tags: [core-discovery]` to the 10 in-scope operations
4. Build fixture manifest at `fixtures/profiles/core-discovery/manifest.json`
5. **Deliverable:** every machine-readable artefact knows which profile it belongs to

### Session 3 - probe + reference impl (2 hours)

1. Add `--profile core-discovery` flag to `conformance/probe.sh`
2. Generate a pass/fail JSON output the badge generator can consume
3. Build `conformance/badge/core-discovery.svg` generator (shields.io-compatible static SVG)
4. Update Heratio `LinkedDataApiController::serviceDescription()` to emit the profiles declaration
5. Run the probe against the reference - should pass with `conformance_level: full`
6. **Deliverable:** the reference implementation concretely claims and passes the profile

### Optional Session 4 - polish (1 hour)

1. Add a "Profiles" section to the proof page
2. Add a badge to the reference API's homepage + README
3. Cross-link from guides/getting-started.md

---

## Open questions

These must be answered before Session 1 can ship. Good candidates for the Richard reply.

| # | Question | My current lean | Why it matters |
|---|---|---|---|
| Q1 | Profile name - "Core Discovery" vs "Discovery" vs "Read" vs "Browse" | Core Discovery | Readable, signals minimum-viable |
| Q2 | Levels (numbered, strict superset) vs profiles (named, orthogonal axes) | Profiles | Our six profiles aren't a linear progression - Export-Only is not a subset of Round-Trip Editing |
| Q3 | Include `/autocomplete` in Core Discovery? | Yes | Without it, "discovery" is only addressable navigation, not search |
| Q4 | Include repositories in Core Discovery, or push to Authority profile? | Yes, include | Repositories are so entangled with records (every record has `rico:heldBy`) that separating them causes false negatives |
| Q5 | Mandatory pagination limit in response envelope? | Yes - list responses MUST paginate | Prevents unbounded responses; gives clients a stable contract |
| Q6 | Error shape - keep Heratio's current `success:false` envelope or switch to RFC 7807 `application/problem+json`? | Switch to RFC 7807 for spec, keep existing shape as "additionally allowed" | RFC 7807 is the HTTP-API standard; `success:false` is a custom Heratio-ism |
| Q7 | SHACL strictness - closed shapes (fail on unknown predicates) or open? | Open | Implementations must be able to add private metadata without being flagged non-conformant |
| Q8 | Version alignment - Core Discovery v0.3.0 releases with spec v0.3.0, or profiles have independent versions? | Track spec | Simpler; avoids N-dimensional version matrix |

---

## Success criteria

Core Discovery Profile v0.3.0 is done when:

1. ✅ Any server that implements the 10 endpoints above with the minimum-field requirements passes `probe.sh --profile core-discovery`
2. ✅ The reference API (`ric.theahg.co.za`) passes at `conformance_level: full`
3. ✅ The spec prose is published at `openric.org/spec/profiles/core-discovery`
4. ✅ The profile is listed on the Proof page, the homepage, and the nav
5. ✅ Richard (or another reviewer in his spirit) has read the spec draft and pushed back on at least one decision
6. ✅ One consumer besides Heratio can drive against a Core-Discovery-only server (this can be the upcoming `@openric/viewer` npm package - the viewer's read path is Core Discovery + optional Graph)

---

## What this plan deliberately does NOT cover

- Other five profiles (Authority, Provenance, Digital Object, Export, Round-Trip) - each gets its own plan after Core Discovery ships
- Profile composition rules in depth (how Authority *extends* Core Discovery, what happens when a server claims both) - deferred to the second-profile session
- Badges beyond SVG (shields.io integration, README copy-paste snippets, a badge browser on openric.org) - polish, not core
- A governance process for changing profiles (who decides a breaking profile change, RFC format, etc.) - the governance doc exists; profile-specific governance rules can wait until two profiles exist

---

## Risk register

| Risk | Probability | Mitigation |
|---|---|---|
| Profile boundaries feel arbitrary in review | Medium | Keep minimum field sets genuinely minimal; document every inclusion with a one-line rationale |
| Reference impl needs breaking changes to declare conformance (e.g. error shape) | Low | Heratio already returns the right shape for 9 of 10 endpoints; the one edit is additive |
| Spec v0.3.0 release churns clients built against v0.2.0 | Low | v0.3.0 is additive (new `profiles` field in service description); no existing field changes shape |
| Core Discovery scope creeps mid-session | Medium | Q1–Q8 above are the scope-fixing decisions; answer them once, don't reopen |
| Profile concept confuses more than it helps | Low | Fallback: document profiles but don't require declaration for v0.3.0; make them aspirational for one release |
