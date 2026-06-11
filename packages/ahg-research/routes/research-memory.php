<?php

/**
 * Research Memory routes - Heratio ahg-research
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * heratio#1233 - Research OS Stage 16: Research Memory. Retains the researcher's
 * intellectual memory after a project so the next one starts smarter. Kept in a
 * separate, self-contained file (loaded from the research ServiceProvider) so
 * the shared routes/web.php stays untouched.
 *
 * All per-project paths are two-segments-or-deeper under
 * /research/projects/{projectId}/memory/... and the cross-project pool lives at
 * /research/memory/carry-forward, so the global /{slug} catch-all never
 * intercepts them. Names live under the 'research.' group as research.memory.*.
 */

use AhgResearch\Controllers\ResearchMemoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Cross-project carry-forward pool (placed before the {projectId} group so
    // the literal 'carry-forward' segment is unambiguous; it lives under
    // /research/memory/... which is distinct from the per-project prefix below).
    Route::get('memory/carry-forward', [ResearchMemoryController::class, 'carryForward'])
        ->name('memory.carryForward');

    // Per-project Memory.
    Route::prefix('projects/{projectId}/memory')
        ->name('memory.')
        ->whereNumber('projectId')
        ->group(function () {
            // Memory view (curated items grouped by kind + read-only suggestions)
            Route::get('/', [ResearchMemoryController::class, 'index'])->name('index');

            // Accept a read-only suggestion into memory (the only write a
            // suggestion produces).
            Route::post('/accept', [ResearchMemoryController::class, 'accept'])->name('accept');

            // Create
            Route::get('/add', [ResearchMemoryController::class, 'create'])->name('create');
            Route::post('/add', [ResearchMemoryController::class, 'store'])->name('store');

            // Edit
            Route::get('/{id}/edit', [ResearchMemoryController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('/{id}', [ResearchMemoryController::class, 'update'])->whereNumber('id')->name('update');
            Route::match(['post', 'patch'], '/{id}/edit', [ResearchMemoryController::class, 'update'])->whereNumber('id')->name('update.post');

            // Quick status change (carry forward / done / dropped / reopen)
            Route::match(['post', 'patch'], '/{id}/status', [ResearchMemoryController::class, 'status'])->whereNumber('id')->name('status');

            // Delete
            Route::match(['post', 'delete'], '/{id}/delete', [ResearchMemoryController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });
});
