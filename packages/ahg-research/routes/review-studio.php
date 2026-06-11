<?php

/**
 * Review Studio routes - Research OS Stage 14 (heratio#1230, epic #1222).
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
 * shared routes/web.php does not need to be edited. All names live under the
 * existing `research.` namespace as `research.review.*`. Every path is
 * three-segment or deeper (/research/projects/{projectId}/review/...), so the
 * locked /{slug} catch-all never intercepts these URLs.
 */

use AhgResearch\Controllers\ReviewStudioController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    Route::prefix('projects/{projectId}/review')
        ->name('review.')
        ->whereNumber('projectId')
        ->group(function () {

            // Studio landing: comment panel + reviewer-twin panel + run history.
            Route::get('/', [ReviewStudioController::class, 'index'])->name('index');

            // Supervisor / co-author comment threads (work fully without AI).
            Route::post('/comments', [ReviewStudioController::class, 'storeComment'])->name('comments.store');
            Route::post('/comments/{commentId}/resolve', [ReviewStudioController::class, 'resolveComment'])
                ->whereNumber('commentId')->name('comments.resolve');
            Route::post('/comments/{commentId}/delete', [ReviewStudioController::class, 'destroyComment'])
                ->whereNumber('commentId')->name('comments.destroy');

            // Adversarial reviewer-twin simulation (AHG gateway, always labelled).
            Route::post('/run', [ReviewStudioController::class, 'runReviewer'])->name('run');
            Route::get('/runs/{runId}', [ReviewStudioController::class, 'showRun'])
                ->whereNumber('runId')->name('runs.show');
            Route::post('/runs/{runId}/delete', [ReviewStudioController::class, 'destroyRun'])
                ->whereNumber('runId')->name('runs.destroy');
        });
});

// Route names produced:
//   research.review.index
//   research.review.comments.store, research.review.comments.resolve, research.review.comments.destroy
//   research.review.run, research.review.runs.show, research.review.runs.destroy
