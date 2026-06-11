<?php

/**
 * Method Design Studio routes - Heratio ahg-research (heratio#1231, ROS Stage 10).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * Self-contained: this file declares its own research-prefixed, web+auth group
 * and is loaded directly by the service provider. All names are research.method.*
 * and all project-scoped paths sit under /research/projects/{projectId}/method,
 * so they are matched before the IO slug catch-all (which excludes 'research').
 */

use AhgResearch\Controllers\MethodStudioController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Discipline template gallery (not project-scoped). ?project={id} threads a
    // project through so "use this template" can jump straight into a project.
    Route::get('/method/templates', [MethodStudioController::class, 'templates'])
        ->name('method.templates');

    // Per-project Method Protocols.
    Route::get('/projects/{projectId}/method', [MethodStudioController::class, 'index'])
        ->name('method.index')->where('projectId', '[0-9]+');

    Route::post('/projects/{projectId}/method', [MethodStudioController::class, 'store'])
        ->name('method.store')->where('projectId', '[0-9]+');

    Route::get('/projects/{projectId}/method/{protocolId}/edit', [MethodStudioController::class, 'edit'])
        ->name('method.edit')->where(['projectId' => '[0-9]+', 'protocolId' => '[0-9]+']);

    Route::match(['put', 'patch', 'post'], '/projects/{projectId}/method/{protocolId}', [MethodStudioController::class, 'update'])
        ->name('method.update')->where(['projectId' => '[0-9]+', 'protocolId' => '[0-9]+']);

    Route::get('/projects/{projectId}/method/{protocolId}', [MethodStudioController::class, 'show'])
        ->name('method.show')->where(['projectId' => '[0-9]+', 'protocolId' => '[0-9]+']);

    // Reuse read endpoint: structured JSON for downstream consumers
    // (thesis methodology chapter, grant, ethics application).
    Route::get('/projects/{projectId}/method/{protocolId}/reuse', [MethodStudioController::class, 'reuse'])
        ->name('method.reuse')->where(['projectId' => '[0-9]+', 'protocolId' => '[0-9]+']);
});
