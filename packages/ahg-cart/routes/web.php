<?php

use AhgCart\Controllers\CartController;
use Illuminate\Support\Facades\Route;

// Public cart routes
Route::get('/cart', [CartController::class, 'browse'])->name('cart.browse');
Route::get('/cart/add/{slug}', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove')->where('id', '[0-9]+');
Route::post('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');

// Marketplace cart entry - Add-to-cart from a marketplace listing,
// then combined PayFast checkout for everything in the cart at once.
Route::post('/cart/listing/add/{listingId}', [CartController::class, 'addListing'])
    ->where('listingId', '[0-9]+')
    ->name('cart.listing-add')
    ->middleware('auth');
Route::post('/cart/listing/remove/{listingId}', [CartController::class, 'removeListing'])
    ->where('listingId', '[0-9]+')
    ->name('cart.listing-remove')
    ->middleware('auth');
Route::post('/cart/marketplace/checkout', [CartController::class, 'marketplaceCheckout'])
    ->name('cart.marketplace-checkout')
    ->middleware('auth');
Route::post('/cart/marketplace/demo-checkout', [CartController::class, 'marketplaceDemoCheckout'])
    ->name('cart.marketplace-demo-checkout')
    ->middleware('auth');

Route::match(['get', 'post'], '/cart/checkout', [CartController::class, 'checkout'])->name('cart.checkout');
Route::get('/cart/thank-you', [CartController::class, 'thankYou'])->name('cart.thankyou');
Route::get('/cart/order/{id}', [CartController::class, 'orderConfirmation'])->name('cart.order-confirmation')->where('id', '[0-9]+');

// heratio#1770: subscription tiers on offer (signing in is asked for on subscribing).
Route::get('/cart/subscriptions', [CartController::class, 'subscriptions'])->name('cart.subscriptions');

// Authenticated user routes
Route::middleware('auth')->group(function () {
    Route::get('/cart/orders', [CartController::class, 'orders'])->name('cart.orders');
    // heratio#1774: pay an order; accept an access request's quotation.
    Route::get('/cart/payment/{id}', [CartController::class, 'payment'])->name('cart.payment')->whereNumber('id');
    Route::post('/cart/quote/{id}/accept', [CartController::class, 'acceptQuote'])->name('cart.quote.accept')->whereNumber('id');
    // heratio#1770: take out a subscription tier.
    Route::post('/cart/subscribe/{tierId}', [CartController::class, 'subscribe'])->name('cart.subscribe')->whereNumber('tierId');
});

// Download route (public - token-authenticated)
Route::get('/cart/download/{token}', [CartController::class, 'download'])->name('cart.download');

// Payment notification webhook (no auth - called by payment gateway)
Route::post('/cart/payment/notify', [CartController::class, 'paymentNotify'])->name('cart.paymentNotify');

// Admin routes
Route::middleware('admin')->group(function () {
    Route::get('/admin/orders', [CartController::class, 'adminOrders'])->name('cart.admin.orders');
    Route::match(['get', 'post'], '/admin/ahgSettings/ecommerce', [CartController::class, 'adminSettings'])->name('cart.admin.settings');
    Route::get('/admin/ecommerce-settings', fn () => redirect('/admin/ahgSettings/ecommerce')); // legacy redirect
});
