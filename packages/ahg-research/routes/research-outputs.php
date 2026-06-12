<?php

/**
 * Research Outputs register routes - Heratio ahg-research (heratio#1222, Research OS).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * Self-contained: this file declares its own research-prefixed, web+auth group
 * and is loaded directly by the service provider. All names are
 * research.outputs.* and all project-scoped paths sit under
 * /research/projects/{projectId}/outputs, so they are matched before the IO slug
 * catch-all (which excludes 'research'). The machine-readable export lives at a
 * multi-segment .json path under the same prefix, so it is never intercepted by
 * the single-segment slug route.
 */

use AhgResearch\Controllers\ResearchOutputController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Per-project research outputs register.
    Route::get('/projects/{projectId}/outputs', [ResearchOutputController::class, 'index'])
        ->name('outputs.index')->where('projectId', '[0-9]+');

    Route::get('/projects/{projectId}/outputs/create', [ResearchOutputController::class, 'create'])
        ->name('outputs.create')->where('projectId', '[0-9]+');

    Route::post('/projects/{projectId}/outputs', [ResearchOutputController::class, 'store'])
        ->name('outputs.store')->where('projectId', '[0-9]+');

    // Machine-readable export of the project's outputs. Multi-segment .json path
    // - catch-all-safe.
    Route::get('/projects/{projectId}/outputs/export.json', [ResearchOutputController::class, 'exportJson'])
        ->name('outputs.export')->where('projectId', '[0-9]+');

    Route::get('/projects/{projectId}/outputs/{outputId}/edit', [ResearchOutputController::class, 'edit'])
        ->name('outputs.edit')->where(['projectId' => '[0-9]+', 'outputId' => '[0-9]+']);

    Route::match(['put', 'patch'], '/projects/{projectId}/outputs/{outputId}', [ResearchOutputController::class, 'update'])
        ->name('outputs.update')->where(['projectId' => '[0-9]+', 'outputId' => '[0-9]+']);

    Route::delete('/projects/{projectId}/outputs/{outputId}', [ResearchOutputController::class, 'destroy'])
        ->name('outputs.destroy')->where(['projectId' => '[0-9]+', 'outputId' => '[0-9]+']);

    Route::get('/projects/{projectId}/outputs/{outputId}', [ResearchOutputController::class, 'show'])
        ->name('outputs.show')->where(['projectId' => '[0-9]+', 'outputId' => '[0-9]+']);
});
