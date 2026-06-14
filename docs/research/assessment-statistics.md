# Assessment Statistics — Research Module

This document summarises the current state of the Assessment Statistics capability in the Research module, lists concrete gaps and incomplete code found in the repository, and suggests practical enhancements with an implementation plan.

1. Gaps (what is missing)

- No central AssessmentStatistics service: there is no single service that aggregates assessment results (condition scores, annotator counts, AI-suggestion acceptance rates, photo-diff deltas) across projects and objects. Data lives in several places (annotations, condition_assessment, ai_provenance) but no aggregator consolidates it for reporting.

- No long-running aggregation jobs / materialised views: heavy queries (per-project rollups, historical trend lines) are executed ad-hoc by controllers rather than precomputed or cached; this makes dashboards slow for large datasets.

- No unified schema for assessment metrics: different tables store related metrics with inconsistent naming and units (e.g. `score`, `condition_index`, `confidence`), making cross-table joins error-prone.

- Missing UI dashboard and export endpoints: no research-facing dashboard summarises assessment statistics (per-project, per-conservator, per-object), and no CSV/JSON export endpoints exist for analysts.

- Limited provenance linkage for metrics: statistical aggregates are not consistently linked back to provenance records (which annotation, which AI suggestion, which reviewer accepted/modified) making audit and reproducibility harder.

2. Incomplete / partial code (concrete repo findings)

- Annotation & Condition tables
  - `packages/ahg-research` and `packages/ahg-annotations` host condition/annotation tables and APIs, but there is no `AssessmentStatisticsService` that reads them and exposes rollups.

- ai_provenance usage
  - `ai_provenance` table exists in core, and some AI call sites create provenance rows, but there are gaps: not all AI-derived assessments (condition-suggestion scoring, image-diff deltas) write a provenance row with structured metadata (model, prompt, confidence, accepted boolean).

- No separate statistics schema
  - No `assessment_statistics` table or materialised view definition in migrations; ad-hoc SELECTs appear where rollups are needed (controller code, report builders).

- Dashboard views absent
  - No blade views under packages/ahg-research/resources/views/research/dashboard-assessment.* that present charts, trend lines, or CSV export links.

3. Suggested enhancements (prioritised)

Immediate (high-impact, low-effort)
- Add an AssessmentStatistics service interface and a concrete implementation that provides these methods: `projectRollup($projectId)`, `objectHistory($objectId)`, `annotatorMetrics($userId)`, `aiAcceptanceRates($projectId)`, `timeSeries($projectId, $metric, $from, $to)`.

- Implement consistent provenance writes for AI-derived metrics: ensure every AI suggestion that affects an assessment writes a structured ai_provenance record with fields: `subject_type`, `subject_id`, `metric`, `value`, `model`, `prompt`, `confidence`, `accepted_by` (nullable), `accepted_at` (nullable).

- Create a lightweight route and controller endpoint for CSV export: `GET /research/{project}/assessment-stats.csv` that streams the rollup for analysts.

Near-term (medium effort)
- Add a migration to create an `assessment_statistics` table (or materialised view) to store daily snapshots per project/object with columns: `date`, `project_id`, `object_id`, `metric_name`, `metric_value`, `sample_count`, `computed_at`.

- Implement a scheduled job `php artisan research:compute-assessment-stats --daily` that computes and upserts daily snapshots into the table. Use chunked queries to avoid memory spikes.

- Add a dashboard blade at `packages/ahg-research/resources/views/research/assessment_dashboard.blade.php` that renders charts (Chart.js) for: condition-score distribution, annotator throughput, AI acceptance rate over time, photo-diff failure rate.

Longer-term (strategic)
- Expose a Prometheus-friendly metrics endpoint for operational monitoring (histograms for computation duration, counters for AI calls and acceptance events) so SRE can alert on anomalies.

- Provide a BI-ready export (Parquet or compressed CSV) for periodic analytics runs (Data Warehouse ingestion).

- Add model drift checks for AI-suggestion confidence vs acceptance rate (flag if model confidence diverges from human acceptance over time).

4. Concrete implementation plan (3-stage, reviewable PRs)

PR A — service + provenance fixes (small)
- Add `packages/ahg-research/src/Services/AssessmentStatisticsServiceInterface.php` and `packages/ahg-research/src/Services/AssessmentStatisticsService.php` (initial methods: `projectRollup`, `objectHistory`, `aiAcceptanceRates`).
- Instrument top AI call sites in the research package to write structured ai_provenance rows for assessment-affecting suggestions. Add unit tests for provenance writes.
- Add a controller endpoint `ResearchAssessmentController@exportCsv` and a route `/research/{id}/assessment/export.csv`.
- Estimate: 1–2 days.

PR B — snapshots + scheduled job (medium)
- Add migration: `create_assessment_statistics_table` (date, project_id, object_id, metric_name, metric_value, sample_count, computed_at).
- Implement an artisan command `research:compute-assessment-stats` and schedule it nightly in the package scheduler registration.
- Add upsert logic and chunked processing for large datasets. Add unit/integration tests targeting correctness and idempotency.
- Estimate: 2–4 days.

PR C — dashboard + monitoring (UI + ops)
- Add blade view `assessment_dashboard.blade.php` and a controller `ResearchAssessmentController@index` that reads from snapshots for chart rendering (Chart.js). Add CSV download buttons and date-range filters.
- Add Prometheus metrics instrumentation (or simple /metrics endpoint) for computation duration and AI acceptance events.
- Add acceptance tests for the dashboard route and visual smoke tests for charts (server-side data assertions only).
- Estimate: 2–4 days.

5. Acceptance criteria (done)
- A service exists that returns consistent rollups for a project or object. Example: `AssessmentStatisticsService::projectRollup(42)` returns JSON with keys `average_condition`, `median_condition`, `annotator_counts`, `ai_acceptance_rate`.

- All AI-derived assessment changes write ai_provenance entries with structured fields; the dashboard can link any aggregate back to its provenance rows.

- A scheduled job computes daily snapshots and the dashboard loads quickly (< 500 ms for cached snapshots).

- Analysts can download CSV exports for any project/day range that includes the raw counts and sample sizes.

6. Files added
- docs/research/assessment-statistics.md (this file) — summary, gaps, suggestions, and PR plan.

Status: very good

Outstanding issue to work on (2–5 items)
1. Create PR A: add AssessmentStatisticsService + provenance instrumentation on AI call sites.  
2. Create PR B: add migration + compute-assessment-stats command + nightly schedule.  
3. Create PR C: add assessment dashboard view + CSV export endpoint.  
4. Add CI feature tests for assessment stats export and provenance linkage.

Reply with the number (1–4) to pick which I should start on next.