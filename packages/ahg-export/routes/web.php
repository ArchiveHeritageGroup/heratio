<?php

use AhgExport\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('export')->group(function () {
    Route::get('/', [ExportController::class, 'index'])->name('export.index');
    Route::get('/csv', [ExportController::class, 'csv'])->name('export.csv');
    Route::get('/ead', [ExportController::class, 'ead'])->name('export.ead');
    Route::get('/archival', [ExportController::class, 'archival'])->name('export.archival');
    Route::get('/authority', [ExportController::class, 'authority'])->name('export.authority');
    Route::get('/repository', [ExportController::class, 'repository'])->name('export.repository');
    Route::get('/accession-csv', [ExportController::class, 'accessionCsv'])->name('export.accessionCsv');
    Route::post('/accession-csv', [ExportController::class, 'accessionCsv'])->name('export.accessionCsv.post');
});
