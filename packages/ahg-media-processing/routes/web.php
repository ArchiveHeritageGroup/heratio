<?php

use AhgMediaProcessing\Controllers\MediaProcessingController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/media-processing', [MediaProcessingController::class, 'index'])
        ->name('media-processing.index');

    Route::post('/admin/media-processing/{id}/regenerate', [MediaProcessingController::class, 'regenerate'])
        ->name('media-processing.regenerate')
        ->where('id', '[0-9]+');

    Route::post('/admin/media-processing/batch', [MediaProcessingController::class, 'batchRegenerate'])
        ->name('media-processing.batch-regenerate');

    Route::match(['get', 'post'], '/admin/media-processing/watermark', [MediaProcessingController::class, 'watermarkSettings'])
        ->name('media-processing.watermark-settings');
});
