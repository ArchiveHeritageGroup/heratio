<?php

/**
 * AI Disclosure routes - Research OS Part IV "AI Containment" (heratio#1242).
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
 * (research/projects/{id}/ai-disclosure/...), so the locked /{slug} catch-all in
 * ahg-information-object-manage never intercepts these URLs.
 */

use AhgResearch\Controllers\AiDisclosureController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Per-project AI-use disclosure.
    Route::prefix('projects/{projectId}/ai-disclosure')
        ->name('aidisclosure.')
        ->whereNumber('projectId')
        ->group(function () {
            // Disclosure page (detected + logged usage + generated statement).
            Route::get('/', [AiDisclosureController::class, 'index'])->name('index');

            // Generated statement as a downloadable plain-text file.
            Route::get('/statement.txt', [AiDisclosureController::class, 'statementDownload'])->name('statement');

            // Manual interaction log (the only write surface).
            Route::post('/log', [AiDisclosureController::class, 'logStore'])->name('log.store');
            Route::post('/log/{entryId}/delete', [AiDisclosureController::class, 'logDestroy'])
                ->whereNumber('entryId')->name('log.destroy');
        });
});

// Route names produced:
//   research.aidisclosure.index        GET   /research/projects/{projectId}/ai-disclosure
//   research.aidisclosure.statement     GET   /research/projects/{projectId}/ai-disclosure/statement.txt
//   research.aidisclosure.log.store     POST  /research/projects/{projectId}/ai-disclosure/log
//   research.aidisclosure.log.destroy   POST  /research/projects/{projectId}/ai-disclosure/log/{entryId}/delete
