<?php

use AhgCart\Controllers\CartController;
use Illuminate\Support\Facades\Route;

// Public cart routes
Route::get('/cart', [CartController::class, 'browse'])->name('cart.browse');
Route::get('/cart/add/{slug}', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove')->where('id', '[0-9]+');
Route::post('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');
Route::match(['get', 'post'], '/cart/checkout', [CartController::class, 'checkout'])->name('cart.checkout');
Route::get('/cart/thank-you', [CartController::class, 'thankYou'])->name('cart.thankyou');
Route::get('/cart/order/{id}', [CartController::class, 'orderConfirmation'])->name('cart.order-confirmation')->where('id', '[0-9]+');

// Authenticated user routes
Route::middleware('auth')->group(function () {
    Route::get('/cart/orders', [CartController::class, 'orders'])->name('cart.orders');
});

// Admin routes
Route::middleware('admin')->group(function () {
    Route::get('/admin/orders', [CartController::class, 'adminOrders'])->name('cart.admin.orders');
    Route::match(['get', 'post'], '/admin/ecommerce-settings', [CartController::class, 'adminSettings'])->name('cart.admin.settings');
});
