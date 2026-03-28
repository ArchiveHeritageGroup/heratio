<?php

use Illuminate\Support\Facades\Route;

$controller = \AhgMarketplace\Controllers\MarketplaceController::class;

// ─── Public routes (no auth required) ─────────────────────────────
Route::prefix('marketplace')->middleware(['web'])->group(function () use ($controller) {
    Route::get('/browse', [$controller, 'browse'])->name('ahgmarketplace.browse');
    Route::get('/search', [$controller, 'search'])->name('ahgmarketplace.search');
    Route::get('/listing', [$controller, 'listing'])->name('ahgmarketplace.listing');
    Route::get('/category', [$controller, 'category'])->name('ahgmarketplace.category');
    Route::get('/sector', [$controller, 'sector'])->name('ahgmarketplace.sector');
    Route::get('/featured', [$controller, 'featured'])->name('ahgmarketplace.featured');
    Route::get('/auction-browse', [$controller, 'auctionBrowse'])->name('ahgmarketplace.auction-browse');
    Route::get('/collection', [$controller, 'collection'])->name('ahgmarketplace.collection');
    Route::get('/seller', [$controller, 'seller'])->name('ahgmarketplace.seller');

    // Enquiry form (may allow guest)
    Route::get('/enquiry-form', [$controller, 'enquiryForm'])->name('ahgmarketplace.enquiry-form');
    Route::post('/enquiry-form', [$controller, 'enquiryFormPost'])->name('ahgmarketplace.enquiry-form.post')->middleware('acl:create');
});

// ─── Authenticated user routes (buyer) ────────────────────────────
Route::prefix('marketplace')->middleware(['web', 'auth'])->group(function () use ($controller) {
    // Dashboard
    Route::get('/dashboard', [$controller, 'dashboard'])->name('ahgmarketplace.dashboard');

    // Bid form
    Route::get('/bid-form', [$controller, 'bidForm'])->name('ahgmarketplace.bid-form');
    Route::post('/bid-form', [$controller, 'bidFormPost'])->name('ahgmarketplace.bid-form.post')->middleware('acl:create');

    // Offer form
    Route::get('/offer-form', [$controller, 'offerForm'])->name('ahgmarketplace.offer-form');
    Route::post('/offer-form', [$controller, 'offerFormPost'])->name('ahgmarketplace.offer-form.post')->middleware('acl:create');

    // Review form
    Route::get('/review-form', [$controller, 'reviewForm'])->name('ahgmarketplace.review-form');
    Route::post('/review-form', [$controller, 'reviewFormPost'])->name('ahgmarketplace.review-form.post')->middleware('acl:create');

    // My pages
    Route::get('/my-bids', [$controller, 'myBids'])->name('ahgmarketplace.my-bids');
    Route::get('/my-following', [$controller, 'myFollowing'])->name('ahgmarketplace.my-following');

    Route::get('/my-offers', [$controller, 'myOffers'])->name('ahgmarketplace.my-offers');
    Route::post('/my-offers', [$controller, 'myOffersPost'])->name('ahgmarketplace.my-offers.post')->middleware('acl:update');

    Route::get('/my-purchases', [$controller, 'myPurchases'])->name('ahgmarketplace.my-purchases');
    Route::post('/my-purchases', [$controller, 'myPurchasesPost'])->name('ahgmarketplace.my-purchases.post')->middleware('acl:update');
});

// ─── Seller routes (authenticated) ───────────────────────────────
Route::prefix('marketplace/seller')->middleware(['web', 'auth'])->group(function () use ($controller) {
    // Registration
    Route::get('/register', [$controller, 'sellerRegister'])->name('ahgmarketplace.seller-register');
    Route::post('/register', [$controller, 'sellerRegisterPost'])->name('ahgmarketplace.seller-register.post')->middleware('acl:create');

    // Profile
    Route::get('/profile', [$controller, 'sellerProfile'])->name('ahgmarketplace.seller-profile');
    Route::post('/profile', [$controller, 'sellerProfilePost'])->name('ahgmarketplace.seller-profile.post')->middleware('acl:update');

    // Listings
    Route::get('/listings', [$controller, 'sellerListings'])->name('ahgmarketplace.seller-listings');

    Route::get('/listing-create', [$controller, 'sellerListingCreate'])->name('ahgmarketplace.seller-listing-create');
    Route::post('/listing-create', [$controller, 'sellerListingCreatePost'])->name('ahgmarketplace.seller-listing-create.post')->middleware('acl:create');

    Route::get('/listing-edit', [$controller, 'sellerListingEdit'])->name('ahgmarketplace.seller-listing-edit');
    Route::post('/listing-edit', [$controller, 'sellerListingEditPost'])->name('ahgmarketplace.seller-listing-edit.post')->middleware('acl:update');

    Route::get('/listing-images', [$controller, 'sellerListingImages'])->name('ahgmarketplace.seller-listing-images');
    Route::post('/listing-images', [$controller, 'sellerListingImagesPost'])->name('ahgmarketplace.seller-listing-images.post')->middleware('acl:update');

    // Collections
    Route::get('/collections', [$controller, 'sellerCollections'])->name('ahgmarketplace.seller-collections');
    Route::post('/collections', [$controller, 'sellerCollectionsPost'])->name('ahgmarketplace.seller-collections.post')->middleware('acl:update');

    Route::get('/collection-create', [$controller, 'sellerCollectionCreate'])->name('ahgmarketplace.seller-collection-create');
    Route::post('/collection-create', [$controller, 'sellerCollectionCreatePost'])->name('ahgmarketplace.seller-collection-create.post')->middleware('acl:create');

    // Offers
    Route::get('/offers', [$controller, 'sellerOffers'])->name('ahgmarketplace.seller-offers');

    Route::get('/offer-respond', [$controller, 'sellerOfferRespond'])->name('ahgmarketplace.seller-offer-respond');
    Route::post('/offer-respond', [$controller, 'sellerOfferRespondPost'])->name('ahgmarketplace.seller-offer-respond.post')->middleware('acl:update');

    // Transactions
    Route::get('/transactions', [$controller, 'sellerTransactions'])->name('ahgmarketplace.seller-transactions');

    Route::get('/transaction-detail', [$controller, 'sellerTransactionDetail'])->name('ahgmarketplace.seller-transaction-detail');
    Route::post('/transaction-detail', [$controller, 'sellerTransactionDetailPost'])->name('ahgmarketplace.seller-transaction-detail.post')->middleware('acl:update');

    // Enquiries
    Route::get('/enquiries', [$controller, 'sellerEnquiries'])->name('ahgmarketplace.seller-enquiries');
    Route::post('/enquiries', [$controller, 'sellerEnquiriesPost'])->name('ahgmarketplace.seller-enquiries.post')->middleware('acl:update');

    // Analytics, Payouts, Reviews (read-only)
    Route::get('/analytics', [$controller, 'sellerAnalytics'])->name('ahgmarketplace.seller-analytics');
    Route::get('/payouts', [$controller, 'sellerPayouts'])->name('ahgmarketplace.seller-payouts');
    Route::get('/reviews', [$controller, 'sellerReviews'])->name('ahgmarketplace.seller-reviews');
});

// ─── Admin routes ─────────────────────────────────────────────────
Route::prefix('admin/marketplace')->middleware(['web', 'auth'])->group(function () use ($controller) {
    Route::get('/dashboard', [$controller, 'adminDashboard'])->name('ahgmarketplace.admin-dashboard');

    Route::get('/listings', [$controller, 'adminListings'])->name('ahgmarketplace.admin-listings');

    Route::get('/listing-review', [$controller, 'adminListingReview'])->name('ahgmarketplace.admin-listing-review');
    Route::post('/listing-review', [$controller, 'adminListingReviewPost'])->name('ahgmarketplace.admin-listing-review.post')->middleware('acl:update');

    Route::get('/categories', [$controller, 'adminCategories'])->name('ahgmarketplace.admin-categories');
    Route::post('/categories', [$controller, 'adminCategoriesPost'])->name('ahgmarketplace.admin-categories.post')->middleware('acl:update');

    Route::get('/currencies', [$controller, 'adminCurrencies'])->name('ahgmarketplace.admin-currencies');
    Route::post('/currencies', [$controller, 'adminCurrenciesPost'])->name('ahgmarketplace.admin-currencies.post')->middleware('acl:update');

    Route::get('/sellers', [$controller, 'adminSellers'])->name('ahgmarketplace.admin-sellers');

    Route::get('/seller-verify', [$controller, 'adminSellerVerify'])->name('ahgmarketplace.admin-seller-verify');
    Route::post('/seller-verify', [$controller, 'adminSellerVerifyPost'])->name('ahgmarketplace.admin-seller-verify.post')->middleware('acl:update');

    Route::get('/transactions', [$controller, 'adminTransactions'])->name('ahgmarketplace.admin-transactions');

    Route::get('/payouts', [$controller, 'adminPayouts'])->name('ahgmarketplace.admin-payouts');
    Route::post('/payouts-batch', [$controller, 'adminPayoutsBatchPost'])->name('ahgmarketplace.admin-payouts-batch.post')->middleware('acl:update');

    Route::get('/reports', [$controller, 'adminReports'])->name('ahgmarketplace.admin-reports');

    Route::get('/reviews', [$controller, 'adminReviews'])->name('ahgmarketplace.admin-reviews');
    Route::post('/reviews', [$controller, 'adminReviewsPost'])->name('ahgmarketplace.admin-reviews.post')->middleware('acl:update');

    Route::get('/settings', [$controller, 'adminSettings'])->name('ahgmarketplace.admin-settings');
    Route::post('/settings', [$controller, 'adminSettingsPost'])->name('ahgmarketplace.admin-settings.post')->middleware('acl:update');
});
