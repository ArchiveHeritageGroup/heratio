# Marketplace — Seller Quick Start

How to register as a seller, get verified, list your first item, and manage offers, sales, and payouts.

---

## 1. Register as a seller

Sellers register separately from buyers (different KYC requirements):

1. Sign in.
2. Go to **`/marketplace/seller/register`**.
3. Fill out the seller form: legal name, trading name, bank details, tax/VAT number where required, sector, profile.
4. Submit — your account enters **`pending_verification`**.

A site admin reviews the application at `/admin/marketplace/seller-verify` and either **approves**, **rejects with reason**, or **requests more info**. You receive an email at each transition.

Edit your profile any time at **`/marketplace/seller/profile`**.

---

## 2. Brokers — managing artists

A seller acting on behalf of multiple artists or estates uses the **Artist** management area at **`/marketplace/seller/artists`**:

- Create an artist record (`/marketplace/seller/artist/create`)
- Edit (`/marketplace/seller/artist/edit`)
- Delete (`/marketplace/seller/artist/delete`)

Each listing can be attributed to one of your artists, so payouts and reviews can be split per artist later.

---

## 3. Create a listing

Open **`/marketplace/seller/listing-create`**:

| Field | Notes |
| --- | --- |
| Title, description | Plain text + Markdown supported |
| Sector + category | Cascade — pick a sector, then a category from the marketplace dropdowns |
| Sale type | **Buy Now**, **Auction**, or **Open to offers** |
| Price | Fixed (Buy Now) or starting + reserve (Auction) |
| Currency | Defaults to site primary; admins set the available list at `/admin/marketplace/currencies` |
| Provenance / condition | Free text, surfaced on the listing page |
| Reproduction rights | Drives the licence delivered to the buyer |

After save, upload images at **`/marketplace/seller/listing-images`** (drag-and-drop, multiple files, set hero).

> The new listing is created in **`draft`** state. It is NOT visible to buyers until you publish it.

---

## 4. Publish — and be reviewed

Publish from the listings page (`/marketplace/seller/listings`) → **Publish** (`POST /marketplace/seller/listing-publish`).

Depending on operator settings, listings either:
- Go live immediately (low-touch operators), or
- Enter `under_review` and wait for an admin pass at `/admin/marketplace/listing-review`.

Withdraw at any time with **Withdraw** (`POST /marketplace/seller/listing-withdraw`) — this hides the listing from buyers but keeps the record.

---

## 5. Collections

Group related listings with **Collections** (`/marketplace/seller/collections`). Useful for:

- A single estate / artist body of work.
- A themed sale (e.g. "1965 Ephemera").
- Cross-sell between related items.

Collections appear on the seller profile and at `/marketplace/collection?id=…`.

---

## 6. Respond to offers

Offers from buyers land at **`/marketplace/seller/offers`**.

Click into one → you can **accept**, **counter** with a price + message, or **decline**. Buyers see your response in their *My Offers* page. If you accept, the buyer is prompted to pay; the listing is held for them while the offer is live.

Endpoint: `POST /marketplace/seller/offer-respond`.

---

## 7. Track sales

| Page | What you'll see |
| --- | --- |
| **`/marketplace/seller/transactions`** | Every transaction across all your listings |
| **`/marketplace/seller/transaction-detail?id=…`** | Per-transaction view with buyer info, payment status, payout status, dispatch tracking |
| **`/marketplace/seller/payouts`** | Lifetime payouts with batch references |
| **`/marketplace/seller/analytics`** | Listing views, conversion, top buyers |
| **`/marketplace/seller/reviews`** | Buyer reviews with your reply option |

After dispatching, mark the transaction as **shipped** (sets the `dispatched_at` timestamp and notifies the buyer).

---

## 8. Get paid

The operator runs payouts on a configurable schedule (admin → Settings).

- Each completed transaction adds to your seller balance.
- Funds become payable after the **dispute window** (operator-configurable, typically 7–14 days from delivery).
- A scheduled batch run (`/admin/marketplace/payouts-batch`) releases the balance to your registered bank account in one transfer.

You see the resulting reference in **My Payouts**.

---

## 9. Buyer enquiries

Pre-sale questions land at **`/marketplace/seller/enquiries`**. Reply in-thread; replies email the buyer. Convert a productive enquiry into an offer by accepting the buyer's stated price.

---

## See also

- *Marketplace — Buyer Quick Start*
- *Marketplace — Bidding & Auctions* (running an auction listing)
- *Marketplace — Admin Operations* (what your admin reviewer sees)
- *Marketplace — Payments & Payouts*
