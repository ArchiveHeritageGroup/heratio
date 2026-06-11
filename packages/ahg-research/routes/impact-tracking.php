<?php

/**
 * Impact Tracking routes - Heratio ahg-research
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * heratio#1241 - Research OS #19 (moonshot 25): Impact Tracking.
 *
 * Per-project tracking of the downstream citations, mentions and dataset reuse
 * of a project's PUBLISHED outputs, fed by the scheduled
 * ahg:research-impact-refresh command (which polls the PUBLIC OpenAlex and
 * Crossref Event Data APIs directly, never the AI gateway).
 *
 * Self-contained: this file declares its own
 * prefix('research')->name('research.')->middleware(['web','auth']) group so it
 * can be loaded plainly from the service provider alongside the other ROS slice
 * route files. Names live under research.impact.* and every path is
 * /research/projects/{projectId}/impact/... (three+ segments) so the global
 * /{slug} catch-all in ahg-information-object-manage never intercepts them.
 */

use AhgResearch\Controllers\ImpactTrackingController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    Route::prefix('projects/{projectId}/impact')
        ->name('impact.')
        ->whereNumber('projectId')
        ->group(function () {

            // Impact panel: citation count, citing works, mentions, dataset reuse.
            Route::get('/', [ImpactTrackingController::class, 'index'])->name('index');

            // Manual refresh: scan the project's published outputs now.
            Route::match(['post', 'patch'], '/refresh', [ImpactTrackingController::class, 'refresh'])->name('refresh');
        });
});
