<?php

use Illuminate\Support\Facades\Route;

// #1281: configurable label/barcode sheet templates (admin designer).
// Registered under /admin/... so it can't be swallowed by /label/{slug} below.
Route::middleware(['web', 'auth', 'admin'])->prefix('admin/label/templates')->name('ahglabel.templates.')->group(function () {
    Route::get('/', [\AhgLabel\Controllers\LabelTemplateController::class, 'index'])->name('index');
    Route::get('/new', [\AhgLabel\Controllers\LabelTemplateController::class, 'create'])->name('create');
    Route::post('/', [\AhgLabel\Controllers\LabelTemplateController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [\AhgLabel\Controllers\LabelTemplateController::class, 'edit'])->where('id', '[0-9]+')->name('edit');
    Route::put('/{id}', [\AhgLabel\Controllers\LabelTemplateController::class, 'update'])->where('id', '[0-9]+')->name('update');
    Route::delete('/{id}', [\AhgLabel\Controllers\LabelTemplateController::class, 'destroy'])->where('id', '[0-9]+')->name('destroy');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/label/{slug}', [\AhgLabel\Controllers\LabelController::class, 'index'])->name('ahglabel.index');
    Route::post('/label/generate', [\AhgLabel\Controllers\LabelController::class, 'generate'])->name('ahglabel.generate');
    Route::post('/label/batch-print', [\AhgLabel\Controllers\LabelController::class, 'batchPrint'])->name('ahglabel.batch');
});
