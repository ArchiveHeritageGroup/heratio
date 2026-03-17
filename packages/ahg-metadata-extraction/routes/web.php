<?php

use AhgMetadataExtraction\Controllers\MetadataExtractionController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/metadata-extraction', [MetadataExtractionController::class, 'index'])
        ->name('metadata-extraction.index');

    Route::get('/admin/metadata-extraction/status', [MetadataExtractionController::class, 'status'])
        ->name('metadata-extraction.status');

    Route::get('/admin/metadata-extraction/{id}/view', [MetadataExtractionController::class, 'view'])
        ->name('metadata-extraction.view')
        ->where('id', '[0-9]+');

    Route::post('/admin/metadata-extraction/{id}/extract', [MetadataExtractionController::class, 'extract'])
        ->name('metadata-extraction.extract')
        ->where('id', '[0-9]+');

    Route::post('/admin/metadata-extraction/{id}/delete', [MetadataExtractionController::class, 'delete'])
        ->name('metadata-extraction.delete')
        ->where('id', '[0-9]+');

    Route::post('/admin/metadata-extraction/batch-extract', [MetadataExtractionController::class, 'batchExtract'])
        ->name('metadata-extraction.batchExtract');
});
