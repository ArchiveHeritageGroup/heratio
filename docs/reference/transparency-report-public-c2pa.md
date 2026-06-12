# Public catalogue-wide Transparency Report (/transparency) - ahg-c2pa

A public, read-only, institution-wide TRANSPARENCY REPORT at `/transparency`
(+ `/transparency.json`, CORS-open). It is the PUBLIC counterpart to the
operator-only admin trust console (`/admin/trust-console`) and to the
per-record trust dossier (`/trust-dossier/{record}`). Built on issue #1209,
lives in the (unlocked) `packages/ahg-c2pa` package. Additive, no DB writes,
no ALTER, no new table, no locked path touched.

## What it shows

Five dimensions over the PUBLISHED catalogue (status.type_id=158 /
status_id=160, synthetic root information_object.id=1 excluded), each a
headline count + share + a CSS progress bar (Bootstrap 5, no charting library):

1. Content credentials - published records carrying a C2PA manifest, plus
   master-files-signed detail. REUSED from `TrustDashboardService::snapshot()`
   (single source of truth with the `/trust` dashboard).
2. AI provenance - published records with >= 1 logged AI inference
   (`ahg_ai_inference`), plus reviewed share. Also REUSED from
   `TrustDashboardService`.
3. Integrity - published master files with a fixity baseline
   (>= 1 row in `preservation_fixity_check.digital_object_id`), plus the share
   with no failing check (`status` not in fail/failed/invalid/mismatch/error/
   tampered). NEW aggregate.
4. Preservation - published master objects with >= 1 PREMIS event
   (`preservation_event.digital_object_id`), plus total events. NEW aggregate.
5. Accessibility - published images with a genuine human-authored alt text
   (`image_alt_text.alt_text` non-empty). Image selection mirrors ahg-core
   `AccessibilityReportService` (mime_type LIKE 'image/%' OR known image
   extension). Curated store only - the IPTC/XMP caption fallback is NOT
   counted, to avoid overclaiming. NEW aggregate.

The master denominator is parentless digital objects with usage_id=140 (master)
or NULL, on published records - the same master selection
`TrustDashboardService` and the c2pa backfill command use.

## Files

- `src/Services/TransparencyReportService.php` - the aggregator. Reuses
  `TrustDashboardService` for dimensions 1-2; computes 3-5 with cheap
  COUNT / EXISTS only. Every table `Schema::hasTable`-guarded, every query
  block try/catch. Empty/zero-state shape always fully populated.
- `src/Controllers/TransparencyController.php` - `index()` (HTML) + `json()`
  (CORS-open). Both wrap the snapshot so a hard fault degrades to a calm empty
  state, never a 500. Mirrors `TrustController`.
- `resources/views/transparency/index.blade.php` - `theme::layouts.1col`,
  big numbers + progress bars, honest framing line per dimension, empty-state
  card, drill-down links to /trust, /verified-records, /open-data,
  /open-data/maturity (Route::has-gated), and a per-record /trust-dossier box.

## Route registration (catch-all safety)

`/transparency` is SINGLE-segment and is NOT in the locked IO `/{slug}`
catch-all's exclusion lookahead, so it would be shadowed if declared in boot().
Registered instead in `AhgC2paServiceProvider::register()` via
`callAfterResolving('router')` - the SAME pattern as `/trust`,
`/verified-records`, and `/content-credentials`. This defines the route during
the register phase, BEFORE the IO package loads its catch-all in boot(), so
Laravel's first-match-wins resolution always picks it. `/transparency.json` is
declared FIRST (its dot can never be captured by the catch-all's
`[a-z0-9-]*$`). Verified by an isolated `Illuminate\Routing\Router` match test:
`/transparency` and `/transparency.json` resolve to the c2pa routes, and a
normal record slug still resolves to `informationobject.show` (catch-all not
shadowed).

## Guarantees

Read-only. No DB writes, no ALTER, no new table, no AI calls, no re-verify.
Only grouped/EXISTS COUNTs - no per-row scan of the whole catalogue. Fresh
install -> calm "nothing measured yet"; missing layer -> that dimension shows
0 + "not active on this installation yet"; neither HTML nor JSON ever 500s.
International / jurisdiction-neutral. No locked path touched
(`./bin/check-locked` exit 0).
