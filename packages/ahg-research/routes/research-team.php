<?php

/**
 * Research Team & Collaborators register routes - Heratio ahg-research
 * (heratio#1222, Research OS).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * Self-contained: this file declares its own research-prefixed, web+auth group
 * and is loaded directly by the service provider. All names are research.team.*
 * and all project-scoped paths sit under
 * /research/projects/{projectId}/team, so they are matched before the IO slug
 * catch-all (which excludes 'research'). The machine-readable export lives at a
 * multi-segment .json path under the same prefix, so it is never intercepted by
 * the single-segment slug route.
 *
 * This is the broader CONTRIBUTOR register (co-investigators, students,
 * partners, external collaborators), distinct from the project's single owner.
 */

use AhgResearch\Controllers\ResearchTeamController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Per-project research team register.
    Route::get('/projects/{projectId}/team', [ResearchTeamController::class, 'index'])
        ->name('team.index')->where('projectId', '[0-9]+');

    Route::get('/projects/{projectId}/team/create', [ResearchTeamController::class, 'create'])
        ->name('team.create')->where('projectId', '[0-9]+');

    Route::post('/projects/{projectId}/team', [ResearchTeamController::class, 'store'])
        ->name('team.store')->where('projectId', '[0-9]+');

    // Machine-readable export of the project's team. Multi-segment .json path -
    // catch-all-safe.
    Route::get('/projects/{projectId}/team/export.json', [ResearchTeamController::class, 'exportJson'])
        ->name('team.export')->where('projectId', '[0-9]+');

    Route::get('/projects/{projectId}/team/{memberId}/edit', [ResearchTeamController::class, 'edit'])
        ->name('team.edit')->where(['projectId' => '[0-9]+', 'memberId' => '[0-9]+']);

    Route::match(['put', 'patch'], '/projects/{projectId}/team/{memberId}', [ResearchTeamController::class, 'update'])
        ->name('team.update')->where(['projectId' => '[0-9]+', 'memberId' => '[0-9]+']);

    Route::delete('/projects/{projectId}/team/{memberId}', [ResearchTeamController::class, 'destroy'])
        ->name('team.destroy')->where(['projectId' => '[0-9]+', 'memberId' => '[0-9]+']);

    Route::get('/projects/{projectId}/team/{memberId}', [ResearchTeamController::class, 'show'])
        ->name('team.show')->where(['projectId' => '[0-9]+', 'memberId' => '[0-9]+']);
});
