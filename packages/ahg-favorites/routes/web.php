<?php

use AhgFavorites\Controllers\FavoritesController;
use Illuminate\Support\Facades\Route;

// Public shared folder view (no auth)
Route::get('/favorites/shared/{token}', [FavoritesController::class, 'viewShared'])->name('favorites.shared');

// All other routes require auth
Route::middleware('auth')->group(function () {
    Route::get('/favorites', [FavoritesController::class, 'browse'])->name('favorites.browse');
    Route::get('/favorites/add/{slug}', [FavoritesController::class, 'add'])->name('favorites.add');
    Route::post('/favorites/remove/{id}', [FavoritesController::class, 'remove'])->name('favorites.remove')->where('id', '[0-9]+');
    Route::post('/favorites/bulk', [FavoritesController::class, 'bulk'])->name('favorites.bulk');
    Route::post('/favorites/notes/{id}', [FavoritesController::class, 'updateNotes'])->name('favorites.notes')->where('id', '[0-9]+');

    // AJAX
    Route::post('/favorites/ajax/toggle', [FavoritesController::class, 'ajaxToggle'])->name('favorites.ajax.toggle');
    Route::get('/favorites/ajax/status/{slug}', [FavoritesController::class, 'ajaxStatus'])->name('favorites.ajax.status');

    // Folders
    Route::post('/favorites/folder/create', [FavoritesController::class, 'folderCreate'])->name('favorites.folder.create');
    Route::post('/favorites/folder/{id}/edit', [FavoritesController::class, 'folderEdit'])->name('favorites.folder.edit')->where('id', '[0-9]+');
    Route::post('/favorites/folder/{id}/delete', [FavoritesController::class, 'folderDelete'])->name('favorites.folder.delete')->where('id', '[0-9]+');
    Route::post('/favorites/folder/{id}/share', [FavoritesController::class, 'shareFolder'])->name('favorites.folder.share')->where('id', '[0-9]+');
    Route::post('/favorites/folder/{id}/revoke-share', [FavoritesController::class, 'revokeSharing'])->name('favorites.folder.revoke')->where('id', '[0-9]+');

    // Export/Import
    Route::get('/favorites/export/csv', [FavoritesController::class, 'exportCsv'])->name('favorites.export.csv');
    Route::get('/favorites/export/json', [FavoritesController::class, 'exportJson'])->name('favorites.export.json');
    Route::post('/favorites/import', [FavoritesController::class, 'importFavorites'])->name('favorites.import');
});
