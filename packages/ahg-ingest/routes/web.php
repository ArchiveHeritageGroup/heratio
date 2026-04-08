<?php

use AhgIngest\Controllers\IngestController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('ingest')->group(function () {
    Route::get('/', [IngestController::class, 'index'])->name('ingest.index');
    Route::match(['get', 'post'], '/configure/{id?}', [IngestController::class, 'configure'])->name('ingest.configure');
    Route::match(['get', 'post'], '/{id}/upload', [IngestController::class, 'upload'])->name('ingest.upload');
    Route::match(['get', 'post'], '/{id}/map', [IngestController::class, 'map'])->name('ingest.map');
    Route::match(['get', 'post'], '/{id}/validate', [IngestController::class, 'validate'])->name('ingest.validate');
    Route::match(['get', 'post'], '/{id}/preview', [IngestController::class, 'preview'])->name('ingest.preview');
    Route::match(['get', 'post'], '/{id}/commit', [IngestController::class, 'commit'])->name('ingest.commit');
    Route::get('/template/{sector?}', [IngestController::class, 'downloadTemplate'])->name('ingest.template');
});
