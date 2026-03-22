<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/gis')->middleware(['web'])->group(function () {
    Route::get('/bbox', [\AhgGis\Controllers\GisController::class, 'bbox'])->name('ahggis.bbox');
    Route::get('/geojson', [\AhgGis\Controllers\GisController::class, 'geojson'])->name('ahggis.geojson');
    Route::get('/radius', [\AhgGis\Controllers\GisController::class, 'radius'])->name('ahggis.radius');
});
