# Collections-wide preservation triage (#1200)

A holding-wide preservation priority list. Every assessed record is scored on its **latest**
condition report and ranked worst-first, so a conservator sees what to treat/digitise next
across the whole collection rather than one room at a time. Scales the exhibition conservation
work (#1188/#1189) to the entire holding.

## Where it lives (ahg-preservation)

- `PreservationTriageService::triage($limit)` -> `{items, summary}`.
  - Reads `condition_report` joined to `information_object_i18n` + `slug`, newest-first, keeping
    only the latest report per object.
  - **Risk score (0-100)** = rating base + priority bump + overdue + staleness:
    - rating (case-insensitive): excellent 0, good 15, fair 45, poor 80, bad/critical 95,
      damaged 90, unstable 85; unknown = 40.
    - priority: low 0, normal/medium 5, high 15, urgent/critical 25.
    - `next_check_date` in the past: +20.
    - staleness: +5 per year since assessment (capped +20).
  - **Bands:** >=75 Critical, >=50 High, >=25 Medium, else Low.
  - **summary:** band counts, assessed count, total records (`information_object.parent_id != 1`),
    never-assessed (total - assessed), overdue count.
- `PreservationController::triage()` -> route `preservation.triage`
  (`/admin/preservation/triage`, admin), linked in the preservation `_menu` nav.
- View `ahg-preservation::triage`: band + assessed/overdue/total/never-assessed summary cards and
  a risk-ranked table (record link, rating, priority, last assessed, next check w/ overdue flag,
  recommendation).

## First slice / follow-ups

First slice is **condition-report driven** (acceptance: a ranked, risk-scored at-risk list).
Planned next axes, noted in the issue and on the page:
- digital **format-obsolescence** risk (PRONOM via `ahg-preservation`'s identification) folded in;
- **budget-aware** allocation (cost/effort per item -> what fits a conservation budget);
- the **forecast engine** (#1147) degradation curves as a forward-looking score.

`ahg-preservation` is a locked package; this shipped via a one-shot unlock.
