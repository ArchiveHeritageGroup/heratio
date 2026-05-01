# Marketplace - Admin Operations

Day-to-day operator playbook for the Heratio Marketplace: reviewing listings, verifying sellers, handling disputes, running payouts, and tuning the marketplace settings.

The admin area lives under **`/admin/marketplace/*`** and requires a user with the `admin` role.

---

## Dashboard

`/admin/marketplace/dashboard` is the at-a-glance view:

- **Listings under review** - needs your action
- **Sellers pending verification** - needs your action
- **Disputes open** - needs your action
- **Pending payouts (R)** - total seller balance ready to pay out
- **GMV today / this month** - financial pulse
- **Recent transactions** - last 10 paid

The numbers under "needs your action" are the SLA you should keep at zero.

---

## Listing review

Sellers publish into **`under_review`** when the operator has the strict-review flag enabled. Review queue is at **`/admin/marketplace/listings`**, filter to *Status: under_review*.

For each listing, click into **`/admin/marketplace/listing-review?id=…`** to:

| Action | Effect |
| --- | --- |
| **Approve** | Listing → `active`, visible to buyers |
| **Request changes** | Listing → `needs_changes` with your note; back to seller |
| **Reject** | Listing → `rejected` with your reason; locked |
| **Force-publish** | Skip review (rare; e.g. trusted seller) |

The audit trail records every action with timestamp + admin user.

---

## Seller verification

Pending sellers appear at **`/admin/marketplace/sellers`** with a *Pending verification* filter. Click into one → **`/admin/marketplace/seller-verify?id=…`**:

- Review the legal name + trading name + KYC documents.
- Verify the bank account match (signed bank confirmation).
- Verify tax/VAT registration where required by the operator's jurisdiction.
- **Approve** / **Reject** with reason / **Request additional info**.

A rejected seller cannot list. They can re-apply after addressing your reason.

---

## Categories & taxonomies

Operator-managed lists at **`/admin/marketplace/categories`**:

- Sectors (Gallery / Library / Archive / Museum / DAM) are configured at install time.
- Categories within each sector are operator-edited - add/rename/disable.
- Sub-categories cascade from category.

> Per project rule: **no enumerated values are hardcoded** - categories live in `ahg_dropdown` via the Dropdown Manager. The marketplace category page is just a friendlier UI over the same data.

---

## Currencies

`/admin/marketplace/currencies`:

- Add/remove currencies the marketplace will accept.
- Set the **primary** (sellers default to it; cross-listing prices on Browse are normalised to it).
- Set FX rates - manual or via an FX feed (cron).
- Disable a currency to stop new listings choosing it without affecting existing ones.

---

## Transactions

`/admin/marketplace/transactions` - every transaction across the platform with filters by:

- Status (`pending_payment` / `paid` / `disputed` / `refunded` / `cancelled`)
- Seller / buyer / listing
- Date range
- Amount range

Click into any row for the full detail: payment trail, ITN log, escrow countdown, payout batch reference.

For disputes, mark the transaction **disputed** with a reason - that pauses the escrow countdown and tags the seller's payout view. Resolve by either marking **paid** (no refund) or **refunded** (refund processed externally; balance does not move to the seller).

---

## Payouts

`/admin/marketplace/payouts` shows two tabs:

### Available to pay
Sellers whose pending balance has cleared escrow. Click **Run batch** to launch:

```
POST /admin/marketplace/payouts-batch
```

That action:
1. Creates a payout-batch record with a unique reference.
2. For each seller, creates a `marketplace_payout` row + flips relevant transactions to `paid_out`.
3. Generates a CSV in your operator's bank's import format.
4. Emails each seller their batch reference + transactions.

Process the CSV through your bank facility, then mark the batch **completed** in Heratio when the bank confirms. (No automatic bank transfer - Heratio is the system of record, your bank is the system of money.)

### Paid
Historical batches with download-CSV per batch.

---

## Reviews

`/admin/marketplace/reviews` - buyer reviews of sellers, with content-moderation tools:

- **Approve** - visible publicly (default for ★3+).
- **Hide** - kept on record, not shown publicly.
- **Delete** - for clearly abusive content (audited).
- **Reply on behalf** - operator can post a public reply if the seller is non-responsive.

---

## Reports

`/admin/marketplace/reports` exports the operator KPIs:

| Report | Use it for |
| --- | --- |
| **GMV by month** | Board reporting |
| **Commission earned** | Revenue recognition |
| **Top sellers** | Strategic accounts |
| **Top categories** | Where the demand is |
| **Disputes / refunds** | Quality + risk |
| **Average time to ship** | Seller SLA |
| **Buyer cohort retention** | Loyalty |

Each as CSV. Plug into your BI tool or accountant's spreadsheet.

---

## Settings

`/admin/marketplace/settings` is the bulk configuration page. The values that change behaviour day-to-day:

| Setting | What it does |
| --- | --- |
| `commission_rate` | Operator's % cut per transaction |
| `escrow_days` | Days from `paid` to payout-eligible |
| `dispute_window_days` | Days a buyer has to dispute |
| `auction.anti_snipe_minutes` | Auction extension trigger window |
| `auction.payment_window_hours` | Time a winner has to pay |
| `reservation_hours` | Hold duration |
| `reservation_max_active` | Buyer cap on concurrent holds |
| `payfast_merchant_id`, `_key`, `_passphrase` | PayFast credentials |
| `strict_review` | Listings need admin approval before going live |
| `email_from` | Marketplace transactional sender |

Changes take effect immediately. Audit log records every change with admin user.

---

## Common tasks - cookbook

| Task | Steps |
| --- | --- |
| **A seller hasn't replied to enquiries for 14 days** | Sellers → filter inactive → email warning → after 30 days, mark `inactive` (hides their listings) |
| **A buyer reports the item never arrived** | Open transaction → mark **disputed** → review seller's evidence → either resolve (mark `paid`) or refund |
| **A category is filling with mis-classified listings** | Categories → rename or split → bulk re-classify in listings filter |
| **The site's ZAR-USD rate is stale** | Currencies → edit USD → update rate → save |
| **Operator wants to pause new sellers temporarily** | Settings → `seller_registration_open` = 0 |

---

## See also

- *Marketplace - Payments & Payouts*
- *Marketplace - Seller Quick Start*
- *Marketplace - Buyer Quick Start*
- *Marketplace - User Guide* (the full end-user reference)
