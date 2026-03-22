<?php

use AhgDisplay\Controllers\DisplayController;
use Illuminate\Support\Facades\Route;

// Public browse routes
Route::match(['GET', 'POST'], '/glam/browse', [DisplayController::class, 'browse'])->name('glam.browse');
Route::match(['GET', 'POST'], '/glam/browseAjax', [DisplayController::class, 'browseAjax'])->name('glam.browse.ajax');
Route::get('/glam/print', [DisplayController::class, 'printView'])->name('glam.print');
Route::get('/glam/exportCsv', [DisplayController::class, 'exportCsv'])->name('glam.export.csv');

// Override standard informationobject browse
Route::match(['GET', 'POST'], '/informationobject/browse', [DisplayController::class, 'browse'])->name('informationobject.browse.override');

// Admin routes (require auth)
Route::middleware('admin')->group(function () {
    Route::match(['GET', 'POST'], '/glam', [DisplayController::class, 'index'])->name('glam.index');
    Route::match(['GET', 'POST'], '/glam/profiles', [DisplayController::class, 'profiles'])->name('glam.profiles');
    Route::match(['GET', 'POST'], '/glam/levels', [DisplayController::class, 'levels'])->name('glam.levels');
    Route::match(['GET', 'POST'], '/glam/fields', [DisplayController::class, 'fields'])->name('glam.fields');
    Route::match(['GET', 'POST'], '/glam/setType', [DisplayController::class, 'setType'])->name('glam.set.type');
    Route::match(['GET', 'POST'], '/glam/assignProfile', [DisplayController::class, 'assignProfile'])->name('glam.assign.profile');
    Route::match(['GET', 'POST'], '/glam/bulkSetType', [DisplayController::class, 'bulkSetType'])->name('glam.bulk.set.type');
    Route::match(['GET', 'POST'], '/glam/changeType', [DisplayController::class, 'changeType'])->name('glam.change.type');
    Route::match(['GET', 'POST'], '/glam/settings', [DisplayController::class, 'browseSettings'])->name('glam.browse.settings');
    Route::post('/glam/toggleGlamBrowse', [DisplayController::class, 'toggleGlamBrowse'])->name('glam.toggle');
    Route::post('/glam/saveBrowseSettings', [DisplayController::class, 'saveBrowseSettings'])->name('glam.save.settings');
    Route::get('/glam/getBrowseSettings', [DisplayController::class, 'getBrowseSettings'])->name('glam.get.settings');
    Route::post('/glam/resetBrowseSettings', [DisplayController::class, 'resetBrowseSettings'])->name('glam.reset.settings');
});
    Route::get('/glam/browse-embedded', [DisplayController::class, 'browseEmbedded'])->name('glam.browse.embedded');
    Route::get('/glam/reindex', [DisplayController::class, 'reindex'])->name('glam.reindex');
    Route::get('/glam/search', [DisplayController::class, 'glamSearch'])->name('glam.search');
    Route::get('/glam/treeview', [DisplayController::class, 'treeviewPage'])->name('glam.treeview');

