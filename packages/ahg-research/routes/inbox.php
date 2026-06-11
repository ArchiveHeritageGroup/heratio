<?php

/**
 * Quick Capture Inbox routes - heratio#1228 (ROS Stage 0, epic #1222).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Loaded from AhgResearchServiceProvider::boot() inside the existing `web`
 * middleware group. All routes are auth-gated (researcher-scoped) and live
 * under the `research.` name prefix with two-segment+ paths.
 *
 * email-in and the web clipper are documented integration points: they POST to
 * research.inbox.capture with origin=email-in|clipper. The mail-server /
 * browser-extension plumbing itself is out of scope.
 */

use AhgResearch\Controllers\InboxController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware('auth')->group(function () {
    Route::prefix('inbox')->name('inbox.')->group(function () {
        // List + filter the current researcher's inbox.
        Route::get('/', [InboxController::class, 'index'])->name('index');

        // Generic capture endpoint (one-tap note, mobile, email-in, clipper).
        Route::post('/capture', [InboxController::class, 'capture'])->name('capture');

        // Per-item triage actions (researcher-scoped).
        Route::post('/{id}/triage', [InboxController::class, 'triage'])->whereNumber('id')->name('triage');
        Route::post('/{id}/archive', [InboxController::class, 'archive'])->whereNumber('id')->name('archive');
        Route::post('/{id}/restore', [InboxController::class, 'restore'])->whereNumber('id')->name('restore');
        Route::post('/{id}/move', [InboxController::class, 'moveToProject'])->whereNumber('id')->name('move');
    });
});
