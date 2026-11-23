> Heratio Help Center article. Category: Library.

# Loan Rules and the Library Calendar

Loan rules decide how long an item goes out, how many times it can be renewed and what a late return costs. The calendar records when each branch is open. Together they make sure a loan does not fall due on a day the doors are shut, and that patrons are not charged for days they could not have returned a book.

Open **Loan Rules** from the Circulation Desk, or go to `/library-manage/circulation/loan-rules`. The **Opening hours and closures** button on that page leads to the calendar at `/library-manage/circulation/calendar`.

## How Heratio chooses a rule

A rule applies to a material type, a patron type (or All) and, where branches are in use, a branch (or All branches). When several rules could apply, a rule for the lending branch beats an all-branches rule, and a rule for the exact patron type beats one for All.

If no rule matches, the loan period comes from **Default Loan Period** in Library Settings at `/admin/ahgSettings/library`, renewals come from **Default Max Renewals**, and overdue fines are charged at 1.00 per day with no grace period or cap. Setting up a rule for every material you lend avoids relying on that fallback.

## Add or edit a rule

To add a rule, fill in the **Add a rule** form below the list. To change one, select the pencil icon beside it, which opens **Edit Loan Rule**. Only one rule can exist for each combination of branch, material type and patron type, so Heratio will ask you to edit the existing rule if you try to add a duplicate.

The fields are:

| Field | Meaning |
| --- | --- |
| Branch | The branch the rule applies to, or All branches. Shown only where branches are in use. |
| Material Type | The kind of item. Required. |
| Patron Type | The type of borrower, or All. |
| Loan (days) | Length of the loan. Required. |
| Renewals | How many times the loan may be renewed. The patron's own renewal limit also applies. |
| Grace (open days) | Open days after the due date before fines begin. |
| Fine/Day | Amount charged per chargeable day. |
| Fine cap | The most a single loan can be fined. Leave empty for no cap. |
| Loanable | Whether items under this rule can be checked out. See the note below. |
| Notes | Internal notes, available on the edit screen. |

Clearing **Loanable** stops new checkouts at the Circulation Desk and at self-check kiosks. The most specific matching rule decides, so a branch rule that allows loans overrides an all-branches rule that does not. At the Offline Desk the checkout arrives in the conflicts report for a staff decision, because the item has already left the building. Loans already out are not recalled, but they cannot be renewed while the rule stays cleared. A loan flagged as overdue cannot be renewed either, so staff deal with the overdue item first.

Material types come from the catalogue's built-in list, plus any already in use. They cannot yet be added through the Dropdown Manager.

Changing a rule affects new loans and renewals, and fines calculated from then on. It does not change the due date of a loan already out.

## Set weekly opening hours

1. Open the calendar. If you have several branches, choose one under **Branch** and select **Show**, or stay on **All branches** to set the pattern every branch inherits.
2. Tick **Open** for each day the library opens and clear it for days it does not.
3. Optionally enter **Opens** and **Closes** times. Give both or neither.
4. Select **Save hours**.

At least one day must stay open, otherwise no due date could be set. The times are shown for information only, because loans are due by date.

A branch inherits the all-branches pattern until you save hours for it. The **Source** column shows which pattern each day is using, and **Use all-branches hours** discards the branch's own pattern. Staff locked to one branch can manage only that branch's calendar and see the others read-only.

## Add a closure

Use a closure for a public holiday, stocktake or unexpected shutdown.

1. Under **Add a closure**, enter a **Name** and choose a **Type**. The types are managed in the Dropdown Manager under `library_closure_type`, and start as Public holiday, Scheduled closure, Stocktake and Emergency closure.
2. Enter the **From** date, and a **To** date for a closure longer than one day.
3. Tick **Yearly** if it recurs on the same date every year.
4. Select **Save closure**.

An all-branches closure applies everywhere, and a branch's own closures add to it. Use **Yearly** only for fixed-date holidays. A holiday that moves each year, such as one set by a lunar calendar, should be entered once per year. A yearly closure cannot start or end on 29 February and must be shorter than a year, though it can span the new year.

## What the calendar changes

When an item is checked out or renewed, a due date landing on a closed day moves to the next open day. Adding a closure later does not move due dates on loans already out, so tell affected patrons if needed.

Closed days are not charged as overdue and do not use up the grace period. If a closure is added after a fine has built up, the next daily fine run lowers the fine, and overdue notices quote the same chargeable days. A fine reduced to nothing this way is not recorded as waived, so removing the closure brings it back on the next run. Money already paid is never reduced.

Both daily fine jobs, `ahg:library-calculate-fines` and `ahg:library-process-fines`, use this same calculation for every open loan, including loans already marked overdue. They follow the lending branch's calendar and its branch and patron-type rules. Each loan keeps one running fine, so running both jobs does not charge a day twice, and neither job charges anything while **Auto-generate daily overdue fines** is off. Offline Desk due dates and fines follow the calendar too.

If nothing has been configured, every day counts as open.

## Troubleshooting

When a due date lands on a closed day, the closure was probably added after the loan was made. Check the loan's branch as well, because the lending branch's calendar is the one that counts.

The message "The calendar tables are not installed yet" means the database update for this feature has not been applied, and every day counts as open until your administrator runs it. A read-only calendar means you are assigned to another branch, so someone at that branch or an administrator must make the change.

Take care when editing an older closure. The type list offers only active values, so a closure whose type has since been deactivated shows the first active type and would be saved with it. Check **Type** before you save.
