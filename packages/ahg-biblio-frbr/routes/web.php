<?php

use AhgBiblioFrbr\Controllers\FrbrController;
use AhgBiblioFrbr\Controllers\WorkClusterController;
use AhgBiblioFrbr\Controllers\WorkOverrideController;
use Illuminate\Support\Facades\Route;

// #763 FRBR cluster expander - public page that lists all editions sharing
// a work-key. Linked from the GLAM browse hit-list once the renderer-side
// integration in locked ahg-display is applied (see docs/reference/frbr-cluster-renderer-integration.md).
Route::get('/library/work-cluster/{workKey}', [WorkClusterController::class, 'show'])
    ->name('library.work-cluster.show')
    ->where('workKey', '[a-zA-Z0-9:_-]{4,64}');

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

        // #763 FRBR work-set clustering: force-group / force-split admin.
        Route::get('/admin/frbr/overrides', [WorkOverrideController::class, 'index'])->name('admin.frbr.overrides.index');
        Route::get('/admin/frbr/overrides/create', [WorkOverrideController::class, 'create'])->name('admin.frbr.overrides.create');
        Route::post('/admin/frbr/overrides', [WorkOverrideController::class, 'store'])->name('admin.frbr.overrides.store');
        Route::delete('/admin/frbr/overrides/{id}', [WorkOverrideController::class, 'destroy'])->name('admin.frbr.overrides.destroy')->whereNumber('id');
        Route::post('/admin/frbr/cluster', [WorkOverrideController::class, 'cluster'])->name('admin.frbr.overrides.cluster');
    });
});
