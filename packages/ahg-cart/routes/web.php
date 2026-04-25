<?php

use AhgCart\Controllers\CartController;
use Illuminate\Support\Facades\Route;

// Public cart routes
Route::get('/cart', [CartController::class, 'browse'])->name('cart.browse');
Route::get('/cart/add/{slug}', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove')->where('id', '[0-9]+');
Route::post('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');

// Marketplace cart entry — Add-to-cart from a marketplace listing,
// then combined PayFast checkout for everything in the cart at once.
Route::post('/cart/listing/add/{listingId}', [CartController::class, 'addListing'])
    ->where('listingId', '[0-9]+')
    ->name('cart.listing-add')
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

// Authenticated user routes
Route::middleware('auth')->group(function () {
    Route::get('/cart/orders', [CartController::class, 'orders'])->name('cart.orders');
});

// Download route (public — token-authenticated)
Route::get('/cart/download/{token}', [CartController::class, 'download'])->name('cart.download');

// Payment notification webhook (no auth — called by payment gateway)
Route::post('/cart/payment/notify', [CartController::class, 'paymentNotify'])->name('cart.paymentNotify');

// Admin routes
Route::middleware('admin')->group(function () {
    Route::get('/admin/orders', [CartController::class, 'adminOrders'])->name('cart.admin.orders');
    Route::match(['get', 'post'], '/admin/ahgSettings/ecommerce', [CartController::class, 'adminSettings'])->name('cart.admin.settings');
    Route::get('/admin/ecommerce-settings', fn () => redirect('/admin/ahgSettings/ecommerce')); // legacy redirect
    Route::get('/cart/payment/{id}', [CartController::class, 'payment'])->name('cart.payment')->whereNumber('id');
});
