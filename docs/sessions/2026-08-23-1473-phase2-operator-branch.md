# #1473 Phase 2: whose counter is this?

Date: 2026-08-23
Author: Dr Johan Pieterse
Status: built and verified on heratio-dev

Phase 1 gave every transaction a branch. Nothing yet said which branch the
person at the counter was standing in, so the desk and the overdue list still
showed the whole service to everyone.

## There was nothing to reuse

No user-to-repository link exists anywhere in the schema. The nearest thing,
`ahg_tenant.repository_id`, maps an entire tenant to one repository - the wrong
grain, because a multi-branch library service is ONE tenant with MANY branches.
Binding operator scope to the tenant would have made the branch axis
unreachable for exactly the deployment it was built for.

So Phase 2 adds `library_staff_branch`: one row per user, carrying a home
branch and an `all_branches` flag for the consortium supervisor who genuinely
needs to see across outlets.

One row per user, not a many-to-many roster. The question being answered is
"where is this person working now", which has one answer at a time. A rostering
model can replace it later without breaking callers, because every read goes
through `LibraryBranch` rather than touching the table.

## No admin screen, on purpose

The operator picks their branch from a selector on the circulation desk. The
choice holds for the shift in session and is persisted so the next sign-in
starts at the same counter. Covering another branch for an afternoon therefore
does not require an administrator, and there is no half-built settings page
that someone has to find before circulation works.

The selector only appears where the branch axis exists AND more than one
repository is defined. A single-outlet service is not asked to choose between
one option - a control with a single choice reads as an unfinished setting.

It sits behind plain `auth` rather than `acl:update`: choosing where you are
standing is not a change to catalogue data, and a read-only staffer still needs
their lists scoped to their own counter.

## null means every branch, never "no access"

This is the distinction the whole phase turns on. `operatorBranchId()` returns
null both for the consortium view and for a single-outlet service that has
never named a branch. If any caller ever reads that null as an empty scope,
every existing installation goes blank on upgrade.

`operatorSeesAllBranches()` exists to tell those two states apart, because the
screens word them differently - "Loans from every branch" against "Loans made
at X" - even though both scope the same way.

## History does not vanish

A loan made before Phase 1 has `branch_id` NULL. Filtering strictly on the
checkout's branch would have dropped every historical loan out of its own
branch's list the moment scoping was switched on, which looks exactly like data
loss to the person at the counter.

`scopeToBranch()` therefore matches a loan whose own branch is the one asked
for, OR one with no branch whose copy is shelved there. Pinned by
`test_a_loan_predating_the_branch_column_is_still_listed_via_its_copy`.

## The #1477 defect, again, on the overdue screen

Found while wiring the branch banner into that view. `overdue.blade.php` bound
`$o->patron_name`, `$o->days_overdue` and `$o->fine_amount`. None of the three
was ever selected by `listOverdue()`, which returns `first_name`, `last_name`,
`due_date`, `barcode`, `call_number` and `title`.

So the Overdue Items screen showed a blank patron, **0 days overdue** and
**0.00 fine** on every row, for as long as the screen has existed. Two of the
five columns worked - Item and Due - which is what made it survive: the table
looked alive.

This is the third instance of one pattern in two days: a Blade view bound to
properties its query does not produce, failing silently into a fallback instead
of erroring. #1477 was the loan-rules screen; the checkout preview was about to
become the second; this is the third.

All three are now derived properly. `listOverdue()` computes them in PHP rather
than SQL, because the library package's tests run against SQLite where
`DATEDIFF` and `CONCAT` are not available.

One deliberate change of meaning: `fine_amount` is **null** when no fine row
exists yet, not 0.00. A fine accrues on return or on the nightly run, so "not
yet calculated" is a real state and is not the same as "nothing owed". The view
says so in words rather than printing a misleading zero.

## What is not done

Still open on #1473:

- Branch-aware notices. `LibraryOverdueNoticeService` still sends as one
  library, from one address.
- Hold routing and queue position per pickup branch.
- An admin editor for branch loan rules - they remain insertable only by hand.
- Patron browse and the circulation reports are not yet scoped; only the desk
  and the overdue list are.
- An administrator override forcing a staffer to one branch. Today any operator
  can select any branch, which suits a public-library service and would not
  suit a consortium with contractual separation.
- Dropping the legacy `library_copy.branch` / `library_hold.pickup_branch` text
  columns once an operator has reconciled what the backfill could not match.

## Verification

- 4 new cases, 11 in `LibraryBranchCirculationTest` total.
- 440 tests pass (242 Feature + 198 ahg-library, 30 skipped).
- Both views rendered and inspected: the desk selector offers 15 repositories
  plus "All branches" with the current one selected, and the overdue table
  renders a real patron name, a real day count, and "Not yet calculated" where
  no fine row exists.
