# Session handover: #1473, #1477-#1483 and the Blade binding defect class

Date: 2026-08-23 to 2026-08-24
Author: Dr Johan Pieterse
Releases: v1.154.633 - v1.154.653 (sixteen), all deployed to heratio.org and sasa and verified there

## The one thing worth carrying forward

Nearly every defect this session came from **one shape**: a name that diverges
between where a value is produced and where it is read, failing into a fallback
instead of an error.

It appears at four gates, and any one of them silently drops the value:

1. the form field name
2. the controller's `$request->only()` / validator whitelist
3. the service's insert/update whitelist
4. the column

`repository_type` needed fixing at all four. The rule adopted throughout: **the
form field name and the column name must be identical**, because that is the
only version a tool can check.

`tools/scan-blade-bindings.php` finds the read-side half. `--check` runs in CI
(`code-quality`) and fails only on something NEW, so the population cannot grow.

## What the numbers did

| | findings |
| --- | --- |
| #1478 when filed | 101 |
| after class A (input-discarding) | 89 |
| after the numeric batch | 71 |
| end of session | **24** |

Roughly a third of each batch was dead code, a third a rename, a third a
feature that needed a decision. The remaining 24 include six `computed_data`
(the landing-page per-block-type resolver, a feature) and eighteen singletons
needing one trace each.

## Defects that were worse than the finding suggested

The scanner points at a symptom. Several times the page behind it was broken
outright:

- **`ExhibitionService::get()` threw for every exhibition.** Five queries
  ordered by `sort_order` and one by `category`; no `exhibition_*` table has
  either column. `ORDER BY` on a missing column is a hard error, so the
  exhibition detail view was down entirely. Found while chasing `stop_count`.
- **The security-clearance page 500'd on every request** (`$targetUser` never
  passed) and its required clearance-level select had zero options
  (`$classifications` never passed). The v1.154.643 fix that made that form
  *persist* vetting evidence was correct and sitting on an unreachable page.
- **The marketplace revenue report showed 0 for every figure** - the service
  returns an array, the view read it as an object on all four lines, and two of
  the four keys were wrong. Real data behind it: R37,000 revenue, R3,700
  commission, R33,300 seller payouts.
- **The PII scan screen reported "No PII detected" on every record ever shown**,
  because `array_merge()` folded a flat list into a keyed array.
- **The POPIA compliance score was invisible** - white text on a gradient that
  never painted. The value had been computed correctly all along.
- **Two audit trails were written and never read**: `user_security_clearance_log`
  and the object classification history.

## Data corruption, not data loss

Two forms were actively overwriting configured values, because the controllers
already read the correct names *with defaults*:

- editing a **researcher type** reset its booking limits to 14 days / 4 hours /
  10 items
- editing a **federation peer** reset harvest interval to 24h, cleared the
  default set, and reset the metadata prefix to `oai_dc`

## Things I got wrong, recorded so nobody repeats them

- **I filed #1480 on a false premise.** I claimed the NER writer hardcodes
  confidence. It does not, and its comments say so. The 1.0 values stop at
  2026-05-05; everything since 2026-08-06 is NULL. The real defect was
  `round(null * 100)` rendering as **0% in red** - "no measurement" shown as
  "confident this is wrong". Corrected on the issue.
- **I introduced a third instance of #1478 while fixing the second**, emitting
  `risk` where the view reads `risk_level`.
- **My v1.154.643 fix blinded the scanner** to a live defect: adding
  `expiry_date` to a validator satisfied its "producible anywhere?" test for a
  binding that was still broken. Only the browser caught it.
- **A basename-grep orphan heuristic flagged live templates.** The landing-page
  block templates are reached by a DYNAMIC `@include`. Had I trusted it I would
  have deleted working code. Every deletion since was confirmed by hand.
- **Fuzzy matching is worse than useless here.** It proposed `computed_data` ->
  `computed_at`, `creator_id` -> `curator_id`, `relation_dates` ->
  `revaluation_date`. Every real fix came from tracing the query.

**Playwright earned its place.** Driving the clearance form through a browser
found three defects that reading the code had missed. Inspection is not
verification.

## #1473 branch-aware circulation: complete

Phases 1 and 2 plus the remainder. A branch is a `repository` row.
`library_loan_rule.branch_id` uses 0 as an "all branches" sentinel (it is in a
UNIQUE key and MySQL treats NULLs as distinct); everywhere else NULL means
unattributed. **`operatorBranchId()` returning null means EVERY branch** - both
the consortium view and a single-outlet service - and must never be read as an
empty scope.

A **fourth** copy of the loan-rule lookup was found in the notice service,
ignoring the branch axis, quoting patrons the wrong fine rate. All four now go
through one `ruleFor()`.

Hold queue *position* is per pickup branch while the *cap* stays service-wide.
`library_loan_rule` had no create, update or delete anywhere - there is now an
editor.

Still open: an administrator override forcing a staffer to one branch, and
dropping the legacy free-text `library_copy.branch` / `library_hold.pickup_branch`.

## #1483 ahg-harris-matrix: built and DORMANT

A separate plugin porting the AtoM Harris Matrix work - six-check consistency
report, LST import, GraphViz DOT, Harris Matrix Data Package, deposit/interface
typing. Reads `ahg-archaeology`'s tables, adds none.

**It is deployed but inactive.** The PSR-4 entry lives in `composer.json`, which
is `skip-worktree` on dev and excluded by `bin/release` because dev's copy
carries `ahg/mva-claims: @dev` that prod does not have. The provider is
`class_exists()`-guarded, so the code sits harmless. Activating needs one line
in each instance's `composer.json` plus `composer dump-autoload`.

**This is the trap for any new package**: the code ships, the autoload does not.

## Other operational notes

- **Concurrent release collisions happened four times.** Another session was
  releasing docs throughout. Recovery: delete the stale local tag, `reset --hard`
  to the fetched remote, re-apply your files from a `format-patch`, re-release as
  the next number. Do the git work as `www-data`; a root `fetch` leaves
  root-owned `.git` objects that break the next release.
- **`museum_metadata` is at ~98% of InnoDB's 65,535-byte row limit.** New columns
  there must be TEXT. A VARCHAR attempt failed *partway*, leaving three columns
  created and the migration unrecorded.
- **CI builds its test DB from `database/core/0*.sql` and never runs
  migrations**, so new DDL must be mirrored there. This session added
  `04_library_branch.sql`, `05_museum_cco_fields.sql`, `06_form_field_columns.sql`
  and `07_research_tools_columns.sql`.

## Open

- **#1478** - 24 findings, CI gate holding the ceiling
- **#1481** - heritage `analytics-search` stub; `display_as_compound` (now one
  view, was three)
- **#1483** - built, dormant, needs the autoload line per instance
- **#1473** - the two deferred items above
- Trust Score's scoring weights (reliability 40 / completeness 30 / metric 30)
  are **my heuristic, not a specification** - review before researchers see it
- The 2,003 legacy NER rows at confidence 1.0 are untouched; backfilling would
  destroy data on an inference about what changed on 5 May
