<?php

/**
 * Time Machine routes - Research OS moonshot 19 (heratio#1240). The honesty engine.
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
 * (research/projects/{id}/timemachine/...), so the locked /{slug} catch-all in
 * ahg-information-object-manage never intercepts these URLs.
 */

use AhgResearch\Controllers\TimeMachineController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Per-project Time Machine: read-only reconstruction of how the research developed.
    Route::prefix('projects/{projectId}/timemachine')
        ->name('timemachine.')
        ->whereNumber('projectId')
        ->group(function () {
            // Merged project timeline grouped by month.
            Route::get('/', [TimeMachineController::class, 'index'])->name('index');

            // "State as of <date>" snapshot (date scrubber).
            Route::get('/as-of', [TimeMachineController::class, 'asOf'])->name('asOf');
        });
});

// Route names produced:
//   research.timemachine.index  GET  /research/projects/{projectId}/timemachine
//   research.timemachine.asOf   GET  /research/projects/{projectId}/timemachine/as-of
