# Marketplace - User Guide

## Overview

The Heratio Marketplace is an online buy/sell platform built for GLAM and DAM institutions (Galleries, Libraries, Archives, Museums, and Digital Asset Management). It enables institutions, artists, collectors, and estates to list items for sale, while buyers can purchase through three distinct sale types: **Fixed Price**, **Auction**, and **Make an Offer**.

The marketplace supports all five GLAM/DAM sectors, each with sector-specific categories tailored to the types of items that institutions and individuals typically sell.

---

## How It Works

```
+-------------------------------------------------------------------------+
|                      MARKETPLACE WORKFLOW                                 |
+-------------------------------------------------------------------------+
|                                                                           |
|   SELLER SIDE                              BUYER SIDE                     |
|   ──────────                               ──────────                     |
|                                                                           |
|   ┌──────────────┐                         ┌──────────────┐               |
|   │  Register as │                         │   Browse     │               |
|   │  Seller      │                         │   Marketplace│               |
|   └──────┬───────┘                         └──────┬───────┘               |
|          │                                        │                       |
|          v                                        v                       |
|   ┌──────────────┐                         ┌──────────────┐               |
|   │  Create      │                         │  View        │               |
|   │  Listing     │                         │  Listing     │               |
|   └──────┬───────┘                         └──────┬───────┘               |
|          │                                        │                       |
|          v                                        │                       |
|   ┌──────────────┐                     ┌──────────┴──────────┐            |
|   │  Publish     │                     │                     │            |
|   │  (or Review) │          ┌──────────┼──────────┐          │            |
|   └──────┬───────┘          │          │          │          │            |
|          │                  v          v          v          v             |
|          │          ┌──────────┐┌──────────┐┌──────────┐┌──────────┐      |
|          │          │  Buy Now ││  Make an ││  Place   ││  Send    │      |
|          │          │  (Fixed) ││  Offer   ││  Bid     ││  Enquiry │      |
|          │          └────┬─────┘└────┬─────┘└────┬─────┘└──────────┘      |
|          │               │           │           │                        |
|          │               v           v           v                        |
|          │          ┌────────────────────────────────┐                    |
|          │          │         TRANSACTION            │                    |
|          │          │   Payment ──> Shipping ──>     │                    |
|          │          │   Delivery ──> Completion       │                    |
|          │          └────────────────────────────────┘                    |
|          │                         │                                      |
|          v                         v                                      |
|   ┌──────────────┐          ┌──────────────┐                              |
|   │  Receive     │          │  Leave       │                              |
|   │  Payout      │          │  Review      │                              |
|   └──────────────┘          └──────────────┘                              |
|                                                                           |
+-------------------------------------------------------------------------+
```

---

## Sale Types

| Sale Type | How It Works |
|-----------|-------------|
| Fixed Price | Seller sets a price; buyer clicks "Buy Now" to purchase immediately |
| Auction | Buyers bid against each other; highest bid wins when the auction ends |
| Make an Offer | Buyer proposes a price; seller can accept, reject, or counter-offer |

---

## Sector Categories

Each GLAM/DAM sector has its own set of item categories:

| Sector | Categories |
|--------|-----------|
| Gallery | Painting, Sculpture, Drawing, Print, Photography, Mixed Media, Textile Art, Ceramics, Glass, Installation, Digital Art, Video Art, Performance Documentation |
| Museum | Reproduction, Merchandise, Catalog, Educational Material, Deaccessioned Object, Artifact Replica |
| Archive | Digital Scan, Research Package, Publication, Facsimile, Image License, Dataset |
| Library | Rare Book, Special Collection, E-Book, Manuscript Facsimile, Map Reproduction |
| DAM | Stock Image, Video Clip, Audio Recording, 3D Model, Design Asset, Font License |

---

## For Buyers

### Browsing the Marketplace

1. Navigate to **/marketplace** from the main menu
2. The browse page displays active listings in a card grid layout
3. Use the **filter panel** on the left to narrow results:
   - **Sector** - Gallery, Museum, Archive, Library, or DAM
   - **Category** - Sector-specific categories (e.g., Painting, Sculpture)
   - **Price range** - Set minimum and maximum price
   - **Condition** - Mint, Excellent, Good, Fair, or Poor
   - **Sale type** - Fixed Price, Auction, or Make an Offer
4. Toggle between **grid view** and **list view** using the view switcher
5. Sort results by:
   - Newest (default)
   - Price: Low to High
   - Price: High to Low
   - Most Viewed
   - Ending Soon (for auctions)

> **Tip:** Use the **/marketplace/sector/:sector** URL (e.g., `/marketplace/sector/gallery`) to browse a specific sector directly.

### Browsing by Sector or Category

1. Click a **sector tab** to filter all listings to that sector
2. Within a sector, click a **category** to drill down further
3. Category pages are accessible at **/marketplace/category/:sector/:slug** (e.g., `/marketplace/category/gallery/painting`)

### Browsing Auctions

1. Navigate to **/marketplace/auctions** to view all active auctions
2. Auctions are sorted by ending soonest by default
3. Each auction card shows the current bid, time remaining, and bid count

### Featured Listings

1. Navigate to **/marketplace/featured** to view curated, highlighted listings
2. Featured items are promoted by sellers (if a featured listing fee applies) or by administrators

---

### Viewing a Listing

1. Click any listing card to open the **listing detail page**
2. The listing page displays:
   - **Image gallery** - Multiple images with zoom capability
   - **Title and description** - Full item details
   - **Price** - Displayed amount (or "Price on Request" if applicable)
   - **Seller information** - Display name, location, rating, and link to seller profile
   - **Item details** - Medium, dimensions, year created, artist name, condition rating
   - **Provenance** - History of ownership (if provided)
   - **Condition** - Rating (Mint to Poor) and detailed description
   - **Shipping** - Domestic and international costs, shipping origin
   - **Edition and authenticity** - Edition info, signed status, certificate of authenticity
3. The action buttons displayed depend on the sale type:
   - **Fixed Price:** "Buy Now" button and optionally "Make an Offer" (if offer-only or minimum offer is set)
   - **Auction:** Current bid, bid count, time remaining, "Place Bid" button, and optionally "Buy Now" button
   - **Offer Only:** "Make an Offer" button

> **Note:** Items marked as "Price on Request" require you to send an enquiry to the seller for pricing.

---

### Buying (Fixed Price)

1. On a fixed-price listing, click **"Buy Now"**
2. You will be directed to the purchase flow at **/marketplace/buy/:slug**
3. Review the order summary:
   - Item price
   - Shipping cost (domestic or international)
   - VAT (included in price)
   - Grand total
4. Proceed to payment via the configured payment gateway (e.g., PayFast)
5. After payment, the listing is marked as **Sold** and a transaction record is created
6. You will receive an order confirmation

> **Note:** You must be logged in to make a purchase. Guest users will be prompted to log in first.

---

### Making an Offer

1. On listings with "Make an Offer" available (offer-only or fixed-price listings with offers enabled), click **"Make an Offer"**
2. You are taken to the offer form at **/marketplace/offer/:slug**
3. Enter your **offer amount**
   - If the seller has set a minimum offer, your amount must meet or exceed it
4. Optionally add a **message** to the seller explaining your offer
5. Submit the offer
6. The offer status flow:

```
  You submit offer
       │
       v
   ┌─────────┐     Seller accepts     ┌──────────┐
   │ PENDING  │ ─────────────────────> │ ACCEPTED │ ──> Proceed to Payment
   │          │                        └──────────┘
   │          │     Seller rejects     ┌──────────┐
   │          │ ─────────────────────> │ REJECTED │
   │          │                        └──────────┘
   │          │     Seller counters    ┌───────────┐
   │          │ ─────────────────────> │ COUNTERED │
   └─────────┘                        └─────┬─────┘
                                             │
                              ┌───────────────┼───────────────┐
                              v               v               v
                        ┌──────────┐   ┌──────────┐   ┌───────────┐
                        │ You      │   │ You      │   │ Offer     │
                        │ Accept   │   │ Reject   │   │ Expires   │
                        │ Counter  │   │ (withdraw│   │ (7 days   │
                        │          │   │  offer)  │   │  default) │
                        └──────────┘   └──────────┘   └───────────┘
```

7. If the seller sends a **counter-offer**, you can:
   - **Accept** the counter-offer (proceeds to payment at the counter amount)
   - **Withdraw** your offer (cancels the negotiation)
8. Offers expire after the configured period (default: **7 days**)
9. You can only have **one pending offer** per listing at a time

---

### Bidding at Auction

1. On an auction listing, click **"Place Bid"** to open the bid form at **/marketplace/bid/:slug**
2. The form displays:
   - **Current bid** (or starting bid if no bids yet)
   - **Minimum bid** (current bid + bid increment)
   - **Bid count** and **time remaining**
3. Enter your bid amount (must meet or exceed the minimum bid)
4. Optionally set a **maximum bid** for proxy/auto bidding:
   - The system will automatically bid on your behalf up to your maximum
   - Auto bids are placed at the minimum increment above the previous bid
   - Your maximum bid is kept private
5. Submit your bid

#### Reserve Price

- Some auctions have a **reserve price** set by the seller
- If the highest bid does not meet the reserve when the auction ends, the item is not sold
- The reserve price is not displayed, but you may see a "Reserve not yet met" indicator

#### Anti-Sniping Protection

- If a bid is placed in the final minutes of an auction (default: last **5 minutes**), the auction end time is automatically extended
- Extensions add the configured number of minutes (default: **5 minutes**)
- A maximum of **10 extensions** are allowed per auction

#### Buy Now in Auctions

- Some auctions offer a **"Buy Now"** option at a set price
- Click "Buy Now" to purchase immediately at the Buy Now price, ending the auction
- This option may be available even while bidding is active

#### Monitoring Your Bids

1. Navigate to **/marketplace/my/bids** to view all your active and past bids
2. Each entry shows the listing, your bid amount, current highest bid, and auction status
3. You will see whether your bid is currently winning or has been outbid

> **Tip:** Set a proxy bid (maximum bid) so you do not need to monitor the auction constantly. The system bids for you automatically.

---

### Enquiries

1. On any listing, click **"Send Enquiry"** to open the enquiry form at **/marketplace/enquiry/:slug**
2. Fill in:
   - Your **name** and **email** (pre-filled if logged in)
   - **Subject** line
   - **Message** to the seller
3. Submit the enquiry
4. The seller receives the enquiry and can reply directly
5. Enquiries are particularly useful for **Price on Request** items

> **Note:** Guest enquiries may be enabled or disabled by the platform administrator. If disabled, you must log in to send an enquiry.

---

### Your Account

Access your buyer account pages from **/marketplace/my/**:

#### My Purchases

- Navigate to **/marketplace/my/purchases**
- View all your orders with:
  - Transaction number
  - Item title and image
  - Sale price and total paid
  - Payment status and shipping status
  - Tracking number and courier (when shipped)
- **Confirm receipt** after you receive the item to complete the transaction
- After confirming receipt, you can leave a review

#### My Bids

- Navigate to **/marketplace/my/bids**
- View all your auction bids:
  - Active bids (auctions still running)
  - Won auctions (proceed to payment)
  - Lost bids (outbid or reserve not met)

#### My Offers

- Navigate to **/marketplace/my/offers**
- View all your sent offers:
  - Pending offers awaiting seller response
  - Countered offers requiring your action
  - Accepted offers (proceed to payment)
  - Rejected or withdrawn offers
- From this page you can **accept a counter-offer** or **withdraw a pending offer**

#### My Following

- Navigate to **/marketplace/my/following**
- View all sellers you follow
- Follow a seller by clicking "Follow" on their profile page (**/marketplace/seller/:slug**)
- Unfollow a seller from this page

#### Leaving Reviews

1. After a transaction is marked as **Completed** (you have confirmed receipt), navigate to **/marketplace/review/:id**
2. Rate the seller from **1 to 5 stars**
3. Add a **title** and optional **comment**
4. Submit your review
5. Reviews are visible on the seller's public profile
6. You can only leave **one review per transaction**

---

## For Sellers

### Getting Started

1. Navigate to **/marketplace/sell/register** to register as a seller
2. Fill in your seller profile:
   - **Display name** - Your public seller name
   - **Seller type** - Choose from:

| Seller Type | Description |
|-------------|-------------|
| Artist | Individual artist selling original work |
| Gallery | Gallery or art dealer |
| Institution | Museum, archive, library, or other GLAM institution |
| Collector | Private collector |
| Estate | Estate or trust |

3. **Select sectors** you sell in (Gallery, Museum, Archive, Library, DAM)
4. Add your contact details (email, phone, website, Instagram)
5. Set up **payout details**:
   - Payout method: Bank Transfer, PayPal, PayFast, or Manual
   - Payout currency (default: ZAR)
   - Banking details or payment account information
6. Accept the terms and conditions
7. Submit your registration

> **Note:** Seller registration may be open or closed depending on platform settings. New sellers start with "Unverified" status and "New" trust level. An administrator may need to verify your account before you can list items.

---

### Your Dashboard

1. Navigate to **/marketplace/sell** to access the seller dashboard
2. The dashboard shows an overview of:
   - **Total listings** (active, draft, all)
   - **Total sales** and **total revenue**
   - **Pending payouts** amount
   - **Follower count**
3. Quick links to manage listings, offers, transactions, and payouts
4. Access **analytics** at **/marketplace/sell/analytics** for detailed performance data

---

### Creating a Listing

1. Navigate to **/marketplace/sell/listings/create**
2. Fill in the listing form:

#### Basic Information

| Field | Description |
|-------|-------------|
| Title | Item title (required) |
| Description | Detailed description of the item |
| Short Description | Brief summary for card displays |
| Sector | Gallery, Museum, Archive, Library, or DAM (required) |
| Category | Sector-specific category (e.g., Painting, Sculpture) |

#### Item Details

| Field | Description |
|-------|-------------|
| Artist Name | Creator/artist name |
| Medium | Materials or medium (e.g., Oil on canvas) |
| Dimensions | Physical dimensions (e.g., 60 x 80 cm) |
| Weight | Weight in kilograms (for shipping calculations) |
| Year Created | Year or date range of creation |
| Condition Rating | Mint, Excellent, Good, Fair, or Poor |
| Condition Description | Detailed condition notes |
| Provenance | Ownership history and chain of custody |
| Is Framed | Whether the item is framed |
| Frame Description | Details about the frame |
| Edition Info | Edition details (e.g., 1/50, Artist Proof) |
| Is Signed | Whether the item is signed by the artist |
| Certificate of Authenticity | Whether a COA is included |

#### Sale Type and Pricing

3. Choose a **sale type**:

| Sale Type | Required Fields |
|-----------|----------------|
| Fixed Price | Price (required) |
| Auction | Starting Bid, Start Time, End Time (required); Reserve Price, Buy Now Price, Bid Increment (optional) |
| Offer Only | Minimum Offer (optional) |

4. Additional pricing options:
   - **Price on Request** - Hides the price; buyers must enquire
   - **Currency** - Select from supported currencies (ZAR, USD, EUR, GBP, AUD)

#### Images

5. Navigate to the images page at **/marketplace/sell/listings/:id/images** after saving
6. Upload up to **20 images** per listing (configurable)
7. Set one image as the **primary image** (used as the listing thumbnail)
8. Add **captions** to each image
9. Drag to **reorder** images

#### Shipping Configuration

| Field | Description |
|-------|-------------|
| Is Digital | Check if the item is a digital product (no shipping required) |
| Requires Shipping | Whether physical shipping is needed |
| Shipping From (Country/City) | Origin for shipping calculations |
| Domestic Shipping Price | Cost for domestic delivery |
| International Shipping Price | Cost for international delivery |
| Free Domestic Shipping | Toggle free shipping within the seller's country |
| Shipping Notes | Additional shipping information for buyers |
| Insurance Value | Declared value for shipping insurance |

#### Tags

10. Add **tags** for searchability (stored as JSON array)
11. Tags help buyers find your listing through search and filters

---

### Managing Listings

#### Listing Lifecycle

```
  ┌───────┐     Publish      ┌────────────────┐    Admin Approves    ┌────────┐
  │ DRAFT │ ───────────────> │ PENDING REVIEW │ ──────────────────> │ ACTIVE │
  └───────┘                  └────────────────┘                     └────┬───┘
      ^                            │                                     │
      │                  Admin Rejects                                   │
      │ <──────────────────────────┘                              Expires│Sold│Withdraw
      │                                                                  │
      │    ┌─────────┐    ┌──────┐    ┌───────────┐                     │
      └─── │ EXPIRED │    │ SOLD │    │ WITHDRAWN │ <───────────────────┘
           └─────────┘    └──────┘    └───────────┘
```

> **Note:** If listing moderation is enabled by the administrator, your listing goes to "Pending Review" when you publish. If moderation is disabled, the listing goes directly to "Active".

#### Listing Actions

1. Navigate to **/marketplace/sell/listings** to view all your listings
2. Available actions per listing:
   - **Edit** - Modify listing details (available for Draft and Active listings)
   - **Images** - Manage listing images
   - **Publish** - Submit for review or make active (from Draft or Expired)
   - **Withdraw** - Remove from marketplace (from Active or Pending Review)
3. Listings expire after the configured duration (default: **90 days**)
4. **Renew expired listings** by publishing them again (they return to Draft or Pending Review)

---

### Handling Offers

1. Navigate to **/marketplace/sell/offers** to view incoming offers
2. Each offer shows:
   - Buyer name
   - Offer amount and currency
   - Optional buyer message
   - Offer status and submission date
   - Expiry date
3. For each pending offer, you can:
   - **Accept** - Agrees to the offer amount; the listing is reserved and the buyer proceeds to payment
   - **Reject** - Declines the offer; optionally include a response message
   - **Counter** - Propose a different amount; the buyer can then accept or withdraw
4. Navigate to **/marketplace/sell/offers/:id/respond** to respond to a specific offer

#### Counter-Offer Workflow

1. When you send a counter-offer, enter the **counter amount** and an optional **message**
2. The buyer receives the counter-offer and can:
   - Accept your counter (proceeds to payment at the counter amount)
   - Withdraw their offer (ends the negotiation)
3. Counter-offers also have an expiry period (default: **7 days**)
4. If the buyer does not respond, the counter-offer expires automatically

---

### Managing Sales

1. Navigate to **/marketplace/sell/transactions** to view all your transactions
2. Each transaction shows:
   - Transaction number
   - Item title
   - Buyer information
   - Sale price, commission, and your payout amount
   - Payment status and shipping status
3. Click a transaction to view full details at **/marketplace/sell/transactions/:id**

#### Updating Shipping

1. Once payment is confirmed, update shipping information:
   - **Tracking number** - Enter the tracking code
   - **Courier** - Name of the shipping provider
   - **Shipping status** - Mark as Shipped, In Transit, or Delivered
2. The buyer is notified of shipping updates
3. When the buyer confirms receipt, the transaction is marked as **Completed**

#### Transaction Flow

| Status | Meaning |
|--------|---------|
| Pending Payment | Transaction created, awaiting buyer payment |
| Paid | Payment received from buyer |
| Shipping | Item shipped, tracking provided |
| Delivered | Item delivered to buyer |
| Completed | Buyer confirmed receipt; sale is final |
| Cancelled | Transaction cancelled |
| Disputed | Buyer or seller raised a dispute |
| Refunded | Payment returned to buyer |

---

### Payouts

1. Navigate to **/marketplace/sell/payouts** to view your payout history

#### How Commission Works

- The platform takes a commission on each sale (default: **10%**)
- Commission is calculated on the sale price
- Example: Sale price R1,000 with 10% commission = R100 platform fee, R900 seller payout
- Individual sellers may have a custom commission rate set by the administrator

#### Payout Process

1. When a buyer confirms receipt, a **pending payout** is automatically created
2. A **cooling period** applies (default: **5 days** after delivery confirmation)
3. After the cooling period, the payout becomes eligible for processing
4. The administrator processes the payout via the configured method
5. Payout status progresses: **Pending** --> **Processing** --> **Completed**

#### Payout Methods

| Method | Description |
|--------|-------------|
| Bank Transfer | Direct deposit to your bank account |
| PayPal | Transfer to your PayPal account |
| PayFast | Transfer via PayFast |
| Manual | Arranged directly with the platform |

---

### Collections

1. Navigate to **/marketplace/sell/collections** to manage your curated collections
2. Click **"Create Collection"** at **/marketplace/sell/collections/create**
3. Fill in:
   - **Title** - Collection name
   - **Description** - What the collection is about
   - **Cover image** - Representative image for the collection
   - **Public/Private** - Whether the collection is visible to buyers
4. Add listings to the collection after creation
5. Collections can be used to showcase:
   - Thematic groupings (e.g., "Landscape Paintings")
   - Exhibition-related works
   - Seasonal promotions or sales
   - Genre-specific selections

#### Collection Types

| Type | Description |
|------|-------------|
| Curated | General curated selection |
| Exhibition | Tied to a specific exhibition |
| Seasonal | Time-limited seasonal promotion |
| Featured | Highlighted by the platform |
| Genre | Grouped by genre or style |
| Sale | Discounted items or clearance |

> **Note:** Featured collections are set by administrators, not sellers.

---

### Reviews

1. Navigate to **/marketplace/sell/reviews** to view reviews from buyers
2. Each review shows:
   - Buyer name
   - Star rating (1-5)
   - Review title and comment
   - Date submitted
3. Your **average rating** and **rating count** are displayed on your public seller profile
4. Flagged or inappropriate reviews can be reported to the platform administrator for moderation

---

### Seller Analytics

1. Navigate to **/marketplace/sell/analytics** for detailed performance metrics
2. View:
   - Monthly revenue trends
   - Total sales count and revenue
   - Listing performance (views, favourites, enquiries)
   - Payout summaries

---

### Managing Enquiries

1. Navigate to **/marketplace/sell/enquiries** to view buyer enquiries
2. Each enquiry shows the listing, buyer name, email, subject, and message
3. Enquiry statuses: **New**, **Read**, **Replied**, **Closed**
4. Reply to enquiries directly from the management page

---

## For Administrators

### Platform Dashboard

1. Navigate to **/marketplace/admin** to access the admin dashboard
2. The dashboard displays:
   - Total sellers (verified, pending, suspended)
   - Total listings (active, pending review, expired)
   - Total transactions and revenue
   - Platform commission earned
   - Pending actions queue (listings to review, sellers to verify, payouts to process)

---

### Moderating Listings

1. Navigate to **/marketplace/admin/listings** to view all listings
2. Filter by status: Pending Review, Active, Suspended, etc.
3. Click a listing to review at **/marketplace/admin/listings/:id/review**
4. For pending listings, you can:
   - **Approve** - Makes the listing Active with the configured listing duration
   - **Reject** - Returns the listing to Draft status; the seller can edit and resubmit
   - **Suspend** - Removes the listing from public view

> **Note:** Listing moderation can be toggled on or off in Platform Settings. When disabled, seller listings go directly to Active when published.

---

### Verifying Sellers

1. Navigate to **/marketplace/admin/sellers** to view all seller accounts
2. Filter by verification status: Unverified, Pending, Verified, Suspended
3. Click a seller to review at **/marketplace/admin/sellers/:id/verify**
4. Review seller documentation and profile details
5. Available actions:
   - **Verify** - Sets status to "Verified" and trust level to "Active"
   - **Suspend** - Deactivates the seller account and hides their listings

#### Verification Statuses

| Status | Description |
|--------|-------------|
| Unverified | New seller, not yet reviewed |
| Pending | Seller has submitted verification documents |
| Verified | Identity confirmed, seller can list and sell |
| Suspended | Account deactivated by administrator |

#### Trust Levels

| Level | Description |
|-------|-------------|
| New | Recently registered seller |
| Active | Verified and actively selling |
| Trusted | Established seller with good track record |
| Premium | Top-tier seller status |

---

### Managing Transactions

1. Navigate to **/marketplace/admin/transactions** to view all platform transactions
2. Filter by status, seller, buyer, date range
3. Each transaction shows:
   - Transaction number
   - Buyer and seller names
   - Sale price, commission amount, seller payout amount
   - Payment status and shipping status
   - Overall transaction status

---

### Managing Payouts

1. Navigate to **/marketplace/admin/payouts** to view all pending and processed payouts
2. For each pending payout:
   - Verify the cooling period has elapsed
   - Click **"Process"** to begin processing
   - After transferring funds, mark as **"Completed"** with an optional payment reference
3. **Batch processing**: Select multiple eligible payouts and process them together at **/marketplace/admin/payouts/batch**
4. Payout statuses:

| Status | Description |
|--------|-------------|
| Pending | Awaiting cooling period and admin processing |
| Processing | Admin has initiated the payout |
| Completed | Funds transferred to seller |
| Failed | Payout attempt failed |
| Cancelled | Payout cancelled |

---

### Managing Reviews

1. Navigate to **/marketplace/admin/reviews** to view and moderate all reviews
2. Flagged reviews appear highlighted for attention
3. Available actions:
   - **Show** - Make a hidden review visible again
   - **Hide** - Remove a review from public display
   - **Clear flag** - Dismiss a flag if the review is appropriate
4. Moderation recalculates the seller's average rating automatically

---

### Managing Categories

1. Navigate to **/marketplace/admin/categories** to manage item categories
2. Categories are organized by sector
3. Each category has:
   - Name and slug
   - Parent category (for hierarchical categories)
   - Sort order
   - Active/Inactive toggle

---

### Managing Currencies

1. Navigate to **/marketplace/admin/currencies** to manage supported currencies
2. Default currencies: ZAR, USD, EUR, GBP, AUD
3. For each currency:
   - Set the **exchange rate to ZAR** (base currency)
   - Toggle **active/inactive**
   - Set **sort order** for display

---

### Platform Settings

Navigate to **/marketplace/admin/settings** to configure the marketplace.

#### General Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Platform Name | Heratio Marketplace | Public marketplace name |
| Default Commission Rate | 10% | Percentage taken from each sale |
| Listing Moderation Enabled | Yes | Require admin approval for new listings |
| Listing Duration (days) | 90 | How long listings stay active before expiring |
| Minimum Listing Price | 1.00 | Lowest allowed listing price |
| Maximum Images Per Listing | 20 | Image upload limit per listing |
| Featured Listing Fee | 0 | Cost to feature a listing (0 = free) |
| VAT Rate | 15% | VAT percentage (prices include VAT) |
| Default Currency | ZAR | Platform base currency |
| Seller Registration Open | Yes | Allow new seller registrations |
| Guest Enquiries Enabled | Yes | Allow non-logged-in users to send enquiries |
| Terms URL | /marketplace/terms | Link to marketplace terms and conditions |

#### Offer Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Offer Expiry (days) | 7 | Days before an unresponded offer expires |

#### Auction Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Auto-Extend Minutes | 5 | Minutes added when a late bid triggers anti-sniping |
| Maximum Extensions | 10 | Maximum number of anti-sniping extensions per auction |

#### Payout Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Cooling Period (days) | 5 | Days after delivery confirmation before payout is released |

#### Payment Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Supported Payment Gateways | PayFast | Enabled payment gateway(s) |

---

### Admin Reports

1. Navigate to **/marketplace/admin/reports** for platform-wide reports
2. Available metrics:
   - Revenue summaries (monthly, quarterly, annual)
   - Commission earned by the platform
   - Top sellers by revenue and sales count
   - Top listings by views and favourites
   - Transaction volume and status breakdown
   - Payout summaries and outstanding amounts

---

## Status Reference

### Listing Statuses

| Status | Meaning |
|--------|---------|
| Draft | Listing created but not published |
| Pending Review | Submitted for admin approval |
| Active | Live on the marketplace |
| Reserved | Buyer has committed; payment pending |
| Sold | Transaction completed |
| Expired | Listing duration has elapsed |
| Withdrawn | Seller removed from marketplace |
| Suspended | Admin removed from marketplace |

### Offer Statuses

| Status | Meaning |
|--------|---------|
| Pending | Awaiting seller response |
| Accepted | Seller agreed to the offer |
| Rejected | Seller declined the offer |
| Countered | Seller proposed a different amount |
| Withdrawn | Buyer cancelled the offer |
| Expired | Offer expired without response |

### Auction Statuses

| Status | Meaning |
|--------|---------|
| Upcoming | Auction scheduled but not yet started |
| Active | Bidding is open |
| Ended | Auction has closed |
| Cancelled | Auction was cancelled |

### Transaction Statuses

| Status | Meaning |
|--------|---------|
| Pending Payment | Awaiting buyer payment |
| Paid | Payment received |
| Shipping | Item shipped to buyer |
| Delivered | Item delivered |
| Completed | Buyer confirmed receipt; sale finalized |
| Cancelled | Transaction cancelled |
| Disputed | Issue raised by buyer or seller |
| Refunded | Payment returned to buyer |

### Shipping Statuses

| Status | Meaning |
|--------|---------|
| Pending | Not yet shipped |
| Preparing | Seller preparing shipment |
| Shipped | Item dispatched with tracking |
| In Transit | Item in transit to buyer |
| Delivered | Item delivered to buyer |
| Returned | Item returned to seller |

### Payout Statuses

| Status | Meaning |
|--------|---------|
| Pending | Awaiting cooling period and processing |
| Processing | Admin initiated the transfer |
| Completed | Funds transferred to seller |
| Failed | Transfer failed |
| Cancelled | Payout cancelled |

---

## URL Reference

### Public Pages

| URL | Description |
|-----|-------------|
| /marketplace | Browse all listings |
| /marketplace/search | Search listings |
| /marketplace/sector/:sector | Browse by sector |
| /marketplace/category/:sector/:slug | Browse by category |
| /marketplace/auctions | Browse active auctions |
| /marketplace/featured | Featured listings |
| /marketplace/collection/:slug | View a curated collection |
| /marketplace/seller/:slug | View seller profile |
| /marketplace/listing/:slug | View listing detail |

### Buyer Pages (Login Required)

| URL | Description |
|-----|-------------|
| /marketplace/buy/:slug | Purchase a fixed-price item |
| /marketplace/offer/:slug | Make an offer |
| /marketplace/bid/:slug | Place a bid |
| /marketplace/enquiry/:slug | Send an enquiry |
| /marketplace/my/purchases | Your purchase history |
| /marketplace/my/bids | Your bid history |
| /marketplace/my/offers | Your offer history |
| /marketplace/my/following | Sellers you follow |
| /marketplace/review/:id | Leave a review |

### Seller Pages (Seller Account Required)

| URL | Description |
|-----|-------------|
| /marketplace/sell | Seller dashboard |
| /marketplace/sell/register | Register as a seller |
| /marketplace/sell/profile | Edit seller profile |
| /marketplace/sell/listings | Manage listings |
| /marketplace/sell/listings/create | Create new listing |
| /marketplace/sell/listings/:id/edit | Edit a listing |
| /marketplace/sell/listings/:id/images | Manage listing images |
| /marketplace/sell/offers | View incoming offers |
| /marketplace/sell/transactions | View transactions |
| /marketplace/sell/payouts | View payouts |
| /marketplace/sell/reviews | View reviews |
| /marketplace/sell/enquiries | View enquiries |
| /marketplace/sell/collections | Manage collections |
| /marketplace/sell/analytics | View analytics |

### Admin Pages (Administrator Required)

| URL | Description |
|-----|-------------|
| /marketplace/admin | Admin dashboard |
| /marketplace/admin/listings | Manage all listings |
| /marketplace/admin/sellers | Manage sellers |
| /marketplace/admin/transactions | View all transactions |
| /marketplace/admin/payouts | Process payouts |
| /marketplace/admin/reviews | Moderate reviews |
| /marketplace/admin/categories | Manage categories |
| /marketplace/admin/currencies | Manage currencies |
| /marketplace/admin/settings | Platform settings |
| /marketplace/admin/reports | Platform reports |

---

## Tips

- **Sellers:** Upload high-quality images and write detailed descriptions to attract buyers
- **Buyers:** Set a proxy bid on auctions so the system bids automatically up to your maximum
- **Sellers:** Use collections to group related listings and showcase themes or exhibitions
- **Buyers:** Follow sellers you like to stay updated on their new listings
- **Sellers:** Respond to offers promptly; they expire after the configured period
- **Admins:** Process payouts regularly; pending payouts affect seller trust and satisfaction
- **Buyers:** Always confirm receipt after delivery so the seller can receive their payout
- **Sellers:** Provide tracking numbers as soon as items are shipped to keep buyers informed

---

## Need Help?

Contact the platform administrator if you:
- Cannot register as a seller
- Have questions about commission rates or payouts
- Need to report an issue with a transaction
- Experience payment or shipping problems
- Want to dispute a review
