<?php

/**
 * Research Funding tracker routes - Heratio ahg-research (heratio#1222, Research OS).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * Self-contained: this file declares its own research-prefixed, web+auth group
 * and is loaded directly by the service provider. All names are
 * research.funding.* and all project-scoped paths sit under
 * /research/projects/{projectId}/funding, so they are matched before the IO slug
 * catch-all (which excludes 'research'). The machine-readable export lives at a
 * multi-segment .json path under the same prefix, so it is never intercepted by
 * the single-segment slug route.
 *
 * This is the AWARDED-FUNDING ledger, distinct from the grant-DRAFTING slice
 * (research.grant.*).
 */

use AhgResearch\Controllers\ResearchFundingController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Per-project research funding tracker.
    Route::get('/projects/{projectId}/funding', [ResearchFundingController::class, 'index'])
        ->name('funding.index')->where('projectId', '[0-9]+');

    Route::get('/projects/{projectId}/funding/create', [ResearchFundingController::class, 'create'])
        ->name('funding.create')->where('projectId', '[0-9]+');

    Route::post('/projects/{projectId}/funding', [ResearchFundingController::class, 'store'])
        ->name('funding.store')->where('projectId', '[0-9]+');

    // Machine-readable export of the project's funding records. Multi-segment
    // .json path - catch-all-safe.
    Route::get('/projects/{projectId}/funding/export.json', [ResearchFundingController::class, 'exportJson'])
        ->name('funding.export')->where('projectId', '[0-9]+');

    Route::get('/projects/{projectId}/funding/{fundingId}/edit', [ResearchFundingController::class, 'edit'])
        ->name('funding.edit')->where(['projectId' => '[0-9]+', 'fundingId' => '[0-9]+']);

    Route::match(['put', 'patch'], '/projects/{projectId}/funding/{fundingId}', [ResearchFundingController::class, 'update'])
        ->name('funding.update')->where(['projectId' => '[0-9]+', 'fundingId' => '[0-9]+']);

    Route::delete('/projects/{projectId}/funding/{fundingId}', [ResearchFundingController::class, 'destroy'])
        ->name('funding.destroy')->where(['projectId' => '[0-9]+', 'fundingId' => '[0-9]+']);

    Route::get('/projects/{projectId}/funding/{fundingId}', [ResearchFundingController::class, 'show'])
        ->name('funding.show')->where(['projectId' => '[0-9]+', 'fundingId' => '[0-9]+']);
});
