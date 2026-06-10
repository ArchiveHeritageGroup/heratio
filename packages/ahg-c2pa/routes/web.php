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
use Illuminate\Support\Facades\Route;

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
