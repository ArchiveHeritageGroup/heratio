# Per-record Trust Dossier (issues #1209 / #1201, next slice)

Public per-record read surface that UNIFIES the three per-record trust surfaces - the Authenticity Report (C2PA content credentials / signing), the AI Inference Provenance Explorer, and the Preservation Timeline (PREMIS lifecycle) - onto ONE print-friendly page plus a machine companion, topped by an honest "what can and cannot be verified about this record" statement that NEVER overclaims. The one-stop "defence dossier" for ONE PUBLISHED archival record. Additive, read-only, in the non-locked `ahg-c2pa` package. Epics #1201 / #1209 stay OPEN.

## Routes (in `packages/ahg-c2pa/routes/web.php`, mounted via the package boot under the `web` group)

- `GET /trust-dossier/{idOrSlug}` -> `TrustDossierController@show` (name `c2pa.trust.dossier`) - the HTML dossier page. `{idOrSlug}` matcher is `.+` so multi-segment slugs resolve.
- `GET /trust-dossier/{idOrSlug}.json` -> `@json` (name `c2pa.trust.dossier.json`) - machine companion, CORS-open. `{idOrSlug}` matcher `[^/]+` (numeric id or single-segment slug).

There is deliberately NO `/badge` / `.svg` surface here (nginx serves `*.svg` statically and would 404 before Laravel). JSON only.

### Catch-all safety

The locked IO slug catch-all `/{slug}` is anchored single-segment. Every trust-dossier route is two or more segments (`/trust-dossier/...`), so the catch-all can never intercept them - identical reasoning to the sibling `/inference-provenance/...`, `/preservation-timeline/...`, `/authenticity/...`, and `/verify/...` routes in the same file. There is deliberately NO bare `/trust-dossier` route (a single-segment path would sit in the catch-all's lane); the dossier always needs a record reference. The `.json` literal is declared BEFORE the `{idOrSlug}` `.+` page route so it is never captured as a slug fragment. `.json` keeps its extension because nginx passes `*.json` through to Laravel. No exclusion-list edit was needed (the exclusion list lives in the locked IO package). Because the route is multi-segment, it lives in `routes/web.php` (NOT the `register()` + `callAfterResolving('router')` path that the single-segment `/trust`, `/content-credentials`, and `/verified-records` routes need).

## Services REUSED (read-only, NOT rebuilt; cited)

`AhgC2pa\Services\TrustDossierService` (NEW, in non-locked `ahg-c2pa`) is a pure READ-ONLY COMPOSER. It owns no table, writes nothing, signs nothing, runs no AI, runs no preservation action, and re-implements NONE of the sub-services' queries or verdict logic. It composes the three existing per-record services' own `report()` methods:

- `AuthenticityReportService::report($idOrSlug)` (C2PA layer) - content credentials / signing, the whole-record provenance verdict, and the honest `can_verify` / `cannot_verify` lists. **This is also the dossier's single resolve point / published gate**: when it returns null (unknown OR unpublished) the whole dossier is null -> 404. The gate is delegated, never re-derived. (That service in turn reuses `ProvenanceTraceService` -> `ProvenanceRecordService` -> `C2paService::verify`.)
- `InferenceProvenanceService::report($idOrSlug)` (AI-inference layer) - which AI inferences touched the record's metadata (model / gateway / when / human-accountability), read-only over `ahg_ai_inference` + `ahg_ai_override`.
- `PreservationTimelineService::report($idOrSlug)` (preservation layer) - the recorded PREMIS preservation lifecycle, read-only over the LOCKED `ahg-preservation` stores (`preservation_event` etc.).

No new DB query is added by the dossier beyond what the three sub-services already run. No ALTER, no write, no duplication of the sub-services' logic.

### Resilience model

The authenticity layer resolves the record (single resolve point). The other two layers are optional contributors: each `report()` call is wrapped in a per-layer guard (`safeReport()`), so a missing store, a faulting sub-service, or a thrown exception degrades ONLY that section to a "not available" note. The page never 500s; the JSON companion never 500s. The `section_status` map (`authenticity`/`inference`/`preservation` booleans) records which layers contributed. The AtoM/Qubit root object (id=1) is excluded as not-a-record (the same contract the preservation sibling enforces), even if it is marked published in a given DB.

## Consolidated dossier structure

```
object            : { id, identifier, title, slug }   (public-safe identity, once)
headline          : { verdict, label, statement }     (honest top-line)
can_verify[]      : verbatim from AuthenticityReportService (live verdict)
cannot_verify[]   : verbatim from AuthenticityReportService (always non-empty)
sections          : { authenticity, inference, preservation }  (each = that service's report() array, or null)
section_status    : { authenticity:true, inference:bool, preservation:bool }
links             : { authenticity, inference, preservation, dossier, dossier_json, record }  (all via url())
generated_at      : ISO-8601 UTC
```

### Honest top-line (`headline`)

The headline confidence `verdict` / `label` is the authenticity layer's own confidence tier (`high` / `partial` / `low` / `broken` / `none`) - carried over verbatim, never strengthened. The `statement` starts from the authenticity layer's own summary sentence, then appends a qualifying clause from the AI layer (only when AI steps are recorded, noting whether any await human review) and from the preservation layer (only when events are recorded, noting whether any step reported a failure). It adds no new judgement; each clause is true only when its layer recorded something. Verified live: a record with NO content credentials but a preservation lifecycle correctly headlines "None recorded" for authenticity while still surfacing the preservation clause - it never overclaims authenticity it does not have.

## Print + JSON forms

- **Print-friendly:** a `@media print` block (`.dossier-no-print`) drops the action bar and the per-section "Open full report" buttons, keeps the evidence cards, and avoids card page-breaks. A "Print or save as PDF" button calls `window.print()`. No PDF library - browser save-to-PDF.
- **JSON companion:** `TrustDossierController@json` returns the same consolidated structure (record identity once at top, headline, can/cannot-verify, `section_status`, the three sections, links, `generated_at`). CORS-open (`Access-Control-Allow-Origin: *`, `GET, OPTIONS`, `nosniff`). Unknown / unpublished -> `404` JSON `{ found:false }`.

## Empty-states (per section)

- Authenticity null -> "The authenticity layer is not available for this record." (only when the resolver-of-record itself faulted, which would already 404; in practice always present).
- AI inference: store unavailable -> "no AI inference provenance store configured"; available but zero rows -> "No AI inference recorded for this record" (absence shown as absence).
- Preservation: layer null -> "not available"; zero events -> "No preservation events recorded yet."

## Cross-links (dossier <-> the three surfaces)

- The dossier links OUT to each full report (`/authenticity/{ref}`, `/inference-provenance/{ref}`, `/preservation-timeline/{ref}`) and to the record.
- Each of the three per-record blades (`authenticity/report.blade.php`, `inference-provenance/show.blade.php`, `preservation-timeline/show.blade.php`) gains a primary "See the full trust dossier" button linking to `/trust-dossier/{slug-or-id}` via `url()`. These three blades are in the non-locked `ahg-c2pa` package (NOT the locked IO show tree).

## Files added / changed (all under `packages/ahg-c2pa/` + `docs/`)

- NEW `src/Services/TrustDossierService.php` - the read-only composer.
- NEW `src/Controllers/TrustDossierController.php` - `show()` (HTML) + `json()` (CORS-open).
- NEW `resources/views/trust-dossier/show.blade.php` - Bootstrap 5 + central theme, print-friendly, per-section empty states.
- CHANGED `routes/web.php` - the `/trust-dossier` group (`.json` first, `.+` page last) + the controller import.
- CHANGED `resources/views/authenticity/report.blade.php`, `inference-provenance/show.blade.php`, `preservation-timeline/show.blade.php` - reciprocal dossier link.
- NEW `docs/help/trust-dossier-user-guide.md`, `docs/reference/trust-dossier-per-record.md`.

## Constraints honoured

AHG / Plain Sailing / AGPL headers; `@copyright "Plain Sailing Information Systems"`; no em-dashes; international copy (no jurisdiction assumptions); read-only, no DB writes, no ALTER, no duplication of the sub-services' logic; `url()` (no hardcoded host); honest framing, no overclaiming. Touches no locked path (`bin/check-locked` exit 0). Worktree only - no commit / push / release.
