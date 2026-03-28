<?php

use Illuminate\Support\Facades\Route;

// Public discovery routes (search is available to all users)
Route::prefix('discovery')->middleware(['web'])->group(function () {
    Route::get('/', [\AhgDiscovery\Controllers\DiscoveryController::class, 'index'])->name('ahgdiscovery.index');
    Route::get('/index', [\AhgDiscovery\Controllers\DiscoveryController::class, 'index']);
    Route::get('/search', [\AhgDiscovery\Controllers\DiscoveryController::class, 'search'])->name('ahgdiscovery.search');
    Route::get('/suggest', [\AhgDiscovery\Controllers\DiscoveryController::class, 'suggest'])->name('ahgdiscovery.suggest');
    Route::get('/popular', [\AhgDiscovery\Controllers\DiscoveryController::class, 'popular'])->name('ahgdiscovery.popular');
    Route::post('/click', [\AhgDiscovery\Controllers\DiscoveryController::class, 'click'])->name('ahgdiscovery.click');
});
