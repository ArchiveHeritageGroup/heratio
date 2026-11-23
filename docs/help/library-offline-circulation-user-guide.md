> Heratio Help Center article. Category: Library.

# Offline Circulation

The Offline Desk keeps checkouts and returns moving when the network drops. Staff scan items into a queue held in the browser. When the connection returns, Heratio replays each transaction as at the moment it happened, and anything it cannot prove safe goes to a conflicts report instead of changing a record.

Open it from the **Offline Desk** button on the Circulation Desk, or at `/library-manage/circulation/offline`. You need update permission.

This feature has been tested in a browser against a simulated server. It has not yet been exercised on a live system or on real desk hardware, so run a practice session before depending on it during an outage.

## Prepare each desk computer while online

The desk cannot be set up once the link has gone, so do this in advance on every computer that may need it.

Heratio must be served over HTTPS. Browsers withhold the storage and security features this page depends on from plain HTTP addresses, so on such an address the page will not reopen offline and the PIN and capture steps will fail.

1. Sign in and open the Offline Desk, so the browser stores the page.
2. Select **Download desk data**. This copies the loan rules, copies and patrons for your branch to this computer.
3. Under **Set offline PIN**, enter at least 6 characters and select **Save PIN**. The PIN belongs to you on this computer, so each member of staff who may use the desk sets their own.
4. Repeat the download regularly. The page warns you when the data is more than a day old.

The download holds only what the desk needs, such as card numbers, abbreviated names and whether a patron is over the fine threshold. It holds no contact details. The PIN stops the next person using the desk by accident, but it does not encrypt this data, so use a computer only staff can reach. Once nothing is waiting to sync, **Remove desk data** clears it, and signing out does the same.

## Capture transactions

If the page is already open when the link drops, carry on using it. Otherwise open the same address in the same browser. If the desk is locked, enter your offline PIN. It also locks itself after 10 minutes without activity, and **Lock desk** does so immediately.

1. Choose **Checkout** or **Return**.
2. For a checkout, scan the **Patron card**.
3. Scan the **Copy barcode** and select **Save**.

The desk warns you if its data shows a problem, such as an unknown card, a copy already out, a patron who cannot borrow or a patron at the loan limit. You can save anyway, and the transaction will be checked on reconnection. For checkouts it shows an indicative due date. Treat that as provisional, because the final date is set during sync.

Saved transactions appear under **Waiting to sync**. They stay safe in the browser if the page is closed or you sign out. Do not clear the browser's site data while anything is waiting, as that would delete the queue.

## Sync when the link returns

The desk syncs by itself when the browser reports it is back online, and you can select **Sync now** at any time. You may be asked to sign in first. Only the person who captured a transaction can sync it, so if someone else is signed in, the queue waits on that computer for its owner.

The result reports how many transactions were applied and how many went to the conflicts report. New desk data cannot be downloaded until the queue is empty.

## Resolve conflicts

Open the **Conflicts report** from the link at the top of the Offline Desk. It lists conflicts by default, and the **Status** filter also shows Applied, Pending, Rejected or All. Times on this page are in UTC.

For each row, choose a resolution, add a note if needed and select **Go**:

| Resolution | What it does |
| --- | --- |
| Check again | Re-runs the checks. Use it after fixing the underlying problem. |
| Apply anyway | Overrides this one check and re-runs all the others. Needs a note. |
| Reject | Discards the transaction without changing any loan. Needs a note. |

**Apply anyway** is offered only for policy and timing problems:

| Conflict | Likely response |
| --- | --- |
| Patron is not in good standing | Apply if the patron was entitled to borrow at the time. |
| Patron was at the loan limit | Apply if you accept the extra loan. |
| Patron is over the fine threshold | Apply if the loan should stand. |
| Desk clock is ahead of or behind the server | Confirm the real time before applying. |
| Older than the offline window | Apply only if you trust the record. |
| Received out of sequence | Check the order of events first. |
| Would reduce or waive an existing fine | Compare the two amounts shown, then decide. |

Problems of fact cannot be overridden, because a copy cannot be in two places at once. These are a barcode or card not found, a copy on loan elsewhere or unavailable, no open loan to return, a loan that began after the return, or a loan belonging to another patron. Correct the record and use **Check again**, or **Reject** the transaction. If a row says an earlier transaction on this copy is unresolved, settle that one first.

Timing and fine conflicts always need a named decision, so **Check again** will not clear them. A time still in the future cannot be applied at all until the clock problem is dealt with.

## Settings

**Offline Window (days)** under Library Settings at `/admin/ahgSettings/library` sets how old a transaction may be before it needs a staff decision. The default is 14. Desk clocks must agree with the server to within five minutes at sync, so keep them on automatic time.

Offline loans are judged by the loan rules recorded for that moment. Changes made with the **Edit** button on a loan rule are not recorded straight away, so after editing a rule, select **Download desk data** on a desk while online to record the new version.

## Known limits

Offline transactions do not use the opening hours and closures calendar. A due date can land on a closed day, and a fine worked out at an offline return counts closed days. Check these by hand after a long outage that included a closure.

A loan already marked overdue by the nightly overdue check is not treated as open by this feature. The desk may warn that the copy has no open loan, and the return will arrive in the conflicts report as **No open loan to return**, which neither **Check again** nor **Apply anyway** can clear. Process the return at the normal Circulation Desk, then **Reject** the offline entry with a note. The return will carry the date you process it, so waive any fine that built up during the outage if appropriate.

When an offline return changes a fine for a patron who has made part payments, the patron's running total can be overstated. The individual balances on the Patron Fines page remain correct, so use those.

## Troubleshooting

"No desk data on this computer yet" means the download was never done here, and it cannot be fetched during an outage. Likewise, "Offline capture needs a PIN" can only be fixed online, so keep a paper log until the link returns and enter the transactions afterwards.

If the page will not open during an outage, it was never opened on this computer, the site is not on HTTPS, or the browser's site data has been cleared.

Two messages concern who is signed in. "A different user is signed in" means the waiting transactions belong to someone else, who must sign in on this computer to sync them. "Sign in again to sync" means your session expired during the outage. Your transactions are safe, so sign in and sync.

A date that looks wrong in the conflicts report is probably just UTC, which may differ from your local time.
