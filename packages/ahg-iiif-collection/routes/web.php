<?php

use AhgIiifCollection\Controllers\IiifCollectionController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/manifest-collections', [IiifCollectionController::class, 'index'])->name('iiif-collection.index');
Route::get('/manifest-collection/{id}/view', [IiifCollectionController::class, 'view'])->name('iiif-collection.view');
Route::get('/manifest-collection/{slug}/manifest.json', [IiifCollectionController::class, 'manifest'])->name('iiif-collection.manifest');
Route::get('/iiif-manifest/{slug}', [IiifCollectionController::class, 'objectManifest'])->name('iiif-collection.object-manifest');

// Authenticated routes
Route::middleware('auth.required')->group(function () {
    Route::get('/manifest-collection/new', [IiifCollectionController::class, 'create'])->name('iiif-collection.create');
    Route::post('/manifest-collection', [IiifCollectionController::class, 'store'])->name('iiif-collection.store');
    Route::get('/manifest-collection/{id}/edit', [IiifCollectionController::class, 'edit'])->name('iiif-collection.edit');
    Route::put('/manifest-collection/{id}', [IiifCollectionController::class, 'update'])->name('iiif-collection.update');
    Route::delete('/manifest-collection/{id}', [IiifCollectionController::class, 'destroy'])->name('iiif-collection.destroy');
    Route::match(['get', 'post'], '/manifest-collection/{id}/items/add', [IiifCollectionController::class, 'addItems'])->name('iiif-collection.add-items');
    Route::get('/manifest-collection/remove-item', [IiifCollectionController::class, 'removeItem'])->name('iiif-collection.remove-item');
    Route::post('/manifest-collection/reorder', [IiifCollectionController::class, 'reorder'])->name('iiif-collection.reorder');
    Route::get('/manifest-collections/autocomplete', [IiifCollectionController::class, 'autocomplete'])->name('iiif-collection.autocomplete');
});
