<?php

/**
 * Claim Ledger routes - Research OS Stage 8 (heratio#1223).
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
 * existing `research.` namespace. Every path is two-segment or deeper, so the
 * locked /{slug} catch-all never intercepts these URLs.
 */

use AhgResearch\Controllers\ClaimLedgerController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Per-project Claim Ledger
    Route::prefix('project/{projectId}/claims')->name('claims.')->whereNumber('projectId')->group(function () {
        Route::get('/', [ClaimLedgerController::class, 'index'])->name('index');
        Route::post('/', [ClaimLedgerController::class, 'store'])->name('store');

        Route::get('/{claimId}', [ClaimLedgerController::class, 'show'])->whereNumber('claimId')->name('show');
        Route::post('/{claimId}', [ClaimLedgerController::class, 'update'])->whereNumber('claimId')->name('update');
        Route::post('/{claimId}/status', [ClaimLedgerController::class, 'setStatus'])->whereNumber('claimId')->name('status');
        Route::post('/{claimId}/delete', [ClaimLedgerController::class, 'destroy'])->whereNumber('claimId')->name('destroy');

        // Evidence (reuses research_assertion_evidence)
        Route::post('/{claimId}/evidence', [ClaimLedgerController::class, 'attachEvidence'])->whereNumber('claimId')->name('evidence.attach');
        Route::post('/{claimId}/evidence/{evidenceId}/detach', [ClaimLedgerController::class, 'detachEvidence'])->whereNumber('claimId')->whereNumber('evidenceId')->name('evidence.detach');
    });
});

// Route names produced:
//   research.claims.index, research.claims.store, research.claims.show,
//   research.claims.update, research.claims.status, research.claims.destroy,
//   research.claims.evidence.attach, research.claims.evidence.detach
