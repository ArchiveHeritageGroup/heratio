<?php

use AhgRic\Controllers\RicController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/ric', [RicController::class, 'index'])->name('ric.index');
    Route::get('/admin/ric/sync-status', [RicController::class, 'syncStatus'])->name('ric.sync-status');
    Route::get('/admin/ric/orphans', [RicController::class, 'orphans'])->name('ric.orphans');
    Route::get('/admin/ric/queue', [RicController::class, 'queue'])->name('ric.queue');
    Route::get('/admin/ric/logs', [RicController::class, 'logs'])->name('ric.logs');
});
