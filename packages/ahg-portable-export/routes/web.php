<?php

use AhgPortableExport\Controllers\PortableExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/portableExport/index', [PortableExportController::class, 'index'])->name('portable-export.index');
    Route::post('/portableExport/export', [PortableExportController::class, 'export'])->name('portable-export.export');
    Route::match(['get','post'], '/portableExport/import', [PortableExportController::class, 'import'])->name('portable-export.import');
});
