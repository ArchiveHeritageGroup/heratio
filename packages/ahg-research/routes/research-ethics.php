<?php

/**
 * Research Ethics & Consent register routes - Heratio ahg-research (heratio#1222, Research OS).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * Self-contained: this file declares its own research-prefixed, web+auth group
 * and is loaded directly by the service provider. All names are
 * research.ethics.* and all project-scoped paths sit under
 * /research/projects/{projectId}/ethics, so they are matched before the IO slug
 * catch-all (which excludes 'research'). The machine-readable export lives at a
 * multi-segment .json path under the same prefix, so it is never intercepted by
 * the single-segment slug route.
 */

use AhgResearch\Controllers\ResearchEthicsController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Per-project research ethics & consent register.
    Route::get('/projects/{projectId}/ethics', [ResearchEthicsController::class, 'index'])
        ->name('ethics.index')->where('projectId', '[0-9]+');

    Route::get('/projects/{projectId}/ethics/create', [ResearchEthicsController::class, 'create'])
        ->name('ethics.create')->where('projectId', '[0-9]+');

    Route::post('/projects/{projectId}/ethics', [ResearchEthicsController::class, 'store'])
        ->name('ethics.store')->where('projectId', '[0-9]+');

    // Machine-readable export of the project's ethics records. Multi-segment
    // .json path - catch-all-safe.
    Route::get('/projects/{projectId}/ethics/export.json', [ResearchEthicsController::class, 'exportJson'])
        ->name('ethics.export')->where('projectId', '[0-9]+');

    Route::get('/projects/{projectId}/ethics/{ethicsId}/edit', [ResearchEthicsController::class, 'edit'])
        ->name('ethics.edit')->where(['projectId' => '[0-9]+', 'ethicsId' => '[0-9]+']);

    Route::match(['put', 'patch'], '/projects/{projectId}/ethics/{ethicsId}', [ResearchEthicsController::class, 'update'])
        ->name('ethics.update')->where(['projectId' => '[0-9]+', 'ethicsId' => '[0-9]+']);

    Route::delete('/projects/{projectId}/ethics/{ethicsId}', [ResearchEthicsController::class, 'destroy'])
        ->name('ethics.destroy')->where(['projectId' => '[0-9]+', 'ethicsId' => '[0-9]+']);

    Route::get('/projects/{projectId}/ethics/{ethicsId}', [ResearchEthicsController::class, 'show'])
        ->name('ethics.show')->where(['projectId' => '[0-9]+', 'ethicsId' => '[0-9]+']);
});
