<?php

/**
 * Data Management Plan (DMP) Builder routes - Heratio ahg-research (heratio#1222, Research OS).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * Self-contained: this file declares its own research-prefixed, web+auth group
 * and is loaded directly by the service provider. All names are research.dmp.*
 * and all project-scoped paths sit under /research/projects/{projectId}/dmp, so
 * they are matched before the IO slug catch-all (which excludes 'research').
 * The machine-readable export lives at a multi-segment .json path under the same
 * prefix, so it is never intercepted by the single-segment slug route.
 */

use AhgResearch\Controllers\DmpController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Per-project data management plans.
    Route::get('/projects/{projectId}/dmp', [DmpController::class, 'index'])
        ->name('dmp.index')->where('projectId', '[0-9]+');

    Route::post('/projects/{projectId}/dmp', [DmpController::class, 'store'])
        ->name('dmp.store')->where('projectId', '[0-9]+');

    Route::get('/projects/{projectId}/dmp/{dmpId}/edit', [DmpController::class, 'edit'])
        ->name('dmp.edit')->where(['projectId' => '[0-9]+', 'dmpId' => '[0-9]+']);

    Route::match(['put', 'patch', 'post'], '/projects/{projectId}/dmp/{dmpId}', [DmpController::class, 'update'])
        ->name('dmp.update')->where(['projectId' => '[0-9]+', 'dmpId' => '[0-9]+']);

    // Machine-readable maDMP export (RDA / Science Europe aligned). Multi-segment
    // .json path - catch-all-safe.
    Route::get('/projects/{projectId}/dmp/{dmpId}/madmp.json', [DmpController::class, 'exportJson'])
        ->name('dmp.export')->where(['projectId' => '[0-9]+', 'dmpId' => '[0-9]+']);

    Route::delete('/projects/{projectId}/dmp/{dmpId}', [DmpController::class, 'destroy'])
        ->name('dmp.destroy')->where(['projectId' => '[0-9]+', 'dmpId' => '[0-9]+']);

    Route::get('/projects/{projectId}/dmp/{dmpId}', [DmpController::class, 'show'])
        ->name('dmp.show')->where(['projectId' => '[0-9]+', 'dmpId' => '[0-9]+']);
});
