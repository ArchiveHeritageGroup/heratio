---
title: REST API - OpenAPI, Swagger UI, ETag, Idempotency-Key
slug: api-openapi
category: Integration
---

# REST API - OpenAPI, Swagger UI, ETag, Idempotency-Key

Heratio publishes an OpenAPI 3.1 specification for the full REST API (v1 + v2)
and ships an interactive Swagger UI viewer.

## Where to find the docs

- **Spec (JSON)**: `https://<your-heratio>/api/openapi.json`
- **Swagger UI**: `https://<your-heratio>/api/docs`

Both endpoints are restricted to authenticated admins by default. To make them
public (for example to share with external integrators), flip the
`openapi_public` flag in **Admin > Settings > API** to `Yes`.

## Conditional GETs with ETag

Every successful GET response carries an `ETag` header. To skip the response
body if you already have a fresh copy, echo the ETag back via `If-None-Match`:

```bash
# First call - returns 200 with body + ETag
curl -i https://your-heratio/api/v2/descriptions/some-slug \
     -H 'X-API-Key: ahg_live_...'

# Replay with If-None-Match - returns 304, no body
curl -i https://your-heratio/api/v2/descriptions/some-slug \
     -H 'X-API-Key: ahg_live_...' \
     -H 'If-None-Match: "abc123..."'
```

This is a great fit for periodic poll jobs and mobile sync.

## Idempotent POSTs with Idempotency-Key

Network blips can make non-idempotent calls (mint DOI, create description,
upload digital object) replay accidentally. Pass a unique `Idempotency-Key`
header and Heratio will cache the response for 24 hours.

```bash
KEY=$(uuidgen)

# First call - executes, response cached for 24h
curl -X POST https://your-heratio/api/v2/descriptions \
     -H 'X-API-Key: ahg_live_...' \
     -H "Idempotency-Key: $KEY" \
     -H 'Content-Type: application/json' \
     -d '{"title":"My new fonds","level_id":172}'

# Same key + same body - returns the cached response without re-running.
# The replay carries an X-Idempotent-Replay: true header so you know it
# was served from cache.
curl -X POST https://your-heratio/api/v2/descriptions \
     -H 'X-API-Key: ahg_live_...' \
     -H "Idempotency-Key: $KEY" \
     -H 'Content-Type: application/json' \
     -d '{"title":"My new fonds","level_id":172}'
```

Rules of thumb:

- Keys are at most 64 characters. UUIDs are a good choice.
- Same key + different body = 409 Conflict. Generate a new key when the
  payload changes.
- The cache is per-user. Two API keys belonging to different users can use
  the same Idempotency-Key string independently.
- Only 2xx responses are cached. 4xx/5xx replay normally.

## Regenerating the static spec

The live `/api/openapi.json` endpoint reflects routes on every request (cached
60 seconds server-side). You can also write the spec to disk:

```bash
php artisan api:generate-openapi
# -> packages/ahg-api/resources/openapi/heratio.json
```

This static file is useful as input for `openapi-generator-cli` to produce
client SDKs in JavaScript, Python, Go, etc.

## Related issues / roadmap

- Issue #652 - REST API + OpenAPI gaps (this page covers Phase 1).
- Phase 2+: GraphQL hardening + generated SDKs.
