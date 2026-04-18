# RiC API — Write-Side (API-2)

**Last updated:** 2026-04-18
**Status:** All 7 write endpoints shipped. Gated by `api.auth:write` middleware. Auth accepts either a logged-in admin session or an API key with the `write` scope (from `ahg-api`).

This is the counterpart to `docs/ric-api-read-gaps.md`. Read covers *what the UI fetches*; Write covers *what the UI mutates*.

---

## What shipped

All endpoints under `/api/ric/v1/`, handled by `LinkedDataApiController`.

### Entity CRUD

| Method | Path | Auth scope | Handler |
|---|---|---|---|
| POST | `/api/ric/v1/{type}` | `write` | `createEntity` |
| PATCH/PUT | `/api/ric/v1/{type}/{id}` | `write` | `updateEntity` |
| DELETE | `/api/ric/v1/{type}/{id}` | `write` | `deleteEntity` |

`{type}` ∈ `places | rules | activities | instantiations`. Body matches the existing `createPlace` / `createRule` / `createActivity` / `createInstantiation` service contracts (same field names as the edit forms).

### Relation CRUD

| Method | Path | Auth scope | Handler |
|---|---|---|---|
| POST | `/api/ric/v1/relations` | `write` | `createRelation` |
| PATCH/PUT | `/api/ric/v1/relations/{id}` | `write` | `updateRelation` |
| DELETE | `/api/ric/v1/relations/{id}` | `write` | `deleteRelation` |

Body keys: `subject_id`, `object_id`, `relation_type`, `start_date`, `end_date`, `certainty`, `evidence`.

### Example — create a Place

```bash
curl -sX POST https://heratio.theahg.co.za/api/ric/v1/places \
  -H "X-API-Key: $KEY" -H "Content-Type: application/json" \
  -d '{"name":"Gqeberha","type_id":"city","latitude":-33.96,"longitude":25.6,"authority_uri":"https://www.geonames.org/964420"}'
# 201 Created → {"id": 12345, "slug": "gqeberha", "type": "place", "href": "/api/ric/v1/places/gqeberha"}
```

### Example — edit a relation

```bash
curl -sX PATCH https://heratio.theahg.co.za/api/ric/v1/relations/67890 \
  -H "X-API-Key: $KEY" -H "Content-Type: application/json" \
  -d '{"certainty":"probable","evidence":"Binneman 1998 p.42","end_date":"1903-06-15"}'
# 200 OK → {"success": true, "id": 67890}
```

---

## Auth model

Writes are protected by `api.auth:write`, which:

1. Accepts a **logged-in admin web session** (sets scopes to `['read','write','delete','batch','publish:write']`) — so Heratio's own admin UI can call the API in-process without a key.
2. Or an **API key** via `X-API-Key`, `X-REST-API-Key`, or `Authorization: Bearer` header, looked up against `ahg_api_key` (sha256-hashed) with at least the `write` scope.

Keys are managed under the existing `ahg-api` package.

---

## Error semantics

| Status | When |
|---|---|
| 201 Created | POST succeeded; body includes `{id, slug, type, href}` |
| 200 OK | PATCH/DELETE succeeded; body includes `{success:true, id}` |
| 401 Unauthorized | Missing or invalid API key / no session |
| 403 Forbidden | Valid key but missing the `write` scope |
| 404 Not Found | Unknown `{type}` or `{id}` |
| 422 Unprocessable Entity | Body validation failed or service threw |
| 429 Too Many Requests | Exceeds `throttle:60,1` |

---

## Out of scope (still)

- **SHACL pre-validation on writes.** Today we rely on DB constraints + service-layer checks. Next step: run `ShaclValidationService::validate()` on the serialised output *before* committing, refuse if it fails. Deferred — not blocking the split.
- **Event/audit log of writes.** `ahg-api` has `api.log` middleware but we don't persist a per-mutation audit trail for RiC entities yet.
- **Bulk endpoints.** No `POST /api/ric/v1/places/bulk`. Consumers that need batch writes can loop today.
- **Optimistic concurrency / `If-Match`.** No etags yet. Last-write-wins.

---

## Next — API-3 internal migration

Consumers of the new API, tracked for the split. Each migration uses a **try-API-first-with-fallback-to-service** pattern for safety during the transition — if the HTTP call throws or returns non-2xx, the controller falls back to the direct service call so the UI never breaks.

- [x] **`RicEntityController::browseRelations`** → `GET /api/ric/v1/relations?q=…` (shipped 2026-04-18). Renders a "Served via …" banner on the admin page to make the code path visible.
- [x] **`RicEntityController::autocompleteEntities`** → `GET /api/ric/v1/autocomplete` (shipped 2026-04-18). Used by every FK picker across the admin UI; biggest blast radius of any single migration.
- [ ] **`RicEntityController::getEntityInfo`** → `GET /api/ric/v1/entities/{id}/info` (info popovers) — **skipped for now** because the admin endpoint returns the *full* entity shape whereas the public one returns a minimal info card. Either broaden the public endpoint or keep the admin as an internal "details" endpoint.
- [x] **`RicEntityController::entitiesForRecord`** → `GET /api/ric/v1/records/{id}/entities` (shipped 2026-04-18).
- [x] **`RicEntityController::relationsForRecord`** → `GET /api/ric/v1/relations-for/{id}` (shipped 2026-04-18; public returns grouped {outgoing, incoming}, admin flattens back to the legacy array shape for front-end compat).
- [x] **`RicEntityController::storeEntity` (AJAX admin)** → `POST /api/ric/v1/{type}` (shipped 2026-04-18). Auth handled by forwarding the admin session cookie via `callRicApi()` — the inner request decrypts the cookie, finds the session, passes `api.auth:write`.
- [x] **`RicEntityController::updateEntity`** / **`updateEntityForm`** → `PATCH /api/ric/v1/{type}/{id}` (shipped 2026-04-18).
- [x] **`RicEntityController::destroyEntity`** / **`destroyEntityForm`** → `DELETE /api/ric/v1/{type}/{id}` (shipped 2026-04-18).
- [x] **`RicEntityController::storeRelation` / `updateRelationAjax` / `destroyRelation`** → `/api/ric/v1/relations` CRUD (shipped 2026-04-18).
- [ ] **Relation-editor modal JS** — still hits `/admin/ric/entity-api/relation-*`. Those admin endpoints are now thin pass-throughs that forward to the API, so the JS works unchanged. Migrating the front-end URLs + deleting the admin wrappers is a later cleanup once the rest of the split is in motion.

**Internal helper:** `RicEntityController::callRicApi($method, $path, $data, $request)` forwards the session cookie to the in-process HTTP call. Returns the decoded JSON on 2xx, `null` on any non-2xx / transport failure — callers fall back to the direct service call.

Each migration is ~1 file, composes. Once all admin controllers are HTTP-only callers, the **ahg-ric package can be lifted into its own Laravel service** and Heratio becomes a true client.

---

## Change log

| Date | Change |
|---|---|
| 2026-04-18 | Initial write-side shipped: 6 entity/relation mutating endpoints, `api.auth:write` gating. Verified 401 without key, 201/200 with session. |
| 2026-04-18 | API-3 migration started — `browseRelations` and `autocompleteEntities` now consume the public API with DB/service fallback. First two of 10 consumers migrated. |
| 2026-04-18 | API-3 migration continues — `entitiesForRecord` and `relationsForRecord` now route through the public API. 4 of 10 read-side consumers done. Only writes + `getEntityInfo` (shape mismatch) remain on the read side. |
| 2026-04-18 | API-3 migration — ALL write consumers migrated in one pass: `storeEntity`, `updateEntity`, `destroyEntity`, `updateEntityForm`, `storeEntityForm`, `destroyEntityForm`, `storeRelation`, `updateRelationAjax`, `destroyRelation`. Uses `callRicApi()` helper that forwards the admin session cookie to the in-process HTTP call, so `api.auth:write` sees the session and accepts it. Direct service call retained as fallback for every endpoint. |
