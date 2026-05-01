# RiC in Heratio - status and plan

**Last updated:** 2026-04-18
**Author:** Johan Pieterse / Plain Sailing Information Systems
**Scope:** Current state of Records in Contexts (RiC) inside Heratio, its relationship to the external OpenRiC specification, and the ordered work list - now mostly complete.

This document is the canonical answer to "where are we with RiC?". It supersedes ad-hoc memory and replaces day-to-day status notes. Update it as work lands, not in separate status files.

---

## 1. Executive summary - 2026-04-18

- **The Heratio/RiC split is live.** `ric.theahg.co.za/api/ric/v1/*` is an independently-deployed Laravel service; Heratio is a consumer of it over HTTPS with `X-API-Key` auth. Every admin mutation in Heratio goes through that HTTP surface. `ric:verify-split` passes 15/15.
- **OpenRiC has four public surfaces, all live.** Spec ([openric.org](https://openric.org)), viewer ([viewer.openric.org](https://viewer.openric.org), `@openric/viewer@0.1.1` on npm), capture ([capture.openric.org](https://capture.openric.org)), reference API ([ric.theahg.co.za](https://ric.theahg.co.za/api/ric/v1/health)).
- **All nine items on the original §3 roadmap are done**; §3.1–§3.7 have been done for weeks; §3.8 (`@openric/viewer` extraction + demo) landed, and §3.9 (direct RiC editing via the UI) is available through the capture studio AND through Heratio admin pages - both of which speak only the public API.
- **Phase 4 (the actual split) is 80% done** - the two apps are deployed, Heratio is a client, embedded Blade JS + server-side admin calls all route through `RIC_API_URL`. What's *not* yet done: the `ahg-ric` package is still shared between both apps via composer path repo, and some Heratio admin controllers still use in-process reads (they work; they're not strictly "pure consumer"). See [`ric-split-collapse-plan.md`](./ric-split-collapse-plan.md) for the remaining 20%.

---

## 2. Current state (verified 2026-04-18)

### 2.1 Deployment topology

| Host | Role | Code path |
|---|---|---|
| `heratio.theahg.co.za` | GLAM platform + RiC API consumer | `/usr/share/nginx/heratio/` |
| `ric.theahg.co.za/api/ric/v1/*` | OpenRiC reference API | `/usr/share/nginx/OpenRiC/` (Laravel; reuses `heratio/packages/ahg-ric`) |
| `viewer.openric.org` | Graph viewer demo | `github.com/openric/viewer` → GH Pages |
| `capture.openric.org` | Pure-browser capture client | `github.com/openric/capture` → GH Pages |
| `openric.org` | Specification | `github.com/openric/spec` → GH Pages |

### 2.2 Database layer

Still shared MySQL between Heratio and `openric-service` (Phase 4.3 **Option A**). Phase 4.3 Option B (separate DB for the service) is planned but not scheduled.

| Table | Purpose | Rows (approx) |
|---|---|---|
| `ric_place` + `ric_place_i18n` | First-class Place entities | 182 |
| `ric_rule` + `ric_rule_i18n` | Mandates / rules | 2 |
| `ric_activity` + `ric_activity_i18n` | Activity entities | 233 |
| `ric_instantiation` + `ric_instantiation_i18n` | Digital/physical manifestations | 1,280 |
| `ric_relation_meta` | Predicate metadata on relations | 681 |
| `ahg_api_key` | API keys for the write surface | operator-managed |

Plus seven `ahg_dropdown` taxonomies in section `ric`: `ric_entity_type`, `ric_place_type`, `ric_rule_type`, `ric_activity_type`, `ric_carrier_type`, `ric_relation_category`, `ric_relation_type` (72 items).

### 2.3 Service layer (`packages/ahg-ric/src/Services/`)

| Service | Status | Notes |
|---|---|---|
| `RicSerializationService` | ✓ Complete | Serializes all 8 entity types with canonical `rico:*` predicates; live-validates against OpenRiC schemas + SHACL. |
| `RicEntityService` | ✓ Complete | CRUD + relations + autocomplete + hierarchy + vocabulary helpers. |
| `RelationshipService` | ✓ Complete | Uses `ric_relation_meta` for canonical predicates. |
| `ShaclValidationService` | ✓ Complete | Shapes for all RiC-native entity types. |
| `SparqlQueryService` | ✓ Complete | Fuseki passthrough. |

These classes now live in one place (the shared `ahg-ric` package) and are executed by `openric-service` at `ric.theahg.co.za`. Heratio's in-process copies exist but are not hit by the admin UI mutations anymore - those go over HTTP via `RicApiClient`.

### 2.4 API layer

**Served by:** `ric.theahg.co.za/api/ric/v1/*` (Laravel at `/usr/share/nginx/OpenRiC/`). Heratio's own `/api/ric/v1/*` routes are not loaded when `RIC_API_URL` is set (Phase 4.4.3).

Endpoints (read-side, public):

- `GET /agents`, `/agents/{slug}`
- `GET /records`, `/records/{slug}`, `/records/{slug}/export`, `/records/{id}/entities`
- `GET /functions`, `/functions/{id}`
- `GET /repositories`, `/repositories/{slug}`
- `GET /places`, `/places/{id}`, `/places/flat`
- `GET /rules`, `/rules/{id}`
- `GET /activities`, `/activities/{id}`
- `GET /instantiations`, `/instantiations/{id}`
- `GET /relations`, `/relations-for/{id}`, `/relation-types`
- `GET /hierarchy/{id}`
- `GET /autocomplete`
- `GET /entities/{id}/info`
- `GET /vocabulary`, `/vocabulary/{taxonomy}`
- `GET /graph?uri=&depth=`
- `GET /sparql`, `/health`, `/openapi.json`, `/`
- `POST /validate`

Endpoints (write-side, `X-API-Key`-gated):

- `POST /{type}`, `PATCH /{type}/{id}`, `PUT /{type}/{id}`, `DELETE /{type}/{id}` - `{type}` ∈ `places|rules|activities|instantiations`
- `DELETE /entities/{id}` - generic delete-by-id
- `POST /relations`, `PATCH /relations/{id}`, `DELETE /relations/{id}`

All writes route through `ahg-api` → `api.auth:write` middleware; keys are SHA-256-hashed with per-key scopes in `ahg_api_key`.

### 2.5 UI layer

- **Heratio admin** - `/admin/ric/entities/{places|rules|activities|instantiations}` (browse + show + edit + create). Every form submit goes through `RicEntityController::*Form` which uses `callRicApi()` to POST/PATCH/DELETE against `ric.theahg.co.za`. Embedded Blade partials (`_ric-view-*`, `_ric-entities-panel`, `_relation-editor`, `_fk-autocomplete`) render via JS `fetch()` to `window.RIC_API_BASE` which equals `https://ric.theahg.co.za/api/ric/v1` when `RIC_API_URL` is set.
- **Graph explorer** - `/admin/ric/explorer`, still in Heratio. Fetches subgraphs via the same `RIC_API_BASE`.
- **Capture studio** - moved out. `/ric-capture` in Heratio is a 302 redirect to `https://capture.openric.org/`. Users land on the neutral client.

### 2.6 OpenRiC coordination

- Spec repository at `github.com/openric/spec` (moved from `ArchiveHeritageGroup/openric-spec` on 2026-04-18; GH redirects the old URL).
- v0.1.0 tagged + frozen; v0.2.0 pending documentation of the new endpoints + fixtures.
- Four seed Discussions live - announcements, second-implementer feedback, mapping sanity-checks, and a progress update covering everything since v0.1.0.

---

## 3. Roadmap - then and now

### Originally identified 9 items (2026-04-17)

| # | Item | Status |
|---|---|---|
| 3.1 | Emit the four new entity types as proper RiC-O JSON-LD | ✅ done (v0.97.1) |
| 3.2 | API endpoints for the four new entity types | ✅ done (v0.94 – v0.97) |
| 3.3 | SHACL shapes for the new entity types | ✅ done |
| 3.4 | Relation predicate alignment via `ric_relation_meta` | ✅ done (v0.97.2) |
| 3.5 | JSON Schemas for every endpoint response | ✅ done (12 schemas in `openric-spec/schemas/`) |
| 3.6 | Fixture pack - 20 canonical cases | ✅ done |
| 3.7 | `openric-validate` CLI | ✅ done (in `openric-spec/validator/`) |
| 3.8 | Extract `@openric/viewer` npm package | ✅ done; published `v0.1.1`; [viewer.openric.org](https://viewer.openric.org) |
| 3.9 | Direct RiC editing in the GUI | ✅ done two ways - Heratio admin forms + the neutral [capture.openric.org](https://capture.openric.org) |

All nine green.

### Phase 4 - the service split

| Phase | Status | Notes |
|---|---|---|
| 4.1 - Blade JS consumes `/api/ric/v1/*` directly | ✅ | All embedded partials use `window.RIC_API_BASE` |
| 4.2 - Delete pass-through admin wrappers | ✅ | `admin/ric/entity-api/*` routes + 12 controller methods removed |
| 4.3.1 - Scaffold `openric-service` | ✅ | `/usr/share/nginx/OpenRiC/`, Laravel 12, reuses `ahg-ric` via composer path repo |
| 4.3.2 - nginx + DNS + TLS | ✅ | Uses existing `theahg.co.za` wildcard cert; vhost at `/etc/nginx/sites-available/ric.theahg.co.za.conf` |
| 4.3.3 - Service API key | ✅ | `php artisan ric:mint-service-key` in place; key in Heratio's `.env` |
| 4.3.4 - `callRicApi` external-mode | ✅ | Auto-detects; forwards session cookie in-process, switches to `X-API-Key` for external |
| 4.3.5 - Staging flip | ⏺ skipped | Went straight to production given the shared-DB + instant rollback shape |
| 4.3.6 - Production cutover | ✅ | 2026-04-18; `ric:verify-split` 15/15 green |
| 4.4.1 - `RicApiClient` thin HTTP facade | ✅ | `packages/ahg-ric/src/Http/RicApiClient.php` |
| 4.4.2 - Swap embedded-view data fetches to `RicApiClient` | ⚠ partial | Front-end JS ✓; server-side browse/show/edit still use services (works, not pure-consumer) |
| 4.4.3 - Stop loading `routes/api.php` in Heratio | ✅ | Service provider guards on `RIC_API_URL` |
| 4.4.4 - Delete service classes | ✗ deferred | Would break `openric-service` which shares the package; proper fix is package split |
| 4.4.5 - `/ric-capture` → `capture.openric.org` | ✅ | 302 redirect; `captureStudio` method deleted |
| 4.4.6 - Remove `ric_*` tables from Heratio's DB | ⏳ | Blocked on `openric-service` having its own DB (Phase 4.3 Option B) |

### What's next

Three threads, no urgency:

1. **Package split** (Phase 4.4.4 properly) - fork `ahg-ric` into `ahg-ric-client` (thin HTTP wrapper + Blade partials) and `ahg-ric-server` (services + controllers + routes). Heratio depends on the first, `openric-service` on the second. ~2 days.
2. **Separate DB for `openric-service`** (Phase 4.3 Option B + 4.4.6) - migrate `ric_*` tables to their own DB; `openric-service` becomes fully standalone. ~1 week.
3. **v0.2.0 spec freeze** - add fixtures for the new read endpoints + the write surface, validate, tag. Blocker: a second implementation in sight so we don't over-commit to the reference's shapes.

---

## 4. Cross-cutting concerns

- **Idempotency.** Every serializer produces identical output on repeat invocations against quiescent data (required by OpenRiC graph primitives §6 invariant 4).
- **Language negotiation.** Responses honour `Accept-Language`. Labels default to `sourceCulture` when the requested locale is unavailable.
- **Caching.** The `/graph` endpoint is the most expensive - consider Fuseki-level caching before optimising PHP. Don't pre-optimise; profile first.
- **Backwards compatibility.** The `/api/ric/v1/` namespace is stable. Breaking changes go to `/v2/`. Never break v1 consumers mid-version.
- **Shared-DB caveat.** Because Heratio and `openric-service` share MySQL, Heratio could still INSERT/UPDATE/DELETE into `ric_*` tables via its service classes. Grep says no code path does, but if a regression occurred the service would silently see the change. Phase 4.3 Option B closes this loophole.

---

## 5. Out of scope for this plan

- **Jurisdictional compliance regimes** (POPIA, GDPR, CDPA, IPSAS, GRAP 103, NAZ, NMMZ, PAIA). These are pluggable per-market modules, not RiC-core.
- **Preservation event ontology (PREMIS-equivalent).** Deferred to OpenRiC v0.2.
- **ODRL rights enforcement.** Separate OpenRiC-Rights spec, forthcoming.
- **Moving off MySQL.** Decided 2026-04-02: staying on MySQL 8. Fuseki is the graph layer; MySQL is the operational store. Do not reopen.

---

## 6. Success criteria

All original success criteria (2026-04-17) are green:

- [x] All four new entity types serialize to JSON-LD (§3.1)
- [x] All four new entity types reachable via `/api/ric/v1/{entity}` (§3.2)
- [x] All emitted entities validate against SHACL shapes (§3.3)
- [x] Graph edges emit canonical RiC predicates from `ric_relation_meta` (§3.4)
- [x] JSON Schemas published (§3.5)
- [x] 20-fixture pack in `openric-spec/fixtures/` (§3.6)
- [x] `openric-validate` CLI published and passing against Heratio at L3 (§3.7)
- [x] `@openric/viewer` published on npm and demonstrated against a non-Heratio backend (§3.8)
- [x] Direct RiC assertion UI shipped (§3.9) - *Heratio admin + capture.openric.org*

Two new criteria added for Phase 4 completion:

- [x] OpenRiC reference API runs in its own deployment, not inside Heratio's process.
- [x] Heratio mutates RiC data only via HTTP calls to that external service, not via in-process service classes.

---

## 7. Related documents

- `docs/ric-split-plan.md` - Phase 4 architectural plan
- `docs/ric-split-runbook.md` - the operational playbook that brought the split live (annotated with what actually happened)
- `docs/ric-split-collapse-plan.md` - Phase 4.4 (shrink Heratio's `ahg-ric` to a thin client)
- `docs/ric-api-read-gaps.md` - read-side API coverage audit (9/11 closed)
- `docs/ric-api-write-plan.md` - write-side API + API-3 migration progress
- `docs/ric-capture-ui-audit.md` - capture UI completeness (all 9 gaps closed)
- `docs/openric-announcement-draft.md` - mailing-list announcement (ready to send)
- External: **[openric.org](https://openric.org)** + **[openric.org/architecture.html](https://openric.org/architecture.html)**

---

## 8. Change log

| Date | Change |
|---|---|
| 2026-04-17 | Initial consolidation. Verified current DB / service / API state. Nine-item ordered work list established. |
| 2026-04-18 | All 9 §3 items closed. Phase 4 (split) executed: `openric-service` live at `ric.theahg.co.za`, Heratio became a consumer, `verify-split` 15/15. Phase 4.4 partial (client thinness pending a package split). |
