<?php

/**
 * Issue #61 Phase 4 - Provenance trace API routes.
 *
 * Endpoints are read-only (GET) and auth-gated.
 */

use AhgProvenanceAi\Controllers\ProvenanceTraceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/api/v1/provenance/coverage',
        [ProvenanceTraceController::class, 'coverage'])
        ->name('provenance.coverage');

    Route::get('/api/v1/provenance/{entityType}/{id}/trace',
        [ProvenanceTraceController::class, 'trace'])
        ->where('entityType', '[a-z_]+')
        ->where('id', '[0-9]+')
        ->name('provenance.trace');
});
