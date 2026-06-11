<?php

/**
 * Writing Studio routes - Research OS Stage 13 (epic heratio#1222).
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
 * Loaded from AhgResearchServiceProvider::boot() alongside routes/web.php so the
 * shared routes file does not need to be edited. All names live under the
 * existing `research.` namespace. Every path is /research/projects/{id}/writing
 * (three-plus segments), so the locked /{slug} catch-all never intercepts these
 * URLs.
 */

use AhgResearch\Controllers\WritingStudioController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Per-project Writing Studio
    Route::prefix('projects/{projectId}/writing')->name('writing.')->whereNumber('projectId')->group(function () {

        // Document list + create
        Route::get('/', [WritingStudioController::class, 'index'])->name('index');
        Route::post('/', [WritingStudioController::class, 'store'])->name('store');

        // A single document (the editor)
        Route::prefix('{docId}')->whereNumber('docId')->group(function () {
            Route::get('/', [WritingStudioController::class, 'edit'])->name('edit');
            Route::post('/', [WritingStudioController::class, 'update'])->name('update');
            Route::post('/delete', [WritingStudioController::class, 'destroy'])->name('destroy');

            // Markdown export
            Route::get('/export.md', [WritingStudioController::class, 'exportMarkdown'])->name('export');

            // Sections (write-as-you-go)
            Route::post('/sections', [WritingStudioController::class, 'addSection'])->name('sections.add');
            Route::post('/sections/{sectionId}', [WritingStudioController::class, 'saveSection'])->whereNumber('sectionId')->name('sections.save');
            Route::post('/sections/{sectionId}/delete', [WritingStudioController::class, 'deleteSection'])->whereNumber('sectionId')->name('sections.delete');

            // Optional AI drafting per section (gateway-only, labelled, never auto-applied)
            Route::post('/sections/{sectionId}/ai-draft', [WritingStudioController::class, 'aiDraft'])->whereNumber('sectionId')->name('sections.ai');

            // Cite a claim / pull a source into a chosen section (read-only sources)
            Route::post('/cite-claim', [WritingStudioController::class, 'citeClaim'])->name('cite');
            Route::post('/pull-source', [WritingStudioController::class, 'pullSource'])->name('source');

            // Versions (snapshot history)
            Route::post('/versions', [WritingStudioController::class, 'saveVersion'])->name('versions.save');
            Route::get('/versions', [WritingStudioController::class, 'versions'])->name('versions');
            Route::get('/versions/{versionId}', [WritingStudioController::class, 'showVersion'])->whereNumber('versionId')->name('versions.show');
        });
    });
});

// Route names produced:
//   research.writing.index, research.writing.store, research.writing.edit,
//   research.writing.update, research.writing.destroy, research.writing.export,
//   research.writing.sections.add, research.writing.sections.save,
//   research.writing.sections.delete, research.writing.sections.ai,
//   research.writing.cite, research.writing.source,
//   research.writing.versions.save, research.writing.versions,
//   research.writing.versions.show
