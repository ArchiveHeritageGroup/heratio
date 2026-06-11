# Per-record Authenticity Report (issue #1209 north star)

Public per-record consolidation page that summarises every authenticity signal Heratio already holds for one PUBLISHED archival record. It is the readable front door above the existing /verify and /verify/record/{id}/trace surfaces. Additive, read-only, in the non-locked `ahg-c2pa` package. Epic #1209 stays open.

## Routes (all in `packages/ahg-c2pa/routes/web.php`, mounted via the package boot under `web`)

- `GET /authenticity/{idOrSlug}` -> `AuthenticityReportController@show` (name `c2pa.authenticity.report`) - the HTML report page. `{idOrSlug}` matcher is `.+` so multi-segment slugs resolve.
- `GET /authenticity/{idOrSlug}.json` -> `@json` (name `c2pa.authenticity.report.json`) - machine companion, CORS-open. `{idOrSlug}` matcher `[^/]+` (numeric id or single-segment slug).
- `GET /authenticity/{idOrSlug}/badge` -> `@badge` (name `c2pa.authenticity.report.badge`) - extensionless self-contained SVG trust badge, CORS-open.

### Catch-all safety

The locked IO slug catch-all `/{slug}` matches a SINGLE segment only. Every authenticity route is two or more segments (`/authenticity/...`), so the catch-all can never intercept them - the same reasoning as the sibling `/verify/...` routes in the same file. There is deliberately NO bare `/authenticity` route (a single-segment path would sit in the catch-all's lane); the report always needs a record reference. The `.json` and `badge` literals are declared BEFORE the `{idOrSlug}` `.+` page route so they are never captured as a slug fragment. The badge is extensionless on purpose: nginx serves `*.svg` statically and would 404 before Laravel; `.json` keeps its extension because nginx passes `*.json` through.

## Services reused (read-only, NOT rebuilt)

- `AhgC2pa\Services\AuthenticityReportService` (NEW) - the consolidator. Owns no table, performs no verification of its own.
- `AhgC2pa\Services\ProvenanceTraceService::trace($ioId)` - reused for the whole-record verdict + counts (records / signed / verified / invalid / digital_objects / ai). This already fans out across every digital object and verifies each signed provenance record LIVE.
- `AhgC2pa\Services\ProvenanceRecordService::verifyRecord()` - reached transitively through the trace service; does the live Ed25519 re-verification. Never called directly here.
- `C2paService::verify()` - reached transitively; the low-level signature/hash check.

## Three consolidated signals -> one confidence tier

1. Content credentials / C2PA signing: counts.signed / counts.verified / counts.invalid -> state verified | invalid | absent.
2. Provenance verification: the trace `summary` verdict (verified | partially | unsigned | invalid | none).
3. AI-inference provenance: counts.ai (AI events recorded in the record's provenance).

Confidence tier (derived from the verdict, never assumed):

| verdict     | confidence | label          | badge colour |
|-------------|------------|----------------|--------------|
| verified    | high       | High           | green        |
| partially   | partial    | Partial        | blue         |
| unsigned    | low        | Recorded, unsigned | amber    |
| invalid     | broken     | Verification failed | red     |
| none        | none       | No signals yet | neutral      |

## Honest framing (hard requirement)

The report never overclaims. It always renders a non-empty "what we cannot verify" list - including the universal line that content credentials attest to a file's history, NOT to whether what the source depicts is itself true. "What we can verify" is empty unless the verdict is high or partial. A record with no signals gets the dignified "no authenticity signals recorded yet" empty state, never an error.

## Published gate

`AuthenticityReportService::isPublished()` requires a `status` row with `type_id = 158` (publication status) and `status_id = 160` (Published) - identical to the public GLAM browse (`DisplayController::applyFilters`). Unknown OR unpublished -> `report()` returns null -> controller 404s (HTML) / 404 JSON. An unpublished record is indistinguishable from a missing one, so the surface cannot leak a draft/embargoed record.

## Resilience

Every path is `Schema::hasTable`-guarded and try/catch-wrapped. The page never 500s; the JSON endpoint never 500s (unknown/unpublished -> clean 404 JSON body); the badge never 500s (any fault -> neutral "none yet" badge, HTTP 200, so an embedding page never breaks). DI is via Laravel auto-wiring (no explicit provider binding needed - same as `VerifyRecordTraceController`).

## Files added

- `packages/ahg-c2pa/src/Services/AuthenticityReportService.php`
- `packages/ahg-c2pa/src/Controllers/AuthenticityReportController.php`
- `packages/ahg-c2pa/resources/views/authenticity/report.blade.php`
- `docs/help/authenticity-report-user-guide.md`
- `docs/reference/authenticity-report-per-record.md` (this file)

Changed: `packages/ahg-c2pa/routes/web.php` (added the `/authenticity` group + import).
