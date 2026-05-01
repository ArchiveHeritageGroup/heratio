# Marketplace - Bidding & Auctions

How auctions run on the Heratio Marketplace, from a seller starting one to a buyer winning and paying.

---

## Anatomy of an auction listing

When a seller chooses **Auction** as the sale type, the listing carries:

- **Starting bid** - the minimum first bid.
- **Reserve price** *(optional)* - if the highest bid at close is below the reserve, the lot does not sell.
- **Bid increment** - defaults to a sliding scale (e.g. R10 below R100, R50 below R1 000, etc.); operators can override per category in admin settings.
- **Start / end timestamps** - the listing is biddable only between these.
- **Anti-snipe window** *(optional)* - if a bid lands in the final N minutes, the close time extends by N more minutes, repeatedly.

The browse page exposes auction-only filters at **`/marketplace/auction-browse`**.

---

## Buyer flow

### Place a bid

1. Open the listing.
2. Click **Place bid** → opens `/marketplace/bid-form`.
3. Enter your max bid (or a single increment).
4. Submit - the system places the bid at the lowest amount needed to outbid the current leader, up to your max.

### Track your bids

**`/marketplace/my-bids`** lists every bid you've made, grouped by listing, with state:

| State | Meaning |
| --- | --- |
| **Leading** | You're currently the high bidder |
| **Outbid** | Someone has out-bid you - re-bid or walk away |
| **Won** | Auction has closed and you are the winner |
| **Lost** | Auction has closed and someone else won |
| **Cancelled** | Listing was withdrawn by the seller before close |

You also receive an email each time you are outbid.

### Win → checkout

When the auction closes and you're the winner, you have a configurable payment window (typically 48 hours) to complete checkout:

```
POST /marketplace/checkout/win/{auctionId}
```

This redirects you to PayFast. After confirmation the transaction moves to `paid` and the seller is notified to dispatch.

If you don't pay inside the window, the lot can be **second-chance-offered** to the next-highest bidder by the operator.

---

## Seller flow

### Start an auction

In `/marketplace/seller/listing-create`, set **Sale type = Auction**, then:

- Set **Start date/time** and **End date/time**.
- Set **Starting bid**.
- Optionally set **Reserve price** - only the seller and operator see whether reserve was met.
- Save → upload images → publish.

While the auction is live, the listing page shows a live countdown (`_auction-timer.blade.php`) and a public bid history (bidder usernames or anonymised handles, configurable).

### Manage live auctions

From `/marketplace/seller/listings`, auction listings show:

- **Bids** count + current high bid
- **Reserve met / not met** indicator (seller-only)
- **Time remaining**

### Close behaviour

At end-time the auction enters one of three terminal states:

1. **Sold** - winner is set, transaction created in `pending_payment`. Buyer is emailed a "complete payment" link.
2. **Reserve not met** - no winner; you may relist or accept the highest bid manually as a *Make an Offer* outcome.
3. **No bids** - listing returns to `draft` for you to re-publish.

---

## Anti-snipe and bid increments

These behaviours are **operator-controlled** in `/admin/marketplace/settings`:

| Setting | Default | Purpose |
| --- | --- | --- |
| `auction.anti_snipe_minutes` | 5 | Extends end-time when a bid lands in the final N minutes |
| `auction.anti_snipe_max_extensions` | 6 | Stops indefinite extension |
| `auction.bid_increment_table` | sliding scale | Increment per price band |
| `auction.payment_window_hours` | 48 | Time the winner has to pay before forfeiting |

Sellers who need different rules for a single lot must request a per-listing override from the operator.

---

## See also

- *Marketplace - Buyer Quick Start*
- *Marketplace - Make an Offer*
- *Marketplace - Payments & Payouts*
- *Marketplace - Admin Operations*
