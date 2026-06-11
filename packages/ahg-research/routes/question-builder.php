<?php

/**
 * Question Builder routes - Heratio ahg-research
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * heratio#1226 - Research OS #4: Question Builder (ROS Stage 2, epic #1222).
 *
 * Per-project Research Design Brief routes. Loaded from
 * AhgResearchServiceProvider alongside routes/web.php. Names live under the
 * `research.` prefix (e.g. research.question.builder) and every path is
 * two-segment or deeper so the /{slug} catch-all never intercepts them.
 */

use AhgResearch\Controllers\QuestionBuilderController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware('auth')->group(function () {

    // heratio#1226 - Research OS #4: Question Builder (per-project, versioned brief).
    Route::prefix('question-builder')->name('question.')->group(function () {
        // /research/question-builder/{projectId}            -> builder + diagnosis
        Route::get('/{projectId}', [QuestionBuilderController::class, 'builder'])
            ->whereNumber('projectId')->name('builder');

        // POST save (appends a new version with a change reason)
        Route::post('/{projectId}', [QuestionBuilderController::class, 'save'])
            ->whereNumber('projectId')->name('save');

        // Version history
        Route::get('/{projectId}/history', [QuestionBuilderController::class, 'history'])
            ->whereNumber('projectId')->name('history');

        // AJAX live diagnosis (heuristic + optional gateway-backed AI note)
        Route::post('/{projectId}/diagnose', [QuestionBuilderController::class, 'diagnose'])
            ->whereNumber('projectId')->name('diagnose');
    });
});
