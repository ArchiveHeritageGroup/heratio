<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/metadata-export')->middleware(['web'])->group(function () {
    Route::get('/bulk', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'bulk'])->name('ahgmetadataexport.bulk');
    Route::get('/index', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'index'])->name('ahgmetadataexport.index');
    Route::get('/preview', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'preview'])->name('ahgmetadataexport.preview');
});
