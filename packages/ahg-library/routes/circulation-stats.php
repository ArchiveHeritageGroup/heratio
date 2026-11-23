<?php

use AhgLibrary\Controllers\CirculationStatsController;
use Illuminate\Support\Facades\Route;

// #1476 circulation statistics. Loaded before web.php so the literal paths are
// matched ahead of any /library-manage/{slug} pattern.
Route::middleware(['auth', 'acl:update'])->group(function () {
    Route::get('/library-manage/reports/circulation', [CirculationStatsController::class, 'index'])
        ->name('library.report-circulation');
    Route::get('/library-manage/reports/circulation/export/{format}', [CirculationStatsController::class, 'export'])
        ->name('library.report-circulation-export')
        ->where('format', 'csv|pdf');
});
