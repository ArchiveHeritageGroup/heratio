> Heratio Help Center article. Category: Library.

# Library Fines and the Cash Desk

The cash desk lets circulation staff take payment for library fines, waive fines with a recorded reason, issue numbered receipts and account for the money in each drawer. Every payment belongs to a cash session. A session starts with a counted float, ends with a counted drawer, and is signed off by a second person.

You will work from two places. The **Cash Desk** at `/library-manage/cash-desk` handles sessions and the end-of-day report, and is linked from the top of the Circulation Desk. A patron's **Patron Fines** page handles payments and waivers. To reach it, open the patron from the Circulation Desk, choose the Fine History tab and select **Take payment or waive**.

## Who can do what

Staff with update permission can open and close their own session, take payments and view the end-of-day report. Waiving a fine and reconciling a session need delete permission, because both write off or approve money. A person with delete permission can also close a session that someone else opened.

Staff assigned to a branch see that branch's sessions, and can waive only fines belonging to it. A fine belongs to the branch of its loan, then the branch of the copy, then the patron's branch.

## Before the first payment

An administrator should check the payment methods at `/admin/dropdowns/library_payment_method/edit`. Heratio ships with Cash, Card, Electronic transfer and Mobile payment. The **Goes in cash drawer** column decides whether a method counts towards the cash expected at closing, so Cash is set to Yes and the others to No.

Any method added later must have this column answered. A method showing **Not set** cannot be used, and the fines page names it in a warning until someone chooses Yes or No.

The session currency defaults to the **Currency** field under Library Settings at `/admin/ahgSettings/library`.

## Open a cash session

1. Go to the Cash Desk.
2. Count the starting cash and enter it as the **Opening float**. Enter 0 if the drawer starts empty.
3. If outstanding fines exist in more than one currency, choose the **Currency** for this session.
4. Select **Open session**.

You can hold only one open session at a time, and it stays tied to the branch you were working at when you opened it. If you move to another branch, you must return to the original branch to take payments, or close the session first. A session handles one currency, so fines in another currency need a separate session.

## Take a payment

1. Open the patron's fines page. The outstanding fines are listed and ticked.
2. Clear the tick on any fine the patron is not paying today.
3. Check the **Amount**. It is filled in with the patron's full payable balance and does not change when you clear a tick, so reduce it yourself if you are paying only some fines.
4. Choose the **Payment method**.
5. For cash, you may enter **Cash tendered** and Heratio will work out the change.
6. For anything other than cash, enter the terminal or transaction **Reference**. Never enter a card number. Heratio refuses anything that looks like one.
7. Select **Record payment**. The numbered receipt opens, ready to print.

Payment is applied to the selected fines oldest first. A part payment is allowed, and the fine stays outstanding with its reduced balance. Receipt numbers look like `FR-2026-000123`.

If a form is submitted twice, for example after a double click or a dropped connection, Heratio shows the original receipt with a notice that no new payment was taken.

## Waive a fine

The **Waive a fine** panel on the patron's fines page lists each fine you are allowed to waive. Type the reason and select **Waive**. The whole remaining balance is written off, and anything already paid stays paid. A partial waiver is not available, although you can take part payment first and then waive what is left. Every waiver appears on the end-of-day report with its reason.

## Close and reconcile

At the end of a shift, open your session from the Cash Desk with **View or close**. The page shows the float, cash takings, other takings and the amount expected in the drawer.

1. Count the drawer and enter **Cash counted in drawer**.
2. If the count differs from the expected figure, explain why in the notes. Heratio will not close the session without a note.
3. Select **Close session**.

A different person then reviews it. The reconciler needs delete permission and cannot be the person who opened or closed the session. They open the session, read the figures, add **Review notes** (required if there is a variance) and select **Mark reconciled**. A small service therefore needs at least two people able to do this.

## End-of-day report

Select **End of day** on the Cash Desk, pick a date and select **Show**. Figures are grouped by currency and show cash takings, other takings, a breakdown by method, net variance and fines waived, followed by the sessions and waivers for that day. **Print** produces a paper copy.

Money is reported on the day it moved: receipts by issue time, variances by when the drawer was counted, and waivers by when they were made. A warning appears while any session from that day or earlier is still open or awaiting reconciliation, because the figures are not yet final.

Fee payments made at a self-check terminal are not part of a cash session, so they do not appear on this report or on a receipt. See the self-check article for details.

## Troubleshooting

If Heratio says "Open a cash desk session before taking a payment", you have no open session, so open one at the Cash Desk and return to the patron. The message "The amount is more than the ... owed on the selected fines" usually means a tick was cleared without lowering the amount.

A fine that cannot be ticked and shows "Other currency" is recorded in a different currency from your session. If the amount is right, close your session and open one in that currency. If the label itself is wrong, have it corrected before taking payment.

"Do not enter a card number" means the reference resembles one. Use the terminal's transaction reference instead. Now and then a genuine long bank reference trips this check, and a shorter form of it will get through.

Can't see the Reconcile form? Either the session is not closed yet, you lack delete permission, or you opened or closed that session yourself.
