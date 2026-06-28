<?php

use Illuminate\Support\Facades\Route;

// Public discovery routes (search is available to all users)
Route::prefix('discovery')->middleware(['web'])->group(function () {
    Route::get('/', [\AhgDiscovery\Controllers\DiscoveryController::class, 'index'])->name('ahgdiscovery.index');
    Route::get('/index', [\AhgDiscovery\Controllers\DiscoveryController::class, 'index']);
    Route::get('/search', [\AhgDiscovery\Controllers\DiscoveryController::class, 'search'])->name('ahgdiscovery.search');
    Route::get('/suggest', [\AhgDiscovery\Controllers\DiscoveryController::class, 'suggest'])->name('ahgdiscovery.suggest');
    Route::get('/popular', [\AhgDiscovery\Controllers\DiscoveryController::class, 'popular'])->name('ahgdiscovery.popular');
    // #1367: open POST — rate-limit to blunt click-log flooding by anon.
    Route::post('/click', [\AhgDiscovery\Controllers\DiscoveryController::class, 'click'])->name('ahgdiscovery.click')->middleware('throttle:30,1');

    // PageIndex LLM retrieval routes
    // #1367: the pageindex/api endpoint drives LLM retrieval (expensive) and is
    // open to anon — tighter throttle to prevent abuse / cost amplification.
    Route::get('/pageindex', [\AhgDiscovery\Controllers\DiscoveryController::class, 'pageindex'])->name('ahgdiscovery.pageindex');
    Route::get('/pageindex/api', [\AhgDiscovery\Controllers\DiscoveryController::class, 'pageindexApi'])->name('ahgdiscovery.pageindex.api')->middleware('throttle:10,1');
    Route::post('/pageindex/api', [\AhgDiscovery\Controllers\DiscoveryController::class, 'pageindexApi'])->middleware('throttle:10,1');
    Route::get('/build', [\AhgDiscovery\Controllers\DiscoveryController::class, 'build'])->name('ahgdiscovery.build')->middleware('auth');
    Route::post('/build', [\AhgDiscovery\Controllers\DiscoveryController::class, 'buildStore'])->name('ahgdiscovery.build.store')->middleware('auth');
});
