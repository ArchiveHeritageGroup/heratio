# ahg-rdm Feature 3 — RDM dashboard (as-built)

Built 2026-06-26 on heratio-dev. Epic #1337 Feature 3 (the last child). Feature 2
shipped a per-dataset compliance *scoreboard* (#1342); Feature 3 is the **roll-up
dashboard** above it - the RDM unit's at-a-glance operational + strategic view.

## What it is
A new auth-gated page at **`/research/datasets/dashboard`** (`rdm.datasets.dashboard`),
all in the net-new, unlocked `packages/ahg-rdm`. Read-only live aggregation; no new
tables. The compliance scoreboard stays as the per-dataset drill-down and is
cross-linked both ways (and from the datasets index).

## Surfaces
- **Defensibility one-liner** banner: N datasets · POPIA-flagged · restricted ·
  DMP-linked (%) · review-backlog badge.
- **KPI cards:** datasets, files deposited, POPIA-flagged, awaiting review (gate
  backlog), restricted, open access, DOIs minted, DMP-linked.
- **Charts (Chart.js 4.4 via jsDelivr CDN, `@push('js')` - the platform's existing
  dashboard pattern):**
  - POPIA verdict mix (doughnut: CLEAR / PERSONAL / SPECIAL_CATEGORY / unscanned)
  - Access disposition mix (doughnut: restrict / embargo / de-identify / release / undecided)
  - Detection method (doughnut: deterministic / lexicon / ner - the rule-vs-AI split)
  - Findings by PII type (horizontal bar)
  - Deposits over the last 12 months (zero-filled line)
- **Posture by faculty/institution** table (total / flagged / DMP per institution).
- **Human-gate backlog** table (datasets with pending findings, linked).
- **Recent deposits** table.

## Code
- **`DashboardService::overview()`** - the only new logic: one method returning all
  KPIs + breakdowns + trend + per-faculty + backlog + recent, as plain arrays ready
  for the view and for `@json` chart data. Guards: `dmp_id` column optional (DMP
  counts degrade to 0/—); `verdict`/`disposition` "remainder" buckets computed as
  total minus known values; deposits zero-filled across 12 months; institution
  `COALESCE(NULLIF(institution,''),'(unlinked)')`.
- **`DatasetController::dashboard()`** - thin: `view(... overview())`.
- **Route** placed before `/research/datasets/{id}` (id is `[0-9]+`-constrained, so
  no collision either way).
- Cross-links added to `compliance.blade` and `index.blade` headers; `ahg:rdm-demo`
  prints the dashboard URL.

## Chart rendering notes
- Mirrors `ahg-research/.../admin-statistics.blade.php`: `@push('js')` →
  `chart.umd.min.js` CDN → guarded `new Chart()` in an IIFE. Empty datasets are
  pruned and the canvas is replaced with a "no data yet" line, so the page is clean
  on a fresh install.
- CSP: same CDN + inline-script shape as the existing shipped dashboards, so the
  platform CSP already permits it.

## Verified on dev (2026-06-26)
`overview()` over the demo corpus: 5 datasets / 10 files / 2 flagged / 0 backlog /
2 restricted / 2 DOIs / 1 DMP-linked (20%); verdict + disposition + by-type
(special_category 8, sa_id 5, email/phone 4, …) + by-method (deterministic 13,
lexicon 8, ner 5) + 12-month trend + 2 institutions. Blade renders (95 KB, all 5
canvases + CDN), route 302→login when unauthenticated, full `ahg:rdm-demo` chain
still green.

## Epic status
Feature 2 (#1338–#1343) ✅ · Feature 1 (DMP link) ✅ · **Feature 3 (dashboard) ✅** —
epic #1337 fully delivered. Remaining items are post-epic follow-ups (scanned-PDF
OCR, binary-download ODRL gating, live DataCite mint).

## Follow-ups (not in Feature 3)
- A direct "RDM Dashboard" entry in the locked `ahg-reports` menu (today the reports
  menu links to "RDM Compliance"; the dashboard is reached from there / the index).
  Needs an `ahg-reports` unlock.
- Date-range / faculty filters on the dashboard (the scoreboard already filters).
