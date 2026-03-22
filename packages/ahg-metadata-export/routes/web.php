<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/metadata-export')->middleware(['web'])->group(function () {
    Route::get('/bulk', [\AhgIngest\Controllers\IngestController::class, 'bulk'])->name('ahgmetadataexport.bulk');
    Route::get('/index', [\AhgIngest\Controllers\IngestController::class, 'index'])->name('ahgmetadataexport.index');
    Route::get('/preview', [\AhgIngest\Controllers\IngestController::class, 'preview'])->name('ahgmetadataexport.preview');
});
