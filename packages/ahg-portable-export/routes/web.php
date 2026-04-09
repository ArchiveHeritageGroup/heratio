<?php

use AhgPortableExport\Controllers\PortableExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    // AtoM-canonical URLs
    Route::get('/portable-export', [PortableExportController::class, 'index'])->name('portable-export.index');
    Route::get('/portable-export/index', [PortableExportController::class, 'index']);
    Route::match(['get', 'post'], '/portable-export/import', [PortableExportController::class, 'import'])->name('portable-export.import');
    Route::get('/portable-export/download', [PortableExportController::class, 'download'])->name('portable-export.download');

    // API endpoints used by the wizard JS
    Route::post('/portable-export/api/start', [PortableExportController::class, 'apiStart'])->name('portable-export.api.start');
    Route::get('/portable-export/api/progress', [PortableExportController::class, 'apiProgress'])->name('portable-export.api.progress');
    Route::get('/portable-export/api/estimate', [PortableExportController::class, 'apiEstimate'])->name('portable-export.api.estimate');
    Route::get('/portable-export/api/fonds-search', [PortableExportController::class, 'apiFondsSearch'])->name('portable-export.api.fonds-search');
    Route::post('/portable-export/api/delete', [PortableExportController::class, 'apiDelete'])->name('portable-export.api.delete');
    Route::post('/portable-export/api/token', [PortableExportController::class, 'apiToken'])->name('portable-export.api.token');

    // Legacy aliases
    Route::get('/portableExport/index', [PortableExportController::class, 'index']);
    Route::post('/portableExport/export', [PortableExportController::class, 'export']);
    Route::match(['get','post'], '/portableExport/import', [PortableExportController::class, 'import']);
});
