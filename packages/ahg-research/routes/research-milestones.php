<?php

/**
 * Research Milestones & Deliverables tracker routes - Heratio ahg-research
 * (heratio#1222, Research OS).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * Self-contained: this file declares its own research-prefixed, web+auth group
 * and is loaded directly by the service provider. All names are
 * research.milestones.* and all project-scoped paths sit under
 * /research/projects/{projectId}/milestones, so they are matched before the IO
 * slug catch-all (which excludes 'research'). The machine-readable export lives
 * at a multi-segment .json path under the same prefix, so it is never intercepted
 * by the single-segment slug route.
 *
 * This is the PLAN register (planned milestones and deliverables, due dates,
 * status, progress), distinct from the Research Outputs register.
 */

use AhgResearch\Controllers\ResearchMilestoneController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Per-project milestones & deliverables tracker.
    Route::get('/projects/{projectId}/milestones', [ResearchMilestoneController::class, 'index'])
        ->name('milestones.index')->where('projectId', '[0-9]+');

    Route::get('/projects/{projectId}/milestones/create', [ResearchMilestoneController::class, 'create'])
        ->name('milestones.create')->where('projectId', '[0-9]+');

    Route::post('/projects/{projectId}/milestones', [ResearchMilestoneController::class, 'store'])
        ->name('milestones.store')->where('projectId', '[0-9]+');

    // Machine-readable export of the project's plan. Multi-segment .json path -
    // catch-all-safe.
    Route::get('/projects/{projectId}/milestones/export.json', [ResearchMilestoneController::class, 'exportJson'])
        ->name('milestones.export')->where('projectId', '[0-9]+');

    Route::get('/projects/{projectId}/milestones/{milestoneId}/edit', [ResearchMilestoneController::class, 'edit'])
        ->name('milestones.edit')->where(['projectId' => '[0-9]+', 'milestoneId' => '[0-9]+']);

    Route::match(['put', 'patch'], '/projects/{projectId}/milestones/{milestoneId}', [ResearchMilestoneController::class, 'update'])
        ->name('milestones.update')->where(['projectId' => '[0-9]+', 'milestoneId' => '[0-9]+']);

    Route::delete('/projects/{projectId}/milestones/{milestoneId}', [ResearchMilestoneController::class, 'destroy'])
        ->name('milestones.destroy')->where(['projectId' => '[0-9]+', 'milestoneId' => '[0-9]+']);

    Route::get('/projects/{projectId}/milestones/{milestoneId}', [ResearchMilestoneController::class, 'show'])
        ->name('milestones.show')->where(['projectId' => '[0-9]+', 'milestoneId' => '[0-9]+']);
});
