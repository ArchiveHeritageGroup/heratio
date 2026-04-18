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

These are the *consumers* of the new API, tracked for the split:

1. **`RicEntityController::storeEntity` (AJAX admin)** — currently calls `$this->service->createPlace()` etc. directly. Migrate to fetch `POST /api/ric/v1/{type}` via Laravel's `Http` facade, inheriting the session auth → server still handles it locally but through the HTTP layer.
2. **`RicEntityController::updateEntity`** / **`updateEntityForm`** — same pattern, for PATCH.
3. **`RicEntityController::destroyEntity`** / **`destroyEntityForm`** — same, for DELETE.
4. **`RicEntityController::storeRelation` / `updateRelationAjax` / `destroyRelation`** — same for relation CRUD.
5. **Relation-editor modal** — already talks to `/admin/ric/entity-api/relation-*` via AJAX. Switch to `/api/ric/v1/relations*` directly and delete the admin wrappers.
6. **`RicEntityController::browseRelations`** — switch to calling `/api/ric/v1/relations?q=…`.

Each migration is ~1 file, should compose. Do incrementally: one page, verify, next. Once all admin controllers are HTTP-only callers, the **ahg-ric package can be lifted into its own Laravel service** and Heratio becomes a true client.

---

## Change log

| Date | Change |
|---|---|
| 2026-04-18 | Initial write-side shipped: 6 entity/relation mutating endpoints, `api.auth:write` gating. Verified 401 without key, 201/200 with session. |
