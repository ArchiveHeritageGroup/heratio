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
    Route::get('/admin/3d-models/index', [Model3dController::class, 'index'])->name('admin.3d-models.index');
    Route::get('/admin/3d-models/{id}/view', [Model3dController::class, 'view'])->name('admin.3d-models.view')->whereNumber('id');
    Route::match(['get','post'], '/admin/3d-models/{id}/edit', [Model3dController::class, 'edit'])->name('admin.3d-models.edit')->whereNumber('id');
    Route::get('/admin/3d-models/{id}/embed', [Model3dController::class, 'embed'])->name('admin.3d-models.embed')->whereNumber('id');
    Route::match(['get','post'], '/admin/3d-models/upload/{objectId}', [Model3dController::class, 'upload'])->name('admin.3d-models.upload')->whereNumber('objectId');
    Route::match(['get','post'], '/admin/3d-models/settings', [Model3dController::class, 'settings'])->name('admin.3d-models.settings');
    Route::match(['get','post'], '/admin/3d-models/triposr', [Model3dController::class, 'triposr'])->name('admin.3d-models.triposr');
