<?php

use AhgIngest\Controllers\IngestController;
use AhgIngest\Controllers\ChunkedUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('ingest')->group(function () {
    Route::get('/', [IngestController::class, 'index'])->name('ingest.index');
    Route::match(['get', 'post'], '/configure/{id?}', [IngestController::class, 'configure'])->name('ingest.configure');
    Route::match(['get', 'post'], '/{id}/upload', [IngestController::class, 'upload'])->name('ingest.upload');
    Route::match(['get', 'post'], '/{id}/map', [IngestController::class, 'map'])->name('ingest.map');
    Route::match(['get', 'post'], '/{id}/validate', [IngestController::class, 'validate'])->name('ingest.validate');
    Route::match(['get', 'post'], '/{id}/preview', [IngestController::class, 'preview'])->name('ingest.preview');
    Route::match(['get', 'post'], '/{id}/commit', [IngestController::class, 'commit'])->name('ingest.commit');
    Route::get('/template/{sector?}', [IngestController::class, 'downloadTemplate'])->name('ingest.template');

    // SharePoint manual-path picker (v2 ingest plan, step D)
    Route::get('/{id}/sharepoint/browse', [IngestController::class, 'browseSharePoint'])->name('ingest.sharepoint.browse');
    Route::post('/{id}/sharepoint/import', [IngestController::class, 'importFromSharePoint'])->name('ingest.sharepoint.import');

    // #1328 Resumable / chunked web upload for large (>1GB) files
    Route::post('/{id}/chunk', [ChunkedUploadController::class, 'chunk'])->name('ingest.chunk');
    Route::get('/{id}/chunk/status', [ChunkedUploadController::class, 'status'])->name('ingest.chunk.status');
    Route::post('/{id}/chunk/complete', [ChunkedUploadController::class, 'complete'])->name('ingest.chunk.complete');
    Route::post('/{id}/chunk/abort', [ChunkedUploadController::class, 'abort'])->name('ingest.chunk.abort');
});
