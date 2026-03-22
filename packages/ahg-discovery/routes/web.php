<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/discovery')->middleware(['web'])->group(function () {
    Route::get('/index', [\AhgDiscovery\Controllers\DiscoveryController::class, 'index'])->name('ahgdiscovery.index');
});
