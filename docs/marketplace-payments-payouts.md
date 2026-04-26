# Marketplace — Payments & Payouts

How money flows in the Heratio Marketplace: from buyer card to PayFast, into a held escrow, out to seller bank accounts on payout day.

---

## Payment provider — PayFast

The marketplace integrates **PayFast** as its sole payment processor. Heratio never stores card details: the buyer is redirected to PayFast for entry, and the result is verified server-to-server.

Three things have to be configured in `/admin/marketplace/settings`:

| Setting | Source |
| --- | --- |
| **Merchant ID** | PayFast dashboard |
| **Merchant key** | PayFast dashboard |
| **Passphrase** | PayFast dashboard (used to sign requests) |

The site URL must be HTTPS in production — PayFast refuses ITN webhooks to plain HTTP.

---

## Buyer flow — what happens at checkout

1. Buyer clicks **Buy Now** / completes an accepted offer / wins an auction.
2. The marketplace creates a transaction in **`pending_payment`**.
3. Buyer is redirected to PayFast's hosted payment page.
4. Buyer pays — card, EFT, Snapscan, etc. (PayFast's choice).
5. PayFast posts an **ITN** (Instant Transaction Notification) to:
   ```
   POST /marketplace/payfast/notify
   ```
6. The handler:
   - Verifies the signature (HMAC of submitted fields + passphrase).
   - Checks the request came from a known PayFast IP block.
   - Performs a **server-to-server validate** call back to PayFast (defence in depth).
   - On success, transaction → **`paid`**.
7. Buyer is redirected to `/marketplace/payment/return` (success) or `/marketplace/payment/cancel` (cancelled).

The transaction is now visible to both parties — buyer at *My Purchases*, seller at *My Transactions*.

---

## Escrow window

After a successful payment, funds are **held by the operator** (not transferred to the seller yet). This gives the buyer a window to dispute non-delivery, wrong item, etc.

| Setting | Default | Where |
| --- | --- | --- |
| `marketplace.escrow_days` | 14 | Admin Settings |
| `marketplace.dispute_window_days` | 7 | Admin Settings |

The transaction's **payout-eligible-from** date = `paid_at + escrow_days`. Until that date, the seller's payout balance shows the amount as *pending*.

---

## Seller payouts

When a transaction passes its escrow window with no open dispute, its amount moves from *pending* to *available* in the seller's payout balance.

### Self-service view

- **`/marketplace/seller/payouts`** — every payout you've received with batch reference, amount, and the underlying transactions.

### Operator-driven batching

Payouts are released by the operator running the **batch payout** action:

```
POST /admin/marketplace/payouts-batch
```

This iterates every seller with a positive available balance and produces:

1. A `marketplace_payout_batch` row with a unique reference.
2. One `marketplace_payout` row per seller in the batch.
3. A CSV/PDF download for the operator's bank to process.
4. An email to each seller with the reference and the underlying transactions.

> Heratio does **not** initiate bank transfers automatically — the CSV is processed by the operator's banking facility (PayFast Pay-Outs, EFT export, or manual). The operator marks the payout as **paid** when the bank confirms; that flips the seller's balance from *available* to *paid out*.

---

## Refunds & disputes

There is no "refund" button in Heratio's UI — refunds are processed manually by the operator:

1. Buyer raises a dispute via the seller's contact form / enquiry.
2. If unresolved, buyer emails the operator (admin contact).
3. Operator reviews the transaction at `/admin/marketplace/transactions`.
4. If a refund is granted **before payout**, the operator simply does not release the funds and uses PayFast's refund API.
5. If a refund is granted **after payout**, the operator deducts from the seller's next payout (or recovers via separate billing).

Configure the operator contact and the dispute SLA in `/admin/marketplace/settings`.

---

## Currencies

The site primary currency is set in `/admin/marketplace/settings`. Additional currencies are configured in `/admin/marketplace/currencies` with:

- **ISO code** (ZAR, USD, EUR, GBP, etc.)
- **Display symbol**
- **Active / inactive** flag
- **FX rate** (manual or fed by a scheduled task)

Sellers pick from active currencies on the listing form. Buyers see prices converted to their preferred currency on Browse, but **pay in the listing's currency** at checkout — Heratio does not do live FX at the checkout stage.

---

## Reports

Operators have read-only financial reports at `/admin/marketplace/reports`:

| Report | Default period |
| --- | --- |
| Gross merchandise value (GMV) | Month-to-date + last 12 months |
| Commission earned | Month-to-date |
| Payouts paid | By batch |
| Pending payouts | Live |
| Disputes opened / closed | Last 90 days |
| Top sellers by GMV | Last 90 days |

Each report exports as CSV.

---

## See also

- *Marketplace — Admin Operations*
- *Marketplace — Seller Quick Start*
- *Marketplace — Buyer Quick Start*
