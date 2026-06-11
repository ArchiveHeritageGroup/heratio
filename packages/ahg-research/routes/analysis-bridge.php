<?php

/**
 * Analysis Bridge routes - Research OS Stage 11 (heratio#1234).
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
 * shared routes/web.php never needs editing. All names live under the existing
 * `research.` namespace. Every path is three-or-more segments deep
 * (/research/projects/{projectId}/analysis/...), so the locked /{slug} catch-all
 * never intercepts these URLs.
 */

use AhgResearch\Controllers\AnalysisBridgeController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    Route::prefix('projects/{projectId}/analysis')
        ->name('analysis.')
        ->whereNumber('projectId')
        ->group(function () {

            // Results register + result CRUD
            Route::get('/', [AnalysisBridgeController::class, 'index'])->name('index');
            Route::post('/', [AnalysisBridgeController::class, 'store'])->name('store');

            // Light thematic-coding tags + memos (project-level)
            Route::post('/codes', [AnalysisBridgeController::class, 'addCode'])->name('codes.add');
            Route::post('/codes/{codeId}/delete', [AnalysisBridgeController::class, 'deleteCode'])
                ->whereNumber('codeId')->name('codes.delete');

            // Per-result detail + provenance + claim links
            Route::get('/{resultId}', [AnalysisBridgeController::class, 'show'])
                ->whereNumber('resultId')->name('show');
            Route::post('/{resultId}', [AnalysisBridgeController::class, 'update'])
                ->whereNumber('resultId')->name('update');
            Route::post('/{resultId}/delete', [AnalysisBridgeController::class, 'destroy'])
                ->whereNumber('resultId')->name('destroy');
            Route::get('/{resultId}/artifact', [AnalysisBridgeController::class, 'downloadArtifact'])
                ->whereNumber('resultId')->name('artifact');

            // Result <-> claim links
            Route::post('/{resultId}/link', [AnalysisBridgeController::class, 'linkClaim'])
                ->whereNumber('resultId')->name('link');
            Route::post('/{resultId}/link/{linkId}/remove', [AnalysisBridgeController::class, 'unlinkClaim'])
                ->whereNumber('resultId')->whereNumber('linkId')->name('unlink');
        });
});

// Route names produced:
//   research.analysis.index, research.analysis.store,
//   research.analysis.codes.add, research.analysis.codes.delete,
//   research.analysis.show, research.analysis.update, research.analysis.destroy,
//   research.analysis.artifact, research.analysis.link, research.analysis.unlink
