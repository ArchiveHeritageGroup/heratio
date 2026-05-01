# Parity Audit Gap Report - 48-hour Window

Generated: 2026-04-12
Scope: All files touched in the last 48 hours (278 files)
Last updated: 2026-04-12 (X.1.R audit added)

## TL;DR

The view layer is at PSIS parity. **The data layer is not.**

- **69 missing service methods** across 9 packages (55 in ahg-marketplace alone)
- **10 unregistered routes** referenced in views without `Route::has()` guard (will throw `RouteNotFoundException` when rendered)
- **11 form POST actions** pointing at unregistered routes (will 404 on submit)
- **0 AJAX endpoint gaps** - all 15 endpoints referenced from ported JS blocks resolve

## ⚠️ X.1.R Second-layer gap (added 2026-04-12)

Phases X.1.1–X.1.5 added 41 service methods to MarketplaceService. Live-invoke tests pass and the controller no longer throws "method not found". **But the business logic is minimum-viable, not a clone of PSIS.** When the user asked "were they all 100% complete?" the honest answer was no. Specific gaps found by diffing against `archive/plugins/ahgMarketplacePlugin/lib/Services/`:

### OfferService gaps
- `createOffer` is missing 6 PSIS guards/side-effects:
  1. `listing.status === 'active'` check
  2. `listing.listing_type !== 'auction'` check
  3. `listing.minimum_offer` floor check
  4. `hasPendingOffer` dedup (prevents duplicate pending offers from same buyer)
  5. `settings.offer_expiry_days` lookup (Heratio hardcodes 7)
  6. `listing.enquiry_count` increment
- `acceptCounterOffer` is missing: `listing.status='reserved'` side-effect. Return key is `amount`; PSIS returns `price`.

### ReviewService gaps
- `createReview` is missing `$txn->status === 'completed'` gate. Has an **incorrect** `buyer_id` check that PSIS does NOT have. Return key is `review_id`; PSIS returns `id`.

### Un-audited (X.1.1–X.1.4)
- All 30 methods added in X.1.1 (admin browse), X.1.2 (admin dashboard), X.1.3 (listing/auction), X.1.4 (seller helpers) were written to satisfy the controller call signature without side-by-side PSIS diffs. They run live but have not been proven to match PSIS SellerService / PayoutService / AuctionService / CollectionService / TransactionService / ListingRepository logic.

### Favourites feature
- `isFavourited` is a silent `return false` stub. PSIS has a real favourites feature (table + service methods) that was never ported to Heratio. Needs full feature port, not just a method.

**See X.1.R sub-phases in `heratio-vs-psis-outstanding-plan.md` for the close-the-gap plan.**

Everything below is a punch-list to bring the last 48 hours of work to genuine 100% functional parity with PSIS. Nothing below is about *new* code or *new* features - it's closing gaps in existing/touched files only.

---

## 1. Missing service methods (69 across 9 packages)

### 1.1 ahg-marketplace (55 methods)

`MarketplaceController.php` calls 55 methods that do not exist in `MarketplaceService.php`. Every admin list page, seller profile, offer/review/collection flow will HTTP 500 as soon as the admin middleware passes.

**Required backfill - add these methods to `MarketplaceService`:**

**Admin browse (list pages that currently 500 for admins):**
- `adminBrowseListings(array $filters, int $limit, int $offset): array`
- `adminBrowsePayouts(array $filters, int $limit, int $offset): array`
- `adminBrowseReviews(array $filters, int $limit, int $offset): array`
- `adminBrowseSellers(array $filters, int $limit, int $offset): array`
- `adminBrowseTransactions(array $filters, int $limit, int $offset): array`

**Admin dashboard stats:**
- `getAdminDashboardStats(): array`
- `getAdminRecentTransactions(int $limit): Collection`
- `getSectorBreakdown(): array`
- `getTopItemsBySales(int $limit): Collection`
- `getTopListingsByViews(int $limit): Collection`
- `getTopSellersByRevenue(int $limit): Collection`
- `getTopSellingListings(int $limit): Collection`

**Listings:**
- `getListingById(int $id): ?object`
- `getAuctionForListing(int $listingId): ?object`
- `getAuctionForListingBySlug(string $slug): ?object`
- `getPrimaryImage(int $listingId): ?object`
- `getRelatedListings(int $listingId, int $limit): Collection`
- `getBidHistory(int $listingId, int $limit): Collection`
- `updateListingStatus(int $listingId, string $status): bool`
- `uploadListingImage(int $listingId, $file, ?string $caption): int`

**Sellers:**
- `getSellerById(int $id): ?object`
- `getSellerPayouts(int $sellerId): Collection`
- `getSellerPendingPayoutAmount(int $sellerId, string $currency): float`
- `getSellerRecentTransactions(int $sellerId, int $limit): Collection`
- `getSellerReviews(int $sellerId, int $limit, int $offset): Collection`
- `getSellerCollections(int $sellerId): Collection`
- `getSellerPublicCollections(int $sellerId): Collection`
- `getFollowedSellers(int $userId, int $limit, int $offset): array`
- `getRatingStats(int $sellerId): array`
- `uploadAvatar(int $sellerId, $file): bool`
- `uploadBanner(int $sellerId, $file): bool`
- `autoProvisionAdminSeller(int $userId): int`

**Offers / enquiries / reviews:**
- `createOffer(array $data): int`
- `acceptCounterOffer(int $offerId): bool`
- `createEnquiry(array $data): int`
- `replyToEnquiry(int $enquiryId, string $reply): bool`
- `createReview(array $data): int`
- `hasReviewed(int $userId, int $transactionId): bool`
- `getReviewedMap(int $userId): array`
- `getBuyerOffers(int $userId, int $limit, int $offset): array`
- `getBuyerTransactions(int $userId, int $limit, int $offset): array`
- `getPendingOfferCount(int $sellerId): int`
- `isFavourited(int $userId, int $listingId): bool`

**Categories / currencies / settings:**
- `createCategory(array $data): int`
- `updateCategory(int $id, array $data): bool`
- `deleteCategory(int $id): bool`
- `addCurrency(array $data): int`
- `updateCurrency(string $code, array $data): bool`
- `getAllSettings(): array`
- `setSetting(string $key, $value): bool`

**Collections:**
- `uploadCollectionCover(int $collectionId, $file): bool`

**Payouts:**
- `batchProcessPayouts(array $payoutIds): int`

**Transactions:**
- `updateShipping(int $transactionId, string $tracking, string $courier): bool`

**Prefill data for create-listing form:**
- `getIOPrefillData(int $objectId): ?object`
- `getUserPrefillData(int $userId): ?object`

### 1.2 ahg-ai-services (4 methods)

`AiController.php` calls:
- `buildPrompt($context, $objectId): string`
- `gatherContext(int $objectId): array`
- `generateSuggestion(string $prompt, array $context): string`
- `getTemplateForObject(int $objectId): ?string`

### 1.3 Other packages (10 more methods)

- **ahg-semantic-search** (3): terms(), template edit helpers
- **ahg-custom-fields** (3)
- **ahg-nmmz** (3): NmmzController methods
- **ahg-access-request** (1)
- **ahg-statistics** (1)
- **ahg-ingest** (1)
- **ahg-multi-tenant** (1)
- **ahg-ipsas** (1)

*Exact method names are in `/tmp/all_service_gaps.json` (generated during this audit).*

## 2. Unregistered routes (10 unguarded)

Routes referenced in ported views that don't exist in `routes/web.php`. Unguarded = will throw RouteNotFoundException on render.

| Route name | Referenced by | Action needed |
|---|---|---|
| `ahgmarketplace.admin-payouts-batch` | `admin-payouts.blade.php` form POST | Add POST `/admin/marketplace/payouts-batch` → `adminPayoutsBatchPost()` |
| `ahgprivacy.breach-update` | `breach-edit.blade.php` form POST | Add POST route + controller method |
| `ahgprivacy.consent-withdraw` | `consent-view.blade.php` action | Add POST route + method |
| `ahgprivacy.dsar-update` | `dsar-edit.blade.php` form POST | Add POST route + method |
| `ahgprivacy.ropa-approve` | `ropa-view.blade.php` action | Add POST route + method |
| `ahgprivacy.ropa-reject` | `ropa-view.blade.php` action | Add POST route + method |
| `ahgprivacy.ropa-submit` | `ropa-view.blade.php` action | Add POST route + method |
| `ingest.` | (empty route name - typo) | Fix the broken `route('ingest.')` reference to a real route |
| `tiffpdfmerge.index` | dashboard sidebar | Register route or update sidebar to use URL |
| `ric.dashboard` | dashboard sidebar | Register route or fix dashboard reference |

## 3. Guarded-but-missing routes (5)

These are referenced in views but the code wraps them in `Route::has()`, so they won't crash - the buttons just silently hide until the route exists. Still need to be added for full functional parity.

| Route | View |
|---|---|
| `ahgmarketplace.buy` | listing.blade.php (Buy Now button) |
| `ahgmarketplace.follow` | seller.blade.php, my-following.blade.php |
| `ahgmarketplace.seller-listing-publish` | seller-listings.blade.php |
| `ahgmarketplace.seller-listing-withdraw` | seller-listings.blade.php |
| `iiif.collections` | (dashboard sidebar) |

## 4. Form action gaps (11)

11 `<form action="...">` tags point at unregistered routes. Subset of section 2 - listed here for completeness because these fail on submit, not on render.

Same list as sections 2+3 filtered by forms.

## 5. AJAX endpoints - CLEAN ✓

All 15 AJAX endpoints referenced in JS blocks resolve via `php artisan route:list`:

- `/admin/ai/ner/extract/{id}`, `/admin/ai/suggest`, `/ai/describe/{id}`
- `/admin/backup/download/{id}`
- `/admin/translation/apply`, `/admin/translation/translate/{id}`
- `/informationobject/autocomplete`, `/research/researcher-autocomplete`, `/research/target-autocomplete`
- `/marketplace/api/{id}/favourite`
- `/research/bulk-validate`, `/research/entity-resolution/{id}/resolve`, `/research/search-diff/{id}`, `/research/search-snapshot`, `/research/validate/{id}`

## 6. DB table / schema verification - NOT AUDITED

Not yet audited. The ported views SELECT from tables like:
- `research_citation_log`, `research_activity_log`, `research_booking`
- `rights_record`, `rights_grant`, `rights_embargo`
- `spectrum_condition_check`, `spectrum_condition_photo`
- `heritage_asset`, `heritage_asset_class`, `heritage_accounting_standard`
- `marketplace_listing`, `marketplace_seller`, `marketplace_transaction`, etc.

**To close:** run `mysql -u root heratio -e "SHOW TABLES LIKE 'research_%'"` (etc.) and compare against required tables. Missing tables need `CREATE TABLE` statements from the `database/install.sql` file in each package's `database/` directory.

## 7. Validation rules - NOT AUDITED

No Laravel FormRequest classes were created for any of the ported forms. PSIS has server-side validation in each `executePost($request)` method. **To close:** per ported form, extract PSIS validation rules, create a `FormRequest` class, wire it into the controller POST handler.

## 8. Email / notification side effects - NOT AUDITED

PSIS triggers emails on: DSAR submit/reply, breach notification, offer accept/reject, review posted, payout processed. **To close:** per action, port the mail template + queue job.

---

## Backfill plan - Phase X: 100% previous-work parity

Added to `docs/heratio-vs-psis-outstanding-plan.md` as a new top-priority phase that must complete before resuming Phase C stubs.

### X.1 - MarketplaceService method backfill (55 methods, ~4 batches)
- X.1.1 Admin browse helpers (5 methods): `adminBrowseListings`, `adminBrowsePayouts`, `adminBrowseReviews`, `adminBrowseSellers`, `adminBrowseTransactions`
- X.1.2 Admin dashboard aggregators (7): stats / recent / top-sellers / top-items / sector-breakdown
- X.1.3 Listing + auction helpers (9): `getListingById`, `getAuctionForListing`, `getBidHistory`, `getRelatedListings`, image upload
- X.1.4 Seller helpers (11): by-id, reviews, payouts, follow graph, avatar/banner upload, rating stats
- X.1.5 Buyer actions (11): offers, enquiries, reviews, favourite, hasReviewed, getBuyerTransactions
- X.1.6 Categories/currencies/settings (7)
- X.1.7 Payouts + shipping (2)
- X.1.8 Prefill data (2)

Each method ported from PSIS `MarketplaceService` in `/usr/share/nginx/archive/plugins/ahgMarketplacePlugin/lib/`.

### X.2 - Privacy POST handlers + controller methods (6 routes)
- `breach-update`, `consent-withdraw`, `dsar-update`, `ropa-approve`, `ropa-reject`, `ropa-submit`
- Each needs: POST route registration + `PrivacyController::xxxPost()` method + PSIS-equivalent business logic

### X.3 - Marketplace POST handlers + routes (5 routes)
- `admin-payouts-batch`, `buy`, `follow`, `seller-listing-publish`, `seller-listing-withdraw`
- Each needs: route registration + controller method

### X.4 - AI service method backfill (4 methods)
- `buildPrompt`, `gatherContext`, `generateSuggestion`, `getTemplateForObject` in `AhgAiServices\Services\*` (pick correct class)

### X.5 - Other small-package service gaps (10 methods total)
- ahg-semantic-search (3), ahg-custom-fields (3), ahg-nmmz (3), ahg-access-request (1), ahg-statistics (1), ahg-ingest (1), ahg-multi-tenant (1), ahg-ipsas (1)
- One batch per package

### X.6 - Fix the typo / broken refs
- `ingest.` empty route name - find the blade that uses it and point at a real route
- `ric.dashboard`, `tiffpdfmerge.index`, `iiif.collections` - register routes OR repoint the dashboard sidebar

### X.7 - DB table verification pass
- Run `SHOW TABLES` against heratio DB, compare against required-tables list compiled from ported views
- For each missing table: run the package's `database/install.sql` or add the table

### X.8 - Validation rule backfill
- Per ported form (62 forms across marketplace + privacy): extract PSIS server-side validation, create FormRequest class, wire into controller POST handler

### X.9 - Email/notification backfill
- Per user-action (DSAR submit, breach notify, offer respond, review post, payout process): port mail template + queue job

### X.10 - Final functional smoke test
- Log in as admin
- Click every button on every ported page
- File a bug per non-working interaction
- Close all bugs before marking Phase X complete

## Size estimate

| Sub-phase | Items | Batches @ 5 |
|-----------|------:|------------:|
| X.1 Marketplace service methods | 55 | 11 |
| X.2 Privacy POST handlers | 6 | 2 |
| X.3 Marketplace POST handlers | 5 | 1 |
| X.4 AI service methods | 4 | 1 |
| X.5 Other service gaps | 10 | 2 |
| X.6 Typo/broken ref fixes | 4 | 1 |
| X.7 DB table verification | ~40 tables | 1 (audit only) |
| X.8 Validation rule backfill | ~62 forms | 13 |
| X.9 Email backfill | ~15 actions | 3 |
| X.10 Final functional smoke | - | manual |
| **Total** | **~200 items** | **~35 batches** |

At 5 items per batch, **~35 batches of 5** to close all gaps in existing 48-hour work before touching any new Phase C stubs.

## Ordering

Must be done **before** resuming Phase C (ahg-registry + smaller packages). Rationale: every new stub ported risks opening more gaps, and the gap pattern is already identified - closing them first prevents compounding debt.

**Execution order:**
1. X.1 (biggest impact - makes marketplace admin actually work)
2. X.2 + X.3 (closes all form-submit gaps)
3. X.4 + X.5 (unblocks AI + small packages)
4. X.6 (typo/broken ref fixes - quick)
5. X.7 (DB audit - blocking)
6. X.8 (validation - needed for prod safety)
7. X.9 (email - needed for user-facing correctness)
8. X.10 (manual smoke)
9. **Then** resume Phase C from C-3 ahg-registry
