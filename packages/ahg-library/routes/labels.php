<?php

use AhgLibrary\Controllers\LibraryLabelController;
use Illuminate\Support\Facades\Route;

// #1476 item 2: label printing and stationery templates. Staff only.
Route::middleware(['auth', 'acl:update'])->prefix('library-manage/labels')->name('library.labels.')->group(function () {
    Route::get('/', [LibraryLabelController::class, 'index'])->name('index');
    Route::post('/preview', [LibraryLabelController::class, 'preview'])->name('preview');
    Route::get('/templates', [LibraryLabelController::class, 'templates'])->name('templates');
    Route::get('/templates/create', [LibraryLabelController::class, 'createTemplate'])->name('templates.create');
    Route::post('/templates', [LibraryLabelController::class, 'storeTemplate'])->name('templates.store');
    Route::get('/templates/{id}/edit', [LibraryLabelController::class, 'editTemplate'])->name('templates.edit')->whereNumber('id');
    Route::post('/templates/{id}', [LibraryLabelController::class, 'updateTemplate'])->name('templates.update')->whereNumber('id');
    Route::post('/templates/{id}/delete', [LibraryLabelController::class, 'deleteTemplate'])->name('templates.delete')->whereNumber('id')->middleware('acl:delete');
});
