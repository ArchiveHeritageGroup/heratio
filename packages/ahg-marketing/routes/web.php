<?php

/**
 * Marketing routes. All two-segment paths so they beat nothing and are beaten
 * by nothing - they sit outside the locked `/{slug}` single-segment catch-all.
 *
 * @license AGPL-3.0-or-later
 */

use AhgMarketing\Controllers\ComparisonController;
use AhgMarketing\Controllers\MigrationLeadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    Route::get('/compare/atom', [ComparisonController::class, 'atom'])
        ->name('marketing.compare.atom');

    Route::get('/migration/assessment', [MigrationLeadController::class, 'show'])
        ->name('marketing.migration.assessment');

    Route::post('/migration/assessment', [MigrationLeadController::class, 'submit'])
        ->name('marketing.migration.assessment.submit');
});
