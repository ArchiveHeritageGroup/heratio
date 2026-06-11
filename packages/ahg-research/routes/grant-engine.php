<?php

/**
 * Grant Engine routes - Heratio ahg-research (heratio#1239, Research OS #17, moonshot 24).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * Self-contained: this file declares its own research-prefixed, web+auth group
 * and is loaded directly by the service provider. All names are research.grant.*
 * and all project-scoped paths sit under /research/projects/{projectId}/grant,
 * so they are matched before the IO slug catch-all (which excludes 'research').
 */

use AhgResearch\Controllers\GrantEngineController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Funder template gallery (not project-scoped). ?project={id} threads a
    // project through so "use this template" can jump straight into a project.
    Route::get('/grant/templates', [GrantEngineController::class, 'templates'])
        ->name('grant.templates');

    // Per-project grant drafts.
    Route::get('/projects/{projectId}/grant', [GrantEngineController::class, 'index'])
        ->name('grant.index')->where('projectId', '[0-9]+');

    Route::post('/projects/{projectId}/grant', [GrantEngineController::class, 'store'])
        ->name('grant.store')->where('projectId', '[0-9]+');

    Route::get('/projects/{projectId}/grant/{draftId}/edit', [GrantEngineController::class, 'edit'])
        ->name('grant.edit')->where(['projectId' => '[0-9]+', 'draftId' => '[0-9]+']);

    Route::match(['put', 'patch', 'post'], '/projects/{projectId}/grant/{draftId}', [GrantEngineController::class, 'update'])
        ->name('grant.update')->where(['projectId' => '[0-9]+', 'draftId' => '[0-9]+']);

    Route::get('/projects/{projectId}/grant/{draftId}', [GrantEngineController::class, 'show'])
        ->name('grant.show')->where(['projectId' => '[0-9]+', 'draftId' => '[0-9]+']);

    // Optional AI drafting per section (gateway only, labelled, never submits).
    Route::post('/projects/{projectId}/grant/{draftId}/ai-draft', [GrantEngineController::class, 'aiDraft'])
        ->name('grant.ai-draft')->where(['projectId' => '[0-9]+', 'draftId' => '[0-9]+']);

    // Tracked funder calls / opportunities.
    Route::get('/projects/{projectId}/grant-calls', [GrantEngineController::class, 'calls'])
        ->name('grant.calls')->where('projectId', '[0-9]+');

    Route::post('/projects/{projectId}/grant-calls', [GrantEngineController::class, 'storeCall'])
        ->name('grant.calls.store')->where('projectId', '[0-9]+');

    Route::match(['put', 'patch', 'post'], '/projects/{projectId}/grant-calls/{callId}', [GrantEngineController::class, 'updateCall'])
        ->name('grant.calls.update')->where(['projectId' => '[0-9]+', 'callId' => '[0-9]+']);

    Route::delete('/projects/{projectId}/grant-calls/{callId}', [GrantEngineController::class, 'destroyCall'])
        ->name('grant.calls.destroy')->where(['projectId' => '[0-9]+', 'callId' => '[0-9]+']);
});
