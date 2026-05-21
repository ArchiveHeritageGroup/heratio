<?php

/**
 * ahg-authority-resolution admin routes - Heratio
 *
 * Task 5 of the AHG Authority Resolution Engine. Mounts the review UI under
 * /admin/authority-resolution/. The `admin` middleware (Heratio convention,
 * used across ahg-research and elsewhere) gates the whole tree behind the
 * administrator role; anonymous users get a 403.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use AhgAuthorityResolution\Http\Controllers\AssignmentController;
use AhgAuthorityResolution\Http\Controllers\AuthorityReviewController;
use AhgAuthorityResolution\Http\Controllers\ParkQueueController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'admin'])
    ->prefix('admin/authority-resolution')
    ->name('auth-res.')
    ->group(function () {
        Route::get('/queue', [AuthorityReviewController::class, 'queue'])->name('queue');
        Route::get('/lookup', [AuthorityReviewController::class, 'lookup'])->name('lookup');

        Route::get('/review/{mention}', [AuthorityReviewController::class, 'show'])
            ->whereNumber('mention')
            ->name('review.show');

        // "View full context" - full source text + highlight offsets as JSON.
        Route::get('/review/{mention}/context', [AuthorityReviewController::class, 'context'])
            ->whereNumber('mention')
            ->name('review.context');

        Route::post('/review/{mention}/link', [AuthorityReviewController::class, 'link'])
            ->whereNumber('mention')->name('review.link');
        Route::post('/review/{mention}/link-different', [AuthorityReviewController::class, 'linkDifferent'])
            ->whereNumber('mention')->name('review.linkDifferent');

        // Task 6: create-new sub-workflow.
        Route::get('/review/{mention}/create-new', [AuthorityReviewController::class, 'createNewForm'])
            ->whereNumber('mention')->name('review.createNewForm');
        Route::post('/review/{mention}/create-new', [AuthorityReviewController::class, 'createNewSubmit'])
            ->whereNumber('mention')->name('review.createNew');

        Route::post('/review/{mention}/park', [AuthorityReviewController::class, 'park'])
            ->whereNumber('mention')->name('review.park');
        Route::post('/review/{mention}/reject', [AuthorityReviewController::class, 'reject'])
            ->whereNumber('mention')->name('review.reject');

        // Task 6: lookup-source admin settings.
        Route::get('/settings/lookup', [AuthorityReviewController::class, 'settings'])
            ->name('settings.show');
        Route::post('/settings/lookup', [AuthorityReviewController::class, 'settingsSave'])
            ->name('settings.save');
        Route::get('/lookup-sources/status', [AuthorityReviewController::class, 'lookupSourcesStatus'])
            ->name('lookup.status');

        // Task 7: parked-mention queue (dedicated screen).
        Route::get('/park', [ParkQueueController::class, 'index'])->name('park.index');
        Route::post('/park/{mention}/unpark', [ParkQueueController::class, 'unpark'])
            ->whereNumber('mention')->name('park.unpark');
        Route::get('/park/dashboard.json', [ParkQueueController::class, 'dashboard'])
            ->name('park.dashboard');

        // Assign / Workflow feature: route mentions through ahg-workflow.
        Route::post('/review/{mention}/assign', [AssignmentController::class, 'assignFromReview'])
            ->whereNumber('mention')->name('review.assign');
        Route::post('/queue/assign', [AssignmentController::class, 'assignFromQueue'])
            ->name('queue.assign');
        Route::get('/archivists.json', [AssignmentController::class, 'archivistsJson'])
            ->name('archivists.json');
    });
