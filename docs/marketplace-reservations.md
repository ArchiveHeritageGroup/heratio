# Marketplace - Reservations & Holds

Reservations let a buyer hold a fixed-price listing for 12 hours while they decide. They are a softer commitment than Buy Now and a cleaner alternative to "I'll send an offer for full price".

---

## What a reservation does

- Marks the listing as **on hold for you** for **12 hours**.
- Other buyers see "Reserved" and cannot Buy Now while the hold is active.
- When the hold expires, the listing automatically goes back to `active`.
- Buyers who paid for a reservation upfront - none. Reservations are **free**; the trade-off is the per-buyer cap below.

---

## Limits

To prevent reservations being used as a denial-of-stock tactic:

| Limit | Default | Operator setting |
| --- | --- | --- |
| Active reservations per buyer | 2 | `marketplace.reservation_max_active` |
| Reservations per buyer per rolling 24 h | 2 | `marketplace.reservation_max_24h` |
| Hold duration | 12 hours | `marketplace.reservation_hours` |

If you hit the cap the system shows: *"You can only hold 2 listings at a time. Cancel one to reserve another."*

---

## Buyer flow

### Reserve

1. Open a fixed-price listing.
2. Click **Reserve for 12 h** next to **Buy Now**.
3. Confirmation flash: *"Reserved for 12 hours. Hold expires at YYYY-MM-DD HH:MM - Buy Now to complete the purchase."*

> Endpoint: `POST /marketplace/listing/reserve/{listingId}`.

### Track and cancel

Reservations show on **My Following** with a countdown. Click **Cancel reservation** to release immediately:

```
POST /marketplace/reservation/cancel/{reservationId}
```

Cancelling counts toward your daily cap (you can't burn it for free), but freeing the slot lets you reserve something else right away.

### Convert to a purchase

Inside the hold window, click **Buy Now** as usual. The system bypasses the listing's stock check (your hold guarantees it) and sends you straight to PayFast.

---

## Seller view

Sellers see a reservation as a **`held`** state on the listing:

- The listing is hidden from the front-page Browse counters of "available".
- A small **Reserved** badge appears on the seller's `/marketplace/seller/listings` view with the holder's username and the hold expiry.
- Sellers cannot accept offers on a held listing - the hold blocks all other paths.
- If the buyer doesn't convert, the listing returns to `active` automatically at hold-expiry.

There is **no override**: a seller cannot revoke a reservation they've made. Operators can in extreme cases (fraud, abuse) via admin tools, but that's not exposed to sellers.

---

## When to reserve vs. when to offer

| Situation | Use |
| --- | --- |
| You want to think for a few hours, no negotiation | **Reserve** |
| You think the price is too high | **Make an Offer** |
| You're 100% sure | **Buy Now** |

Reserving and then making an offer on the same listing is allowed but not encouraged - the hold limits other buyers but doesn't get you a discount.

---

## See also

- *Marketplace - Buyer Quick Start*
- *Marketplace - Make an Offer*
- *Marketplace - Bidding & Auctions* (auctions cannot be reserved)
