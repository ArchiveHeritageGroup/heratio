# Collection timeline - technical reference

Summary: a public, read-only date-distribution surface at `/timeline` (+ `/timeline.json`) in `packages/ahg-semantic-search`, mirroring the `/themes` registration. Shipped Heratio v1.142.52.

## Date source + bucketing

Derives the year from `YEAR(MIN(event.start_date))` per published object (the earliest dated event), buckets by century, and drills a dense century to its decades. A published record with no event carrying a usable `start_date` year is reported in an honest "undated" bucket, never dropped.

The whole timeline is three cheap bounded aggregates - century GROUP BY, decade GROUP BY for the drill-down, and one COUNT for the undated group - with arithmetic guards against absurd year spans. No per-row PHP scan of the catalogue.

Published gate: status type_id=158 status_id=160, root id=1 excluded.

## Routes + catch-all safety

Registered in `AhgSemanticSearchServiceProvider::register()` via `callAfterResolving('router')`, exactly like `/themes` and `/related`:

- `GET /timeline.json` -> `TimelineController::json` (`timeline.json`) - declared FIRST, dotted, CORS-open.
- `GET /timeline` -> `TimelineController::index` (`timeline.index`) - single-segment, bound in `register()` so it wins ahead of the single-segment `/{slug}` archival-record catch-all in ahg-information-object-manage (first-registered-wins). Verified live: `/timeline` 200, `/timeline.json` 200, and a normal record slug still resolves.

Each century bucket links into `/glam/browse` filtered to its year range where the browse date filter exists (otherwise the bar renders without a link - no dead link).

## Properties

Read-only (SELECT/aggregate only; no writes, no ALTER, no new table); `url()`/`route()` not hardcoded host; Bootstrap 5 + central theme; AGPL / Plain Sailing headers; international.

## Provenance note

The TimelineController/Service/view were authored by an agent that terminated on an account session limit before wiring its routes; the route registration in the provider was completed by hand following the `/themes` pattern, and the slice was verified live before release.
