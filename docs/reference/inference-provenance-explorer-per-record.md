# Per-record AI Inference Provenance Explorer (issue #1201)

Public per-record read surface that shows which AI inferences contributed to ONE PUBLISHED archival record's metadata - the model, the gateway, when, and that a human remained accountable. Additive, read-only, in the non-locked `ahg-c2pa` package. Epic #1201 stays OPEN. It is the inference-provenance sibling of the per-record Authenticity Report (`/authenticity/{idOrSlug}`).

## Routes (all in `packages/ahg-c2pa/routes/web.php`, mounted via the package boot under the `web` group)

- `GET /inference-provenance/{idOrSlug}` -> `InferenceProvenanceController@show` (name `c2pa.inference.provenance`) - the HTML explorer page. `{idOrSlug}` matcher is `.+` so multi-segment slugs resolve.
- `GET /inference-provenance/{idOrSlug}.json` -> `@json` (name `c2pa.inference.provenance.json`) - machine companion, CORS-open. `{idOrSlug}` matcher `[^/]+` (numeric id or single-segment slug).

There is deliberately NO `/badge` / `.svg` surface here: nginx serves `*.svg` statically and would 404 before Laravel. JSON only.

### Catch-all safety

The locked IO slug catch-all `/{slug}` is anchored single-segment (`->where('slug', '...[a-z0-9-]*$')`). Every inference-provenance route is two or more segments (`/inference-provenance/...`), so the catch-all can never intercept them - identical reasoning to the sibling `/authenticity/...` and `/verify/...` routes in the same file. There is deliberately NO bare `/inference-provenance` route (a single-segment path would sit in the catch-all's lane); the explorer always needs a record reference. The `.json` literal is declared BEFORE the `{idOrSlug}` `.+` page route so it is never captured as a slug fragment. `.json` keeps its extension because nginx passes `*.json` through to Laravel. No exclusion-list edit was needed (and the exclusion list lives in the locked IO package).

## Services / tables reused (read-only, NOT rebuilt)

- `AhgC2pa\Services\InferenceProvenanceService` (NEW) - the consolidator. Owns no table, writes nothing, runs no AI, re-verifies nothing.
- `ahg_ai_inference` table (foundation from issue #61 / ADR-0002, shipped via `packages/ahg-provenance-ai/database/install.sql`) - one row per AI write: `service_name`, `model_name`, `model_version`, `endpoint`, `target_entity_type`/`target_entity_id`/`target_field`, `confidence`, `standard`, `user_id`, `occurred_at`. Read filtered to `target_entity_type='information_object'` for the record. Live DB has real rows (TRANSLATION via `opus-mt-ct2`, etc.).
- `ahg_ai_override` table (same foundation) - one row per human reviewer correction (`inference_id`, `reviewer_user_id`, `status`, `original_value`, `override_value`, `reason`, `occurred_at`). The latest override per inference is joined to derive the human-accountability outcome.
- `AhgProvenanceAi\Services\InferenceService` is the WRITER for those tables (not invoked here) - this explorer is purely the reader. The writer was NOT touched.
- `user` table - read-only `username` lookup to label the triggering user / reviewer; NULL user_id -> "automated / batch process".

The existing `ProvenanceTraceService` only counts AI *events* embedded in C2PA provenance records (`counts.ai`); it does NOT surface the dedicated `ahg_ai_inference` rows. This explorer fills exactly that gap - a per-record window onto the inference store itself.

## Human-accountability model (honest framing)

Per inference, derived from its latest `ahg_ai_override`:

| override                                   | review_state | label                              |
|--------------------------------------------|--------------|------------------------------------|
| none                                       | pending      | AI-suggested, not yet reviewed     |
| status=rejected                            | rejected     | Rejected by a curator              |
| applied/superseded, original == override   | accepted     | Reviewed and kept by a curator     |
| applied/superseded, original != override   | corrected    | Corrected by a curator             |

The page NEVER claims an AI output is correct - only that it was recorded (model + gateway + timing) and whether a human kept/corrected/rejected it. Counts: total, reviewed (accepted+corrected+rejected), corrected, rejected, pending, services, models.

## Published gate + resolution

Same contract as `AuthenticityReportService`: resolve numeric id or (multi-segment) slug via the `slug` table, require a `status` row with `type_id=158` and `status_id=160` (Published). Unknown OR unpublished -> `report()` returns null -> controller `abort(404)` (HTML) / 404 JSON. A published record with zero inferences -> populated report with empty `inferences` list -> dignified "No AI inference recorded for this record" empty state (HTTP 200). If `ahg_ai_inference` is absent on an older install, `available=false` -> "no inference store configured" empty state. The service never throws; the controller wraps `report()` in a try/catch and treats any fault as not-found.

## Files added

- `packages/ahg-c2pa/src/Services/InferenceProvenanceService.php`
- `packages/ahg-c2pa/src/Controllers/InferenceProvenanceController.php`
- `packages/ahg-c2pa/resources/views/inference-provenance/show.blade.php`
- `packages/ahg-c2pa/routes/web.php` (route group added; controller import)
- `docs/help/inference-provenance-explorer-user-guide.md`
- `docs/reference/inference-provenance-explorer-per-record.md` (this file)

## Constraints honoured

Read-only (SELECT only, no ALTER / no write to any live table). No locked path touched (`ahg-c2pa` is not in `.locked-paths`; `bin/check-locked` exits 0). `@extends('theme::layouts.1col')` + Bootstrap 5 + central theme; `url()` not hardcoded host; `__()` / `trans_choice` throughout; no em-dashes; international copy. AHG / Plain Sailing / AGPL headers with `@copyright "Plain Sailing Information Systems"`.
