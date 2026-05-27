# COUNTER 5 + SUSHI Implementation Reference

**Issue:** heratio#766
**Package:** `packages/ahg-library/` (lives inside ahg-library, not a separate `ahg-counter` package - deferred)
**Status:** SUSHI client + TR/DR/PR generators shipped. Event capture, IR + TR_J1/TR_J3 variants, SUSHI server endpoint, scheduled email, conformance dossier pending.

## Surface

| Component | Path |
|---|---|
| Service (server-side reports) | `packages/ahg-library/src/Services/LibraryUsageService.php` (18k) |
| Service (SUSHI client) | `packages/ahg-library/src/Services/SushiService.php` (21k) |
| Controller | `packages/ahg-library/src/Controllers/LibraryUsageController.php` (10k) |
| Views | `packages/ahg-library/resources/views/usage/{index,tr,dr,harvest,subscriptions}.blade.php` |

## Routes

```
GET  /library-manage/usage                      -> library.usage
GET  /library-manage/usage/tr                   -> library.usage-tr
GET  /library-manage/usage/dr                   -> library.usage-dr
GET  /library-manage/usage/harvest              -> library.usage-harvest
GET  /library-manage/usage/subscriptions        -> library.usage-subscriptions
POST /library-manage/usage/subscriptions        -> library.usage-subscriptions-store
GET  /library-manage/usage/subscriptions/test   -> library.usage-subscriptions-test
GET  /library-manage/usage/export/{type}        -> library.usage-export
```

## Database

- `library_usage_subscription` - SUSHI subscription credentials (vendor URL, customer/requestor IDs, API key, report list)
- `library_usage_report` - harvested + locally-generated COUNTER reports (one row per report instance)
- Existing `audit_trail` is the source of catalogue-side usage events until per-event instrumentation lands.

## COUNTER R5 mapping

| Report | View | Generator | Note |
|---|---|---|---|
| TR | `/usage/tr` | `LibraryUsageService::generateTr()` | Title-level |
| DR | `/usage/dr` | `LibraryUsageService::generateDr()` | Database-level |
| PR | export only | `LibraryUsageService::generatePr()` | Platform aggregate |
| IR | pending | - | Item-level granularity needed |
| TR_J1 | pending | - | Journal title (excluding OA) |
| TR_J3 | pending | - | Journal title + section type |

## SUSHI client

`SushiService::harvest($subscriptionId, $reportId, $beginDate, $endDate)` issues a SUSHI request against the configured endpoint and writes the response to `library_usage_report`.

## Gaps vs heratio#766 acceptance

- [ ] Dedicated `packages/ahg-counter/` package (intentional deferral)
- [ ] Per-event instrumentation (page view, link click, download)
- [ ] IR + TR_J1 + TR_J3 generators
- [ ] SUSHI 5.0 server endpoint (currently client only)
- [ ] Scheduled email delivery
- [ ] COUNTER conformance certification dossier
