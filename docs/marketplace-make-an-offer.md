# Marketplace - Make an Offer

How buyers negotiate a price on listings flagged "Open to offers", and how sellers respond.

---

## When can I make an offer?

Listings show a **Make an Offer** button when the seller has enabled offers (sale type = Open to offers, or fixed-price listings with `accepts_offers = 1`).

If the listing is auction-only, offers are not accepted while bidding is live - submit a bid instead.

---

## Buyer flow

### Submit an offer

1. Open the listing → **Make an Offer**.
2. Form opens at **`/marketplace/offer-form`**.
3. Enter your price and an optional message (e.g. "willing to collect from your store").
4. Submit. The seller receives an email and the offer appears in their offers queue.

### Track your offers

**`/marketplace/my-offers`** lists each offer with current state:

| State | What it means | What you can do |
| --- | --- | --- |
| **Pending** | Seller hasn't responded yet | Wait, or **withdraw** before they reply |
| **Countered** | Seller proposed a different price | **Accept**, **counter back**, or **decline** |
| **Accepted** | Seller agreed to your offer | Pay within the payment window |
| **Declined** | Seller rejected | Make a new offer with a different price |
| **Expired** | The offer wasn't responded to in time | Reset / re-offer |

A counter exchange can go back and forth - the system records the full chain.

### Pay an accepted offer

When the seller accepts (or you accept their counter), the listing is **held for you** for the payment window (operator-set, typically 48 hours):

```
POST /marketplace/buy   (paths the accepted offer through PayFast)
```

After payment confirmation the transaction is created in the same way as Buy Now.

---

## Seller flow

### Configure offers

In the listing form, tick **Accepts offers** and optionally set:

- **Minimum acceptable offer** - automatically rejects offers below this price.
- **Auto-accept threshold** - automatically accepts offers above this price (e.g. if you list at R5 000 and set auto-accept at R4 500, any offer ≥ R4 500 closes the deal immediately).

### Respond to offers

Offers land at **`/marketplace/seller/offers`** with a count badge. Click into one → opens **`/marketplace/seller/offer-respond`**:

- **Accept** - locks the listing for the buyer; PayFast checkout is initiated.
- **Counter** - set a price + message; the buyer chooses again.
- **Decline** - rejects with optional reason.

Endpoint behind the form: `POST /marketplace/seller/offer-respond` (CSRF + acl:update).

### Auto-decline rules

If the buyer's offer is below your **Minimum acceptable offer** the system declines automatically with a polite reason. You won't see those in your queue (audit-only).

---

## Etiquette & best practice

- Sellers should respond within 48 hours; pending offers tie up your stock and frustrate buyers.
- Buyers should make realistic offers - repeat low-ball offers can be ignored or, in operator settings, can result in a temporary cooldown.
- When countering, include a short reason - "this is my floor because of provenance", "I can do this if you collect" - it dramatically improves accept-rate.

---

## See also

- *Marketplace - Buyer Quick Start*
- *Marketplace - Seller Quick Start*
- *Marketplace - Reservations & Holds* (a different way to lock an item without negotiating)
