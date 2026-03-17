<?php

use Ahg3dModel\Controllers\Model3dController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/3d-models', [Model3dController::class, 'browse'])
        ->name('admin.3d-models.browse');

    Route::get('/admin/3d-models/{id}/thumbnail', [Model3dController::class, 'generateThumbnail'])
        ->where('id', '[0-9]+')
        ->name('admin.3d-models.thumbnail');

    Route::get('/admin/3d-models/{id}/multiangle', [Model3dController::class, 'generateMultiAngle'])
        ->where('id', '[0-9]+')
        ->name('admin.3d-models.multiangle');

    Route::post('/admin/3d-models/batch-thumbnails', [Model3dController::class, 'batchThumbnails'])
        ->name('admin.3d-models.batch-thumbnails');
});
