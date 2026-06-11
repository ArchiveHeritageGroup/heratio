<?php

/**
 * Source Triage routes - Heratio ahg-research
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * heratio#1227 - Research OS Stage 5: per-project Source Triage board. All paths are two
 * segments or more under /research/projects/{projectId}/triage so the /{slug} catch-all in
 * ahg-information-object-manage never intercepts them. Loaded from AhgResearchServiceProvider.
 */

use AhgResearch\Controllers\SourceTriageController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {
    Route::prefix('projects/{projectId}/triage')
        ->whereNumber('projectId')
        ->name('triage.')
        ->group(function () {
            Route::get('/', [SourceTriageController::class, 'index'])->name('index');
            Route::post('/category', [SourceTriageController::class, 'setCategory'])->name('category');
            Route::post('/read-status', [SourceTriageController::class, 'setReadStatus'])->name('readStatus');
            Route::post('/notes', [SourceTriageController::class, 'setNotes'])->name('notes');
            Route::post('/ai-preview', [SourceTriageController::class, 'aiPreview'])->name('aiPreview');
        });
});
