<?php

/**
 * Publication Studio routes - Heratio ahg-research
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * heratio#1232 - Research OS #10: Publication Studio (ROS Stage 15, epic #1222).
 *
 * Per-project publication workflow on top of the target-journal directory.
 * Self-contained: this file declares its own
 * prefix('research')->name('research.')->middleware(['web','auth']) group so it
 * can be loaded plainly from the service provider alongside the other ROS slice
 * route files. Names live under research.publication.* and every path is
 * /research/projects/{projectId}/publication/... (three+ segments) so the
 * /{slug} catch-all in ahg-information-object-manage never intercepts them.
 */

use AhgResearch\Controllers\PublicationStudioController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    Route::prefix('projects/{projectId}/publication')
        ->name('publication.')
        ->whereNumber('projectId')
        ->group(function () {

            // Studio home: submissions list + venue matching panel.
            Route::get('/', [PublicationStudioController::class, 'index'])->name('index');

            // Optional AI venue-fit suggestion (gateway-backed, labelled). AJAX.
            Route::post('/ai-fit', [PublicationStudioController::class, 'aiFit'])->name('ai-fit');

            // Create a submission against a matched (or free-text) venue.
            Route::post('/submissions', [PublicationStudioController::class, 'storeSubmission'])->name('submissions.store');

            // Submission detail + actions.
            Route::prefix('submissions/{submissionId}')->whereNumber('submissionId')->group(function () {
                Route::get('/', [PublicationStudioController::class, 'submission'])->name('submission');
                Route::post('/', [PublicationStudioController::class, 'updateSubmission'])->name('submission.update');
                Route::post('/transition', [PublicationStudioController::class, 'transition'])->name('submission.transition');

                // Compliance checklist.
                Route::post('/requirements', [PublicationStudioController::class, 'addRequirement'])->name('requirement.add');
                Route::post('/requirements/{reqId}', [PublicationStudioController::class, 'updateRequirement'])
                    ->whereNumber('reqId')->name('requirement.update');
                Route::delete('/requirements/{reqId}', [PublicationStudioController::class, 'deleteRequirement'])
                    ->whereNumber('reqId')->name('requirement.delete');

                // Response-to-reviewers / revision history.
                Route::post('/responses', [PublicationStudioController::class, 'addResponse'])->name('response.add');
            });
        });
});
