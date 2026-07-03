<?php

use AhgResearch\Controllers\ResearchOfflineController;
use Illuminate\Support\Facades\Route;

/**
 * Researcher offline packages (Phase 1–2). Self-contained route group loaded by
 * AhgResearchServiceProvider alongside the other Research OS slices.
 */
Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {
    Route::prefix('offline')->name('offline.')->group(function () {
        Route::get('/', [ResearchOfflineController::class, 'index'])->name('index');

        Route::post('/take/{source}/{id}', [ResearchOfflineController::class, 'take'])
            ->whereNumber('id')
            ->whereIn('source', ['project', 'collection', 'workspace', 'favorites'])
            ->name('take');

        Route::get('/{id}/status', [ResearchOfflineController::class, 'status'])->whereNumber('id')->name('status');
        Route::get('/{id}/download', [ResearchOfflineController::class, 'download'])->whereNumber('id')->name('download');
    });
});
