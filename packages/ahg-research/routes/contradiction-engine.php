<?php

/**
 * Contradiction Engine routes - Research OS moonshot 17 (heratio#1236).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify it under the
 * terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version. See <https://www.gnu.org/licenses/>.
 *
 * Self-contained: this file carries its own
 * prefix('research')->name('research.')->middleware(['web','auth']) group so the
 * shared routes/web.php is never edited. All names live under the existing
 * `research.` namespace. Every path is three segments or deeper
 * (research/projects/{id}/contradictions/...), so the locked /{slug} catch-all
 * in ahg-information-object-manage never intercepts these URLs.
 */

use AhgResearch\Controllers\ContradictionEngineController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Per-project Contradiction Engine over the Claim Ledger.
    Route::prefix('projects/{projectId}/contradictions')
        ->name('contradictions.')
        ->whereNumber('projectId')
        ->group(function () {
            // Report
            Route::get('/', [ContradictionEngineController::class, 'index'])->name('index');

            // Scans
            Route::post('/scan', [ContradictionEngineController::class, 'scan'])->name('scan');
            Route::post('/ai-scan', [ContradictionEngineController::class, 'aiScan'])->name('aiScan');

            // Per-finding lifecycle
            Route::post('/{findingId}/dismiss', [ContradictionEngineController::class, 'dismiss'])->whereNumber('findingId')->name('dismiss');
            Route::post('/{findingId}/resolve', [ContradictionEngineController::class, 'resolve'])->whereNumber('findingId')->name('resolve');
            Route::post('/{findingId}/reopen', [ContradictionEngineController::class, 'reopen'])->whereNumber('findingId')->name('reopen');
        });
});

// Route names produced:
//   research.contradictions.index   GET    /research/projects/{projectId}/contradictions
//   research.contradictions.scan    POST   /research/projects/{projectId}/contradictions/scan
//   research.contradictions.aiScan  POST   /research/projects/{projectId}/contradictions/ai-scan
//   research.contradictions.dismiss POST   /research/projects/{projectId}/contradictions/{findingId}/dismiss
//   research.contradictions.resolve POST   /research/projects/{projectId}/contradictions/{findingId}/resolve
//   research.contradictions.reopen  POST   /research/projects/{projectId}/contradictions/{findingId}/reopen
