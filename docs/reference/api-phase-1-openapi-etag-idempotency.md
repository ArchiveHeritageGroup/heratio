# REST API Phase 1 - OpenAPI, Swagger UI, ETag, Idempotency-Key

Shipped as Phase 1 of Heratio issue #652. Lives entirely in `packages/ahg-api/`.
Phase 2+ (GraphQL hardening, generated SDKs) are tracked separately.

## What ships

- **OpenAPI 3.1 spec generator** - reflective: walks `Route::getRoutes()`, picks `/api/*`, introspects controller signatures + any `FormRequest::rules()` it finds, and emits a minimal-but-correct JSON document. No annotations. No build step beyond an artisan command.
- **Live spec endpoint** at `GET /api/openapi.json` (cached 60s server-side).
- **Swagger UI** at `GET /api/docs` - CDN-loaded `swagger-ui-dist@5.17.14`, loads the live spec, deep-linking enabled, Try-It-Out on.
- **Idempotency-Key middleware** (`api.idempotency`) wired on `/api/v1/*` and `/api/v2/*` route groups. POST/PUT/PATCH with an `Idempotency-Key` header are cached for 24h; replays return the cached response. Different body + same key returns 409 Conflict. No header = pass-through.
- **ETag middleware** (`api.etag`) wired on the same groups + on `/api/openapi.json`. GET responses carry `ETag: "<sha256-32hex>"`. `If-None-Match` matches return 304 with empty body.

## Visibility flag

`ahg_settings.openapi_public` (default `0`) gates the spec + Swagger UI for unauthenticated callers. Admins (any Laravel session-authenticated user) are always allowed. Flip to `1` in the settings dashboard to expose `/api/docs` to anonymous users.

## Files

```
packages/ahg-api/
  src/
    Services/OpenApiGenerator.php           - reflective generator
    Controllers/OpenApiController.php       - spec + docs endpoints
    Middleware/ETagMiddleware.php
    Middleware/IdempotencyKeyMiddleware.php
    Console/GenerateOpenApiCommand.php      - api:generate-openapi
    Console/PruneIdempotencyCommand.php     - api:prune-idempotency
    Providers/AhgApiServiceProvider.php     - aliases + view loader + auto-install
  resources/
    views/swagger-ui.blade.php              - CDN-backed Swagger UI 5.17.14
    openapi/heratio.json                    - written by api:generate-openapi
  database/install.sql                      - adds ahg_api_idempotency_key + openapi_public seed
  routes/api.php                            - new doc routes + middleware on v1/v2

tests/Feature/Api/
  OpenApiGeneratorTest.php
  ETagMiddlewareTest.php
  IdempotencyKeyMiddlewareTest.php
```

## Schema

```sql
CREATE TABLE ahg_api_idempotency_key (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    idem_key VARCHAR(64) NOT NULL,
    user_id INT NOT NULL DEFAULT 0,
    route VARCHAR(255) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    response_status INT NOT NULL,
    response_body LONGBLOB,
    response_headers JSON,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_key (user_id, idem_key),
    KEY idx_expires_at (expires_at)
);
```

Auto-installed on first service-provider boot when missing. Manual install:

```bash
mysql -u root heratio < packages/ahg-api/database/install.sql
```

## CLI

```bash
# Regenerate the static spec file
php artisan api:generate-openapi
php artisan api:generate-openapi --print           # echo to stdout

# Daily prune (add to /etc/cron.d/heratio or schedule:run)
php artisan api:prune-idempotency
```

## Idempotency-Key semantics

- Recognised on POST/PUT/PATCH only.
- Key max length 64 chars (RFC draft). Larger keys = 400 Bad Request.
- Cache TTL = 24h.
- Only 2xx responses are cached. 4xx/5xx replay normally.
- Same key + same (user, route, body) = cached response with `X-Idempotent-Replay: true`.
- Same key + different route or body = 409 Conflict.
- Per-user keyspace: two users can use the same key string independently.

## ETag semantics

- Computed as `"<sha256-of-body-truncated-32hex>"`.
- `If-None-Match` matches (strong or weak `W/"..."`) trigger 304 with empty body.
- Controllers can bypass by setting `request()->attributes->set('etag.bypass', true)`.
- Non-200 responses get no ETag (avoids caching error bodies).

## OpenAPI generator design notes

- Reflective, not annotation-based. Pros: no docblock churn, no build step, no `zircote/swagger-php` dependency. Cons: less expressive than `@OA\Operation` annotations.
- FormRequest detection looks at the controller method's parameter list. If a parameter is a subclass of `Illuminate\Foundation\Http\FormRequest`, the generator instantiates it and calls `rules()`. Rules are translated to a JSON-schema fragment (`integer`, `string`, `array`, `boolean`, `date`, `email`, `url` formats). Dot-notation rule keys collapse to their root field.
- Path parameters come from `Route::parameterNames()`. Integer schema is used for parameters with `where('id', '[0-9]+')` constraints or names in {id, objectId, photoId}.
- Each POST operation advertises the optional `Idempotency-Key` header in its parameter list, so client SDKs / Swagger UI surface the field automatically.
- Each GET operation advertises optional `If-None-Match` + a `304` response.

## Phase 2+ outstanding (not in this release)

- GraphQL hardening - depth limiting, query cost analysis, persisted queries (issue #652).
- Generated SDKs - JS/TS + Python from the OpenAPI spec via `openapi-generator-cli`.
- Per-operation `summary`/`description` overrides via a sidecar YAML file (so we can keep enrichment outside controller docblocks).
- Webhook signature reference page.
