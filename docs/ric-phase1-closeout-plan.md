# OpenRiC Phase 1 closeout — implementation plan

**Date:** 2026-04-17
**Scope:** Close out Phase 1 of the OpenRiC decoupling plan by producing the three remaining artifacts: JSON Schemas, fixture pack, and validator CLI.
**Owner:** Johan Pieterse / Plain Sailing Information Systems
**Target completion:** 2026-05-08 (3 weeks from today).

---

## 1. Why now

Phase 0 and the documentation portion of Phase 1 are done. What's missing is the *machine-verifiable* half — without it, conformance claims are vibes. Three artifacts close the gap:

1. **JSON Schemas** — validate the on-the-wire shape of every endpoint response.
2. **Fixture pack** — 20 canonical input/output pairs so any implementation can self-check.
3. **`openric-validate` CLI** — a single command an implementer runs against a live server to get a conformance report.

Until these land, Heratio cannot honestly claim "OpenRiC 0.1.0 L3 conformance" on its README.

## 2. Layout decisions

### 2.1 Everything lives in `openric-spec`

All three artifacts live inside the existing `github.com/openric/spec` repo, not in a separate `openric/validator` repo. Reason: v0.1 is too young for multi-repo coordination; a subfolder split is trivial later when the validator acquires its own release cadence.

New tree:

```
openric-spec/
├── schemas/                   # JSON Schema 2020-12 per endpoint
│   ├── service-description.schema.json
│   ├── vocabulary.schema.json
│   ├── record-list.schema.json
│   ├── record.schema.json
│   ├── agent-list.schema.json
│   ├── agent.schema.json
│   ├── repository-list.schema.json
│   ├── repository.schema.json
│   ├── subgraph.schema.json
│   ├── error.schema.json
│   └── validation-report.schema.json
├── shapes/
│   └── openric.shacl.ttl      # ported from Heratio ric_shacl_shapes.ttl + new entity shapes
├── fixtures/
│   ├── README.md
│   └── <case>/                # 20 of these
│       ├── input.json
│       ├── expected.jsonld
│       ├── expected-graph.json
│       └── notes.md
└── validator/                 # Python CLI — publishable separately later
    ├── pyproject.toml
    ├── README.md
    ├── openric_validate/
    │   ├── __init__.py
    │   ├── __main__.py        # entry point
    │   ├── cli.py             # arg parsing, orchestration
    │   ├── http_client.py     # fetch endpoints
    │   ├── schema_check.py    # JSON Schema validation
    │   ├── shape_check.py     # SHACL via pyshacl
    │   ├── graph_check.py     # invariants + isomorphism
    │   └── report.py          # human / json / junit output
    └── tests/
        └── test_against_fixtures.py
```

### 2.2 JSON Schema flavour

Draft **2020-12**. Reasons:
- Current stable.
- Native `$dynamicRef` helps with RiC-O polymorphism (Record / RecordSet / RecordPart share structure).
- Excellent Python support (`jsonschema >= 4.18`) for the validator.

### 2.3 Validator language

**Python 3.11+.** Reasons:
- `pyshacl` is the canonical SHACL engine; already used inside Heratio.
- `rdflib` handles JSON-LD → RDF + graph isomorphism.
- `jsonschema` for schema validation.
- Single language stack beats mixing Node + Python.

Shipped as `pip install openric-validate` eventually; for v0.1 a `pipx` local install from the repo is enough.

## 3. Thin-slice first, then fill

Do not build all 11 schemas + 20 fixtures + full validator then wire them together at the end. Ship a thin end-to-end slice first to de-risk the architecture.

### 3.1 Thin slice (Day 1–3)

- **1 schema:** `record.schema.json` — the single-record endpoint response shape
- **1 fixture:** `fonds-minimal` — fonds with title and creator only
- **Validator MVP:** takes a URL, fetches `/records/{id}`, checks JSON Schema, checks SHACL `:RecordShape`, prints human-readable report

Acceptance: `openric-validate --record https://ric.theahg.co.za/api/ric/v1/records/<slug>` runs green.

### 3.2 Expansion (Day 4–14)

- Remaining 10 schemas
- Remaining 19 fixtures
- Endpoint walker (`--level=L2`, `--level=L3`, `--level=L4`)
- Graph invariants check (six invariants from graph-primitives.md §6)
- Graph-isomorphism comparison against fixture expected output (for L1)

### 3.3 Polish (Day 15–21)

- JUnit and JSON report formats for CI
- Meaningful error messages with fixture path + line number when assertion fails
- A `Makefile` / `pyproject` script target: `openric-validate --all-fixtures` runs the local suite
- CI job on `openric-spec` repo: `on: pull_request` runs validator against the fixture pack

## 4. Work items — ordered

Each item is scoped small enough to land as one PR. Items upstream of each other are flagged.

### Week 1 — Thin slice

| # | Item | Unblocks |
|---|---|---|
| 1 | Scaffold `validator/` directory with `pyproject.toml`, `argparse` CLI, `http_client.py` | 2, 3 |
| 2 | Write `record.schema.json` with RecordSet / Record / RecordPart polymorphism | 3 |
| 3 | Write fixture `fonds-minimal/` (input, expected.jsonld, notes.md) | 4 |
| 4 | `schema_check.py` — validate fetched response against record.schema.json | 5 |
| 5 | Port `ric_shacl_shapes.ttl` → `openric.shacl.ttl`, strip SA/GRAP-specific shapes | 6 |
| 6 | `shape_check.py` — validate response against SHACL via pyshacl | 7 |
| 7 | `report.py` — human format | end-of-week demo |
| 8 | End-to-end run against live ric.theahg.co.za, fix whatever breaks | — |

Deliverable: `openric-validate --record <url>` end-to-end, committed, documented.

### Week 2 — Fill out schemas + fixtures

| # | Item |
|---|---|
| 9 | Schemas: service-description, vocabulary, error, validation-report (4 files, ~4h) |
| 10 | Schemas: list-form schemas (record-list, agent-list, repository-list — share a base, 3 files, ~4h) |
| 11 | Schemas: agent, repository, subgraph (3 files, ~6h) |
| 12 | Fixtures 2–7 (fonds-with-series, fonds-multilingual, agent-person, agent-corporate, agent-family, agent-with-relations) |
| 13 | Fixtures 8–13 (repository, function, production event, accumulation event, record+digital-object, record-in-container) |
| 14 | Fixtures 14–20 (security, personal-data, access-restriction, subgraph depth 1/2/filtered, validation-failure) |
| 15 | Validator: endpoint walker (`--level=L2` fetches all required endpoints, runs each against its schema) |
| 16 | Validator: `graph_check.py` — six invariants from graph-primitives.md §6 |
| 17 | Validator: graph-isomorphism check for fixture `expected.jsonld` vs fresh Heratio output |

### Week 3 — CI, polish, release

| # | Item |
|---|---|
| 18 | JUnit report format (for GitHub Actions CI annotations) |
| 19 | JSON report format (for scripted consumption) |
| 20 | CI job in `openric-spec`: run validator against fixtures on every PR |
| 21 | `validator/README.md` with install + usage |
| 22 | Update `openric-spec/index.md` with "Run the validator" section |
| 23 | Tag `openric-spec@v0.1.0`; first frozen release |
| 24 | Update `ric-status-and-plan.md` §3.5–3.7 to ✓ done |
| 25 | Draft blog post / newsletter item announcing first frozen spec release |

## 5. Producing fixtures efficiently

Hand-writing 20 fixture pairs is tedious and error-prone. Strategy:

1. **Inputs** — extract AtoM-shape JSON from Heratio's existing unit tests (`packages/ahg-ric/tests/Unit/RicSerializationServiceTest.php`). Some need new minimal records created in the DB; cheaper to write a small PHP seeder than hand-craft JSON.
2. **Expected outputs** — for each input, run it through `RicSerializationService::serializeRecord()` (or the relevant method), capture the JSON-LD, save it as `expected.jsonld`. This is Heratio "certifying itself" which feels circular, but it's the right starting point: the expected outputs are whatever the reference implementation produces, reviewed and accepted, then frozen. Future implementations must match.
3. **Expected subgraph** — similarly, call the `/graph` endpoint against the fixture input, capture output, review, freeze.
4. **Normalise JSON-LD** — canonicalise whitespace / key order before committing so diffs stay clean.

Script: `validator/tools/freeze-fixture.py <case-name>` runs Heratio, fetches outputs, writes files into `fixtures/<case>/`.

## 6. Effort estimate

| Block | Days | Notes |
|---|---|---|
| Thin slice (Week 1) | 5 | Includes unknowns: Python packaging, JSON-LD parsing quirks |
| Fill-out (Week 2) | 5 | Schemas are mostly mechanical; fixtures are the long pole |
| Polish + release (Week 3) | 3 | Depends on CI platform setup |
| **Total** | **~13 working days** | Fits in 3 calendar weeks at realistic cadence |

## 7. Success criteria

Phase 1 is complete when:

- [ ] `openric-spec/schemas/` contains 11 JSON Schema files, each validating a sample response from Heratio
- [ ] `openric-spec/shapes/openric.shacl.ttl` exists with node shapes for every RiC-O class Heratio emits
- [ ] `openric-spec/fixtures/` contains 20 case folders, each with input, expected.jsonld, expected-graph.json, notes
- [ ] `openric-spec/validator/` is installable via `pipx install ./validator` and runs
- [ ] `openric-validate https://ric.theahg.co.za/api/ric/v1 --level=L3` passes
- [ ] CI on `openric-spec` runs the validator against the fixture pack on every PR
- [ ] `openric-spec@v0.1.0` tag exists; spec is frozen at this version
- [ ] Heratio README carries "OpenRiC 0.1.0 L3 reference implementation" badge

## 8. Out of scope for this plan

- **L4 conformance.** Requires full round-trip preservation testing. Future work.
- **Second implementation.** Getting a non-Heratio backend to conform is critical for legitimacy but is outside the scope of shipping the tooling. That's Phase 4.
- **Browser-based viewer fixtures.** `@openric/viewer` extraction is a separate effort (ric-status-and-plan §3.8).
- **OpenRiC org creation on GitHub.** Still living under `ArchiveHeritageGroup/` until governance is ready.

## 9. Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| JSON-LD framing / context differences cause spurious fixture diffs | High | Normalise to RDF before comparing; don't compare JSON structurally |
| SHACL shapes over-constrain and reject valid data | Medium | Start with warnings (sh:Warning), promote to sh:Violation only after fixture testing |
| pyshacl performance on large graphs | Low | Scope validator to per-entity validation, not whole-store |
| Freezing spec v0.1.0 too early → churn for early adopters | Medium | Mark v0.1.0 "draft/unstable", reserve v1.0 for the version after external review |
| Richard's review yields structural feedback after freeze | Medium | v0.2.0 is allowed to break v0.1.0; communicate that explicitly |

## 10. Next action

Start Week 1 item 1: scaffold `validator/` directory with `pyproject.toml`, argparse CLI stub, `http_client.py`. Commit to `openric-spec`.

---

## 11. Change log

| Date | Change |
|---|---|
| 2026-04-17 | Initial plan |
