> Heratio Help Center article. Category: Library.

# Supplier Invoices and the Acquisitions Ledger

The acquisitions ledger records what your suppliers bill you for. You capture an invoice against a purchase order, Heratio checks it against what was ordered and received, and once posted it counts as money owed until it is paid or credited. The ledger also gives you an aged creditors report, a comparison of invoices with budgets, and a CSV export for your finance system.

Open **Library Management > Acquisitions**, then choose **Invoices** on the Purchase Orders page. The address is `/library-manage/acquisition/ledger`. Working in the ledger needs the update permission, with create needed to capture documents and delete needed to void them. Tax rates are for administrators.

## Before you start: load tax rates

Heratio ships with no tax rates, because they differ by market. Every invoice line needs a tax code, even to save a draft, so an administrator should do this first.

1. In the ledger, choose **Tax rates**.
2. Under **Add rate**, enter a **Code**, a **Label**, the **Rate %** and **Valid from**. Add **Valid to** only if the end date is known.
3. Choose **Add**. If you have zero-rated or exempt supplies, add a code for those at 0%.
4. Choose the **Rounding rule**: **Round on each line**, or **Round on the document total per rate**. Confirm which one your finance team requires.

A rate is never edited once in use. When a rate changes, enter an end date against the old one with **End**, then add the new rate from the following day. Posted documents keep the rate they were calculated with. An end date can only be brought forward, and one code cannot have two rates covering the same day.

## Capture an invoice

1. Choose **New invoice**.
2. Select the **Purchase order** and choose **Continue**. Lines still to be billed are filled in for you.
3. Check the **Supplier** and enter the **Supplier invoice number**, **Invoice date** and, if known, the **Due date**.
4. Review the lines. Amounts are before tax. Change quantities or prices to match the supplier's document, and use the blank rows for anything extra.
5. Choose a **Tax code** on every line.
6. Choose **Save draft**.

The supplier must match the one on the order, and the same supplier invoice number cannot be captured twice for one supplier. Tax is not calculated on a draft, so those columns stay empty until posting.

## Post an invoice

Open the draft and review it. If it does not match the order or receipts, a warning lists each difference, such as a line billed for more than was received, a price that differs from the order, or a line not on the order.

1. Resolve the differences by choosing **Edit draft**, or decide to accept them.
2. If you accept them, enter a **Reason for posting anyway**.
3. Choose **Post document**.

Posting calculates tax using the rate in force on the invoice date and assigns a number such as `SINV-2026-000001`. The posted figures are then fixed, and any variance reason stays on the document.

## Record a payment

On a posted invoice, the panel shows the amount **Outstanding**.

1. Enter the **Payment date**, **Amount** and **Payment reference**.
2. Choose **Record payment**.

Part payments are fine. A payment cannot exceed what is outstanding, and its date must fall between the invoice date and today. To undo one, choose **Reverse** on its row. The reversal stays visible, struck through, so the history is kept.

## Raise a credit note

Use a credit note when a supplier reduces what you owe.

1. On the posted invoice, choose **Raise credit note**.
2. Choose the **Credit type**.
3. Enter the **Supplier credit note number**, **Date** and **Reason**. The date cannot be before the invoice.
4. Enter a **Quantity** on each line to credit, and adjust the **Unit price** if it is a partial credit. Leave the quantity empty to skip a line.
5. Choose **Save draft credit note**, review it, and then choose **Post document**.

The credit type matters. **Billing correction (goods kept)** at the original price lets the supplier invoice that quantity again. **Goods returned to supplier** leaves the quantity counted as billed, so billing a replacement delivery will need a variance reason. A credit keeps the tax rate of the original invoice and is numbered in the form `SCN-2026-000001`.

## Void a document

Voiding cancels a document that should not stand. It needs a reason and the delete permission. An invoice with active payments or credit notes cannot be voided until those are reversed or voided. A document already exported to finance cannot be voided at all, so raise a credit note instead.

## Aged creditors

**Aged creditors** shows what you owe each supplier at a chosen date, split into Not yet due, 1-30, 31-60, 61-90 and Over 90 days. Age is counted from the due date, or from the invoice date when no due date was captured. Each currency is totalled separately, and a negative figure means the supplier owes the library.

Choose an **As at** date to look back. The figures reflect that day's position: a payment reversed or a document voided after that date still counts. An invoice captured late appears at its invoice date, so a backdated capture can change the figure for a past date you have already reported.

## Budget match

**Budget match** sets invoices against budgets, per budget code and fiscal year, showing allocated, committed, received and invoiced amounts before tax. **Received, not invoiced** points to bills you can expect. A red figure means more has been invoiced than was received or committed.

A badge marked **excluded** counts orders in a different currency from the budget. A badge marked **unplaced** counts orders that could not be assigned to a single fiscal year, which can happen when split fiscal years overlap. Both are left out of the figures, so review them before relying on a total.

## Finance export

**Finance export** batches every posted document not yet exported.

1. Choose **Create export batch** and confirm.
2. Choose **Download CSV** on the new batch.

Exporting is a one-way step for those documents, because they can no longer be voided afterwards. Past batches stay listed and can be downloaded again.

## Troubleshooting

"No tax rate for code" on posting means the code has no rate covering the invoice date. An administrator can add a rate for that period.

The tax code list offers only codes that are current today. A late invoice dated inside a retired code's period therefore cannot select that code, and a draft that already uses one shows a blank tax code. Until this is improved, ask an administrator how your institution wants such invoices handled before changing the rate table.

The order picker on a new invoice lists the 200 most recent orders that are not cancelled. For an older order, open the invoice form with the order number in the address, as `/library-manage/acquisition/ledger/invoice/create?order_id=` followed by the order's internal ID.

"This supplier reference is already captured for this supplier" means the invoice is probably in the ledger already. Search for it on the Documents page before capturing it again.
