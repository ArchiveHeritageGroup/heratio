> Heratio Help Center article. Category: Library.

# Library Stocktake

A stocktake compares what is physically on your shelves with what the catalogue and the loans records say should be there. You scan a shelf range, Heratio works out the differences, and a second person signs off the result. Where a missing copy is on the heritage asset register, an administrator can then post the loss to the register.

Open **Library Management > Stocktake**, or go to `/library-manage/stocktake`. Counting needs the update permission. Signing off and posting to the register are for administrators.

## The stages of a count

A stocktake moves through three statuses. **Counting** is the working state. **Reconciled** means the variance has been calculated from the latest scans. **Signed off** freezes the result permanently.

Scanning after a reconcile returns the stocktake to Counting, so a variance can never be signed off without reflecting the most recent scans.

## Start a stocktake

1. Under **Start a stocktake**, enter a **Name**.
2. Choose a **Branch**, or leave All branches. Staff locked to a branch can only count that branch.
3. Enter the **First shelf** and **Last shelf** to limit the count, or leave them empty to count everything.
4. Choose **Start**.

Shelf ranges use natural order, so A10 comes after A2, and both ends are included. In a ranged count, copies with no shelf location recorded fall outside the range and are not expected.

## Scan the shelves

1. Enter the **Shelf being counted**.
2. Scan into **Barcodes, one per line**. Most scanners add the line break for you.
3. Choose **Record scans**. The message reports how many were new, repeated or rejected.
4. Change the shelf and carry on.

Record the shelf each time. Without it, Heratio can confirm a copy was seen but cannot tell you it was on the wrong shelf.

You can send up to 2,000 barcodes at a time, and a barcode longer than 50 characters is rejected. Scanning the same barcode again moves it to the latest shelf, which is handy when a misplaced book is put back and rescanned.

## Reconcile and review

Choose **Reconcile**. Heratio rebuilds the variance from the scans, the live catalogue and open loans. You can reconcile as often as you like before sign-off.

| Result | Meaning |
| --- | --- |
| Found | Seen, and on its home shelf or with no home shelf recorded |
| Missing | Expected on the shelf but not seen |
| Misfiled | Seen on a different shelf, or outside this count's range |
| Unexpected | Scanned, but should not be here, such as an unknown barcode, a withdrawn copy, another branch's copy or a copy with an open loan |
| On loan | Not seen, and on an open loan |
| Accounted for elsewhere | Not seen, with a reason that explains it, such as a return during the count or a status that places it off the shelf |

: Stocktake results

The **Reason** column gives the specific cause on each line. Missing lines sort first, and the buttons above the table filter by result and show the count for each.

A copy with no barcode cannot be matched by a scan, so it is listed as accounted for elsewhere with a note to check it by hand. Copy statuses that are no longer expected on the shelf (Withdrawn, Lost, Missing, Disposed and Written off by default) are managed in the Dropdown Manager under **Copy Statuses Not Expected On The Shelf**.

Use **Export CSV** to take the variance into a spreadsheet or onto the floor for a recheck. Asset register amounts appear in the export for administrators only.

## Sign off

Sign-off is a separate control: an administrator other than the person who started the stocktake must do it.

1. Open the reconciled stocktake.
2. Add a **Sign-off note** if useful.
3. Tick the confirmation that you have reviewed the variance.
4. Choose **Sign off**.

After sign-off the stocktake cannot change. At this point Heratio matches missing copies to the heritage asset register and captures the carrying amounts.

## Post a loss to the asset register

This step appears for administrators on a signed-off stocktake when the heritage asset register is available. A missing copy can be posted when its record has exactly one recognised asset on the register that has not been derecognised.

1. Find the missing line. The **Asset register** column shows a proposed amount and the carrying amount at sign-off.
2. Review the **Impairment amount**. The proposal is the carrying amount shared equally across the copies still held, and it is only a starting point for your judgement. It can be no more than the carrying amount.
3. Set the **Impairment date**, which cannot be in the future.
4. Choose **Post impairment**.

The loss is recorded as **not located**, not as physical damage. The assessment notes read, for example, "Stocktake #12: copy 30001234 not located", and that wording carries through to the impairment details on the asset. The ledger takes the loss against any revaluation surplus first and treats the remainder as a loss for the period. The copy's status changes to Missing, and each copy can be posted only once across all stocktakes.

If a copy later turns up, the posting is not reversed automatically. Refer it to whoever manages your heritage accounting.

## Troubleshooting

If the sign-off form is replaced by a message about another administrator, you started this stocktake. Ask a different administrator to sign it.

"Reconcile the latest scans before signing off" means scans were added after the last reconcile. Choose **Reconcile** again.

"No single recognised entry" means the record has no asset on the register, or more than one, so Heratio will not guess which to adjust.

Some lines show a red message in place of the posting form. "Needs review" means the copy's status changed, or it was lent or returned, after the count, so check whether it is really missing. A message about the institution currency means it must be set in the heritage accounting settings. A message about the revaluation surplus or a foreign currency means the register and ledger disagree for that asset, and finance staff should resolve that before posting.
