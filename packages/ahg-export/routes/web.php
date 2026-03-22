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

// Export routes
Route::middleware(['web'])->prefix('admin/export')->group(function () {
    Route::get('/', fn() => view('export::index'))->name('export.index');
    Route::get('/ead', fn() => view('export::ead'))->name('export.ead');
    Route::get('/csv', fn() => view('export::csv'))->name('export.csv');
    Route::get('/archival', fn() => view('export::archival'))->name('export.archival');
    Route::get('/authority', fn() => view('export::authority'))->name('export.authority');
    Route::get('/repository', fn() => view('export::repository'))->name('export.repository');
    Route::get('/accession-csv', fn() => view('export::accession-csv'))->name('export.accessionCsv');
    Route::post('/accession-csv', fn() => redirect()->back())->name('export.accessionCsv.post');
});
Route::get('/', fn() => redirect('/heritage'))->name('homepage');
