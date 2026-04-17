# RiC in Heratio — status and plan

**Last updated:** 2026-04-17
**Author:** Johan Pieterse / Plain Sailing Information Systems
**Scope:** Current state of Records in Contexts (RiC) inside Heratio, its relationship to the external OpenRiC specification, and the ordered work list to close known gaps.

This document is the canonical answer to "where are we with RiC?". It supersedes ad-hoc memory and replaces day-to-day status notes. Update it as work lands, not in separate status files.

---

## 1. Executive summary

- **RiC is a first-class feature in Heratio.** Every archival description, actor, and repository is translated into RiC-CM/RiC-O, served through a REST + JSON-LD API, and visualised through 2D and 3D graph viewers.
- **Four RiC-native entity types exist and are populated.** `ric_place` (182), `ric_rule` (2), `ric_activity` (233), `ric_instantiation` (1,280). Relation metadata covers 681 predicate links.
- **The spec behind it is now public.** [openric.org](https://openric.org) publishes the four draft documents (mapping, viewing API, graph primitives, conformance) that describe the contract Heratio implements.
- **Heratio is the reference implementation of OpenRiC.** Wherever Heratio and the spec diverge, either Heratio catches up or the spec is corrected — those decisions happen case-by-case, captured here.

---

## 2. Current state (verified 2026-04-17)

### 2.1 Database layer

| Table | Purpose | Rows |
|---|---|---|
| `ric_place` + `ric_place_i18n` | First-class Place entities | 182 |
| `ric_rule` + `ric_rule_i18n` | Mandates / rules | 2 |
| `ric_activity` + `ric_activity_i18n` | Activity entities | 233 |
| `ric_instantiation` + `ric_instantiation_i18n` | Digital/physical manifestations | 1,280 |
| `ric_relation_meta` | Predicate metadata on relations | 681 |

Plus the seven `ahg_dropdown` taxonomies in section `ric`: `ric_entity_type`, `ric_place_type`, `ric_rule_type`, `ric_activity_type`, `ric_carrier_type`, `ric_relation_category`, `ric_relation_type` (72 items total).

The legacy `information_object`, `actor`, `repository`, and `function` tables remain the operational store; RiC-native entities sit alongside, not instead.

### 2.2 Service layer (`packages/ahg-ric/src/Services/`)

| Service | Status | Notes |
|---|---|---|
| `RicSerializationService` | Partial | Serializes Record, Agent, Repository, Function, RecordSet export. References `ric_place`, `ric_instantiation`, `ric_activity` in joins but has no dedicated `serializePlace` / `serializeRule` / `serializeActivity` / `serializeInstantiation` methods. |
| `RicEntityService` | Complete | CRUD across all four new entity types + relations. |
| `RelationshipService` | Partial | Walks the triple store via SPARQL but does not consult `ric_relation_meta` to emit canonical RiC predicates — returns generic labels instead. |
| `ShaclValidationService` | Partial | Shapes for Record/Agent/Repository exist in `tools/ric_shacl_shapes.ttl`. No shapes yet for Place/Rule/Activity/Instantiation. |
| `SparqlQueryService` | Complete | Fuseki passthrough. |

### 2.3 API layer (`packages/ahg-ric/src/Http/Controllers/LinkedDataApiController.php`)

Routes published under `/api/ric/v1/`:

- ✓ `GET /agents`, `/agents/{slug}`
- ✓ `GET /records`, `/records/{slug}`, `/records/{slug}/export`
- ✓ `GET /functions`, `/functions/{id}`
- ✓ `GET /repositories`, `/repositories/{slug}`
- ✓ `GET /sparql`, `/graph`, `/vocabulary`, `/health`, `/openapi.json`
- ✓ `POST /validate`

Rate limit: 60/min. Content type: `application/ld+json`.

**Missing** (per OpenRiC Viewing API §4):
- `GET /places`, `/places/{id}`
- `GET /rules`, `/rules/{id}`
- `GET /activities`, `/activities/{id}`
- `GET /instantiations`, `/instantiations/{id}`
- `GET /` service-description endpoint with OpenRiC conformance advertisement

### 2.4 UI layer

Browse + show + edit views for the four new entity types live at:
- `/admin/ric/entities/places`
- `/admin/ric/entities/rules`
- `/admin/ric/entities/activities`
- `/admin/ric/entities/instantiations`

Embedded RiC partials (`_ric-view-*.blade.php`) render on the IO, Actor, Repository, Donor, Rights-holder, Accession, Storage, Function, and Term show pages.

2D force-directed and 3D WebGL graph viewers render subgraphs with BFS drill-down, consuming `buildGraphFromDatabase` (with Fuseki fallback via `buildGraphData`).

### 2.5 OpenRiC coordination

The [openric.org](https://openric.org) site is live with four draft documents (mapping, viewing API, graph primitives, conformance), derived from this codebase. Key points:

- Spec licence: CC-BY 4.0. Reference implementation (this repo): AGPL-3.0.
- The spec's **mapping tables, endpoint catalogue, node/edge shape, and SHACL shape set** are all extracted from this codebase and are expected to round-trip — changes here should be reflected in the spec, and vice versa.
- Heratio will claim **L3 conformance** when the items in §3 land. L4 requires the fixture pack (§3.6) and round-trip preservation tests.

---

## 3. What to start next — ordered by value

Each item is scoped small enough to ship as a single release. Items earlier in the list unblock items later.

### 3.1 Emit the four new entity types as proper RiC-O JSON-LD — **START HERE**

**Why first:** The entity data exists (1,697 rows across the four new tables), and the browse/edit UI works, but the serialization layer does not yet produce clean JSON-LD for Place, Rule, Activity, or Instantiation. Until this is done:
- `/api/ric/v1/places/{id}` and siblings cannot exist.
- The OpenRiC mapping spec claims more than Heratio currently emits.
- Subgraph responses referencing these nodes inherit the gap.

**Work:**
- Add `serializePlace(int $placeId): array` using the Place property table from `spec/mapping.md` §7 (name, dates, coordinates, place type, hierarchy).
- Add `serializeRule(int $ruleId)` per ISDF-style mandate/rule (name, type, dates, description, applies-to).
- Add `serializeActivity(int $activityId)` mapping to `rico:Production` / `rico:Accumulation` / `rico:Activity` per the event-type table.
- Add `serializeInstantiation(int $instantiationId)` with `rico:identifier`, `rico:mimeType`, `rico:carrier`, `rico:hasExtent`.

**Acceptance:** each emits JSON-LD that validates against its SHACL shape (see §3.3).

### 3.2 API endpoints for the four new entity types

**Why next:** unblocks OpenRiC L2 conformance.

**Work:** Extend `LinkedDataApiController` with `listPlaces`, `showPlace`, `listRules`, `showRule`, `listActivities`, `showActivity`, `listInstantiations`, `showInstantiation`. Register under `/api/ric/v1/`. Pagination + filter parameters per Viewing API §4.

**Acceptance:** curl each list and show endpoint; responses validate against the JSON Schemas (see §3.5).

### 3.3 SHACL shapes for the new entity types

**Why:** Required by OpenRiC L1 conformance claim.

**Work:** Add `:PlaceShape`, `:RuleShape`, `:ActivityShape`, `:InstantiationShape` to `packages/ahg-ric/tools/ric_shacl_shapes.ttl` and mirror into `openric-spec/shapes/openric.shacl.ttl`.

**Acceptance:** `pyshacl -s shapes.ttl -d <serialized entity>.jsonld` reports conformance for each.

### 3.4 Relation predicate alignment — use `ric_relation_meta`

**Why:** `ric_relation_meta` holds canonical RiC predicate metadata (predicate, inverse, category, domain, range, symmetric flag) for 681 relation links. `RelationshipService` currently emits generic labels instead of these predicates. Fixing this closes the loop between data and spec.

**Work:** When emitting edges in `/graph` responses and embedded subgraphs, look up `ric_relation_meta` for the specific `relation.id` and emit `edge.predicate = rico:<predicate>` rather than free-text `edge.label`.

**Acceptance:** a subgraph response for a known actor-with-subordinate-body case shows `rico:hasOrHadSubordinate` as the predicate, not `"related"`.

### 3.5 JSON Schemas for every endpoint response

**Why:** OpenRiC conformance §3.2.

**Work:** Write 11 JSON Schemas (service-description, vocabulary, record-list, record, agent-list, agent, repository-list, repository, subgraph, error, validation-report). Publish to `openric-spec/schemas/`.

**Acceptance:** CI step validates sample responses against schemas.

### 3.6 Fixture pack — 20 canonical cases

**Why:** OpenRiC conformance §3.3. Makes conformance claims machine-verifiable.

**Work:** For each of the 20 cases listed in `spec/conformance.md` §3.3, capture `input.json` (AtoM-shape) + `expected.jsonld` (RiC-O) + `expected-graph.json` + `notes.md`. Source inputs: extract from `tests/Unit/RicSerializationServiceTest.php`.

**Acceptance:** all 20 round-trip through Heratio without diff.

### 3.7 `openric-validate` CLI

**Why:** Turns OpenRiC conformance from aspirational into checkable.

**Work:** Python CLI in a new `github.com/openric/validator` repo. Wraps `pyshacl` + JSON Schema validator + graph-isomorphism check. Emits JUnit/JSON/human reports. Exit codes per spec.

**Acceptance:** `openric-validate https://ric.theahg.co.za/api/ric/v1 --level=L3` runs clean.

### 3.8 Extract `@openric/viewer` npm package

**Why:** Delivers on the OpenRiC decoupling promise — proves the viewer works against any OpenRiC-conformant server, not just Heratio. Moves the IIIF-style separation from talk to code.

**Work:** Extract the 2D force-directed + 3D WebGL viewer JS out of `ahg-ric/resources` into a standalone npm package. Interface: `mount(element, {server: "<base-url>"})`. Publish to npm under `@openric/viewer`.

**Acceptance:** demo page hosted at `viewer.openric.org` pointed at a second OpenRiC-conformant backend (can be a tiny prototype against AtoM DB to prove portability).

### 3.9 Direct RiC editing in the GUI

**Why:** Closes Richard's observation #2 ("GUI does not support 'adding RiC directly'"). Moves Heratio from RiC-projection to RiC-native editing.

**Work:** A form surface inside record/agent show pages that lets users assert additional RiC triples (subject/predicate/object) beyond what the ISAD/ISAAR translation produced. Stored in a new `ric_user_assertion` table, layered over the derived projection at read time.

**Acceptance:** a user can add "Record X `rico:isRelatedTo` Record Y" without editing either record's ISAD fields, and the graph viewer reflects it.

---

## 4. Cross-cutting concerns

- **Idempotency.** Every serializer must produce identical output on repeat invocations against quiescent data (required by OpenRiC graph primitives §6 invariant 4).
- **Language negotiation.** Responses honour `Accept-Language`. Labels default to `sourceCulture` when the requested locale is unavailable.
- **Caching.** The `/graph` endpoint is the most expensive — consider Fuseki-level caching before optimising PHP. Don't pre-optimise; profile first.
- **Backwards compatibility.** The `/api/ric/v1/` namespace is stable. Breaking changes go to `/v2/`. Never break v1 consumers mid-version.

---

## 5. Out of scope for this plan

- **Jurisdictional compliance regimes** (POPIA, GDPR, CDPA, IPSAS, GRAP 103, NAZ, NMMZ, PAIA). These are pluggable per-market modules, not RiC-core.
- **Preservation event ontology (PREMIS-equivalent).** Deferred to OpenRiC v0.2.
- **ODRL rights enforcement.** Separate OpenRiC-Rights spec, forthcoming.
- **Moving off MySQL.** Decided 2026-04-02: staying on MySQL 8. Fuseki is the graph layer; MySQL is the operational store. Do not reopen.

---

## 6. Success criteria

- [ ] All four new entity types serialize to JSON-LD (§3.1)
- [ ] All four new entity types reachable via `/api/ric/v1/{entity}` (§3.2)
- [ ] All emitted entities validate against SHACL shapes (§3.3)
- [ ] Graph edges emit canonical RiC predicates from `ric_relation_meta` (§3.4)
- [ ] 11 JSON Schemas published (§3.5)
- [ ] 20-fixture pack in `openric-spec/fixtures/` (§3.6)
- [ ] `openric-validate` CLI published and passing against Heratio at L3 (§3.7)
- [ ] `@openric/viewer` published on npm and demonstrated against a non-Heratio backend (§3.8)
- [ ] Direct RiC assertion UI shipped (§3.9)

When the first six are green, Heratio can legitimately claim **OpenRiC 0.1.0 L3 conformance** on its README.

---

## 7. Related documents

- `docs/openric-decoupling-plan.md` — strategic plan for separating spec from implementation
- `docs/openric-reply-to-richard.md` — reply to Richard's IIIF-analogy feedback
- `docs/openric-richard-feedback-response.md` — analysis of Richard's feedback
- `docs/ric-sync-setup.md` — operational setup (Fuseki, source DB config)
- `docs/ric-user-guide.md` — end-user guide
- External spec: **[openric.org](https://openric.org)**

---

## 8. Change log

| Date | Change |
|---|---|
| 2026-04-17 | Initial consolidation. Verified current DB / service / API state. Nine-item ordered work list established. |
