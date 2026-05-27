# Publish requests (issue #745) - token-anchored workflow

Engineering reference for the new `ahg-request-publish` surface added in
Heratio #745. Complements the user-facing help at
`docs/help/publish-requests.md`.

## Why a new flow

The legacy AtoM-port (`request_to_publish` + `request_to_publish_i18n`)
requires the submitter to be authenticated and stores researcher PII
alongside i18n columns. The new flow targets the PSIS use case: an
**anonymous** external researcher submits from a public archival-record
page, gets a tracking receipt URL, and the curator reviews from an admin
inbox panel. Both flows coexist; the new one is independent so the legacy
data is unaffected.

## Schema

`ahg_publish_request` (single un-i18n'd table):

```
id                       BIGINT UNSIGNED PK
information_object_id    BIGINT UNSIGNED NULL       FK-ish (no constraint to avoid coupling to MPTT churn)
submitter_email          VARCHAR(190) NOT NULL
submitter_name           VARCHAR(190) NULL
message_text             TEXT NULL
status                   VARCHAR(40) NOT NULL DEFAULT 'pending'   (lookup -> ahg_dropdown.publish_request_status)
token                    CHAR(40) NOT NULL UNIQUE   sha1 hex
created_at               DATETIME NOT NULL DEFAULT NOW()
decided_at               DATETIME NULL
decided_by_user_id       BIGINT UNSIGNED NULL
curator_notes            TEXT NULL
```

Status values live in `ahg_dropdown` under taxonomy
`publish_request_status`. Per CLAUDE.md "Never hardcode enumerated values"
the controller falls back to the canonical four codes only when the
dropdown table is missing.

## Surface

| Method | Path                                       | Auth   | Notes |
|--------|--------------------------------------------|--------|-------|
| POST   | `/publish-request`                         | none   | CSRF-exempt, JSON or form. Returns 201 with token + receipt_url on JSON. |
| GET    | `/publish-request/receipt/{token}`         | none   | Token regex `[a-f0-9]{40}`; route + controller both guard. |
| GET    | `/admin/publish-requests`                  | admin  | Status filter via `?status=pending|approved|rejected|edited|all`. |
| GET    | `/admin/publish-requests/{id}/edit`        | admin  | Per-request review panel. |
| POST   | `/admin/publish-requests/{id}/decision`    | admin  | Writes status, curator_notes, decided_at, decided_by_user_id. |

## Token shape

`sha1(random_bytes(32) . microtime(true) . Str::random(16))` -> 40 hex chars.
Cheap; collision-resistant enough for an opaque receipt URL given the
ahg_publish_request unique key on `token`. We do not use the
URL-safe-base64 token shape `ahg-share-link` uses because we want zero risk
of `/` or `+` chars breaking the route regex.

## Slug catch-all

`/{slug}` in `packages/ahg-information-object-manage/routes/web.php` has a
hard-coded exclusion list. `#745` adds `publish-request$` next to
`requesttopublish$` so the new public endpoint is never swallowed by the
catch-all. `admin$` was already in the list.

## CSRF exemption

Added to `bootstrap/app.php` `validateCsrfTokens(except: [...])`:
`'publish-request'`. Anonymous submission has no session and therefore no
token to validate; pre-flight abuse protection (rate-limit + captcha) is
the right layer for hardening - see TODOs in
`PublishRequestController::submit()`.

## Auto-install

`AhgRequestPublishServiceProvider::ensureSchema()` calls
`Schema::hasTable('ahg_publish_request')` and, if missing, runs
`database/install_publish_request.sql` followed by
`database/seed_publish_request_status.sql`. Both files are idempotent
(`CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`) so re-boot is safe. Probe
+ install is wrapped in one outer try/catch per
`reference_ci_schema_hastable.md` so CI bootstrap never aborts on a
not-yet-connected DB.

## Notifications

`PublishRequestSubmittedNotification` (acknowledgement on submit) and
`PublishRequestDecisionNotification` (status change). Both implement
`via() = ['mail']` and rely on whatever `MAIL_MAILER` resolves to (log/array
in tests, smtp in prod). Sent best-effort - a failed delivery is logged via
`Log::warning` and never blocks the submission/decision write.

## Tests

`packages/ahg-request-publish/tests/Feature/PublishRequestSmokeTest.php`
covers: provider boot, token shape, all five new routes, admin middleware
on inbox + decision, notification render, malformed-token receipt 404.

## Release

```
cd /usr/share/nginx/heratio
git add packages/ahg-request-publish/ \
        packages/ahg-information-object-manage/routes/web.php \
        bootstrap/app.php phpunit.xml \
        docs/help/publish-requests.md docs/reference/publish-requests-745.md
./bin/release patch "Phase 1 publish-request workflow" --issue 745
```
