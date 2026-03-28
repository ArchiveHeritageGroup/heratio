<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/label/{slug}', [\AhgLabel\Controllers\LabelController::class, 'index'])->name('ahglabel.index');
    Route::post('/label/generate', [\AhgLabel\Controllers\LabelController::class, 'generate'])->name('ahglabel.generate');
    Route::post('/label/batch-print', [\AhgLabel\Controllers\LabelController::class, 'batchPrint'])->name('ahglabel.batch');
});
