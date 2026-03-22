<?php

use AhgCore\Controllers\ClipboardController;
use Illuminate\Support\Facades\Route;

// Clipboard routes
Route::prefix('clipboard')->name('clipboard.')->group(function () {
    Route::match(['get', 'post'], '/',    [ClipboardController::class, 'index'])->name('index');
    Route::match(['get', 'post'], '/view', [ClipboardController::class, 'index'])->name('view');
    Route::post('/add',       [ClipboardController::class, 'add'])->name('add');
    Route::delete('/remove',  [ClipboardController::class, 'remove'])->name('remove');
    Route::post('/clear',     [ClipboardController::class, 'clear'])->name('clear');
    Route::post('/sync',      [ClipboardController::class, 'sync'])->name('sync');
    Route::post('/save',      [ClipboardController::class, 'save'])->name('save');
    Route::get('/load',       [ClipboardController::class, 'loadForm'])->name('load');
    Route::post('/load',      [ClipboardController::class, 'load'])->name('load.post');
    Route::get('/export/csv', [ClipboardController::class, 'exportCsv'])->name('export.csv');
    Route::get('/count',      [ClipboardController::class, 'count'])->name('count');
    Route::post('/exportCheck', [ClipboardController::class, 'exportCheck'])->name('exportCheck');

// Auto-registered stub routes
Route::match(['get','post'], '/tiffpdfmerge/create', function() { return view('core::create'); })->name('tiffpdfmerge.create');
Route::match(['get','post'], '/tiffpdfmerge/upload', function() { return view('core::upload'); })->name('tiffpdfmerge.upload');
Route::match(['get','post'], '/tiffpdfmerge/reorder', function() { return view('core::reorder'); })->name('tiffpdfmerge.reorder');
Route::match(['get','post'], '/tiffpdfmerge/remove-file', function() { return view('core::remove-file'); })->name('tiffpdfmerge.removeFile');
Route::match(['get','post'], '/tiffpdfmerge/process', function() { return view('core::process'); })->name('tiffpdfmerge.process');
Route::match(['get','post'], '/tiffpdfmerge/delete', function() { return view('core::delete'); })->name('tiffpdfmerge.delete');
Route::match(['get','post'], '/object/import-select', function() { return view('core::import-select'); })->name('object.importSelect');
});

// Object import select
Route::middleware(['web'])->group(function () {
    Route::get('/object/{slug}/import-select', fn($slug) => view('ahg-core::object-import-select', ['slug' => $slug]))->name('object.importSelect');
    Route::get('/tiffpdfmerge/create', fn() => view('ahg-core::tiffpdfmerge-create'))->name('tiffpdfmerge.create');
    Route::post('/tiffpdfmerge/upload', fn() => redirect()->back())->name('tiffpdfmerge.upload');
    Route::post('/tiffpdfmerge/process', fn() => redirect()->back())->name('tiffpdfmerge.process');
    Route::post('/tiffpdfmerge/reorder', fn() => redirect()->back())->name('tiffpdfmerge.reorder');
    Route::delete('/tiffpdfmerge/{id}/file', fn($id) => redirect()->back())->name('tiffpdfmerge.removeFile');
    Route::delete('/tiffpdfmerge/{id}', fn($id) => redirect()->back())->name('tiffpdfmerge.delete');
});
