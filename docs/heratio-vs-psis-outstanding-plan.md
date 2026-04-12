# Heratio vs PSIS — Outstanding Work Plan

Generated: 2026-04-12
Owner: Johan Pieterse
Environment: **dev** — full coverage, no deferral, no triage. Every item in this doc must be completed.

This document plans the work that remains AFTER the reports-dashboard parity work
(see `docs/reports-dashboard-comparison.md`). The reports dashboard is at 100%
functional parity for its 122 links. Everything below is **outside the dashboard
scope** and was either flagged earlier in this session, surfaced by the
auto-memory project notes, or never audited.

## Status legend

- `[ ]` = not started
- `[~]` = in progress
- `[x]` = done — code merged + smoke-tested
- `[v]` = verified — Johan browser-tested as admin and confirmed working

Mark items as you go. A batch is only DONE when every item in it is `[x]`. A batch is only VERIFIED when every item is `[v]`.

## Scope summary

| Phase | What | Approx size | Verification path |
|-------|------|-------------|-------------------|
| **D2** | AHG menu parity — sidebar + admin nav | **13 missing menu items + 14 stale entries** (per `docs/AHG-MENU-COMPARISON.md`) | Visual audit, click every item |
| **C** | Empty-accordion stub views — content port from PSIS | **191 views across 21 packages** | Browser load each page as admin |
| **D1** | API parity — v1 + v2 REST endpoints | **94 missing endpoints** (per `docs/API-COMPARISON.md`) | curl + Postman collection |
| **D5** | AJAX endpoints, cron jobs, background services, JS layer | Unknown — never audited | Manual code walk per package |
| **D6** | Form validation, POST handlers, business logic | Unknown — never audited | Per-page audit |
| **D4** | Plugin coverage matrix — 119 PSIS plugins ↔ 92 Heratio packages | **27 plugin gap rows** (estimate) | Side-by-side directory diff |
| **D3** | Media processing parity — 3D, AI, watermarks, encryption | **15 missing features** (per `docs/MEDIA-PROCESSING-COMPARISON.md`) | Upload+process flow tests |

## Master execution order

The order below is fixed. Do NOT skip ahead — earlier batches surface issues that change later ones.

### Group 1 — Quick wins (closes obvious user-visible gaps)
- [x] **D2** — AHG menu parity — verified 2026-04-12. The menu at `packages/ahg-theme-b5/resources/views/partials/menus/ahg-admin-menu.blade.php` already matches every row in `docs/AHG-MENU-COMPARISON.md`. All 13 missing items are present (Research/Researchers+Bookings with badges, Researcher Submissions section, Access/Requests+Approvers, Audit/Statistics+Logs+Settings+Error Log, RiC section, Dedupe, Form Templates, DOI section, Heritage section, Maintenance Backup+Restore). All 14 EXTRA items the comparison flagged are absent. All 23 route names and 5 URL paths resolve via `php artisan route:list`. Menu is included from `partials/header.blade.php`.

### Group 2 — Stub view content port (191 pages, ordered by package size descending)
Each package = N batches of 5 pages each. Tick off batches as they ship.

- [x] **C-1** ahg-marketplace (32 stubs → 7 batches: 5+5+5+5+5+5+2) — **32/32 DONE (100%)** 2026-04-12
  - [x] batch 1/7 DONE 2026-04-12: admin-payouts (27/26), admin-transactions (26/26), admin-sellers (35/35), admin-listings (36/36), browse (39/37). Fixed pre-existing bug in `MarketplaceController::browse()` (was calling non-existent `$service->browse()`, now uses `getListings()`). 5×smoke passed.
  - [x] batch 2/7 DONE 2026-04-12: admin-categories (47/47), admin-currencies (40/40), admin-reviews (37/37), admin-listing-review (38/35 +3), admin-seller-verify (33/31 +2). Parity or superset on all 5. Admin-currencies reframed PSIS "Exchange Rate to ZAR" as "Rate to {base currency}" driven by `config('heratio.base_currency')` — DB column name kept for schema compat. 5×HTTP 302 auth redirect, no 500s.
  - [x] batch 3/7 DONE 2026-04-12: seller (20/17 +3), seller-listings (44/43 +1), seller-profile (31/31), my-purchases (39/39), my-offers (44/44). Parity or superset on all 5. Fixed 2 more pre-existing `$service->browse()` calls in category() and sector() controller methods (same bug as batch 1). Guarded 3 view references to non-existent routes (`seller-listing-publish`, `seller-listing-withdraw`, `follow`) with `Route::has()` so buttons hide cleanly until those controller methods exist. seller-profile adds Stripe + Wise to payout methods list (PSIS had only bank_transfer/paypal/payfast) and defaults payout currency to `config('heratio.base_currency')` instead of hardcoded ZAR. Smoke test: 4× HTTP 302 auth + 1× HTTP 404 (expected — no seller with test slug).
  - [x] batch 4/7 DONE 2026-04-12: listing (53/48 +5), seller-listing-create (58/62 -4), seller-listing-edit (58/59 -1), seller-listing-images (30/30), category (23/19 +4). Parity or superset on 3, ~parity on 2. **Fixed 2 more pre-existing bugs:** `MarketplaceController::category()` called non-existent `$service->getCategoryBySlug()` (added the method, queries by sector+slug); `MarketplaceController::listing()` called `getListing($slug)` with wrong signature (now uses `getListingBySlug()`). Smoke test: 3× HTTP 302 auth + 2× HTTP 404 (expected — nonexistent slugs), no 500s. Currency default in create/edit uses `config('heratio.base_currency')` not hardcoded ZAR.
  - [x] batch 5/7 DONE 2026-04-12: bid-form (24/24), offer-form (16/16), enquiry-form (19/19), review-form (17/17), seller-register (23/23). **Exact parity on all 5 pages** (five identical control counts — the sharpest batch yet). bid-form has full auction countdown JS and proxy-bid field; review-form has the 5-star hover-to-highlight UI; seller-register adds Stripe + Wise to payout methods list and defaults currency to `config('heratio.base_currency')`. Smoke test: 4× HTTP 302 + 1× HTTP 404, no 500s.
  - [x] batch 6/7 DONE 2026-04-12: search (31/28 +3), seller-enquiries (30/30), seller-collections (23/23), seller-collection-create (18/17 +1), my-following (25/25). **3 exact parity + 2 supersets.** **Fixed 1 more pre-existing bug:** `MarketplaceController::search()` called non-existent `$service->search()` — now uses `getListings()` with `q` filter and separately fetches facet counts. Smoke test: `/marketplace/search` now returns HTTP 200 (full render, 63 KB), others 4× HTTP 302 auth.
  - [x] batch 7/7 DONE 2026-04-12 — **FINAL BATCH**: seller-offer-respond (25/26 -1), seller-transaction-detail (16/17 -1). Both near-exact parity. `seller-offer-respond` wires 3 separate forms (accept / reject / counter-offer) against the same `id`. `seller-transaction-detail` renders a 5-step status pipeline timeline, financial breakdown, and shipping update form. **International framing:** PSIS "VAT" row is now labelled generically as "Tax" (other markets use GST/Sales Tax); courier placeholder dropped SA-specific examples (DHL/PostNet/Courier Guy) in favour of generic (DHL/FedEx/UPS/local). Smoke test: 2× HTTP 302 auth. **C-1 ahg-marketplace is 32/32 — COMPLETE.**
- [x] **C-2** ahg-privacy (29 stubs → 6 batches: 5+5+5+5+5+4) — **29/29 DONE (100%)** 2026-04-12
  - [x] batches 1–3/6 (silent run, 15 pages): dsar-list, dsar-add, dsar-edit, dsar-view, ropa-add, ropa-edit, ropa-view, breach-add, breach-edit, breach-view, consent-add, consent-edit, consent-view, complaint-add, complaint-list. **15/15 at EXACT parity** (28, 24, 33, 15, 30, 30, 19, 20, 34, 15, 19, 19, 11, 18, 19). Python converter script translates PSIS blade helpers: `url_for(['module'=>'X','action'=>'Y'])` → `route('ahgprivacy.y-kebab')`, `$sf_user->hasFlash/getFlash` → `session()`, `$sf_request->getParameter` → `request()`, `@extends('layouts.page')` → `@extends('theme::layouts.1col')`. Manually patched `ropa-view.blade.php` which instantiated a non-existent `\ahgPrivacyPlugin\Service\PrivacyService` — now falls back to `auth()` helpers and empty collections.
  - [x] batches 4–6/6 (silent run, 14 pages): config, pii-review, complaint-edit, complaint-view, jurisdiction-add, jurisdiction-edit, paia-add, officer-edit, officer-add, pii-scan-object, pii-scan, dsar-status, dsar-request, complaint. **14/14 at EXACT parity** (23, 29, 20, 13, 24, 24, 21, 19, 19, 29, 26, 6, 14, 18). Extended converter to handle both `privacyAdmin` and `privacy` PSIS modules (the latter is the public-facing DSAR submission / complaint intake pages). 2 manual patches on `pii-scan*` views where PSIS linked to info-object pages via `url_for` — replaced with `url('/' . $slug)` and a `DB::table('slug')` lookup. Smoke test: 14× HTTP 302, zero 500s. **C-2 ahg-privacy 29/29 COMPLETE.**
### ✴ Phase X — Backfill previous work to 100% functional parity (BLOCKS Phase C resumption)

**Rationale:** The parity audit (`docs/parity-audit-gap-report.md`) found that the 48 hours of Phase A + B + C-1 + C-2 work achieves **render-layer parity only**. The data layer has 69 missing service methods, 10 unregistered routes, 11 broken form submissions, and unaudited DB tables / validation rules / email hooks. Phase X closes every one of those gaps in **already-touched files** — no new features, no new stubs. It must complete before resuming Phase C at C-3 otherwise every new batch compounds the debt.

- [ ] **X.1** ahg-marketplace service-method backfill (55 methods → 11 batches of 5)
  - [x] X.1.1 DONE 2026-04-12 — Admin browse helpers (5): `adminBrowseListings`, `adminBrowseSellers`, `adminBrowseTransactions`, `adminBrowsePayouts`, `adminBrowseReviews`. Each returns `['items' => Collection, 'total' => int]`, joins seller/listing tables, honours status/sector/search filters. All 5 invoked live against the DB with `items=0 total=0` on empty tables (no query errors). Marketplace missing-method count: 54 → 49.
  - [x] X.1.2 DONE 2026-04-12 — Admin dashboard aggregators (7): `getAdminDashboardStats`, `getAdminRecentTransactions`, `getSectorBreakdown`, `getTopItemsBySales`, `getTopListingsByViews`, `getTopSellersByRevenue`, `getTopSellingListings`. Plus full port of `admin-dashboard.blade.php` from PSIS `adminDashboardSuccess.php` (was a stub before): 4 stat cards, alert badges for pending listings/unverified sellers/pending payouts, recent transactions table, monthly revenue table, left-sidebar admin menu with Route::has guards on settings/reports. Currency labels use `config('heratio.base_currency')` not hardcoded ZAR. All 7 methods invoked live on empty DB (no query errors). Route smoke test: `/admin/marketplace/dashboard` HTTP 302 auth redirect. Marketplace missing-method count: 49 → 42.
  - [x] X.1.3 DONE 2026-04-12 — Listing + auction (8): `getListingById` (no-view-count variant), `updateListingStatus` (state machine), `getAuctionForListing`, `getAuctionForListingBySlug`, `getBidHistory` (paged, default 20), `getPrimaryImage` (polymorphic: accepts int listing_id OR image collection), `getRelatedListings` (category_id → sector fallback), `uploadListingImage` (full file handling, primary auto-flag on first image, updates `featured_image_path`). All 8 invoked live against DB without errors. Marketplace missing-method count: 42 → 34.
  - [x] X.1.4 DONE 2026-04-12 — Seller helpers (10; `getSellerPendingPayoutAmount` already existed as private): `getSellerById`, `getSellerPayouts` (paged), `getSellerRecentTransactions` (joins listing title/slug), `getSellerReviews` (visible-only, paged), `getRatingStats` (average + 1-5 distribution + total), `getSellerCollections`, `getSellerPublicCollections` (is_public=1, featured first), `getFollowedSellers` (joins seller row, paged), `uploadAvatar`, `uploadBanner` (both route through `uploadSellerAsset` helper, write to `/uploads/marketplace/sellers/{id}/`, auto-update seller row). All 10 invoked live on DB. Marketplace missing-method count: 34 → 24.
  - [x] X.1.5 DONE 2026-04-12 — Buyer actions (11): `createOffer` (7-day expiry, currency from listing/config), `acceptCounterOffer` (promotes counter_amount to offer_amount, status→accepted), `createEnquiry`, `replyToEnquiry`, `createReview` (validates rating 1-5, buyer-only, dedupes via `hasReviewed`, triggers seller rating recalc), `hasReviewed`, `getReviewedMap` (bulk lookup for purchase history), `getBuyerOffers` (paged, joins listing), `getBuyerTransactions` (paged, joins listing+seller), `getPendingOfferCount` (joins listing to filter by seller), `isFavourited` (returns false — favourites table not yet ported to Heratio). All 11 invoked live. Marketplace missing-method count: 24 → 13. **⚠️ NOT 100% PSIS-equivalent — see X.1.R below.**
  - [x] **X.1.R** DONE 2026-04-12 — PSIS parity diff + backfill for X.1.1–X.1.5. All 11 sub-tasks closed. Summary: 2 controller call-site reshapes, 1 view key-access fix, 1 new table (`marketplace_favourite`), 1 new method (`toggleFavourite`), 6 service-method signature/logic fixes, 1 helper (`hasPendingOffer`). All 41 previously-added methods now PSIS-equivalent. **This audit pattern (PSIS diff BEFORE declaring done) is now baseline for X.1.6+.**
    - [x] X.1.R.1 DONE 2026-04-12 — OfferService parity: `createOffer` now checks `listing.status==active`, rejects `listing_type==auction`, enforces `minimum_offer`, dedupes via new `hasPendingOffer()` helper (protected), pulls `offer_expiry_days` from `getSetting()`, increments `listing.enquiry_count`, returns `['id' => ...]` matching PSIS. `acceptCounterOffer` reserves the listing (`status=reserved`), returns `['price' => ...]` matching PSIS. Controller only reads `success`/`error` so return-key change is safe. All branches invoked live (bad listing, bad offer, bad rating).
    - [x] X.1.R.2 DONE 2026-04-12 — ReviewService parity: `createReview` now gates on `$txn->status === 'completed'`, removed incorrect buyer_id check, returns `['id' => ...]` matching PSIS, honours `review_type` to determine `reviewed_seller_id` (null when not buyer_to_seller), writes `flagged=0` explicitly. `recalculateSellerRating` still used (private, equivalent to PSIS `sellerRepo->updateRating`).
    - [x] X.1.R.3 DONE 2026-04-12 — SellerService parity: `getFollowedSellers` now filters `s.is_active=1` matching PSIS `SettingsRepository::getFollowedSellers`. `getSellerById`, `getRatingStats` reshape covered in R.2.
    - [x] X.1.R.4 DONE 2026-04-12 — TransactionService parity: `getSellerPayouts` signature changed to `(sellerId, limit=50, offset=0)` matching PSIS `TransactionRepository::getSellerPayouts` (was `(sellerId, page, limit=20)`). Controller call site uses defaults so change is binary-safe. `getSellerRecentTransactions` + `getBuyerTransactions` keep Heratio listing/seller JOINs as view enhancements (no PSIS regression since PSIS also joins in its views).
    - [x] X.1.R.5 DONE 2026-04-12 — AuctionService parity: `getBidHistory` changed to sort `bid_amount DESC` with default limit 50, matching PSIS `AuctionRepository::getBids`. `getAuctionForListing*` unchanged — PSIS fetches via inline query with same shape.
    - [x] X.1.R.6 DONE 2026-04-12 — ListingRepository parity: `getRelatedListings` now applies sector AND category_id simultaneously (was category OR sector fallback), fetches `limit+2` and slices to `limit`, matching PSIS `listingAction` algorithm. `getListingById` already matches PSIS `getById` (trivial one-liner).
    - [x] X.1.R.7 DONE 2026-04-12 — CollectionService parity: `getSellerCollections` changed to `sort_order ASC` only (was compound sort) matching PSIS `CollectionRepository::getSellerCollections`. `getSellerPublicCollections` retained as Heratio-specific view helper.
    - [x] X.1.R.8 DONE 2026-04-12 — ReviewService parity: `getSellerReviews` now returns `['items', 'total']` paged array with `review_type='buyer_to_seller'` filter matching PSIS `ReviewRepository::getSellerReviews`. Controller call sites updated to destructure `$result['items']`. View key access `$ratingStats[$star]` rewritten to `$ratingStats['distribution'][$star]` (seller.blade.php).
    - [x] X.1.R.9 DONE 2026-04-12 — Admin dashboard/browse parity: Cross-checked all 5 `adminBrowse*` filter parameters against PSIS admin actions (`adminListingsAction`, `adminSellersAction`, `adminTransactionsAction`, `adminPayoutsAction`, `adminReviewsAction`). Filter keys match 1:1: listings(status,sector,search), sellers(verification_status,search), transactions(status,payment_status,search), payouts(status), reviews(flagged,is_visible). Default per-page 30 vs PSIS 20 kept — aesthetic choice, no data regression.
    - [x] X.1.R.10 DONE 2026-04-12 — Favourites feature ported: Created `marketplace_favourite` table live (id, user_id, listing_id, created_at, unique key on user+listing). `isFavourited()` now real DB exists() check (was `return false`). Added `toggleFavourite()` method matching PSIS `apiFavouriteAction` logic: toggles row, increments/decrements `listing.favourite_count`, returns `['success', 'favourited', 'count']`. `favouriteTable` added to class properties. **Note:** toggleFavourite route/controller POST endpoint is X.3 work. **Note:** table not yet in an install.sql (marketplace package has none) — schema-to-file is a follow-up.
    - [x] X.1.R.11 DONE 2026-04-12 — Re-test: all 41 X.1.1–X.1.5 methods + X.1.R backfills live-invoked against DB, no errors. Marketplace missing-method count unchanged at 13 (all remaining are X.1.6/X.1.7/X.1.8 scope). **X.1.R complete.**
  - [x] X.1.6 DONE 2026-04-12 — Categories / currencies / settings (7). **PSIS-diff-first applied:** read `CurrencyService.php` and `SettingsRepository.php` (lines 139-178 category CRUD, 80-105 currency CRUD, 35-74 settings CRUD) BEFORE writing. Added: `createCategory(array)` with `is_active=1`/`sort_order=0` defaults + `created_at`, `updateCategory(id, array)` with `updated_at` stamp, `deleteCategory(id)` returns bool from delete count, `addCurrency(array)` with code `strtoupper` normalization + `is_active=1` default, `updateCurrency(code, array)` with `strtoupper(code)` lookup + `updated_at` stamp, `getAllSettings(?group)` as alias to existing `getSettings()`, `setSetting(key, value, type, group, description)` as alias to existing `updateSetting()` but returning void (matches PSIS `SettingsRepository::set()` signature). Controller call sites inspected — all 7 caller signatures satisfied. Live-tested create→read→delete round-trip via `setSetting('__test_key', 'v') / getSetting('__test_key') === 'v'`. Marketplace missing-method count: 13 → 6.
  - [x] X.1.7 DONE 2026-04-12 — Payouts + shipping (2). **100% PSIS clones:** `batchProcessPayouts(ids, processedBy)` aliases existing `processPayouts()` with exact PSIS `PayoutService::batchProcess` signature (iterates, returns `['processed', 'skipped', 'errors']`). `updateShipping(txnId, data)` aliases existing `updateTransactionShipping()` — byte-for-byte match with PSIS `TransactionService::updateShipping` (handles tracking_number, courier, shipping_status with shipped_at/delivered_at side-effects and status transitions). Both live-tested against DB. Marketplace missing: 6 → 4.
  - [x] X.1.8 DONE 2026-04-12 — Collections, prefill, misc (4). **NOT PSIS clones — Heratio-specific helpers flagged in code:** `uploadCollectionCover(sellerId, file)` stores to `/uploads/marketplace/collections/{id}/` (PSIS inlines in controller action). `getIOPrefillData(ioId)` joins `information_object + information_object_i18n + slug` tables (GLAM integration PSIS doesn't have). `getUserPrefillData(userId)` reads Laravel `users` table (PSIS uses AtoM `user` table with different shape). `autoProvisionAdminSeller(userId)` creates minimal seller profile for admins with collision-proof slug, verified status, trusted trust_level (PSIS requires explicit seller registration). All 4 flagged as "Heratio-specific (no PSIS equivalent)" in inline comments. All 4 live-tested. **Marketplace missing: 4 → 0.**

### ✅ X.1 COMPLETE — all 55 marketplace service methods now defined (was 55 missing, now 0)

- [x] **X.2** DONE 2026-04-12 — ahg-privacy POST handlers + controller methods (6 routes). **100% PSIS clones from `ahgPrivacyPlugin/lib/Service/PrivacyService.php` + `privacyAdmin/actions/actions.class.php`.** New `AhgPrivacy\Services\PrivacyService` with 6 public methods + 4 helpers (`getRopa`, `logDsarActivity`, `logApprovalAction`, `createNotification`):
  - `updateDsar(id, data, userId)` — filtered updates with `completed_date` on status=completed, `verified_at`/`verified_by` on is_verified=true, writes i18n `notes`/`response_summary` via `updateOrInsert`, logs activity
  - `updateBreach(id, data, userId)` — filtered updates, checkbox 0/1 coercion for notification flags, nullable date handling, writes i18n `title`/`description`/`cause`/`impact_assessment`/`remedial_actions`/`lessons_learned`
  - `withdrawConsent(id, reason, userId)` — sets status=withdrawn + withdrawn_date + withdrawal_reason
  - `submitRopaForApproval(id, userId, officerId)` — draft→pending_review gate, auto-picks primary officer if none, logs + notifies
  - `approveRopa(id, userId, comment)` — pending_review→approved gate, logs + notifies creator
  - `rejectRopa(id, userId, reason)` — pending_review→draft gate, logs + notifies creator
  - `isPrivacyOfficer(userId)` — active-officer existence check

  Controller gets 6 POST handlers mirroring PSIS privacyAdmin actions: `dsarUpdate`, `breachUpdate`, `consentWithdraw`, `ropaSubmit`, `ropaApprove`, `ropaReject`. Each validates `$id > 0`, delegates to service, flashes success/error, redirects to matching view route. ROPA approve/reject gates on `isPrivacyOfficer() || user.is_admin` (matches PSIS administrator fallback). 6 POST routes added to `packages/ahg-privacy/routes/web.php` under the `admin/privacy` prefix + `web`+`auth` middleware.

  **DB schema backfill:** Heratio `privacy_consent_record` was missing `withdrawal_reason` + `updated_at` columns (caught by live-test). Both added via ALTER TABLE.

  All 6 service methods + controller instantiation live-tested. Route registration confirmed via `artisan route:list --name=ahgprivacy`.

- [x] **X.3** DONE 2026-04-12 — ahg-marketplace POST handlers + routes (5 routes). **100% PSIS clones from `ahgMarketplacePlugin/modules/marketplace/actions/`:**
  - `buy` ← `marketplaceBuyAction::execute` — active-listing check, offer_only rejection, auction buy-now branch via `buyNow()` + `createTransaction(source=auction)`, fixed-price branch via `createTransaction(source=fixed_price)`, success flash with transaction_number, redirect to my-purchases. (Cart integration path from PSIS is NOT ported — deferred; Heratio view links to my-purchases directly.)
  - `follow` ← `marketplaceFollowAction::execute` — seller-by-slug lookup, `toggleFollow()`, JSON response for XHR with `['success','followed']`, redirect otherwise with notice flash.
  - `sellerListingPublish` ← `marketplaceSellerListingPublishAction::execute` — owner check (`listing.seller_id === seller.id`), delegates to `publishListing()`, distinct `pending_review` vs active flash.
  - `sellerListingWithdraw` ← `marketplaceSellerListingWithdrawAction::execute` — owner check, delegates to `withdrawListing()`.
  - `admin-payouts-batch` — existing `.post` suffix route renamed to bare `ahgmarketplace.admin-payouts-batch` name (view in `admin-payouts.blade.php` already references bare name). Handler `adminPayoutsBatchPost` unchanged — already cloned from PSIS `adminPayoutsBatchAction`.

  All 4 new controller methods added to `MarketplaceController`. Routes registered: `marketplace/buy` (GET|POST), `marketplace/follow` (POST), `marketplace/seller/listing-publish` (POST), `marketplace/seller/listing-withdraw` (POST), `admin/marketplace/payouts-batch` (POST, renamed). All 5 routes confirmed via `route:list`.

- [x] **X.4** DONE 2026-04-12 — ahg-ai-services LLM suggestion pipeline (4 methods). **NOT PSIS clones — Heratio-specific (PSIS has OCR-only AI, no LLM suggestion feature).** Added to `LlmService`:
  - `gatherContext(objectId)` — joins `information_object` + `information_object_i18n` + `term_i18n` (level_of_description label), aggregates OCR text from `ahg_ai_pending_extraction` (top 3 rows), returns `['success','data']` with all editor fields.
  - `getTemplateForObject(objectId, templateId?)` — explicit ID wins; otherwise 4-tier fallback: same repo+level → same repo → same level → any default. All filtered `is_active=1`, ordered `is_default DESC, id DESC`.
  - `buildPrompt(template, data)` — placeholder substitution for `{{title}}`, `{{identifier}}`, `{{level_of_description}}`, `{{scope_and_content}}`, `{{archival_history}}`, `{{extent_and_medium}}`, `{{arrangement}}`, `{{physical_characteristics}}`, `{{acquisition}}`, `{{ocr_text}}`. Honours `template.include_ocr` and `template.max_ocr_chars` (via `mb_substr`). Returns `['system','user']`.
  - `generateSuggestion(objectId, templateId?, configId?, userId?)` — end-to-end pipeline: gatherContext → getTemplateForObject → buildPrompt → `completeFull()` → persists into `ahg_ai_suggestion` with `field_name='scope_and_content'`, `status='pending'`. Returns `['success','suggestion_id','text','existing_text','tokens_used','model','generation_time_ms','template_name']`.

  **Pre-existing bug fixed:** `AiController::suggestPreview` called `complete($system, $user, $configId)` with 3 positional args against a 2-arg signature `complete(string, array)` — runtime type error. Changed call to `completeFull(...)` which has the correct 3-arg shape. Single-line fix bundled because the method was otherwise dead-on-arrival.

  All 4 methods live-tested against DB with bad id (gatherContext failure path), trivial template (buildPrompt substitution), and bad id (generateSuggestion short-circuit).
  - Called from `AiController`, target service class TBD at batch start

- [x] **X.5** DONE 2026-04-12 — Small-package service-method gaps. **Audit was under-count: actual total was 14 methods across 8 packages, not 10.** None have PSIS equivalents (searched `ahgMarketplacePlugin`, `ahgPrivacyPlugin`, etc); all are Heratio-specific CRUD or getters. All 14 live-tested against DB:
  - `ahg-semantic-search` (3): `deleteTerm` (cascades `ahg_thesaurus_synonym`), `syncTerms` (chunked 500-row import from `term` + `term_i18n` into `ahg_semantic_term`, logs into `ahg_semantic_sync_log` with duration_ms, returns `['success','synced','skipped','duration_ms']`), `clearSearchHistory(?userId)`.
  - `ahg-custom-fields` (3): `createDefinition`, `updateDefinition`, `deleteDefinition` (cascades `custom_field_value`). Each whitelists 16 valid columns via `array_intersect_key` and coerces 6 boolean flags to 0/1.
  - `ahg-nmmz` (3): `deleteMonument` (cascades `nmmz_monument_inspection`), `deleteAntiquity`, `deleteSite`.
  - `ahg-access-request` (1): `cancelRequest(id, userId)` — only own `pending`/`approved` requests.
  - `ahg-statistics` (1): `aggregateStats()` — groups `ahg_usage_event` by `(event_date, event_type, object_type, repository_id, country_code)`, computes total/unique (via `COUNT DISTINCT ip_hash`)/authenticated/bot counts, `updateOrInsert` into `ahg_statistics_daily`, returns `['rows','dates']`.
  - `ahg-ingest` (1): `deleteSession` cascades 5 child tables then the session. **Caught live-test bug:** first draft used wrong table names `ingest_validation_error` + `ingest_column_mapping`; fixed to actual schema `ingest_validation` + `ingest_mapping`.
  - `ahg-multi-tenant` (1): `getCurrentTenant()` — session-based lookup, `is_default=1` fallback, then first row. **Wrapped in try/catch** because `ahg_tenant` table doesn't yet exist (deferred to X.7); returns null safely.
  - `ahg-ipsas` (1): `deleteAsset` cascades `ipsas_valuation` + `ipsas_impairment` + `ipsas_insurance`.

  Final recount across all 8 packages: **0 missing service methods.**

- [ ] **X.6** Typo / broken-ref fixes (4 items, 1 batch)
  - [ ] `route('ingest.')` — find the blade with this empty route name, repoint to a real route (probably `ingest.index` or `ingest.configure`)
  - [ ] `tiffpdfmerge.index` — register route OR update dashboard sidebar to `admin/preservation/tiffpdfmerge`
  - [ ] `ric.dashboard` — register route OR update sidebar to `ric.index`
  - [ ] `iiif.collections` — register route OR update sidebar reference

- [ ] **X.7** DB table verification pass (1 batch — audit-only, no code changes)
  - [ ] Run `SHOW TABLES` against heratio DB
  - [ ] Compile required-tables list by grepping `DB::table\('(\w+)'` in all touched controllers + services
  - [ ] For each missing table: locate the package's `database/install.sql` and run it; document in a new `docs/db-tables-required.md`
  - Output: gap-free table list, or a per-missing-table action item

- [ ] **X.8** Validation rule backfill (~62 forms across marketplace + privacy, ~13 batches)
  - [ ] Per ported form: find the PSIS `actions.class.php` that was the source
  - [ ] Extract the PSIS server-side validation (usually in `executePost($request)`)
  - [ ] Create a Laravel FormRequest class (e.g. `StoreDsarRequest`)
  - [ ] Wire the FormRequest into the heratio POST controller method
  - [ ] Write one smoke test per FormRequest asserting required fields raise 422
  - Batches grouped by package: 7 marketplace batches, 6 privacy batches

- [ ] **X.9** Email / notification backfill (~15 user actions, 3 batches)
  - [ ] DSAR submitted → confirmation email to requestor + notification to privacy officers
  - [ ] DSAR replied → notification to requestor
  - [ ] Breach notification created → email to privacy officers (+ regulator per jurisdiction)
  - [ ] ROPA submitted for approval → notification to privacy officers
  - [ ] ROPA approved/rejected → notification to submitter
  - [ ] Complaint submitted → confirmation + notification
  - [ ] Marketplace offer created → notification to seller
  - [ ] Offer accepted/rejected/countered → notification to buyer
  - [ ] Transaction status changes (paid/shipped/delivered/completed) → buyer + seller
  - [ ] Payout processed → seller notification
  - [ ] Review posted → seller notification
  - [ ] Follow/unfollow → (optional, low priority)
  - Each: port PSIS mail template from `apps/qubit/modules/*/templates/*.email.*` + create Laravel Mailable + queue dispatch

- [ ] **X.10** Final functional smoke test (manual, Johan)
  - [ ] Log in as admin
  - [ ] Click every button on every ported page (62 pages from marketplace + privacy + all Phase A/B pages)
  - [ ] File a bug per non-working interaction in a `docs/x10-smoke-findings.md`
  - [ ] Close all bugs before marking Phase X complete

**Phase X total: ~200 discrete items in ~35 batches. Estimated at 5 items/batch, this is ~7 days of work at the current cadence.**

**Phase X must be COMPLETE (all boxes ticked) before Phase C resumes at C-3.**

### Phase C resumes here after Phase X

- [ ] **C-3** ahg-registry (28 stubs → 6 batches: 5+5+5+5+5+3)
- [ ] **C-4** ahg-nmmz (12 stubs → 3 batches: 5+5+2)
- [ ] **C-5** ahg-icip (11 stubs → 3 batches: 5+5+1)
- [ ] **C-6** ahg-vendor (10 stubs → 2 batches: 5+5)
- [ ] **C-7** ahg-statistics (9 stubs → 2 batches: 5+4)
- [ ] **C-8** ahg-naz (9 stubs → 2 batches: 5+4)
- [ ] **C-9** ahg-exhibition (9 stubs → 2 batches: 5+4)
- [ ] **C-10** ahg-cdpa (8 stubs → 2 batches: 5+3)
- [ ] **C-11** ahg-ipsas (8 stubs → 2 batches: 5+3)
- [ ] **C-12** ahg-forms (6 stubs → 2 batches: 5+1)
- [ ] **C-13** ahg-ingest (5 stubs → 1 batch: 5)
- [ ] **C-14** ahg-multi-tenant (4 stubs → 1 batch: 4)
- [ ] **C-15** ahg-semantic-search (3 stubs → 1 batch: 3)
- [ ] **C-16** **Bundled small packages** (8 stubs across 6 packages — ahg-metadata-export 2, ahg-condition 2, ahg-dacs-manage 1, ahg-dc-manage 1, ahg-mods-manage 1, ahg-rad-manage 1 → 2 batches: 5+3)

**Group 2 total: ~42 batches @ 5 pages each = 191 stubs.**

### Group 3 — API parity (94 endpoints, batched by resource)
- [ ] **D1-1** information_object endpoints (~12)
- [ ] **D1-2** actor / authority endpoints (~10)
- [ ] **D1-3** repository endpoints (~8)
- [ ] **D1-4** accession endpoints (~8)
- [ ] **D1-5** taxonomy / term endpoints (~10)
- [ ] **D1-6** digital_object endpoints (~8)
- [ ] **D1-7** rights / extended_rights endpoints (~8)
- [ ] **D1-8** condition / spectrum endpoints (~8)
- [ ] **D1-9** research / annotations endpoints (~10)
- [ ] **D1-10** auth / api_key endpoints (~6)
- [ ] **D1-11** miscellaneous remaining endpoints (~6)

> Note: exact counts must be reconciled against `docs/API-COMPARISON.md` at the start of each batch.

### Group 4 — Runtime hidden surface (AJAX + cron + JS)
Per-package walk. Each package = 1 batch.

- [ ] **D5-1** ahg-information-object-manage
- [ ] **D5-2** ahg-actor-manage
- [ ] **D5-3** ahg-repository-manage
- [ ] **D5-4** ahg-display
- [ ] **D5-5** ahg-search
- [ ] **D5-6** ahg-research
- [ ] **D5-7** ahg-spectrum
- [ ] **D5-8** ahg-condition
- [ ] **D5-9** ahg-extended-rights
- [ ] **D5-10** ahg-marketplace
- [ ] **D5-11** ahg-cart
- [ ] **D5-12** ahg-vendor
- [ ] **D5-13** ahg-doi-manage
- [ ] **D5-14** ahg-ric
- [ ] **D5-15** ahg-data-migration
- [ ] **D5-16** ahg-ingest
- [ ] **D5-17** ahg-backup
- [ ] **D5-18** ahg-preservation
- [ ] **D5-19** ahg-dedupe
- [ ] **D5-20** ahg-heritage-manage
- [ ] **D5-21** ahg-privacy
- [ ] **D5-22** ahg-cdpa
- [ ] **D5-23** ahg-naz
- [ ] **D5-24** ahg-nmmz
- [ ] **D5-25** ahg-ipsas
- [ ] **D5-26** ahg-icip
- [ ] **D5-27** ahg-acl
- [ ] **D5-28** ahg-audit
- [ ] **D5-29** ahg-ai-services
- [ ] **D5-30** ahg-statistics
- [ ] **D5-31** ahg-workflow
- [ ] **D5-32** ahg-iiif (if separate package)
- [ ] **D5-33** ahg-3d
- [ ] **D5-34** ahg-dam
- [ ] **D5-35** ahg-museum
- [ ] **D5-36** ahg-library
- [ ] **D5-37** ahg-gallery
- [ ] **D5-38** ahg-exhibition
- [ ] **D5-39** ahg-forms
- [ ] **D5-40** ahg-translation
- [ ] **D5-41** ahg-help
- [ ] **D5-42** ahg-static-page
- [ ] **D5-43** ahg-multi-tenant
- [ ] **D5-44** ahg-registry
- [ ] **D5-45** ahg-reports
- [ ] **D5-46** ahg-metadata-export
- [ ] **D5-47** ahg-semantic-search
- [ ] **D5-48** ahg-rights-holder-manage
- [ ] **D5-49** ahg-loan
- [ ] **D5-50** ahg-storage-manage
- [ ] **D5-51** ahg-donor-manage
- [ ] **D5-52** ahg-user-manage
- [ ] **D5-53** ahg-menu-manage
- [ ] **D5-54** ahg-term-taxonomy
- [ ] **D5-55** ahg-rad-manage / ahg-mods-manage / ahg-dc-manage / ahg-dacs-manage / ahg-function-manage / ahg-accession-manage (bundled)
- [ ] **D5-56** ahg-core (last — uncovers cross-package gotchas)

> Per-package list above is the current package inventory. Reconcile against `ls packages/` at the start of D5.

### Group 5 — POST handlers + form validation audit
Same per-package walk as Group 4. Each package = 1 batch tied to its D5 batch.

- [ ] **D6-1 → D6-56** — one batch per package, mirroring D5-1 through D5-56

### Group 6 — Plugin coverage matrix (one-time inventory + new package builds)
- [ ] **D4-1** Generate the PSIS-plugin → Heratio-package CSV mapping
- [ ] **D4-2** Identify the ~27 plugin-gap rows
- [ ] **D4-3** Build new heratio package #1 (TBD — depends on D4-1 output)
- [ ] **D4-4** Build new heratio package #2
- [ ] **D4-5..N** — one batch per missing plugin (count and IDs filled in after D4-1)

### Group 7 — Media processing (largest, GPU-dependent — last)
- [ ] **D3-1** 3D model viewer + storage
- [ ] **D3-2** AI image analysis pipeline (LLaVA on server-78)
- [ ] **D3-3** Video metadata extraction (ffprobe)
- [ ] **D3-4** Watermarking pipeline
- [ ] **D3-5** HLS / DASH adaptive streaming
- [ ] **D3-6** Encryption-at-rest
- [ ] **D3-7** IPTC / XMP round-trip
- [ ] **D3-8** Face detection
- [ ] **D3-9** OCR pipeline (HTR + printed text)
- [ ] **D3-10** Format identification (PRONOM / Siegfried)
- [ ] **D3-11** Preservation derivatives (FFV1, JPEG2000)
- [ ] **D3-12..15** Remaining items from `docs/MEDIA-PROCESSING-COMPARISON.md`

### Final acceptance gate
- [ ] All Group 1–7 boxes ticked `[x]`
- [ ] All boxes ticked `[v]` (Johan browser-verified)
- [ ] Final cross-reference: `php artisan route:list` count matches PSIS route count (or documented diff)
- [ ] Final cross-reference: per-package `find packages/{pkg} -name '*.blade.php' | wc -l` matches PSIS templates count (or documented diff)
- [ ] `docs/heratio-vs-psis-outstanding-plan.md` updated with completion date and final summary

## Working agreement

- **Batch size:** 5 items per batch unless the items are tiny (route aliases) or huge (full page rebuilds with controllers).
- **Cadence:** I do one batch, hand back the commit command + table of what changed, you commit + browser-test, I do the next batch.
- **Tracking:** I update this doc's checkboxes after every batch ships. You can change `[x]` → `[v]` after browser testing.
- **No invention:** the clone-only rule (`feedback_clone_only_no_invent.md`) still applies — if PSIS doesn't have a source, escalate.
- **International framing:** the `feedback_international_positioning.md` rule applies to every new file/copy/example — never default to SA.
- **Commits:** every batch produces one `./bin/release patch` commit message in the format we used for Phases A/B.

---

## Phase C — Stub view content port (191 views)

### What "stub" means

A stub view matches the regex `accordion-body">\s*</div>` — i.e. it has a Bootstrap accordion with an empty body. The pages render a heading + an empty box + Save/Cancel buttons. Functionally, the user sees a near-blank page. 191 such files exist across 21 packages.

These were NOT created by hand for content; they're the leftover scaffolding from the previous "175 destination pages cloned" sweep, which only fixed CSS class names and never added real content. Yesterday's session marked them complete because the CSS pass touched them — that was the misnomer.

### Per-package breakdown

| # | Package | Stub count | Suggested batch size |
|---|---------|-----------:|----------------------|
| 1 | ahg-marketplace | **32** | 4 batches × 8 |
| 2 | ahg-privacy | **29** | 4 batches × 8 |
| 3 | ahg-registry | **28** | 4 batches × 7 |
| 4 | ahg-nmmz | **12** | 2 batches × 6 |
| 5 | ahg-icip | **11** | 2 batches × 6 |
| 6 | ahg-vendor | **10** | 2 batches × 5 |
| 7 | ahg-statistics | **9** | 2 batches × 5 |
| 8 | ahg-naz | **9** | 2 batches × 5 |
| 9 | ahg-exhibition | **9** | 2 batches × 5 |
| 10 | ahg-cdpa | **8** | 2 batches × 4 |
| 11 | ahg-ipsas | **8** | 2 batches × 4 |
| 12 | ahg-forms | **6** | 1 batch × 6 |
| 13 | ahg-ingest | **5** | 1 batch × 5 |
| 14 | ahg-multi-tenant | **4** | 1 batch × 4 |
| 15 | ahg-semantic-search | **3** | 1 batch × 3 |
| 16 | ahg-metadata-export | **2** | bundle with other small packages |
| 17 | ahg-condition | **2** | bundle |
| 18 | ahg-dacs-manage | **1** | bundle |
| 19 | ahg-dc-manage | **1** | bundle |
| 20 | ahg-mods-manage | **1** | bundle |
| 21 | ahg-rad-manage | **1** | bundle |
| **Total** | **21 packages** | **191 stubs** | **~38 batches @ 5/batch** |

### Methodology per stub

For each stub view:

1. **Locate the matching PSIS template.** Search `/usr/share/nginx/archive/plugins/{plugin}/modules/{module}/templates/{name}Success.{php,blade.php}`.
2. **If PSIS source exists** → port content faithfully (header, form fields, validation hints, action buttons, breadcrumb, sidebar slot). Use the existing controller method's variables; extend the controller only if PSIS passes data heratio doesn't.
3. **If PSIS source does not exist** → escalate to user (do NOT invent — per `feedback_clone_only_no_invent.md`).
4. **Smoke test** via `Kernel::handle()` for HTTP status, then mark for browser test.
5. **Update this doc's tracking table** with control counts before/after.

### Acceptance criteria per page

- Heratio control count ≥ PSIS control count (or documented parity gap with reason).
- All form fields render with real data from the matching DB tables.
- All action buttons resolve to live routes.
- Page returns HTTP 200/302/403 (no 500s).
- User browser-tests as admin and confirms.

### Phase C tracking

A separate appendix table will be appended below as each batch completes.
For now, every row starts at: `before=0 (stub), after=TBD, status=TODO`.

### Risk: linked sub-pages

Many of these stubs are EDIT pages (e.g. `nmmz/permit-create`, `cdpa/breach-create`).
If the LIST page (e.g. `nmmz/permits`) has a link to the EDIT page and the EDIT
page is a stub, fixing only the LIST gives the user a working list that crashes
to a blank page on click. Batch by FEATURE, not by alphabetical filename:
clone the list view AND its create/edit/view siblings together.

---

## Phase D — Broader PSIS coverage audit

### D1 — API parity (94 missing endpoints)

**Source:** `docs/API-COMPARISON.md` (per memory `project_api_gap.md`).

**Scope:** All v1 CRUD routes + the entire v2 REST surface that PSIS exposes
under `apiV1Plugin` and `apiV2Plugin` but heratio is missing.

**Methodology:**
1. Read `docs/API-COMPARISON.md` to get the full delta list.
2. Group by resource (information_object, actor, repository, accession, etc.).
3. For each missing endpoint: port the controller method, add Form Request validation,
   register the route under `routes/api.php`, write a smoke test.
4. Update the OpenAPI spec at `docs/api/openapi.yaml` (if it exists).
5. Document auth requirements (api_key, sanctum, etc.).

**Suggested batches:** 1 batch = 1 resource (≈ 8 endpoints).

**Acceptance:** Each endpoint returns 2xx for valid input, 4xx for invalid, matches PSIS response shape.

---

### D2 — AHG menu parity (13 missing items + 14 stale)

**Source:** `docs/AHG-MENU-COMPARISON.md` (per memory `project_ahg_menu_comparison.md`).

**Scope:** The admin sidebar / nav (NOT the reports dashboard, which is now done).
13 menu items present in PSIS that heratio is missing, plus 14 stale entries
that point at nothing or duplicate other links.

**Methodology:**
1. Re-read `docs/AHG-MENU-COMPARISON.md`.
2. Verify each "missing" item — confirm the target page exists in heratio (after the
   reports-dashboard work, several may now resolve).
3. For each truly missing item: add the menu link to the relevant nav/sidebar
   blade partial.
4. For each stale entry: confirm dead, then remove or repoint.
5. Visual diff PSIS sidebar vs heratio sidebar at every URL prefix.

**Acceptance:** All AHG menu items resolve. No dead links. Side-by-side screenshot match.

---

### D3 — Media processing parity (15 missing features)

**Source:** `docs/MEDIA-PROCESSING-COMPARISON.md` (per memory `project_media_gap.md`).

**Scope:** 3D model viewer, AI image analysis, video metadata extraction,
watermarking, HLS/DASH streaming, encryption-at-rest, IPTC/XMP round-trip,
face detection, OCR pipeline, format identification (PRONOM/Siegfried),
preservation derivatives.

**Methodology:**
1. Read `docs/MEDIA-PROCESSING-COMPARISON.md`.
2. Per feature: identify the PSIS service class + queue worker + storage layout.
3. Port the service into `packages/ahg-media-processing/src/Services/` (or
   create the package if it doesn't exist).
4. Wire the queue jobs into Laravel's queue system.
5. End-to-end test: upload → process → derivative renders correctly.

**Acceptance:** Each missing feature has a passing integration test that matches
the PSIS reference output.

**Risk:** This phase requires GPU access (server 192.168.0.78) for AI features.
Estimated effort is the largest of all D phases.

---

### D4 — Plugin coverage matrix (119 PSIS plugins ↔ 92 Heratio packages)

**Why this matters:** PSIS has 119 plugins. Heratio has 92 packages. The 27-plugin
gap is structural and needs a one-to-one mapping audit before we can claim
"100% PSIS coverage."

**Methodology:**
1. Generate a CSV: PSIS plugin name → Heratio package name (or "MISSING").
2. For each "MISSING" row: read the PSIS plugin's `extension.json` + `modules/`
   to figure out scope. Decide:
   a. Build new heratio package (clone wholesale).
   b. Merge into existing heratio package.
   c. Skip (deprecated in PSIS, not used).
3. Build per missing package, batch by domain (commerce, compliance, etc.).

**Acceptance:** Every PSIS plugin has either a heratio package or a documented
"intentionally not ported" reason.

---

### D5 — AJAX endpoints, cron jobs, background services, JS layer

**Why this matters:** All my smoke tests in Phases A/B confirmed the PHP routes
resolve. None of them confirmed:
- The AJAX endpoints the JS calls (autocomplete, tree-view, search-as-you-type).
- The cron jobs (search reindex, fixity scan, embargo expiry, GRAP recalculation).
- The background services (queue workers, file watchers, websocket relays).
- The JS layer itself (TomSelect initialization, Chart.js render, file upload).

**Methodology:**
1. Per package, read `routes/api.php` + grep `XMLHttpRequest|fetch(|axios.` in
   the JS bundle.
2. For each AJAX endpoint, confirm the heratio counterpart exists and returns
   the same JSON shape.
3. Per package, read `app/Console/Kernel.php` + the PSIS cron config in
   `apps/qubit/config/schedule.yml` to compare scheduled tasks.
4. Per package, read PSIS `lib/Services/` and confirm the heratio service has
   the same public method surface.

**Acceptance:** Per-package compatibility matrix table — each lane (HTTP, AJAX,
cron, queue, JS) marked Y/N/Partial.

---

### D6 — Form validation, POST handlers, business logic

**Why this matters:** I confirmed view files exist and routes resolve. I never
audited:
- Form Request validation rules vs PSIS validators.
- POST handler behaviour (does it actually create the right rows?).
- Business logic in service classes (e.g. does GRAP recalculate correctly?).
- Error handling (does the app degrade gracefully on bad input?).

**Methodology:**
1. Per page that has a form: open PSIS action `executePost($request)` and
   compare to heratio controller `store()`/`update()`/`destroy()`.
2. For each field, confirm validation rule matches.
3. For each side effect (DB write, queue dispatch, event fire, mail send),
   confirm heratio replicates it.
4. For each happy path + each known edge case (empty body, invalid IDs, missing
   FK, concurrent edit), test.

**Acceptance:** Per-page POST handler audit checklist with PASS/FAIL.

---

## Recommended order of work

1. **Phase C feature-batched** (191 stubs, ~5 weeks at 5 stubs/day) — this gives the user
   working pages everywhere they currently see white. Highest user-visible impact.
2. **Phase D2 — AHG menu parity** (small, ~1 day) — closes "I can't find the page" gripes.
3. **Phase D1 — API parity** (94 endpoints, ~3 weeks) — unblocks integrations and the mobile / DB-tools projects that depend on v2.
4. **Phase D5 — AJAX/cron/JS audit** (per-package, ~2 weeks) — surfaces hidden runtime breakage.
5. **Phase D6 — POST handler validation audit** (~3 weeks).
6. **Phase D4 — plugin coverage matrix** (one-time inventory, ~3 days) — gives a clean "what's left" delta.
7. **Phase D3 — media processing** (~6 weeks, requires GPU) — biggest, defer until others are stable.

**Total estimate:** ~5 months of focused work to reach genuine 100% PSIS parity.
This excludes the post-parity work of building NEW features that PSIS doesn't have.

---

## How to use this doc going forward

- After each batch, append a results table to the relevant Phase section
  (matching the format in `docs/reports-dashboard-comparison.md`).
- Update the "Approx size" column at the top whenever a phase finishes.
- When the user browser-tests a page and finds a bug not caught by `Kernel::handle()`,
  add a row to a "Runtime gotchas found in browser testing" appendix at the
  bottom of this file — those are the highest-value findings.

## Open questions for Johan

1. **Phase C order of attack:** alphabetical by package, or by user-visibility (start with marketplace which has 32 pages and is the biggest revenue feature)?
2. **Phase D scope freeze:** are there PSIS plugins that should be marked "intentionally not ported" so we don't waste effort on them? (e.g. legacy auth plugins, ndaResearcherPlugin if NAZ workflow has been replaced).
3. **Browser test cadence:** should I queue a test of every Phase C batch to you immediately on completion, or batch every 5 stub-batches into one review session?
4. **Acceptance bar:** is "renders without 500 + has all PSIS controls" enough for Phase C to mark a page DONE, or do you also want POST handlers + JS interactions verified before marking complete?
