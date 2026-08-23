# #1473 Phase 1: giving circulation a branch

Date: 2026-08-23
Author: Dr Johan Pieterse
Status: built and verified on heratio-dev; not yet released

## What was wrong

Heratio's circulation layer had no concept of a branch. `library_loan_rule`
carried `material_type` and `patron_type` and a unique key over exactly those
two columns, so an installation could hold one rule per (material, patron) pair
for the entire system. A service running more than one outlet could not say
that a title lends for 14 days at the central library and 7 at a branch.

Two branch columns did exist - `library_copy.branch` and
`library_hold.pickup_branch` - and both were inert. They were free text rather
than a key onto anything, nothing wrote them, no screen offered an input for
them, and across the whole `packages/ahg-library/src` tree there was one
reference to either: a SELECT in the OPAC holds list.

The gap came out of the SLIMS/Brocade evaluation for SITA, where it is the sole
BLOCKER in the gap register. It is not specific to that engagement - a
university with faculty libraries or a consortium union catalogue hits the same
wall.

## The decision: a branch is a repository

Branch identity is a `repository` row, not a new table. That is the SLIMS
evaluation's foundational decision - model 1,300 branches as repositories
inside one shared instance rather than one instance per branch - and it reuses
the tenancy substrate the catalogue side already runs on through
`information_object.repository_id`. Inventing a second notion of "where" would
have left the platform able to say which repository holds a bibliographic
record and unable to say which branch lent the copy, which is exactly the split
that produced the gap.

## Two NULL conventions, on purpose

`library_loan_rule.branch_id` is NOT NULL DEFAULT 0, where 0 means "applies at
every branch". Everywhere else `branch_id` is nullable and NULL means "not
attributed to a branch".

The sentinel is not cosmetic. That column is part of a UNIQUE key, and MySQL
treats NULLs as distinct - a nullable column would have permitted unlimited
duplicate system-default rules for the same (material, patron) pair, which is
the precise ambiguity the original `uk_type_patron` key existed to prevent. It
also matches the wildcard idiom the table already used for `patron_type`, where
`*` means all patron types.

## Resolution order

Most specific first: a rule for this exact branch beats an all-branches rule,
and within one branch a rule naming the patron type beats the `*` wildcard.

Branch outranks patron-type specificity. A branch's local policy overrides the
system default even when the default names the patron type exactly and the
branch rule is a wildcard. That is a decision rather than an accident, and
`test_branch_outranks_patron_type_specificity` pins it so a later change has to
be deliberate.

Verified live on dev against a real repository (id 630, The Archive And
Heritage Group) holding a 3-day student rule for monographs, over an
all-branches 21-day rule:

    branch 630 / student : 3 days      (the branch rule)
    branch 630 / public  : 21 days     (branch rule names students; falls back)
    branch 982 / student : 21 days     (a branch that never opted out)
    no branch  / student : 21 days

## Three copies of one query

The branch axis had to be added in three places to be added at all:
`resolveLoanDays`, `resolveMaxRenewals` and `generateOverdueFine` each carried
their own copy of the rule lookup. They had already drifted - two filtered
`is_loanable` and one did not.

All three now go through one `matchRule()`. This is the same failure mode as
the NER ledger, where two implementations of one write produced a divergence
nobody noticed for seven months. Three copies of a lookup is three chances to
miss the next axis.

## The bug this nearly shipped with

`LibraryBranch::available()` memoises a `Schema::hasColumn` result, because a
schema round-trip on the hot path of every checkout is not free. The first
version cached it once per process.

The library package's own feature harness swaps the default connection to an
in-memory SQLite database built from four named migrations - not including this
one. So a value cached against MySQL, where the column exists, was being
answered for SQLite, where it does not. Two existing tests started failing with
plausible wrong numbers rather than an error: `resolveLoanDays` fell through to
`LibrarySettings::defaultLoanDays()`, whose default is 14, and a fine came out
at 10.00 instead of 4.50 because no rule matched and the hardcoded 1.00/day
fallback applied.

The tell was that both suites passed alone and failed together. The cache is
now keyed by connection name, in `LibraryBranch` and in the service's
per-table equivalent. This is not a test accommodation: any process that
switches connections - an artisan command pointed at another database, a
multi-tenant request cycle - would have read the same stale answer.

## Why the core schema gained a file rather than an edit

CI builds its test database by loading `database/core/0*.sql` and never runs
package migrations, so a column that exists only in a migration is absent from
every CI run - queries naming it fail there while passing on a real instance.
The house pattern is to mirror new DDL into `00_core_schema.sql`.

When this was decided, that file carried another session's uncommitted work, so
the mirror went into a new `database/core/04_library_branch.sql` instead. It
matches the same `0*.sql` glob that `bin/install` and all five CI workflows
use, loads after `00` has created the tables, and can be staged and reverted on
its own.

That constraint has since lifted - the other session's changes turned out to be
dead code and were moved out of the tree. The separate file is kept anyway, now
on its own merits rather than out of necessity: folding five columns and an
index swap into a 24,000-line SQL file is more invasive and harder to review
than a guarded file already verified idempotent. Consolidating is now a free
choice, not a forced follow-up.

Every statement in it is guarded: MySQL 8 has no `ADD COLUMN IF NOT EXISTS`, so
each is wrapped in a prepared statement that becomes a no-op when the column or
index is already present. An instance can reach it in either order - core
schema first, or the package migration first - and both must work. Verified by
loading it twice against `heratio_test`.

## #1477 fixed in passing

The loan-rules screen bound `$r->loan_days` and `$r->max_items`. Neither column
exists. The real column is `loan_period_days`, and there is no per-rule item
cap at all, so both fell through to `?? 14` and `?? 5` - which happen to equal
the `library_default_loan_days` and `library_patron.max_checkouts` defaults.
Every rule therefore rendered as "14 days / 5 max items" and looked entirely
plausible while contradicting what circulation enforced. On dev, rule 1 is 21
days and electronic is 0 days and not loanable; the screen said 14 for both.

Max Items is removed rather than corrected. A per-rule item cap is a missing
concept, not a misnamed column, and displaying a number nothing enforces is
what caused the problem. #1475 covers adding it properly with enforcement.

The same shape was about to appear at the desk: `checkoutForm` previewed a loan
period resolved without a branch while `checkout()` lent on the branch's rule,
so the screen would have quoted one due date and the transaction written
another. The preview now resolves against the same branch, shows which branch
it used, and carries it through to the POST.

## What is not done

Phase 1 is the substrate. Still open on #1473:

- Scoping the circulation desk, patron browse, overdue lists and reports to the
  operator's branch, with a cross-branch view for consortium staff.
- Branch-aware notices - `LibraryOverdueNoticeService` and its templates still
  send as one library.
- Hold routing and queue position per pickup branch.
- An admin editor for branch loan rules; they are insertable but not editable
  through the UI.
- Dropping the legacy `library_copy.branch` and `library_hold.pickup_branch`
  text columns, once an operator has reconciled whatever the backfill could not
  match. The migration deliberately leaves them: dropping the only copy of that
  data in the same migration that adds its replacement would make a partial
  match unrecoverable.
- Optionally folding `04_library_branch.sql` into `00_core_schema.sql` to match
  the house pattern. No longer blocked; a judgement call about review cost.

## Verification

- Migration applies, rolls back and re-applies cleanly on dev; rollback
  restored all 11 loan rules and the original `uk_type_patron` key.
- `database/core/04_library_branch.sql` loads twice against `heratio_test` with
  no error and the correct column comments.
- 7 new cases in `tests/Feature/LibraryBranchCirculationTest.php`.
- 238 root Feature tests and 198 ahg-library package tests pass.
