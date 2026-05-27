<?php

/**
 * ahg-request-publish routes.
 *
 * Two flows live here:
 *
 *   1. Legacy AtoM-port (request_to_publish / request_to_publish_i18n) under
 *      /admin/request-publish/* + /requesttopublish/browse - handled by
 *      RequestPublishController. Authenticated only.
 *
 *   2. New token-anchored flow (ahg_publish_request) under /publish-request
 *      + /admin/publish-requests/* - handled by PublishRequestController.
 *      The public submission + receipt endpoints are anonymous.
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
 * Author:    Johan Pieterse <johan@plainsailingisystems.co.za>
 * Licensed under the GNU Affero General Public License v3 or later.
 */

use AhgRequestPublish\Controllers\PublishRequestController;
use AhgRequestPublish\Controllers\RequestPublishController;
use Illuminate\Support\Facades\Route;

// ============================================================================
// Legacy AtoM-port flow (existing - retained for in-flight requests)
// ============================================================================

// Public alias for DB menu path
Route::middleware('auth')->group(function () {
    Route::get('/requesttopublish/browse', [RequestPublishController::class, 'browse']);
});

Route::middleware('admin')->group(function () {
    Route::get('/admin/request-publish', [RequestPublishController::class, 'browse'])->name('request-publish.browse');
    Route::get('/admin/request-publish/{id}/edit', [RequestPublishController::class, 'edit'])->name('request-publish.edit')->whereNumber('id');
    Route::post('/admin/request-publish/{id}/update', [RequestPublishController::class, 'update'])->name('request-publish.update')->whereNumber('id');
    Route::post('/admin/request-publish/{id}/delete', [RequestPublishController::class, 'destroy'])->name('request-publish.destroy')->whereNumber('id');
    Route::match(['get', 'post'], '/admin/request-publish/{id}/edit-request', [RequestPublishController::class, 'editRequest'])->name('request-publish.edit-request')->whereNumber('id');
});

Route::middleware('auth')->group(function () {
    Route::post('/request-publish/submit/{slug}', [RequestPublishController::class, 'submit'])->name('request-publish.submit');
});

// ============================================================================
// New token-anchored flow (Heratio #745)
// ============================================================================

// Public submission + anonymous receipt. No auth. CSRF-exempt for submit (see
// bootstrap/app.php validateCsrfTokens except[] entry: 'publish-request').
Route::post('/publish-request', [PublishRequestController::class, 'submit'])
    ->name('publish-request.submit');
Route::get('/publish-request/receipt/{token}', [PublishRequestController::class, 'receipt'])
    ->name('publish-request.receipt')
    ->where('token', '[a-f0-9]{40}');

// Curator inbox + per-request panel + decision write. Admin only.
Route::middleware('admin')->group(function () {
    Route::get('/admin/publish-requests', [PublishRequestController::class, 'inbox'])
        ->name('publish-requests.inbox');
    Route::get('/admin/publish-requests/{id}/edit', [PublishRequestController::class, 'edit'])
        ->name('publish-requests.edit')
        ->whereNumber('id');
    Route::post('/admin/publish-requests/{id}/decision', [PublishRequestController::class, 'decision'])
        ->name('publish-requests.decision')
        ->whereNumber('id');
});
