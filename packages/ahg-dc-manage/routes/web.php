<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/dc-manage')->middleware(['web'])->group(function () {
    Route::get('/edit', [\AhgDiscovery\Controllers\DiscoveryController::class, 'edit'])->name('ahgdcmanage.edit');
});
