# Rights and access report - technical reference

Summary: an admin, read-only rights/access/policy-coverage aggregate at `/admin/rights-report` in `packages/ahg-reports`, mirroring the preservation-health / catalogue-growth report pattern (Schema::hasTable-guarded aggregate COUNTs, Route::has-gated links, `theme::layouts.2col`, a two-segment `/admin/...` route). It is deliberately distinct from its siblings: data-quality measures descriptive completeness, ai-usage measures where AI assisted, catalogue-growth measures size and composition, preservation-health measures preservation integrity, and THIS report measures rights, access and ODRL policy coverage. Reads the publication, rights and ODRL stores; writes nothing, runs no ALTER, creates no table.

## What it reads (DESCRIBE-verified)

Existing tables only (read-only):

- `information_object` - the archival descriptions. The real-record base excludes the synthetic root and its direct children (`id != 1 AND parent_id != 1`), matching the sibling reports. Columns used: `id`, `parent_id`.
- `status` - publication-status signal. Published = `type_id = 158 AND status_id = 160` (`status.object_id = information_object.id`). DESCRIBE columns: `object_id, type_id, status_id, id, serial_number`. A GROUP BY over `status.type_id` confirms `158` (publication) is the ONLY type present - there is NO separate access/accessibility status type on this schema, so none is invented; publication is the access baseline.
- `rights` - the Class-Table-Inheritance rights record. DESCRIBE columns: `id, start_date, end_date, basis_id, rights_holder_id, copyright_status_id, copyright_status_date, copyright_jurisdiction, statute_determination_date, statute_citation_id, source_culture`. The report uses `id` and `copyright_status_id`.
- `relation` - the generic many-to-many link. DESCRIBE columns: `id, subject_id, object_id, type_id, start_date, end_date, source_culture`. The record-to-rights link is `relation.subject_id = information_object.id`, `relation.object_id = rights.id`, `relation.type_id = 168` (the "Right" relation-type term, confirmed against `term_i18n` name "Right"). That same term id 168 is also used for actor-rights edges, so the report ONLY counts an edge when its object side joins an actual `rights` row AND its subject side joins an actual `information_object` - never a bare `type_id = 168` match.
- `term_i18n` - resolves `rights.copyright_status_id` to a label (culture `en`), e.g. "Under copyright", "Public domain".
- `research_rights_policy` - the ODRL policy store, owned by ahg-research's `OdrlService` and enforced by `OdrlPolicyMiddleware`. DESCRIBE columns: `id, target_type, target_id, policy_type, action_type, constraints_json, policy_json, created_by, created_at, updated_at`. The report uses `target_type`, `target_id`, `action_type`.

## ODRL model notes

- `policy_type` is `permission` / `prohibition`. `action_type` stores the BARE ODRL verb (`use`, `reproduce`, `distribute`, ...). `OdrlPolicyMiddleware` aliases these as `odrl:use` / `odrl:reproduce`, but the stored value is the bare verb, so the report matches `use` / `reproduce`, not `odrl:use`.
- `target_type` is `archival_description` (set by the middleware) or `collection` (a record-rooted collection, present in live data). Both resolve to a record by `target_id`. The report matches `['archival_description','collection','informationObject','information_object']` and joins `target_id` to `information_object` so a stale policy on a removed record is excluded.
- `OdrlService` default: a record with NO policy is OPEN access. The report counts governed records (a distinct real `target_id` under a policy) and frames everything else as open by default - it does NOT claim those records are restricted.

## Honest-signal notes

- There is NO separate access/accessibility status type in `status` (only `type_id = 158`). The report does not invent an accessibility breakdown; publication is the access baseline and is labelled as such.
- A record with no linked `rights` row is "no rights statement recorded", not "no rights".
- A `rights` row with no `copyright_status_id` is "(copyright status not recorded)", not inferred.
- A record with no ODRL policy is "open access by default", not "unknown" and not "restricted".

## Metrics (each a single grouped/aggregate COUNT or EXISTS)

| Metric | Query shape |
|---|---|
| Total records | `COUNT(*)` over the real-record base (the denominator for the shares) |
| Published / unpublished | `WHERE EXISTS` a published `status` row (158/160); unpublished = total - published |
| With / without a rights statement | `WHERE EXISTS` a `relation` (type 168) joined to `rights` on `object_id`, subject = record; without = total - with |
| Copyright status | `GROUP BY rights.copyright_status_id` over the IO-linked rights edges, `COUNT(DISTINCT io.id)`, label from `term_i18n`, share of the rights-bearing record set, "(copyright status not recorded)" for NULL |
| ODRL governed / open by default | `COUNT(DISTINCT io.id)` of policy `target_id` joined to a real record; open = total - governed |
| ODRL policies by action | `GROUP BY action_type` over the same policy-to-record join, `COUNT(DISTINCT io.id)` per action (use / reproduce / ...) |
| ODRL policy rows total | `COUNT(*)` over `research_rights_policy` |

Copyright-status term labels are resolved with a single batched `whereIn(...)->pluck()` over the small id set actually present - no per-row query in a loop.

## Properties

- Read-only: SELECT / aggregate only; no writes, no ALTER, no new table.
- Bounded: grouped aggregate COUNTs and EXISTS checks only; no per-row PHP scan of the catalogue.
- Resilient: the whole build is wrapped in try/catch and every store probe is Schema::hasTable-guarded; a missing `rights`/`relation` table hides the rights cards with an explanation, a missing `research_rights_policy` table hides the ODRL cards with an explanation, and an empty catalogue degrades to a calm "Nothing catalogued yet" state - never a 500.
- Admin-gated route (`admin` middleware = `App\Http\Middleware\RequireAdmin`, which aborts 403 for anonymous and non-admin users); two-segment `/admin/rights-report` (catch-all-safe against the `/{slug}` archival-record route).
- Links (back to Reports, across to the Trust & Transparency console, catalogue-growth and preservation-health) are Route::has-gated so no dead links render.
- International: ODRL actions, rights statements and copyright status are the jurisdiction-neutral vocabulary; no single country's copyright regime is assumed.

## Files

- `packages/ahg-reports/src/Controllers/RightsReportController.php`
- `packages/ahg-reports/resources/views/rights-report/index.blade.php`
- route in `packages/ahg-reports/routes/web.php` (`reports.rights-report`)
