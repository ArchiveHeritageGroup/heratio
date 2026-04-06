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

    // PageIndex LLM retrieval routes
    Route::get('/pageindex', [\AhgDiscovery\Controllers\DiscoveryController::class, 'pageindex'])->name('ahgdiscovery.pageindex');
    Route::get('/pageindex/api', [\AhgDiscovery\Controllers\DiscoveryController::class, 'pageindexApi'])->name('ahgdiscovery.pageindex.api');
    Route::post('/pageindex/api', [\AhgDiscovery\Controllers\DiscoveryController::class, 'pageindexApi']);
    Route::get('/build', [\AhgDiscovery\Controllers\DiscoveryController::class, 'build'])->name('ahgdiscovery.build')->middleware('auth');
    Route::post('/build', [\AhgDiscovery\Controllers\DiscoveryController::class, 'buildStore'])->name('ahgdiscovery.build.store')->middleware('auth');
});
