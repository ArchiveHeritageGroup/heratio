<?php

/**
 * Research Decision Log routes - Heratio ahg-research
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * heratio#1224 - Research OS Stage 9: the per-project Decision Log (the recorded
 * memory of every loop). Kept in a separate file (loaded from the research
 * ServiceProvider) to avoid heavy edits to the shared routes/web.php.
 *
 * All paths are two-segment-or-deeper and live under /research/projects/{id}/...
 * so the global /{slug} catch-all never intercepts them. Names live under the
 * 'research.' group as research.decisions.*.
 */

use AhgResearch\Controllers\DecisionLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    Route::prefix('projects/{projectId}/decisions')
        ->name('decisions.')
        ->whereNumber('projectId')
        ->group(function () {
            // Timeline (per-project Decision Log)
            Route::get('/', [DecisionLogController::class, 'index'])->name('index');

            // Create
            Route::get('/add', [DecisionLogController::class, 'create'])->name('create');
            Route::post('/add', [DecisionLogController::class, 'store'])->name('store');

            // Edit
            Route::get('/{id}/edit', [DecisionLogController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('/{id}', [DecisionLogController::class, 'update'])->whereNumber('id')->name('update');
            Route::match(['post', 'patch'], '/{id}/edit', [DecisionLogController::class, 'update'])->whereNumber('id')->name('update.post');

            // Delete
            Route::match(['post', 'delete'], '/{id}/delete', [DecisionLogController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });
});
