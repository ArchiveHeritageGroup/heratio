# Grade Distribution — Research Module

Purpose

This note documents the current state of "Grade Distribution" functionality as it relates to the Research module, lists gaps and incomplete code found in the repository, and suggests pragmatic enhancements and a staged implementation plan. Place this file under docs/research so it is discoverable from the Research help index.

1. First look — gaps (what is missing now)

- No dedicated Grade Distribution service
  - There is no packages/ahg-research/src/Services/AssessmentGradeDistributionService.php or equivalent. Aggregation is performed ad‑hoc in controller logic or in JS in a few places (scatter). No centralised API for grade statistics exists.

- No scheduled recomputation or materialised snapshots
  - There is no scheduled job that computes grade‑distribution snapshots for large projects. Current pages compute aggregates on the fly which will scale poorly for large projects.

- Missing export and chart endpoints
  - No server-side CSV/JSON export endpoint that returns the grade distribution for a project. The frontend charts rely on inline aggregation and have no robust server API.

- Limited tests and no historical baselines
  - No PHPUnit tests covering grade-distribution calculations. No migration to store historical distribution snapshots.

- Incomplete provenance linkage
  - Grade items and derived distributions are not consistently linked to ai_provenance or research_activity_log entries when derived by AI or bulk jobs.

- UI not standardised across Research studios
  - Different modules (Writing Studio, Assessment, Claims) show different visualisations; there's no shared component or pattern for grade histograms, quartiles, or confidence bands.

2. Look at incomplete code (exact, repo-grounded)

- Search results (examples to inspect):
  - packages/ahg-research/src/Controllers/ResearchController.php — ad‑hoc aggregation snippets (groupBy/raw SQL), used by admin reports.
  - packages/ahg-research/src/Services/ResearchService.php — helper methods for counts but no distribution service.
  - packages/ahg-research/resources/views/research/analysis/grade_histogram.blade.php — a partial that renders a frontend chart but expects precomputed bins; no API to fetch bins on demand.
  - packages/ahg-research/tests/Feature — no test that asserts numerical correctness of any aggregation logic.

- Temporary/partial patches present in tmp/ (developer artifacts): tmp/admin_acl_full_remediation.patch, tmp/admin_acl_remediation.patch — not relevant to grading but indicate active editing.

3. Enhancements and suggestions (concrete)

A. Infrastructure
- Add AssessmentGradeDistributionService (server-side) responsible for:
  - computing per-project / per-section / per-assessor grade histograms and quantiles;
  - returning bin counts, percentiles, mean/stddev, and sample sizes;
  - exposing a stable API: GET /research/projects/{id}/grade-distribution[?section=xx&bins=10&since=2025-01-01].

- Add a scheduled job compute:assessment-grade-distribution that:
  - computes and stores materialised snapshots per project (daily/weekly) for large projects;
  - supports incremental recompute (since last updated id or updated_at);
  - stores snapshots in a new table research_grade_distribution_snapshots with columns: id, project_id, scope, bins_json, meta_json, computed_at.

B. API and export
- Implement API endpoints:
  - GET /api/research/projects/{id}/grade-distribution — returns latest snapshot or on‑the‑fly small-project computation.
  - GET /api/research/projects/{id}/grade-distribution.csv — exports CSV of bin ranges and counts.
  - POST /api/research/projects/{id}/grade-distribution/recompute — admin-only trigger.

C. UI components
- Create a shared Blade + Vue/React component `grade-distribution-card`:
  - shows histogram + histogram export button + quartiles + sample size + confidence interval; accessible and printable.
  - props: projectId, section, bins, showQuartiles, snapshotDate.

D. Provenance & audit
- When a distribution is produced by an AI process or bulk worker, create an ai_provenance row (provider, prompt, response, confidence) and a research_activity_log event linking snapshot id → provenance id.
- For manual recompute (admin action), write an activity_log row with actor and reason.

E. Tests & validation
- Unit tests for distribution math (bins, quantiles, mean/stddev) with deterministic datasets.
- Integration tests that call the API endpoint and assert CSV output shape and sample counts match DB queries.

F. Performance & scale
- Use a DB‑side aggregation approach for small datasets (GROUP BY floor(score / bin_size)). For large datasets use incremental materialised snapshots and Redis caching for hot endpoints.
- Ensure snapshot table has indexes on project_id + computed_at and JSON columns are stored as JSONB (if using Postgres) or TEXT (MySQL) with precomputed schema.

4. Staged implementation plan (PRs)

PR 1 — Service + API (small)
- Create AssessmentGradeDistributionService with computeDistribution($projectId, array $opts).
- Add controller endpoints (public read, admin recompute).
- Add simple feature test verifying computeDistribution on a seeded dataset.
- Files: packages/ahg-research/src/Services/AssessmentGradeDistributionService.php, packages/ahg-research/src/Controllers/GradeDistributionController.php, packages/ahg-research/tests/Feature/GradeDistributionTest.php
- Est. effort: 1–2 days.

PR 2 — Snapshot & job (medium)
- Add migration for research_grade_distribution_snapshots table.
- Add artisan command/Job compute:assessment-grade-distribution that saves snapshots.
- Add admin recompute endpoint to trigger job for a project.
- Est. effort: 1–2 days.

PR 3 — UI + export (medium)
- Add blade partial and frontend component grade-distribution-card; integrate into Project Studio and Reports.
- Add CSV export route and implement streaming CSV generation for large exports.
- Est. effort: 1–2 days.

PR 4 — Provenance, tests, polish (medium)
- Instrument provenance (ai_provenance entries) for AI-derived distributions and log activity for manual recomputes.
- Add comprehensive unit tests for math and integration tests for API + CSV.
- Add docs: docs/research/grade-distribution.md and help article describing assumptions and how to interpret bins and confidence intervals.
- Est. effort: 2–3 days.

Acceptance criteria

- API returns correct counts for seeded datasets; CSV exports match API output.  
- Snapshots exist and are used for large projects; recompute endpoint triggers job and creates snapshot.  
- UI component renders histogram + quartiles, accessible and mobile friendly.  
- All new code passes php -l and PHPUnit tests locally; no direct writes to core user/ACL tables introduced.

Files created (suggested)
- packages/ahg-research/src/Services/AssessmentGradeDistributionService.php  
- packages/ahg-research/src/Controllers/GradeDistributionController.php  
- packages/ahg-research/database/migrations/2026_06_XX_000002_create_grade_distribution_snapshots.php  
- packages/ahg-research/resources/views/research/partials/grade_distribution_card.blade.php  
- packages/ahg-research/tests/Feature/GradeDistributionTest.php  
- docs/research/grade-distribution.md (this file)

If you want I can:

- Create PR 1 now (service + simple API + unit test) and post the unified patch for review.  
- Or just add docs to the Research help index and stop.  

Pick next action:

1. Create PR 1 now (service + API + tests).  
2. Create PR 2 (snapshot + job) next.  
3. Create PR 3 (UI + export).  
4. Only add the doc to the help index and stop.