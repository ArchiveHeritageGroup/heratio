<?php

/**
 * Project Export routes - Research OS #15 (heratio#1237).
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
 * Founding principle: "no lock-in / the exit door is always open." A one-click,
 * full-fidelity, open-format export of a single research project.
 *
 * Self-contained: this file owns its own
 * prefix('research')->name('research.')->middleware(['web','auth']) group, so
 * the shared routes/web.php is never edited. Every path is two-segment or
 * deeper, so the locked /{slug} catch-all never intercepts these URLs.
 */

use AhgResearch\Controllers\ProjectExportController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Per-project open-format export.
    Route::prefix('projects/{projectId}/export')->name('export.')->whereNumber('projectId')->group(function () {
        // Landing page: what is included + format buttons.
        Route::get('/', [ProjectExportController::class, 'index'])->name('index');

        // One-click full ZIP bundle.
        Route::get('/zip', [ProjectExportController::class, 'zip'])->name('zip');

        // Individual open formats.
        Route::get('/markdown', [ProjectExportController::class, 'markdown'])->name('markdown');
        Route::get('/json', [ProjectExportController::class, 'json'])->name('json');
        Route::get('/bibtex', [ProjectExportController::class, 'bibtex'])->name('bibtex');
        Route::get('/ris', [ProjectExportController::class, 'ris'])->name('ris');
        Route::get('/csl', [ProjectExportController::class, 'csl'])->name('csl');
    });
});

// Route names produced:
//   research.export.index, research.export.zip, research.export.markdown,
//   research.export.json, research.export.bibtex, research.export.ris,
//   research.export.csl
