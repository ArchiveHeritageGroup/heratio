# Marketplace - Buyer Quick Start

A short walk-through of the buyer side of the Heratio Marketplace: registering, finding items, and the three ways to acquire something.

---

## 1. Get an account

Anyone with a Heratio account is automatically a buyer - no separate registration is required. New users can sign up at **`/marketplace/register`** (chooses between buyer-only and seller signup).

> Authenticated convenience entry: **`/marketplace/buyer/start`** lands logged-in users on the Browse page with a confirmation flash.

---

## 2. Find an item

| Page | URL | When to use |
| --- | --- | --- |
| Browse | `/marketplace/browse` | Filter by sector / category / price |
| Search | `/marketplace/search` | Free-text search across all listings |
| Featured | `/marketplace/featured` | Curated highlights from the operator |
| Auctions only | `/marketplace/auction-browse` | Live and upcoming auctions |
| By sector | `/marketplace/sector` | Walk a single GLAM sector (Gallery / Library / Archive / Museum / DAM) |
| By collection | `/marketplace/collection` | Items grouped by seller-curated collections |
| By seller | `/marketplace/seller` | All listings from one seller |
| Single listing | `/marketplace/listing?id=…` | Item detail page |

Each listing has a **price block** showing one of three sale types: **Buy Now** (fixed price), **Auction**, or **Make an Offer**.

---

## 3. Acquire the item - three paths

### a. Buy Now (fixed price)

1. Open the listing.
2. Click **Buy Now**.
3. You're redirected to PayFast to pay.
4. After PayFast confirms, the transaction moves from `pending_payment` → `paid`. The seller is notified.

> Behind the scenes: `POST /marketplace/checkout/buy/{listingId}` creates the transaction and hands you off to PayFast's `process` URL. The ITN webhook (`/marketplace/payfast/notify`) flips the transaction to `paid` once the bank confirms.

### b. Auction

1. Open the auction listing or browse `/marketplace/auction-browse`.
2. Click **Place bid** - the bid form opens (`/marketplace/bid-form`).
3. Track your bids at **My Bids** (`/marketplace/my-bids`). Outbid → re-bid; winning bid at close → checkout.
4. When the auction closes and you're the winner, complete payment via `POST /marketplace/checkout/win/{auctionId}`.

### c. Make an Offer

1. Open the listing and click **Make an Offer** (`/marketplace/offer-form`).
2. Submit your price + optional message.
3. Watch **My Offers** (`/marketplace/my-offers`) for seller responses: accepted / countered / declined.
4. If accepted, you're prompted to pay via PayFast.

---

## 4. Hold an item - Reservations

Spotted something you want to think about? Use **Reserve** on a fixed-price listing:

- 12-hour hold. Buy Now during the window or it auto-releases.
- Maximum **2 reservations per buyer per 24 hours**.
- Cancel any time at the listing page or via My Following.

> Endpoints: `POST /marketplace/listing/reserve/{listingId}`, `POST /marketplace/reservation/cancel/{reservationId}`.

---

## 5. After the purchase

| Page | What's there |
| --- | --- |
| **My Purchases** (`/marketplace/my-purchases`) | All paid items, with a "Download licence" link per item |
| **My Licences** (`/marketplace/my-licences`) | Per-item licences for digital purchases (CC / commercial / etc.) |
| **My Following** (`/marketplace/my-following`) | Listings you favourited |
| **My Bids** | Active and historical bids |
| **My Offers** | Offers you've made + counters from the seller |
| **Review form** (`/marketplace/review-form`) | Leave a seller review after delivery |

---

## 6. Payment & refunds

- All payments run through **PayFast**. The site never stores card details.
- A successful payment returns you to `/marketplace/payment/return`. Cancellations land at `/marketplace/payment/cancel`.
- For refunds, contact the seller first; if unresolved, escalate via the marketplace operator (admin contact in site settings).

---

## See also

- *Marketplace - Seller Quick Start*
- *Marketplace - Bidding & Auctions*
- *Marketplace - Make an Offer*
- *Marketplace - Reservations & Holds*
