<?php

use AhgPdfTools\Controllers\PdfToolsController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/pdf-tools', [PdfToolsController::class, 'index'])
        ->name('pdf-tools.index');

    Route::match(['get', 'post'], '/admin/pdf-tools/merge', [PdfToolsController::class, 'merge'])
        ->name('pdf-tools.merge');

    Route::post('/admin/pdf-tools/extract-text', [PdfToolsController::class, 'extractText'])
        ->name('pdf-tools.extractText');

    Route::post('/admin/pdf-tools/batch-extract-text', [PdfToolsController::class, 'batchExtractText'])
        ->name('pdf-tools.batchExtractText');
});
