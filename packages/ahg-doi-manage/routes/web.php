<?php

use AhgDoiManage\Controllers\DoiController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/doi', [DoiController::class, 'index'])->name('doi.index');
    Route::get('/admin/doi/browse', [DoiController::class, 'browse'])->name('doi.browse');
    Route::get('/admin/doi/queue', [DoiController::class, 'queue'])->name('doi.queue');
    Route::get('/admin/doi/view/{id}', [DoiController::class, 'view'])->name('doi.view')->whereNumber('id');
    Route::get('/admin/doi/config', [DoiController::class, 'config'])->name('doi.config');
    Route::post('/admin/doi/config', [DoiController::class, 'configSave'])->name('doi.configSave');
    Route::get('/admin/doi/report', [DoiController::class, 'report'])->name('doi.report');
    Route::match(['get','post'], '/admin/doi/batch-mint', [DoiController::class, 'batchMint'])->name('doi.batch-mint');
    Route::post('/admin/doi/{id}/deactivate', [DoiController::class, 'deactivate'])->name('doi.deactivate')->whereNumber('id');
    Route::post('/admin/doi/{id}/mint', [DoiController::class, 'mint'])->name('doi.mint')->whereNumber('id');
    Route::post('/admin/doi/sync', [DoiController::class, 'sync'])->name('doi.sync');
});
