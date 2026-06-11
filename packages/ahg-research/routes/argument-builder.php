<?php

/**
 * Argument Builder routes - Research OS Stage 12 (heratio#1229).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify it under the
 * terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version. See <https://www.gnu.org/licenses/>.
 *
 * Self-contained: this file declares its own
 * prefix('research')->name('research.')->middleware(['web','auth']) group so the
 * shared routes/web.php is never edited. All names live under the existing
 * `research.` namespace as `research.argument.*`. Every path is
 * /research/projects/{projectId}/argument/... - two-plus segments deep - so the
 * locked /{slug} catch-all never intercepts these URLs.
 */

use AhgResearch\Controllers\ArgumentBuilderController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Per-project Argument Builder (one argument per project).
    Route::prefix('projects/{projectId}/argument')->name('argument.')->whereNumber('projectId')->group(function () {
        // Canvas + warnings panel.
        Route::get('/', [ArgumentBuilderController::class, 'show'])->name('show');

        // Argument header (title + central thesis).
        Route::post('/', [ArgumentBuilderController::class, 'update'])->name('update');

        // Steps.
        Route::post('/steps', [ArgumentBuilderController::class, 'addStep'])->name('steps.add');
        Route::post('/steps/reorder', [ArgumentBuilderController::class, 'reorder'])->name('steps.reorder');
        Route::post('/steps/{stepId}/claim', [ArgumentBuilderController::class, 'attachClaim'])->whereNumber('stepId')->name('steps.claim');
        Route::post('/steps/{stepId}/note', [ArgumentBuilderController::class, 'updateStep'])->whereNumber('stepId')->name('steps.note');
        Route::post('/steps/{stepId}/delete', [ArgumentBuilderController::class, 'removeStep'])->whereNumber('stepId')->name('steps.delete');
    });
});

// Route names produced:
//   research.argument.show, research.argument.update,
//   research.argument.steps.add, research.argument.steps.reorder,
//   research.argument.steps.claim, research.argument.steps.note,
//   research.argument.steps.delete
