<?php

use AhgExport\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('export')->group(function () {
    Route::get('/', [ExportController::class, 'index'])->name('export.index');
    // GET renders the form; POST generates the actual download (#1357 — the
    // forms POST to these names, which were previously GET-only → 405/no output).
    Route::match(['get', 'post'], '/csv', [ExportController::class, 'csv'])->name('export.csv');
    Route::match(['get', 'post'], '/ead', [ExportController::class, 'ead'])->name('export.ead');
    Route::match(['get', 'post'], '/archival', [ExportController::class, 'archival'])->name('export.archival');
    Route::match(['get', 'post'], '/authority', [ExportController::class, 'authority'])->name('export.authority');
    Route::match(['get', 'post'], '/repository', [ExportController::class, 'repository'])->name('export.repository');
    Route::get('/accession-csv', [ExportController::class, 'accessionCsv'])->name('export.accessionCsv');
    Route::post('/accession-csv', [ExportController::class, 'accessionCsv'])->name('export.accessionCsv.post');
});

