<?php

use AhgLibrary\Controllers\MarcRecordController;
use Illuminate\Support\Facades\Route;

// Source MARC records (#1476). They can hold local and vendor fields, so all of it is staff-only.
Route::middleware(['auth', 'acl:update'])->prefix('library-manage/marc/{id}/source')->where(['id' => '[0-9]+'])->group(function () {
    Route::get('/', [MarcRecordController::class, 'history'])->name('library.marc-source');
    Route::get('/diff', [MarcRecordController::class, 'diff'])->name('library.marc-source-diff');
    Route::get('/replay', [MarcRecordController::class, 'replayPreview'])->name('library.marc-source-replay');
    Route::post('/replay', [MarcRecordController::class, 'replayApply'])->name('library.marc-source-replay-apply');
    Route::get('/{version}/{format}', [MarcRecordController::class, 'download'])
        ->name('library.marc-source-download')->where(['version' => '[0-9]+', 'format' => 'xml|binary']);
});
