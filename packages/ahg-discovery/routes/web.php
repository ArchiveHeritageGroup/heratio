<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/discovery')->middleware(['web', 'auth'])->group(function () {
    Route::get('/', [\AhgDiscovery\Controllers\DiscoveryController::class, 'index'])->name('ahgdiscovery.index');
    Route::get('/index', [\AhgDiscovery\Controllers\DiscoveryController::class, 'index']);
    Route::get('/suggest', [\AhgDiscovery\Controllers\DiscoveryController::class, 'suggest'])->name('ahgdiscovery.suggest');
});
