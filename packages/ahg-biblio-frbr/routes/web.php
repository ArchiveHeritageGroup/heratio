<?php

use AhgBiblioFrbr\Controllers\FrbrController;
use Illuminate\Support\Facades\Route;

// FRBR integration routes.
// Converts Heratio bibliographic catalogue records to/from the IFLA FRBR
// conceptual model (Work, Expression, Item, Manifestation) via OpenRiC.

Route::middleware('web')->group(function () {
    // Public read
    Route::get('/frbr', [FrbrController::class, 'index'])->name('frbr.index');
    Route::get('/frbr/{workId}', [FrbrController::class, 'show'])->name('frbr.show')
        ->where('workId', '[0-9]+');

    // Auth-gated management
    Route::middleware('auth')->group(function () {
        Route::get('/frbr/export', [FrbrController::class, 'export'])->name('frbr.export');
        Route::post('/frbr/export', [FrbrController::class, 'exportRun'])->name('frbr.export-run');

        Route::get('/frbr/import', [FrbrController::class, 'import'])->name('frbr.import');
        Route::post('/frbr/import', [FrbrController::class, 'importRun'])->name('frbr.import-run');

        Route::get('/frbr/validate', [FrbrController::class, 'validate'])->name('frbr.validate');
        Route::post('/frbr/validate', [FrbrController::class, 'validateRun'])->name('frbr.validate-run');

        Route::get('/frbr/agent', [FrbrController::class, 'agent'])->name('frbr.agent');
    });
});
