<?php

// Physical stocktake (#1474 item 5). Counting is staff work; signing off and
// posting to the asset register are administrator actions.

use AhgLibrary\Controllers\StocktakeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'acl:update'])->prefix('library-manage/stocktake')->group(function () {
    Route::get('/', [StocktakeController::class, 'index'])->name('library.stocktake.index');
    Route::post('/', [StocktakeController::class, 'store'])->name('library.stocktake.store');
    Route::get('/{id}', [StocktakeController::class, 'show'])->whereNumber('id')->name('library.stocktake.show');
    Route::get('/{id}/export', [StocktakeController::class, 'export'])->whereNumber('id')->name('library.stocktake.export');
    Route::post('/{id}/scan', [StocktakeController::class, 'scan'])->whereNumber('id')->name('library.stocktake.scan');
    Route::post('/{id}/reconcile', [StocktakeController::class, 'reconcile'])->whereNumber('id')->name('library.stocktake.reconcile');

    Route::middleware('admin')->group(function () {
        Route::post('/{id}/sign-off', [StocktakeController::class, 'signOff'])->whereNumber('id')->name('library.stocktake.sign-off');
        Route::post('/{id}/lines/{lineId}/impairment', [StocktakeController::class, 'postImpairment'])
            ->whereNumber('id')->whereNumber('lineId')->name('library.stocktake.post-impairment');
    });
});
