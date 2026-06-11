# Trust Dashboard (collection-wide) - /trust

Public, read-only, collection-wide "trust at a glance" summary of verifiable authenticity. Lives in `packages/ahg-c2pa` (issue #1209, north star epic - left OPEN). It is the public trust SUMMARY, distinct from the admin-only authenticity-coverage report at `/admin/c2pa/coverage`, and broader than the existing `/verify` content-credentials front door because it also reports AI-inference coverage.

## Surfaces

- `GET /trust` - HTML dashboard (`TrustController::index` -> `ahg-c2pa::trust.index`).
- `GET /trust.json` - machine-readable companion, CORS-open (`Access-Control-Allow-Origin: *`), read-only GET (`TrustController::json`).

Both are single-segment paths. They are registered in `AhgC2paServiceProvider::register()` via `callAfterResolving('router')`, BEFORE the locked IO slug catch-all (`/{slug}` in `ahg-information-object-manage`) loads in `boot()`. First-match-wins resolution therefore always picks `/trust` and `/trust.json` - the same catch-all-safe pattern `/content-credentials` uses (see `reference_slug_catchall_route_precedence.md`). `.json` is declared before `/trust` so the literal extension can never be captured as a slug. The catch-all is NOT edited (it is locked).

## What it computes (cheap aggregate COUNTs only)

All figures are scoped to PUBLISHED records (`status.type_id=158` / `status_id=160`, the same gate the GLAM browse and per-record authenticity report use) and EXCLUDE the synthetic root `information_object.id=1`.

Service: `AhgC2pa\Services\TrustDashboardService::snapshot()`. Two halves:

**Content credentials** (over `ahg_c2pa_provenance` + `ahg_c2pa_manifest`, with `digital_object` as the master denominator):
- `published_records`, `masters_total` (parentless master/unset-usage digital objects on published IOs)
- `records_with_credentials`, `records_signed` (distinct published IOs)
- `masters_signed`, `masters_unsigned`, `signed_verified`, `signed_failed` (verified/failed split from the cached `sign_status` only - NO live crypto on a dashboard load)
- `coverage_pct` (signed masters / total masters), `credentials_pct` (records with creds / published), `verified_pct`
- `manifests_total`, `issuers` (distinct `kid`), `last_signed_at`

**AI inference** (over `ahg_ai_inference` + `ahg_ai_override`):
- `records_with_ai` (distinct published IOs with any inference), `inferences_total`
- `reviewed` (inferences with at least one override) vs `pending`
- `ai_coverage_pct`, `reviewed_pct`

`has_any_signal` drives the empty state ("authenticity signals are still being established").

## Reused read-only (cited)

- `AuthenticityStatsService` - the master-file denominator convention (parentless, `usage_id=140` or NULL) and the issuer/kid + sign-status patterns are mirrored.
- `InferenceProvenanceService` - the `ahg_ai_inference` column contract (`target_entity_type='information_object'`, `target_entity_id`, `service_name`, `occurred_at`) and the override-means-reviewed model.
- `AuthenticityReportService` / `ProvenanceTraceService` - the published gate (158/160) and the honest-framing posture (signed != true).

`TrustDashboardService` owns no table, writes nothing, runs no AI, and re-verifies nothing. Every table is `Schema::hasTable`-guarded and every query block is try/catch-wrapped, so a missing layer or unreachable DB yields the honest zero/empty-state shape, never a 500. `TrustController` additionally wraps the snapshot in a try/catch that falls back to a well-formed empty snapshot.

## Honest framing

Standing caveat (constant `TrustDashboardService::HONEST_CAVEAT`, reused verbatim in the view and the JSON): content credentials attest to a file's history - how it was captured and handled - not that the content itself is true, accurate, or complete. A signed record is a verifiable record, not a guarantee of what the source depicts. AI steps shown "not yet reviewed" are suggestions, never "verified".

## Verified aggregates (live DB, read-only, 2026-06-11)

377 published records; 260 master files; 1 record with content credentials; 1 signed (verified, 0 failed); 3 manifests; 1 signing key; coverage 0.4%; AI: 21 inferences across 2 records, 1 reviewed / 20 pending.
