# ahgMarketplacePlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Commerce / GLAM Marketplace
**Dependencies:** atom-framework (>=2.0.0), ahgCorePlugin
**Optional Integration:** ahgCartPlugin, ahgGalleryPlugin, ahgExhibitionPlugin, ahgFavoritesPlugin, ahgLoanPlugin, ahgHeritagePlugin, ahgLandingPagePlugin, ahgDisplayPlugin

---

## Overview

The ahgMarketplacePlugin provides a comprehensive online marketplace for GLAM and DAM institutions built on the Heratio (AtoM) archival platform. It supports fixed-price sales, make-an-offer negotiations, and timed auctions with anti-sniping protection across all five GLAM/DAM sectors (Gallery, Museum, Archive, Library, DAM).

**Key Features:**
- Multi-sector marketplace supporting Gallery, Museum, Archive, Library, and DAM categories
- Three sales models: fixed-price, make-an-offer negotiation, and timed auctions (English, sealed-bid, Dutch)
- Seller verification workflow with trust levels (new, active, trusted, premium)
- Platform commission tracking with per-seller overrides
- Multi-currency support (ZAR, USD, EUR, GBP, AUD) with exchange rate conversion via ZAR base
- Payout management (bank transfer, PayPal, PayFast, Stripe Connect) with configurable cooling period
- Buyer/seller review and rating system
- Curated collections and storefronts
- Auction anti-sniping (auto-extend) and proxy bidding
- Full-text search with autocomplete and faceted filtering
- Admin dashboard with moderation, seller verification, and financial reporting

**Architecture Decisions:**
- Repository pattern separates data access (8 repositories) from business logic (12 services)
- All database access uses Laravel Query Builder (Illuminate\Database) per framework standards
- Services are lazy-loaded via `require_once` inside action classes (Symfony 1.x autoloader limitation)
- Plugin registers routes via `AtomFramework\Routing\RouteLoader` event listener
- Namespaced under `AtomAhgPlugins\ahgMarketplacePlugin`

---

## Architecture

```
+------------------------------------------------------------------------+
|                       ahgMarketplacePlugin                              |
+------------------------------------------------------------------------+
|                                                                         |
|  +-----------------------------+    +-----------------------------+     |
|  |   Plugin Configuration      |    |      Routing System         |     |
|  |  ahgMarketplacePlugin       |    |  RouteLoader('marketplace') |     |
|  |  Configuration.class.php    |    |  57 routes registered       |     |
|  +-----------------------------+    +-----------------------------+     |
|              |                               |                          |
|              v                               v                          |
|  +------------------------------------------------------------------+  |
|  |                      Services (12)                                |  |
|  |  +------------------+  +------------------+  +------------------+ |  |
|  |  | MarketplaceService|  | AuctionService   |  | OfferService     | |  |
|  |  | (Listing CRUD)    |  | (Bidding, proxy) |  | (Negotiation)    | |  |
|  |  +------------------+  +------------------+  +------------------+ |  |
|  |  +------------------+  +------------------+  +------------------+ |  |
|  |  | TransactionService|  | SellerService    |  | PayoutService    | |  |
|  |  | (Sales, payment)  |  | (Registration)   |  | (Disbursement)   | |  |
|  |  +------------------+  +------------------+  +------------------+ |  |
|  |  +------------------+  +------------------+  +------------------+ |  |
|  |  | ShippingService   |  | ReviewService    |  | CollectionService| |  |
|  |  | (Estimates, track)|  | (Ratings, mod.)  |  | (Curated sets)   | |  |
|  |  +------------------+  +------------------+  +------------------+ |  |
|  |  +------------------+  +------------------+  +------------------+ |  |
|  |  | CurrencyService   |  | Marketplace      |  | Marketplace      | |  |
|  |  | (Convert, format) |  | SearchService    |  | Notification Svc | |  |
|  |  +------------------+  +------------------+  +------------------+ |  |
|  +------------------------------------------------------------------+  |
|              |                                                          |
|              v                                                          |
|  +------------------------------------------------------------------+  |
|  |                    Repositories (8)                                |  |
|  |  SellerRepo  ListingRepo  AuctionRepo  OfferRepo                  |  |
|  |  TransactionRepo  ReviewRepo  CollectionRepo  SettingsRepo        |  |
|  +------------------------------------------------------------------+  |
|              |                                                          |
|              v                                                          |
|  +------------------------------------------------------------------+  |
|  |              Database (16 tables, Laravel Query Builder)           |  |
|  |  marketplace_settings   marketplace_currency  marketplace_category |  |
|  |  marketplace_seller     marketplace_listing   marketplace_listing_ |  |
|  |  marketplace_auction    marketplace_bid          image             |  |
|  |  marketplace_offer      marketplace_transaction                    |  |
|  |  marketplace_payout     marketplace_review                         |  |
|  |  marketplace_enquiry    marketplace_follow                         |  |
|  |  marketplace_collection marketplace_collection_item                |  |
|  +------------------------------------------------------------------+  |
|                                                                         |
+------------------------------------------------------------------------+
                              |
                              v
+------------------------------------------------------------------------+
|                    External Plugin Integration                           |
|  +--------------+  +-------------+  +-------------+  +--------------+  |
|  | ahgCartPlugin|  | ahgGallery  |  | ahgFavorites|  | ahgHeritage  |  |
|  | (Checkout)   |  | Plugin      |  | Plugin      |  | Plugin       |  |
|  +--------------+  +-------------+  +-------------+  +--------------+  |
|  +--------------+  +-------------+  +-------------+                    |
|  | ahgLoanPlugin|  | ahgLanding  |  | ahgDisplay  |                    |
|  | (Loans)      |  | PagePlugin  |  | Plugin      |                    |
|  +--------------+  +-------------+  +-------------+                    |
+------------------------------------------------------------------------+
```

---

## Database Schema

### Table Summary

| # | Table | Purpose | Row Type |
|---|-------|---------|----------|
| 1 | `marketplace_settings` | Platform-wide configuration key/value store | Config |
| 2 | `marketplace_currency` | Supported currencies with exchange rates to ZAR | Config |
| 3 | `marketplace_category` | Hierarchical item categories per GLAM sector | Config |
| 4 | `marketplace_seller` | Seller/gallery/institution profiles with verification | Entity |
| 5 | `marketplace_listing` | Items for sale (core entity) | Entity |
| 6 | `marketplace_listing_image` | Multiple images per listing | Entity |
| 7 | `marketplace_auction` | Auction configuration per listing (1:1) | Entity |
| 8 | `marketplace_bid` | Individual bids on auctions | Event |
| 9 | `marketplace_offer` | Make-an-offer negotiations | Event |
| 10 | `marketplace_transaction` | Completed sales with commission breakdown | Financial |
| 11 | `marketplace_payout` | Seller payout records | Financial |
| 12 | `marketplace_review` | Buyer/seller ratings (1-5 stars) | Social |
| 13 | `marketplace_enquiry` | Item enquiries (supports guest) | Social |
| 14 | `marketplace_follow` | Follow seller relationships | Social |
| 15 | `marketplace_collection` | Curated collections / storefronts | Entity |
| 16 | `marketplace_collection_item` | Listings assigned to collections (M:N) | Junction |

### ERD Diagram

```
+----------------------------+        +----------------------------+
|   marketplace_settings     |        |   marketplace_currency     |
+----------------------------+        +----------------------------+
| PK id INT                  |        | PK id INT                  |
|    setting_key VARCHAR(100) |        |    code VARCHAR(3) UK      |
|    setting_value TEXT       |        |    name VARCHAR(100)       |
|    setting_type ENUM       |        |    symbol VARCHAR(10)      |
|    setting_group VARCHAR(50)|        |    exchange_rate_to_zar    |
|    description VARCHAR(255) |        |      DECIMAL(12,6)        |
|    created_at DATETIME     |        |    is_active TINYINT       |
|    updated_at DATETIME     |        |    sort_order INT          |
+----------------------------+        +----------------------------+

+----------------------------+
|   marketplace_category     |
+----------------------------+
| PK id INT                  |
|    sector ENUM(5)          |<--+
|    parent_id INT (FK self) |---+  (hierarchical)
|    name VARCHAR(100)       |
|    slug VARCHAR(100)       |
|    description TEXT         |
|    icon VARCHAR(50)        |
|    sort_order INT          |
|    is_active TINYINT       |
+----------------------------+
         |
         | 1:N
         v
+----------------------------+        +----------------------------+
|   marketplace_listing      |------->|  marketplace_listing_image  |
+----------------------------+  1:N   +----------------------------+
| PK id BIGINT UNSIGNED      |        | PK id BIGINT UNSIGNED      |
|    listing_number VARCHAR UK|        | FK listing_id BIGINT       |
| FK seller_id BIGINT        |        |    file_path VARCHAR(500)  |
|    information_object_id   |        |    file_name VARCHAR(255)  |
|    sector ENUM(5)          |        |    mime_type VARCHAR(100)  |
|    listing_type ENUM(3)    |        |    caption VARCHAR(500)    |
|    status ENUM(8)          |        |    is_primary TINYINT      |
|    title VARCHAR(500)      |        |    sort_order INT          |
|    slug VARCHAR(500) UK    |        +----------------------------+
|    description TEXT         |
|    short_description       |        +----------------------------+
| FK category_id INT         |------->|   marketplace_auction      |
|    medium VARCHAR(255)     |  1:1   +----------------------------+
|    dimensions VARCHAR      |        | PK id BIGINT UNSIGNED      |
|    weight_kg DECIMAL       |        | FK listing_id BIGINT UK    |
|    year_created VARCHAR    |        |    auction_type ENUM(3)    |
|    artist_name VARCHAR     |        |    status ENUM(4)          |
|    provenance TEXT         |        |    starting_bid DECIMAL    |
|    condition_rating ENUM(5)|        |    reserve_price DECIMAL   |
|    price DECIMAL(12,2)     |        |    bid_increment DECIMAL   |
|    currency VARCHAR(3)     |        |    buy_now_price DECIMAL   |
|    price_on_request TINYINT|        |    current_bid DECIMAL     |
|    minimum_offer DECIMAL   |        |    current_bidder_id INT   |
|    reserve_price DECIMAL   |        |    bid_count INT           |
|    starting_bid DECIMAL    |        |    start_time DATETIME     |
|    buy_now_price DECIMAL   |        |    end_time DATETIME       |
|    shipping_* fields       |        |    auto_extend_minutes INT |
|    tags JSON               |        |    extension_count INT     |
|    featured_image_path     |        |    max_extensions INT      |
|    view_count INT          |        |    winner_id INT           |
|    favourite_count INT     |        |    winning_bid DECIMAL     |
|    enquiry_count INT       |        +----------------------------+
|    listed_at DATETIME      |                    |
|    expires_at DATETIME     |                    | 1:N
|    sold_at DATETIME        |                    v
+----------------------------+        +----------------------------+
         |                            |   marketplace_bid          |
         | 1:N                        +----------------------------+
         v                            | PK id BIGINT UNSIGNED      |
+----------------------------+        | FK auction_id BIGINT       |
|   marketplace_offer        |        |    user_id INT             |
+----------------------------+        |    bid_amount DECIMAL      |
| PK id BIGINT UNSIGNED      |        |    max_bid DECIMAL (proxy) |
| FK listing_id BIGINT       |        |    is_auto_bid TINYINT     |
|    buyer_id INT            |        |    is_winning TINYINT      |
|    status ENUM(6)          |        |    ip_address VARCHAR(45)  |
|    offer_amount DECIMAL    |        |    user_agent VARCHAR(255) |
|    currency VARCHAR(3)     |        |    created_at DATETIME     |
|    message TEXT             |        +----------------------------+
|    seller_response TEXT    |
|    counter_amount DECIMAL  |
|    expires_at DATETIME     |
|    responded_at DATETIME   |
+----------------------------+

+----------------------------+        +----------------------------+
|  marketplace_transaction   |------->|   marketplace_payout       |
+----------------------------+  1:N   +----------------------------+
| PK id BIGINT UNSIGNED      |        | PK id BIGINT UNSIGNED      |
|    transaction_number UK   |        | FK seller_id BIGINT        |
| FK listing_id BIGINT       |        | FK transaction_id BIGINT   |
| FK seller_id BIGINT        |        |    payout_number VARCHAR UK|
|    buyer_id INT            |        |    amount DECIMAL(12,2)    |
|    source ENUM(3)          |        |    currency VARCHAR(3)     |
| FK offer_id BIGINT NULL    |        |    method ENUM(5)          |
| FK auction_id BIGINT NULL  |        |    status ENUM(5)          |
|    sale_price DECIMAL      |        |    reference VARCHAR(255)  |
|    currency VARCHAR(3)     |        |    payout_details JSON     |
|    platform_commission_rate|        |    processed_by INT        |
|    platform_commission_amt |        |    processed_at DATETIME   |
|    seller_amount DECIMAL   |        |    notes TEXT              |
|    vat_amount DECIMAL      |        +----------------------------+
|    total_with_vat DECIMAL  |
|    shipping_cost DECIMAL   |
|    insurance_cost DECIMAL  |        +----------------------------+
|    grand_total DECIMAL     |        |   marketplace_review       |
|    payment_status ENUM(5)  |        +----------------------------+
|    payment_gateway VARCHAR |        | PK id BIGINT UNSIGNED      |
|    payment_transaction_id  |        | FK transaction_id BIGINT   |
|    gateway_response JSON   |        |    reviewer_id INT         |
|    paid_at DATETIME        |        | FK reviewed_seller_id BIGINT|
|    shipping_status ENUM(6) |        |    review_type ENUM(2)     |
|    tracking_number VARCHAR |        |    rating INT (1-5)        |
|    courier VARCHAR         |        |    title VARCHAR(255)      |
|    shipped_at DATETIME     |        |    comment TEXT             |
|    delivered_at DATETIME   |        |    is_visible TINYINT      |
|    buyer_confirmed_receipt |        |    flagged TINYINT          |
|    status ENUM(8)          |        |    flagged_reason TEXT      |
|    completed_at DATETIME   |        +----------------------------+
+----------------------------+

+----------------------------+        +----------------------------+
|   marketplace_enquiry      |        |   marketplace_follow       |
+----------------------------+        +----------------------------+
| PK id BIGINT UNSIGNED      |        | PK id BIGINT UNSIGNED      |
| FK listing_id BIGINT       |        |    user_id INT             |
|    user_id INT NULL        |        | FK seller_id BIGINT        |
|    name VARCHAR(255)       |        |    created_at DATETIME     |
|    email VARCHAR(255)      |        | UK (user_id, seller_id)    |
|    phone VARCHAR(50)       |        +----------------------------+
|    subject VARCHAR(255)    |
|    message TEXT             |
|    status ENUM(4)          |
|    reply TEXT               |
|    replied_by BIGINT       |
|    replied_at DATETIME     |
+----------------------------+

+----------------------------+        +----------------------------+
|  marketplace_collection    |------->| marketplace_collection_item |
+----------------------------+  1:N   +----------------------------+
| PK id BIGINT UNSIGNED      |        | PK id BIGINT UNSIGNED      |
| FK seller_id BIGINT NULL   |        | FK collection_id BIGINT    |
|    title VARCHAR(255)      |        | FK listing_id BIGINT       |
|    slug VARCHAR(255) UK    |        |    sort_order INT          |
|    description TEXT         |        |    curator_note TEXT        |
|    cover_image_path VARCHAR|        | UK (collection_id,listing_id)|
|    collection_type ENUM(6) |        +----------------------------+
|    is_public TINYINT       |
|    is_featured TINYINT     |
|    sort_order INT          |
|    created_by INT          |
+----------------------------+
```

### Key Relationships

| From Table | To Table | Relationship | FK Constraint |
|------------|----------|-------------|---------------|
| marketplace_listing | marketplace_seller | N:1 | `fk_listing_seller` CASCADE |
| marketplace_listing | marketplace_category | N:1 | `fk_listing_category` SET NULL |
| marketplace_listing_image | marketplace_listing | N:1 | `fk_image_listing` CASCADE |
| marketplace_auction | marketplace_listing | 1:1 | `fk_auction_listing` CASCADE |
| marketplace_bid | marketplace_auction | N:1 | `fk_bid_auction` CASCADE |
| marketplace_offer | marketplace_listing | N:1 | `fk_offer_listing` CASCADE |
| marketplace_transaction | marketplace_listing | N:1 | `fk_txn_listing` (no cascade) |
| marketplace_transaction | marketplace_seller | N:1 | `fk_txn_seller` (no cascade) |
| marketplace_transaction | marketplace_offer | N:1 | `fk_txn_offer` SET NULL |
| marketplace_transaction | marketplace_auction | N:1 | `fk_txn_auction` SET NULL |
| marketplace_payout | marketplace_seller | N:1 | `fk_payout_seller` (no cascade) |
| marketplace_payout | marketplace_transaction | N:1 | `fk_payout_txn` SET NULL |
| marketplace_review | marketplace_transaction | N:1 | `fk_review_txn` (no cascade) |
| marketplace_review | marketplace_seller | N:1 | `fk_review_seller` (no cascade) |
| marketplace_enquiry | marketplace_listing | N:1 | `fk_enquiry_listing` CASCADE |
| marketplace_follow | marketplace_seller | N:1 | `fk_follow_seller` CASCADE |
| marketplace_collection | marketplace_seller | N:1 | `fk_collection_seller` SET NULL |
| marketplace_collection_item | marketplace_collection | N:1 | `fk_ci_collection` CASCADE |
| marketplace_collection_item | marketplace_listing | N:1 | `fk_ci_listing` CASCADE |
| marketplace_category | marketplace_category | N:1 (self) | `fk_marketplace_category_parent` SET NULL |

### FULLTEXT Index

The `marketplace_listing` table has a FULLTEXT index `idx_search` on columns `(title, description, artist_name, medium)` used by `MarketplaceSearchService` for search and autocomplete.

---

## Repositories (8)

All repositories use `Illuminate\Database\Capsule\Manager as DB` (Laravel Query Builder). Located in `lib/Repositories/`.

### SellerRepository

| Method | Description |
|--------|-------------|
| `getById(int $id): ?object` | Get seller by primary key |
| `getBySlug(string $slug): ?object` | Get seller by URL slug |
| `getByUserId(int $userId): ?object` | Get seller by `created_by` user ID |
| `create(array $data): int` | Insert seller, return ID |
| `update(int $id, array $data): bool` | Update seller fields |
| `slugExists(string $slug): bool` | Check slug uniqueness |
| `browse(array $filters, int $limit, int $offset): array` | Browse sellers with filtering |
| `getFollowerCount(int $sellerId): int` | Count followers |
| `incrementSales(int $sellerId, float $amount): void` | Increment total_sales and total_revenue |
| `updateRating(int $sellerId): void` | Recalculate average_rating from visible reviews |

### ListingRepository

| Method | Description |
|--------|-------------|
| `getById(int $id): ?object` | Get listing by primary key |
| `getBySlug(string $slug): ?object` | Get listing by URL slug |
| `getByListingNumber(string $number): ?object` | Get by listing number |
| `create(array $data): int` | Insert listing, return ID |
| `update(int $id, array $data): bool` | Update listing fields |
| `delete(int $id): bool` | Delete listing |
| `browse(array $filters, int $limit, int $offset, string $sort): array` | Browse with filters, sorting, pagination |
| `generateListingNumber(): string` | Generate unique listing number |
| `incrementViewCount(int $id): void` | Increment view_count |
| `getFeatured(int $limit): array` | Get featured active listings |
| `getFacetCounts(array $filters): array` | Get facet counts (sector, type, condition) |
| `getExpiredListings(): array` | Get active listings past expires_at |
| `getSellerListings(int $sellerId): array` | Get all listings for a seller |
| `getImages(int $listingId): array` | Get listing images ordered by sort_order |
| `getImageCount(int $listingId): int` | Count images for a listing |
| `addImage(array $data): int` | Insert listing image |
| `setImagePrimary(int $listingId, int $imageId): void` | Set one image as primary, clear others |
| `deleteImage(int $imageId): bool` | Delete an image |

### AuctionRepository

| Method | Description |
|--------|-------------|
| `getById(int $id): ?object` | Get auction by primary key |
| `getByListingId(int $listingId): ?object` | Get auction for a listing |
| `create(array $data): int` | Insert auction, return ID |
| `update(int $id, array $data): bool` | Update auction fields |
| `placeBid(array $data): int` | Insert bid record |
| `clearWinningFlags(int $auctionId): void` | Clear is_winning on all bids for auction |
| `getHighestBid(int $auctionId): ?object` | Get highest bid for auction |
| `getProxyBids(int $auctionId): array` | Get bids with max_bid set (proxy bids) |
| `getBids(int $auctionId, int $limit): array` | Get bid history ordered by amount DESC |
| `getUserBids(int $userId, int $limit, int $offset): array` | Get all bids by a user |
| `getActiveAuctions(int $limit, int $offset): array` | Get active auctions with listing data |
| `getEndingSoon(int $minutes): array` | Get auctions ending within N minutes |
| `getAuctionsToStart(): array` | Get upcoming auctions past start_time |
| `getAuctionsToEnd(): array` | Get active auctions past end_time |

### OfferRepository

| Method | Description |
|--------|-------------|
| `getById(int $id): ?object` | Get offer by primary key |
| `create(array $data): int` | Insert offer, return ID |
| `update(int $id, array $data): bool` | Update offer fields |
| `hasPendingOffer(int $listingId, int $buyerId): bool` | Check if buyer has pending offer |
| `getBuyerOffers(int $userId, int $limit, int $offset): array` | Get offers made by a buyer |
| `getSellerOffers(int $sellerId, ?string $status, int $limit, int $offset): array` | Get offers on seller listings |
| `getOfferWithDetails(int $id): ?object` | Get offer with listing and buyer details |
| `getExpiredOffers(): array` | Get pending/countered offers past expires_at |

### TransactionRepository

| Method | Description |
|--------|-------------|
| `getById(int $id): ?object` | Get transaction by primary key |
| `create(array $data): int` | Insert transaction, return ID |
| `update(int $id, array $data): bool` | Update transaction fields |
| `generateTransactionNumber(): string` | Generate unique transaction number |
| `generatePayoutNumber(): string` | Generate unique payout number |
| `getTransactionWithDetails(int $id): ?object` | Get transaction with listing and seller joins |
| `getBuyerTransactions(int $userId, int $limit, int $offset): array` | Buyer purchase history |
| `getSellerTransactions(int $sellerId, int $limit, int $offset): array` | Seller sales history |
| `getRevenueStats(?int $sellerId): array` | Revenue statistics (total sales, amounts) |
| `getMonthlyRevenue(?int $sellerId, int $months): array` | Monthly revenue breakdown |
| `getSellerPendingPayoutAmount(int $sellerId): float` | Sum of pending payouts for a seller |
| `createPayout(array $data): int` | Insert payout record |
| `getPayoutById(int $id): ?object` | Get payout by primary key |
| `updatePayout(int $id, array $data): bool` | Update payout fields |
| `getSellerPayouts(int $sellerId, int $limit, int $offset): array` | Seller payout history |
| `getPendingPayouts(int $limit): array` | All pending payouts (admin) |
| `getAllPayoutsForAdmin(array $filters, int $limit, int $offset): array` | Admin payout browse |

### ReviewRepository

| Method | Description |
|--------|-------------|
| `getById(int $id): ?object` | Get review by primary key |
| `create(array $data): int` | Insert review, return ID |
| `update(int $id, array $data): bool` | Update review fields |
| `hasReviewed(int $txnId, int $userId): bool` | Check if user already reviewed transaction |
| `getSellerReviews(int $sellerId, int $limit, int $offset): array` | Get visible reviews for a seller |
| `getSellerRatingStats(int $sellerId): array` | Rating distribution (1-5 star counts) |

### CollectionRepository

| Method | Description |
|--------|-------------|
| `getById(int $id): ?object` | Get collection by primary key |
| `getBySlug(string $slug): ?object` | Get collection by URL slug |
| `create(array $data): int` | Insert collection, return ID |
| `update(int $id, array $data): bool` | Update collection fields |
| `delete(int $id): bool` | Delete collection (cascades items) |
| `slugExists(string $slug): bool` | Check slug uniqueness |
| `addItem(int $collectionId, int $listingId, int $sortOrder, ?string $note): int` | Add listing to collection |
| `removeItem(int $collectionId, int $listingId): bool` | Remove listing from collection |
| `getItems(int $collectionId): array` | Get collection items with listing details |
| `getItemCount(int $collectionId): int` | Count items in collection |
| `getPublicCollections(int $limit, int $offset): array` | Browse public collections |
| `getSellerCollections(int $sellerId): array` | Get seller's collections |
| `getFeatured(int $limit): array` | Get featured collections |

### SettingsRepository

| Method | Description |
|--------|-------------|
| `get(string $key, $default): mixed` | Get setting value by key |
| `set(string $key, $value): void` | Set setting value |
| `getGroup(string $group): array` | Get all settings in a group |
| `getCurrencies(bool $activeOnly): array` | Get currencies |
| `getCurrency(string $code): ?object` | Get currency by code |
| `updateCurrency(string $code, array $data): void` | Update currency fields |
| `convertToZar(float $amount, string $from): float` | Convert to ZAR using exchange rate |
| `convertFromZar(float $zarAmount, string $to): float` | Convert from ZAR to target currency |

---

## Services (12)

All services are located in `lib/Services/` and namespaced under `AtomAhgPlugins\ahgMarketplacePlugin\Services`.

### MarketplaceService

Primary service for listing lifecycle management.

```php
class MarketplaceService
{
    // Listing CRUD
    public function createListing(int $sellerId, array $data): array
    public function updateListing(int $id, array $data): array
    public function publishListing(int $id): array
    public function approveListing(int $id): array
    public function rejectListing(int $id): array
    public function withdrawListing(int $id): array
    public function markSold(int $id): void

    // Browse & Search
    public function browse(array $filters = [], int $limit = 24, int $offset = 0, string $sort = 'newest'): array
    public function getListing(string $slug): ?object  // Also increments view_count
    public function getListingById(int $id): ?object
    public function getListingImages(int $listingId): array
    public function getFeaturedListings(int $limit = 12): array
    public function getFacetCounts(array $filters = []): array

    // Images
    public function addListingImage(int $listingId, array $data): int
    public function setPrimaryImage(int $listingId, int $imageId): void
    public function deleteListingImage(int $imageId): bool

    // Expiry
    public function processExpiredListings(): int

    // Settings
    public function getSetting(string $key, $default = null)
}
```

### AuctionService

Manages auction lifecycle, bidding, anti-sniping, and proxy bids.

```php
class AuctionService
{
    public function createAuction(int $listingId, array $data): array
    public function placeBid(int $auctionId, int $userId, float $amount, ?float $maxBid = null): array
    public function buyNow(int $auctionId, int $userId): array
    public function endAuction(int $auctionId): array
    public function getAuctionStatus(int $auctionId): ?array
    public function getActiveAuctions(int $limit = 24, int $offset = 0): array
    public function getEndingSoon(int $minutes = 60): array
    public function getBidHistory(int $auctionId, int $limit = 50): array
    public function getUserBids(int $userId, int $limit = 50, int $offset = 0): array
    public function processAuctionLifecycle(): array  // Start upcoming, end expired
}
```

### OfferService

Handles make-an-offer negotiation including counter-offers.

```php
class OfferService
{
    public function createOffer(int $listingId, int $buyerId, float $amount, ?string $message = null): array
    public function acceptOffer(int $offerId): array
    public function rejectOffer(int $offerId, ?string $response = null): array
    public function counterOffer(int $offerId, float $counterAmount, ?string $response = null): array
    public function withdrawOffer(int $offerId, int $buyerId): array
    public function acceptCounter(int $offerId, int $buyerId): array
    public function getBuyerOffers(int $userId, int $limit = 50, int $offset = 0): array
    public function getSellerOffers(int $sellerId, ?string $status = null, int $limit = 50, int $offset = 0): array
    public function getOfferWithDetails(int $id): ?object
    public function processExpiredOffers(): int
}
```

### TransactionService

Handles transaction creation from all three sales channels, payment, shipping, and receipt confirmation.

```php
class TransactionService
{
    public function createFromFixedPrice(int $listingId, int $buyerId): array
    public function createFromOffer(int $offerId, int $buyerId): array
    public function createFromAuction(int $auctionId): array
    public function markPaid(int $txnId, string $gateway, string $gatewayTxnId, ?array $gatewayResponse = null): array
    public function updateShipping(int $txnId, array $data): array
    public function confirmReceipt(int $txnId, int $buyerId): array
    public function getTransaction(int $id): ?object
    public function getBuyerTransactions(int $userId, int $limit = 50, int $offset = 0): array
    public function getSellerTransactions(int $sellerId, int $limit = 50, int $offset = 0): array
    public function getRevenueStats(?int $sellerId = null): array
    public function getMonthlyRevenue(?int $sellerId = null, int $months = 12): array
}
```

### SellerService

Seller registration, profile management, verification, and dashboard statistics.

```php
class SellerService
{
    public function register(int $userId, array $data): array
    public function updateProfile(int $sellerId, array $data): array
    public function verifySeller(int $sellerId): array
    public function suspendSeller(int $sellerId): array
    public function getSellerBySlug(string $slug): ?object
    public function getSellerByUserId(int $userId): ?object
    public function getSellerById(int $id): ?object
    public function getDashboardStats(int $sellerId): array
    public function browseSellers(array $filters = [], int $limit = 20, int $offset = 0): array
}
```

### PayoutService

Payout processing with cooling period enforcement and batch operations.

```php
class PayoutService
{
    public function processPayout(int $payoutId, int $processedBy): array
    public function completePayout(int $payoutId, ?string $reference = null): array
    public function batchProcess(array $payoutIds, int $processedBy): array
    public function getSellerPayouts(int $sellerId, int $limit = 50, int $offset = 0): array
    public function getPendingPayouts(int $limit = 100): array
    public function getPayoutStats(?int $sellerId = null): array
}
```

### ShippingService

Shipping estimates (domestic/international/digital), tracking, and delivery confirmation.

```php
class ShippingService
{
    public function getShippingEstimate(int $listingId, string $country): array
    public function updateTracking(int $txnId, string $trackingNumber, string $courier): array
    public function getTrackingInfo(int $txnId): ?array
    public function confirmDelivery(int $txnId): array
}
```

### ReviewService

Buyer/seller review management with moderation.

```php
class ReviewService
{
    public function createReview(int $txnId, int $reviewerId, int $rating, string $title, ?string $comment = null, string $type = 'buyer_to_seller'): array
    public function getSellerReviews(int $sellerId, int $limit = 20, int $offset = 0): array
    public function hasReviewed(int $txnId, int $userId): bool
    public function getRatingStats(int $sellerId): array
    public function flagReview(int $reviewId, string $reason): array
    public function moderateReview(int $reviewId, bool $visible): array
}
```

### CollectionService

Curated collection management (create, update, add/remove items).

```php
class CollectionService
{
    public function createCollection(int $sellerId, array $data): array
    public function updateCollection(int $id, array $data): array
    public function deleteCollection(int $id): array
    public function addItem(int $collectionId, int $listingId, int $sortOrder = 0, ?string $note = null): array
    public function removeItem(int $collectionId, int $listingId): array
    public function getCollection(string $slug): ?array
    public function getPublicCollections(int $limit = 20, int $offset = 0): array
    public function getSellerCollections(int $sellerId): array
    public function getFeatured(int $limit = 6): array
}
```

### CurrencyService

Multi-currency conversion (via ZAR base), formatting, and exchange rate management.

```php
class CurrencyService
{
    public function getCurrencies(bool $activeOnly = true): array
    public function getCurrency(string $code): ?object
    public function getDefaultCurrency(): string
    public function convert(float $amount, string $from, string $to): array
    public function formatPrice(float $amount, string $currencyCode): string
    public function updateExchangeRate(string $code, float $rate): array
}
```

### MarketplaceSearchService

Full-text search, autocomplete, popular searches, and filter construction.

```php
class MarketplaceSearchService
{
    public function search(string $query, array $filters = [], int $limit = 24, int $offset = 0): array
    public function getAutocompleteSuggestions(string $query, int $limit = 10): array
    public function getPopularSearches(int $limit = 10): array
    public function buildSearchFilters(array $params): array
}
```

### MarketplaceNotificationService

Event logging for marketplace events. Currently logs to PHP error_log; designed as a hook point for email/push notification integration.

```php
class MarketplaceNotificationService
{
    public function notifyBidPlaced(int $auctionId, float $bidAmount): void
    public function notifyAuctionEnding(int $auctionId): void
    public function notifyOfferReceived(int $offerId): void
    public function notifySaleCompleted(int $txnId): void
    public function notifyPayoutProcessed(int $payoutId): void
    public function notifyListingApproved(int $listingId): void
}
```

---

## Routes (57 total)

All routes are registered in `ahgMarketplacePluginConfiguration.class.php` via `RouteLoader('marketplace')`. Module: `marketplace`.

### Public Routes (9) - No authentication required

| Route Name | URL Pattern | Action | Description |
|------------|-------------|--------|-------------|
| `ahg_marketplace_browse` | `/marketplace` | browse | Main marketplace browse page |
| `ahg_marketplace_search` | `/marketplace/search` | search | Full-text search with filters |
| `ahg_marketplace_sector` | `/marketplace/sector/:sector` | sector | Browse by GLAM sector |
| `ahg_marketplace_category` | `/marketplace/category/:sector/:slug` | category | Browse by category within sector |
| `ahg_marketplace_auctions` | `/marketplace/auctions` | auctionBrowse | Browse active auctions |
| `ahg_marketplace_featured` | `/marketplace/featured` | featured | Browse featured listings |
| `ahg_marketplace_collection` | `/marketplace/collection/:slug` | collection | View curated collection |
| `ahg_marketplace_seller` | `/marketplace/seller/:slug` | seller | View seller profile/storefront |
| `ahg_marketplace_listing` | `/marketplace/listing/:slug` | listing | View listing detail page |

### Buyer Routes (10) - Authentication required

| Route Name | URL Pattern | Action | Description |
|------------|-------------|--------|-------------|
| `ahg_marketplace_buy` | `/marketplace/buy/:slug` | buy | Buy now / add to cart |
| `ahg_marketplace_offer` | `/marketplace/offer/:slug` | offerForm | Make an offer form |
| `ahg_marketplace_bid` | `/marketplace/bid/:slug` | bidForm | Place bid form |
| `ahg_marketplace_enquiry` | `/marketplace/enquiry/:slug` | enquiryForm | Send enquiry to seller |
| `ahg_marketplace_my_purchases` | `/marketplace/my/purchases` | myPurchases | Buyer purchase history |
| `ahg_marketplace_my_bids` | `/marketplace/my/bids` | myBids | Buyer bid history |
| `ahg_marketplace_my_offers` | `/marketplace/my/offers` | myOffers | Buyer offer history |
| `ahg_marketplace_my_following` | `/marketplace/my/following` | myFollowing | Followed sellers |
| `ahg_marketplace_follow` | `POST /marketplace/follow/:seller` | follow | Follow/unfollow seller |
| `ahg_marketplace_review` | `/marketplace/review/:id` | reviewForm | Leave review for transaction |

### Seller Routes (20) - Authentication + seller profile required

| Route Name | URL Pattern | Action | Description |
|------------|-------------|--------|-------------|
| `ahg_marketplace_sell` | `/marketplace/sell` | dashboard | Seller dashboard |
| `ahg_marketplace_sell_register` | `/marketplace/sell/register` | sellerRegister | Seller registration form |
| `ahg_marketplace_sell_profile` | `/marketplace/sell/profile` | sellerProfile | Edit seller profile |
| `ahg_marketplace_sell_listings` | `/marketplace/sell/listings` | sellerListings | Manage listings |
| `ahg_marketplace_sell_listing_create` | `/marketplace/sell/listings/create` | sellerListingCreate | Create new listing |
| `ahg_marketplace_sell_listing_edit` | `/marketplace/sell/listings/:id/edit` | sellerListingEdit | Edit listing |
| `ahg_marketplace_sell_listing_images` | `/marketplace/sell/listings/:id/images` | sellerListingImages | Manage listing images |
| `ahg_marketplace_sell_listing_publish` | `/marketplace/sell/listings/:id/publish` | sellerListingPublish | Publish/submit for review |
| `ahg_marketplace_sell_listing_withdraw` | `/marketplace/sell/listings/:id/withdraw` | sellerListingWithdraw | Withdraw listing |
| `ahg_marketplace_sell_offers` | `/marketplace/sell/offers` | sellerOffers | View received offers |
| `ahg_marketplace_sell_offer_respond` | `/marketplace/sell/offers/:id/respond` | sellerOfferRespond | Accept/reject/counter offer |
| `ahg_marketplace_sell_transactions` | `/marketplace/sell/transactions` | sellerTransactions | Sales history |
| `ahg_marketplace_sell_transaction_detail` | `/marketplace/sell/transactions/:id` | sellerTransactionDetail | Transaction detail |
| `ahg_marketplace_sell_payouts` | `/marketplace/sell/payouts` | sellerPayouts | Payout history |
| `ahg_marketplace_sell_reviews` | `/marketplace/sell/reviews` | sellerReviews | Reviews received |
| `ahg_marketplace_sell_enquiries` | `/marketplace/sell/enquiries` | sellerEnquiries | Item enquiries |
| `ahg_marketplace_sell_collections` | `/marketplace/sell/collections` | sellerCollections | Manage curated collections |
| `ahg_marketplace_sell_collection_create` | `/marketplace/sell/collections/create` | sellerCollectionCreate | Create collection |
| `ahg_marketplace_sell_analytics` | `/marketplace/sell/analytics` | sellerAnalytics | Sales analytics |

### Admin Routes (13) - Admin authentication required

| Route Name | URL Pattern | Action | Description |
|------------|-------------|--------|-------------|
| `ahg_marketplace_admin` | `/marketplace/admin` | adminDashboard | Admin overview dashboard |
| `ahg_marketplace_admin_listings` | `/marketplace/admin/listings` | adminListings | All listings with moderation |
| `ahg_marketplace_admin_listing_review` | `/marketplace/admin/listings/:id/review` | adminListingReview | Approve/reject listing |
| `ahg_marketplace_admin_sellers` | `/marketplace/admin/sellers` | adminSellers | All sellers |
| `ahg_marketplace_admin_seller_verify` | `/marketplace/admin/sellers/:id/verify` | adminSellerVerify | Verify/suspend seller |
| `ahg_marketplace_admin_transactions` | `/marketplace/admin/transactions` | adminTransactions | All transactions |
| `ahg_marketplace_admin_payouts` | `/marketplace/admin/payouts` | adminPayouts | Payout management |
| `ahg_marketplace_admin_payouts_batch` | `POST /marketplace/admin/payouts/batch` | adminPayoutsBatch | Batch process payouts |
| `ahg_marketplace_admin_reviews` | `/marketplace/admin/reviews` | adminReviews | Review moderation |
| `ahg_marketplace_admin_categories` | `/marketplace/admin/categories` | adminCategories | Category management |
| `ahg_marketplace_admin_currencies` | `/marketplace/admin/currencies` | adminCurrencies | Currency/exchange rate management |
| `ahg_marketplace_admin_settings` | `/marketplace/admin/settings` | adminSettings | Platform settings |
| `ahg_marketplace_admin_reports` | `/marketplace/admin/reports` | adminReports | Financial reports |

### API Routes (6) - AJAX / future mobile

| Route Name | URL Pattern | Method | Action | Description |
|------------|-------------|--------|--------|-------------|
| `ahg_marketplace_api_search` | `/marketplace/api/search` | GET | apiSearch | Search listings (JSON) |
| `ahg_marketplace_api_bid` | `/marketplace/api/listing/:id/bid` | POST | apiBid | Place bid (AJAX) |
| `ahg_marketplace_api_favourite` | `/marketplace/api/listing/:id/favourite` | POST | apiFavourite | Toggle favourite (AJAX) |
| `ahg_marketplace_api_auction_status` | `/marketplace/api/auction/:id/status` | GET | apiAuctionStatus | Poll auction status (JSON) |
| `ahg_marketplace_api_currencies` | `/marketplace/api/currencies` | GET | apiCurrencies | List active currencies (JSON) |
| `ahg_marketplace_api_categories` | `/marketplace/api/categories/:sector` | GET | apiCategories | Categories for sector (JSON) |

---

## Templates

### Success Templates (46)

All located in `modules/marketplace/templates/`.

#### Public (9)

| Template | Route | Description |
|----------|-------|-------------|
| `browseSuccess.php` | browse | Main marketplace grid with sidebar filters |
| `searchSuccess.php` | search | Search results with facets |
| `listingSuccess.php` | listing | Listing detail page (images, price, actions) |
| `sellerSuccess.php` | seller | Seller storefront / profile page |
| `sectorSuccess.php` | sector | Sector landing page |
| `categorySuccess.php` | category | Category listings page |
| `auctionBrowseSuccess.php` | auctionBrowse | Active auctions grid |
| `featuredSuccess.php` | featured | Featured listings page |
| `collectionSuccess.php` | collection | Curated collection view |

#### Buyer (8)

| Template | Route | Description |
|----------|-------|-------------|
| `offerFormSuccess.php` | offerForm | Make an offer form |
| `bidFormSuccess.php` | bidForm | Place bid form with auction timer |
| `enquiryFormSuccess.php` | enquiryForm | Enquiry form |
| `myPurchasesSuccess.php` | myPurchases | Purchase history table |
| `myBidsSuccess.php` | myBids | Bid history table |
| `myOffersSuccess.php` | myOffers | Offer history table |
| `myFollowingSuccess.php` | myFollowing | Followed sellers grid |
| `reviewFormSuccess.php` | reviewForm | Review submission form |

#### Seller (17)

| Template | Route | Description |
|----------|-------|-------------|
| `dashboardSuccess.php` | dashboard | Seller dashboard with stats |
| `sellerRegisterSuccess.php` | sellerRegister | Seller registration form |
| `sellerProfileSuccess.php` | sellerProfile | Edit seller profile |
| `sellerListingsSuccess.php` | sellerListings | Listing management table |
| `sellerListingCreateSuccess.php` | sellerListingCreate | Create listing form |
| `sellerListingEditSuccess.php` | sellerListingEdit | Edit listing form |
| `sellerListingImagesSuccess.php` | sellerListingImages | Image upload/sort interface |
| `sellerOffersSuccess.php` | sellerOffers | Received offers table |
| `sellerOfferRespondSuccess.php` | sellerOfferRespond | Offer response form |
| `sellerTransactionsSuccess.php` | sellerTransactions | Sales table |
| `sellerTransactionDetailSuccess.php` | sellerTransactionDetail | Transaction detail view |
| `sellerPayoutsSuccess.php` | sellerPayouts | Payout history table |
| `sellerReviewsSuccess.php` | sellerReviews | Reviews table |
| `sellerEnquiriesSuccess.php` | sellerEnquiries | Enquiry management |
| `sellerCollectionsSuccess.php` | sellerCollections | Collection management |
| `sellerCollectionCreateSuccess.php` | sellerCollectionCreate | Create collection form |
| `sellerAnalyticsSuccess.php` | sellerAnalytics | Analytics charts |

#### Admin (12)

| Template | Route | Description |
|----------|-------|-------------|
| `adminDashboardSuccess.php` | adminDashboard | Admin overview with key metrics |
| `adminListingsSuccess.php` | adminListings | All listings with status filters |
| `adminListingReviewSuccess.php` | adminListingReview | Listing moderation form |
| `adminSellersSuccess.php` | adminSellers | Seller management table |
| `adminSellerVerifySuccess.php` | adminSellerVerify | Seller verification form |
| `adminTransactionsSuccess.php` | adminTransactions | Transaction overview |
| `adminPayoutsSuccess.php` | adminPayouts | Payout queue management |
| `adminReviewsSuccess.php` | adminReviews | Review moderation queue |
| `adminCategoriesSuccess.php` | adminCategories | Category CRUD |
| `adminCurrenciesSuccess.php` | adminCurrencies | Currency management |
| `adminSettingsSuccess.php` | adminSettings | Platform settings form |
| `adminReportsSuccess.php` | adminReports | Financial reports |

### Partials (10)

| Partial | Description |
|---------|-------------|
| `_listingCard.php` | Reusable listing card (image, title, price, seller badge) |
| `_auctionTimer.php` | Countdown timer for auctions (JavaScript) |
| `_priceDisplay.php` | Price display with currency symbol and conversion |
| `_sellerBadge.php` | Seller verification badge and trust level indicator |
| `_offerForm.php` | Embedded offer form partial |
| `_bidForm.php` | Embedded bid form partial |
| `_imageGallery.php` | Image gallery/carousel for listing detail |
| `_filterSidebar.php` | Browse filter sidebar (sector, type, price range, condition) |
| `_shippingInfo.php` | Shipping information display |
| `_breadcrumb.php` | Breadcrumb navigation |

---

## Business Logic

### Listing Lifecycle

```
                    +--------+
                    | (new)  |
                    +--------+
                        |
                        v
+----------+       +---------+      moderation      +----------------+
| expired  |<------| draft   |--------------------->| pending_review |
+----------+       +---------+      enabled?        +----------------+
     |                  ^                                |         |
     |    (re-list)     |                      approve   |  reject |
     +------------------+                                v         v
                        |                           +--------+  +-------+
            (withdraw)  |             moderation     | active |  | draft |
          +-------------+             disabled?      +--------+  +-------+
          |                                              |
          |                                    +---------+---------+
          |                                    |                   |
   +-----------+                          +----------+       +-----------+
   | withdrawn |                          | reserved |       | suspended |
   +-----------+                          +----------+       +-----------+
                                               |
                                               v
                                           +------+
                                           | sold |
                                           +------+
```

**Status transitions:**
- `draft` -> `pending_review` (when moderation enabled) or `active` (when disabled)
- `pending_review` -> `active` (admin approves) or `draft` (admin rejects)
- `active` -> `reserved` (offer accepted or buy initiated)
- `active` -> `withdrawn` (seller withdraws)
- `active` -> `expired` (past expires_at)
- `active` -> `sold` (fixed price purchase completed)
- `reserved` -> `sold` (payment confirmed)
- `expired` -> `draft` (can be re-listed)

### Auction Flow

```
+----------+     start_time      +--------+     end_time      +-------+
| upcoming |-------------------->| active |-------------------->| ended |
+----------+                     +--------+                    +-------+
                                   |    ^                         |
                                   |    |                         v
                               bid in   | anti-snipe         has winner?
                              last N    | extend              /     \
                              minutes   | end_time          yes      no
                                   |    |                  /          \
                                   +----+            +----------+ +----------+
                                                     | winner_id| | no sale  |
                                                     | set      | | (reserve |
                                                     +----------+ | not met) |
                                                                   +----------+
```

**Auction types:**
- **English** (default): Open ascending bids. Highest bidder wins when time expires.
- **Sealed Bid**: Bids hidden from other bidders. Highest wins at end.
- **Dutch**: Descending price (future).

**Anti-sniping:**
- When a bid is placed within `auto_extend_minutes` (default: 5) of end_time, the auction extends by that duration
- Maximum extensions capped at `max_extensions` (default: 10)
- Extension count tracked per auction

**Proxy bidding:**
- Bidders can set a `max_bid` (maximum they are willing to pay)
- When outbid, the system automatically places incremental bids up to the max_bid
- Only one auto-bid processed per round to prevent runaway bidding
- Auto-bids tracked via `is_auto_bid` flag

**Reserve price:**
- If `reserve_price` is set and highest bid is below it, auction ends without a winner
- Reserve status exposed via API (`getAuctionStatus` returns `reserve_met` boolean)

**Buy Now:**
- If `buy_now_price` is set on the auction, buyers can bypass bidding
- Buy Now immediately ends the auction and sets the winner

### Offer Negotiation Flow

```
+--------+                      +---------+
| Buyer  |---create offer------>| pending |
+--------+                      +---------+
                                  |  |  |
                          +-------+  |  +-------+
                          |          |          |
                     accept      counter     reject
                          |          |          |
                          v          v          v
                   +----------+ +-----------+ +----------+
                   | accepted | | countered | | rejected |
                   +----------+ +-----------+ +----------+
                        |              |
                        |         buyer can:
                   (create         |      |
                   transaction)  accept  withdraw
                        |          |      |
                        v          v      v
                   +------+ +----------+ +-----------+
                   | sold | | accepted | | withdrawn |
                   +------+ +----------+ +-----------+

    All pending/countered offers auto-expire after offer_expiry_days (default: 7)
```

**Offer rules:**
- Offers are only allowed on `fixed_price` and `offer_only` listing types (not auctions)
- Minimum offer enforced if `minimum_offer` is set on the listing
- Only one pending offer per buyer per listing at a time
- Counter-offers reset the expiry timer

### Transaction & Payment Flow

```
+------------------+    payment     +------+    ship     +----------+
| pending_payment  |--------------->| paid |------------>| shipping |
+------------------+                +------+             +----------+
                                                              |
                                                         delivered
                                                              |
                                                              v
                                                        +-----------+
                                                        | delivered |
                                                        +-----------+
                                                              |
                                                      buyer confirms
                                                         receipt
                                                              |
                                                              v
                                                        +-----------+
                                                        | completed |
                                                        +-----------+
                                                              |
                                                       auto-create
                                                        payout
                                                              |
                                                              v
                                                   +-----------------+
                                                   | payout: pending |
                                                   +-----------------+
```

**Transaction creation sources:**
1. **Fixed price**: `TransactionService::createFromFixedPrice()` - direct purchase at listed price
2. **Offer**: `TransactionService::createFromOffer()` - uses accepted offer amount (or counter amount)
3. **Auction**: `TransactionService::createFromAuction()` - uses winning bid amount

### Commission & Payout Calculation

```
sale_price                      = listing price / winning bid / accepted offer
platform_commission_rate        = seller.commission_rate ?? settings.default_commission_rate (default: 10%)
platform_commission_amount      = sale_price * (commission_rate / 100)
seller_amount                   = sale_price - platform_commission_amount
vat_amount                      = sale_price - (sale_price / (1 + (vat_rate / 100)))  // VAT-inclusive pricing
total_with_vat                  = sale_price  // Prices already include VAT
shipping_cost                   = listing.shipping_domestic_price (if requires_shipping)
grand_total                     = total_with_vat + shipping_cost + insurance_cost
```

**Payout lifecycle:**
1. Buyer confirms receipt -> payout auto-created with status `pending`
2. Cooling period (default: 5 days) must elapse before processing
3. Admin processes payout -> status `processing`
4. Admin completes payout -> status `completed` with optional payment reference
5. Batch processing available via `PayoutService::batchProcess()`

### Multi-Currency

- Base currency: **ZAR** (South African Rand)
- All exchange rates stored as `exchange_rate_to_zar` in `marketplace_currency`
- Conversion: source -> ZAR -> target (two-step via ZAR base)
- Formatting respects currency symbol and position
- Default seeded currencies: ZAR (R), USD ($), EUR (EUR), GBP (GBP), AUD (A$)

---

## Configuration

### marketplace_settings Defaults (Seed Data)

| Key | Default Value | Type | Group | Description |
|-----|---------------|------|-------|-------------|
| `platform_name` | `Heratio Marketplace` | text | general | Platform display name |
| `default_commission_rate` | `10.00` | number | general | Default platform commission (%) |
| `listing_moderation_enabled` | `1` | boolean | general | Require admin approval for new listings |
| `listing_duration_days` | `90` | number | general | Default listing duration (days) |
| `offer_expiry_days` | `7` | number | offers | Days before an offer expires |
| `auction_auto_extend_minutes` | `5` | number | auctions | Anti-sniping extension minutes |
| `auction_max_extensions` | `10` | number | auctions | Maximum auction extensions |
| `payout_cooling_period_days` | `5` | number | payouts | Days after delivery before payout release |
| `min_listing_price` | `1.00` | number | general | Minimum listing price |
| `max_listing_images` | `20` | number | general | Maximum images per listing |
| `featured_listing_fee` | `0` | number | general | Fee for featuring a listing |
| `vat_rate` | `15.00` | number | general | VAT rate percentage |
| `default_currency` | `ZAR` | currency | general | Default platform currency |
| `supported_payment_gateways` | `["payfast"]` | json | general | Enabled payment gateways |
| `terms_url` | `/marketplace/terms` | text | general | Terms and conditions URL |
| `seller_registration_open` | `1` | boolean | general | Allow new seller registrations |
| `guest_enquiries_enabled` | `1` | boolean | general | Allow guest enquiries without login |

### Seeded Categories

| Sector | Categories |
|--------|-----------|
| Gallery (13) | Painting, Sculpture, Drawing, Print, Photography, Mixed Media, Textile Art, Ceramics, Glass, Installation, Digital Art, Video Art, Performance Documentation |
| Museum (6) | Reproduction, Merchandise, Catalog, Educational Material, Deaccessioned Object, Artifact Replica |
| Archive (6) | Digital Scan, Research Package, Publication, Facsimile, Image License, Dataset |
| Library (5) | Rare Book, Special Collection, E-Book, Manuscript Facsimile, Map Reproduction |
| DAM (6) | Stock Image, Video Clip, Audio Recording, 3D Model, Design Asset, Font License |

### Seeded Currencies

| Code | Name | Symbol | Exchange Rate (to ZAR) |
|------|------|--------|----------------------|
| ZAR | South African Rand | R | 1.000000 |
| USD | US Dollar | $ | 0.054000 |
| EUR | Euro | EUR | 0.050000 |
| GBP | British Pound | GBP | 0.043000 |
| AUD | Australian Dollar | A$ | 0.084000 |

---

## Plugin Configuration

```php
// ahgMarketplacePluginConfiguration.class.php
class ahgMarketplacePluginConfiguration extends sfPluginConfiguration
{
    public function initialize()
    {
        // Register routing event listener
        $this->dispatcher->connect('routing.load_configuration', [$this, 'routingLoadConfiguration']);

        // Bootstrap Laravel DB on context load
        $this->dispatcher->connect('context.load_factories', [$this, 'contextLoadFactories']);
    }
}
```

**Key behaviors:**
- Routes registered via `AtomFramework\Routing\RouteLoader` (not YAML)
- Laravel DB bootstrapped via `atom-framework/src/bootstrap.php` on `context.load_factories` event
- Module name: `marketplace` (single module for all routes)
- Load order: 120

---

## Integration Points

### ahgCartPlugin Integration

- **`buyAction`**: For fixed-price listings, can add to cart via ahgCartPlugin if available
- **Cart item data**: listing_id, title, price, currency, featured_image_path, seller display name
- Falls back to direct transaction creation if ahgCartPlugin is not enabled

### ahgGalleryPlugin Integration

- **`marketplace_seller.gallery_artist_id`**: FK links seller profile to `gallery_artist` table
- **`marketplace_listing.information_object_id`**: Links listing to AtoM information_object, which may have gallery CCO metadata
- **Valuation data**: `gallery_valuation` can inform listing pricing via `gallery_valuation_id` on listing

### ahgFavoritesPlugin Integration

- **`apiFavouriteAction`**: AJAX toggle to add/remove listing from user favorites via ahgFavoritesPlugin
- **`marketplace_listing.favourite_count`**: Tracks favourite count for display/sorting
- Gracefully degrades if ahgFavoritesPlugin is not enabled

### ahgLoanPlugin Integration

- **Loan listings**: Museum/Gallery items listed as "loan available" can link to ahgLoanPlugin for loan workflow
- **Gallery loan integration**: Outgoing loans from `gallery_loan` table can feed into marketplace for institutional lending

### ahgHeritagePlugin Integration

- **`marketplace_seller.heritage_contributor_id`**: FK links seller to heritage contributor profile
- **Heritage discovery**: Listings from heritage contributors appear in heritage discovery feeds

### ahgLandingPagePlugin Integration

- **Featured collections**: Marketplace featured collections and listings can be embedded as landing page blocks
- **Marketplace widget**: Sellers, featured items, and auction timers available as landing page components

### ahgDisplayPlugin Integration

- **Sector detection**: Uses `DisplayModeService` for GLAM sector auto-detection on linked information objects
- **Browse integration**: Marketplace listings can appear in ahgDisplayPlugin browse results when linked to information_object

### ahgExhibitionPlugin Integration

- **Exhibition marketplace**: Collections of type `exhibition` can link to exhibition records
- **Exhibition shop**: Items from exhibitions can be listed for sale in the marketplace

---

## CLI Commands

No CLI commands are currently implemented. The following are planned for future releases:

| Command | Description | Status |
|---------|-------------|--------|
| `marketplace:process-auctions` | Start upcoming auctions, end expired auctions, process lifecycle | Planned |
| `marketplace:expire-listings` | Expire active listings past their expiry date | Planned |
| `marketplace:expire-offers` | Expire pending/countered offers past their expiry date | Planned |
| `marketplace:update-exchange-rates` | Fetch and update currency exchange rates from external API | Planned |

Currently, auction lifecycle and expiry processing is handled by service methods that can be called from action classes or future cron-triggered CLI tasks:
- `AuctionService::processAuctionLifecycle()`
- `MarketplaceService::processExpiredListings()`
- `OfferService::processExpiredOffers()`

---

## Dependencies

### Required

| Dependency | Minimum Version | Purpose |
|-----------|----------------|---------|
| atom-framework | >= 2.0.0 | Laravel Query Builder, RouteLoader, bootstrap |
| AtoM | >= 2.8 | Base platform |
| PHP | >= 8.1 | Type declarations, named arguments |
| ahgCorePlugin | (any) | Core framework integration |

### Optional

| Plugin | Integration |
|--------|-------------|
| ahgCartPlugin | Shopping cart checkout flow |
| ahgGalleryPlugin | Artist profiles, valuations, CCO metadata |
| ahgExhibitionPlugin | Exhibition-linked collections |
| ahgFavoritesPlugin | User favourites/bookmarks |
| ahgLoanPlugin | Institutional lending |
| ahgHeritagePlugin | Heritage contributor profiles |
| ahgLandingPagePlugin | Marketplace widgets on landing pages |
| ahgDisplayPlugin | GLAM sector detection and browse integration |

---

## Security

### Authentication Levels

| Route Group | Auth Required | Additional Check |
|-------------|--------------|-----------------|
| Public (9) | No | None |
| Buyer (10) | Yes | `$this->getUser()->isAuthenticated()` |
| Seller (20) | Yes | Authenticated + has seller profile |
| Admin (13) | Yes | Authenticated + admin role |
| API (6) | Varies | AJAX with CSRF token where applicable |

### Data Validation

- Seller cannot bid on own listing (checked in `AuctionService::placeBid()`)
- One pending offer per buyer per listing (checked in `OfferService::createOffer()`)
- One review per user per transaction (checked in `ReviewService::createReview()`)
- Rating constrained to 1-5 range
- Minimum offer enforcement
- Listing status transition guards prevent invalid state changes
- Payout cooling period enforced before processing

### Seller Profile Update Allowlist

The `SellerService::updateProfile()` method uses a field allowlist to prevent mass-assignment:

```php
$allowedFields = [
    'display_name', 'bio', 'country', 'city', 'website', 'instagram',
    'email', 'phone', 'payout_method', 'payout_currency', 'avatar_path',
    'banner_path', 'seller_type',
];
```

Fields like `commission_rate`, `verification_status`, `trust_level`, and `is_active` are admin-only.

---

## File Structure

```
ahgMarketplacePlugin/
  config/
    ahgMarketplacePluginConfiguration.class.php   # Plugin init + route registration
  database/
    install.sql                                      # 16 tables + seed data
  lib/
    Repositories/
      SellerRepository.php
      ListingRepository.php
      AuctionRepository.php
      OfferRepository.php
      TransactionRepository.php
      ReviewRepository.php
      CollectionRepository.php
      SettingsRepository.php
    Services/
      MarketplaceService.php
      AuctionService.php
      OfferService.php
      TransactionService.php
      SellerService.php
      PayoutService.php
      ShippingService.php
      ReviewService.php
      CollectionService.php
      CurrencyService.php
      MarketplaceSearchService.php
      MarketplaceNotificationService.php
  modules/
    marketplace/
      actions/
        browseAction.class.php          # ... (57 action classes)
      config/
        module.yml
      templates/
        browseSuccess.php               # ... (46 success templates)
        _listingCard.php                # ... (10 partials)
  extension.json                        # Plugin metadata + dependency declaration
```

---

## Related Documentation

- [ahgGalleryPlugin Technical Documentation](./ahgGalleryPlugin.md)
- [ahgCartPlugin Technical Documentation](./ahgCartPlugin.md)
- [ahgFavoritesPlugin Technical Documentation](./ahgFavoritesPlugin.md)
- [ahgHeritagePlugin Technical Documentation](./ahgHeritagePlugin.md)
- [ahgLandingPagePlugin Technical Documentation](./ahgLandingPagePlugin.md)
- [ahgExhibitionPlugin Technical Documentation](./ahgExhibitionPlugin.md)

---

*Part of the AtoM AHG Framework (Heratio) - Version 1.0.0*
