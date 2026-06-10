<?php
/**
 * Heratio - C2PA provenance / content-credentials routes (issue #1201).
 *
 * Mounted under /admin/c2pa. The IO slug catch-all only matches single-segment
 * paths, so these multi-segment admin routes are never intercepted by it.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

use AhgC2pa\Controllers\ProvenanceController;
use AhgC2pa\Controllers\VerifyController;
use Illuminate\Support\Facades\Route;

// Public "verify authenticity" trust-anchor surface (issue #1209).
// Multi-segment /verify/... paths so the single-segment IO slug catch-all
// (/{slug}) never intercepts them. No auth: this is the page society turns to
// when "is this real?" matters.
Route::prefix('verify')->group(function () {
    // Numeric id, namespaced under /verify/id/ so a purely numeric slug can
    // never collide with the id route.
    Route::get('/id/{informationObjectId}', [VerifyController::class, 'byId'])
        ->name('c2pa.verify.id')
        ->where('informationObjectId', '[0-9]+');

    // Slug, including multi-segment slugs (e.g. /verify/fonds/series/item).
    Route::get('/{slug}', [VerifyController::class, 'bySlug'])
        ->name('c2pa.verify.slug')
        ->where('slug', '.+');
});

Route::middleware('admin')->prefix('admin/c2pa')->group(function () {
    Route::get('/object/{informationObjectId}', [ProvenanceController::class, 'index'])
        ->name('c2pa.provenance.index')
        ->where('informationObjectId', '[0-9]+');

    Route::get('/object/{informationObjectId}/record', [ProvenanceController::class, 'create'])
        ->name('c2pa.provenance.create')
        ->where('informationObjectId', '[0-9]+');

    Route::post('/object/{informationObjectId}/record', [ProvenanceController::class, 'store'])
        ->name('c2pa.provenance.store')
        ->where('informationObjectId', '[0-9]+');

    Route::get('/object/{informationObjectId}/record/{provenanceId}', [ProvenanceController::class, 'show'])
        ->name('c2pa.provenance.show')
        ->where('informationObjectId', '[0-9]+')
        ->where('provenanceId', '[0-9]+');

    Route::get('/object/{informationObjectId}/record/{provenanceId}/manifest.json', [ProvenanceController::class, 'manifestJson'])
        ->name('c2pa.provenance.manifest')
        ->where('informationObjectId', '[0-9]+')
        ->where('provenanceId', '[0-9]+');
});
