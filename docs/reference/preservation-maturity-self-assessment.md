# Preservation maturity self-assessment (human-entered) - heratio#1244

**Summary.** Heratio has TWO distinct digital-preservation maturity surfaces, and they
must not be confused. (1) The **computed** dashboard at `/admin/preservation-maturity`
(route `preservation-maturity.index`) derives a maturity reading automatically from
concrete records via `AhgCore\Services\PreservationMaturityService` (NDSA five
functional areas, evidence-scored, read-only, never writes). (2) The **human-entered
self-assessment** at `/admin/preservation-self-assessment` (route
`preservation-self-assessment.*`) records what the institution says about itself when
it rates its own practice against a recognised model. They are siblings, not
duplicates: the self-assessment was added (heratio#1244 maturity slice) WITHOUT editing
the computed surface beyond adding one `Route::has`-guarded link.

Both live in the `ahg-core` package (NOT the locked `ahg-preservation`).

## Models (seeded as data, jurisdiction-neutral)

- **NDSA Levels of Digital Preservation** (`ndsa`) - 5 functional areas: storage,
  integrity (fixity), control (security), metadata, content (file formats). Rated 0..4.
- **DPC Rapid Assessment Model / DPC RAM** (`dpc_ram`) - 11 sections (3 organisational:
  organisational viability, policy and strategy, legal basis; 8 service-capability: IT
  capability, continuous improvement, acquisition/transfer/ingest, bitstream
  preservation, content preservation, metadata management, discovery and access, reuse).
  Each rated 0..4.
- Shared 0..4 scale labels: 0 Minimal awareness, 1 Awareness, 2 Basic, 3 Managed,
  4 Optimised.

The section catalogue + per-level descriptors are defined in
`PreservationSelfAssessmentService::models()`. NDSA must be 5 sections; DPC RAM must be
11.

## Storage (two NEW side tables; no ALTER of any existing table)

- `preservation_self_assessment` - one assessment RUN (model, title, assessor,
  assessor_user_id, assessment_date, status draft|complete, notes).
- `preservation_self_assessment_rating` - one rating per section
  (assessment_id soft-ref, section_key, level TINYINT 0..4, evidence). Unique key
  (assessment_id, section_key).

Both are soft-referenced (no FK into the AtoM/Qubit base schema) so they install on any
mid-migration DB. Install is `Schema::hasTable`-guarded in `AhgCoreServiceProvider::boot()`
inside ONE outer try/catch per `reference_ci_schema_hastable` (CI sqlite fallback safe),
loading `database/install_preservation_self_assessment.sql`.

## Dropdowns (ahg_dropdown, NEVER ENUM, NEVER hardcoded options)

- `assessment_model` group - the selectable models (dpc_ram, ndsa).
- `maturity_level` group - the 0..4 scale labels.

Seeded via `database/seed_preservation_self_assessment_dropdowns.sql` (INSERT IGNORE),
fired from the provider only when ahg_dropdown exists and the `assessment_model` group is
absent (no-op on every boot after the first). The service reads both groups live with a
built-in fallback when the table is unreadable; views render `<option>`s from these rows.

## Surfaces

- Landing (`index`): list of runs + maturity trend per model (CSS mini-bars) + a
  "start a new assessment" model-picker form.
- Rating form (`edit`): rate each section on radio level cards showing the level
  descriptor, plus an evidence textarea per section. Save draft / save + complete.
- Profile (`profile`): CSS-only SVG radar polygon + per-section bars + evidence +
  overall average. No charting library.
- Export (`export`): downloadable `.json` snapshot
  (schema `heratio.preservation_self_assessment.v1`).

## Wiring / safety

- Provider: `PreservationSelfAssessmentService` registered as a singleton in
  `AhgCoreServiceProvider::register()`; install block chained into the existing
  discovered provider's `boot()` (no new undiscovered provider).
- Routes: added to the existing `packages/ahg-core/routes/web.php`, in an
  `auth`-middleware group (admin-gated; anon -> 302 to login). All paths are
  MULTI-SEGMENT (`/admin/preservation-self-assessment...`) and `{id}` is `whereNumber`,
  so they can never collide with the single-segment `/{slug}` archival-record catch-all.
- Writes are confined to the two new tables via the service; no AtoM base table is
  written, no ALTER, no AI call. Level validated 0..4 (clamped in the service, validated
  in the controller). Out-of-model section keys are ignored on save. Resilient: missing
  tables -> calm empty state, never a 500.

## Scope note

WARC web-archiving (the other part of heratio#1244) is OUT OF SCOPE for this slice;
issue #1244 stays OPEN with the WARC work remaining.
