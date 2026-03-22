<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/gis')->middleware(['web'])->group(function () {
    Route::get('/bbox', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'bbox'])->name('ahggis.bbox');
    Route::get('/geojson', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'geojson'])->name('ahggis.geojson');
    Route::get('/radius', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'radius'])->name('ahggis.radius');
});
