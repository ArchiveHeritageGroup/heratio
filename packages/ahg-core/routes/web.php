<?php

use AhgCore\Controllers\ClipboardController;
use Illuminate\Support\Facades\Route;

// Clipboard routes
Route::prefix('clipboard')->name('clipboard.')->group(function () {
    Route::get('/',           [ClipboardController::class, 'index'])->name('index');
    Route::get('/view',       [ClipboardController::class, 'index'])->name('view');
    Route::post('/add',       [ClipboardController::class, 'add'])->name('add');
    Route::delete('/remove',  [ClipboardController::class, 'remove'])->name('remove');
    Route::post('/clear',     [ClipboardController::class, 'clear'])->name('clear');
    Route::post('/sync',      [ClipboardController::class, 'sync'])->name('sync');
    Route::post('/save',      [ClipboardController::class, 'save'])->name('save');
    Route::get('/load',       [ClipboardController::class, 'loadForm'])->name('load');
    Route::post('/load',      [ClipboardController::class, 'load'])->name('load.post');
    Route::get('/export/csv', [ClipboardController::class, 'exportCsv'])->name('export.csv');
    Route::get('/count',      [ClipboardController::class, 'count'])->name('count');
});
