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
    Route::post('/favorites/clear', [FavoritesController::class, 'clear'])->name('favorites.clear');
    Route::post('/favorites/bulk', [FavoritesController::class, 'bulk'])->name('favorites.bulk');
    Route::post('/favorites/notes/{id}', [FavoritesController::class, 'updateNotes'])->name('favorites.notes')->where('id', '[0-9]+');

    // AJAX
    Route::post('/favorites/ajax/toggle', [FavoritesController::class, 'ajaxToggle'])->name('favorites.ajax.toggle');
    Route::post('/favorites/ajax/toggle-custom', [FavoritesController::class, 'ajaxToggleCustom'])->name('favorites.ajax.toggle-custom');
    Route::get('/favorites/ajax/status/{slug}', [FavoritesController::class, 'ajaxStatus'])->name('favorites.ajax.status');
    Route::get('/favorites/ajax/folders', [FavoritesController::class, 'ajaxFolders'])->name('favorites.ajax.folders');
    Route::get('/favorites/ajax/search', [FavoritesController::class, 'ajaxSearch'])->name('favorites.ajax.search');

    // Move-to-folder (dedicated endpoint - sister of bulk move)
    Route::post('/favorites/move-to-folder', [FavoritesController::class, 'moveToFolder'])->name('favorites.move-to-folder');

    // Folders
    Route::get('/favorites/folder/{id}', [FavoritesController::class, 'folderView'])->name('favorites.folder.view')->where('id', '[0-9]+');
    Route::post('/favorites/folder/create', [FavoritesController::class, 'folderCreate'])->name('favorites.folder.create');
    Route::post('/favorites/folder/{id}/edit', [FavoritesController::class, 'folderEdit'])->name('favorites.folder.edit')->where('id', '[0-9]+');
    Route::post('/favorites/folder/{id}/delete', [FavoritesController::class, 'folderDelete'])->name('favorites.folder.delete')->where('id', '[0-9]+');
    Route::post('/favorites/folder/{id}/share', [FavoritesController::class, 'shareFolder'])->name('favorites.folder.share')->where('id', '[0-9]+');
    Route::post('/favorites/folder/{id}/revoke-share', [FavoritesController::class, 'revokeSharing'])->name('favorites.folder.revoke')->where('id', '[0-9]+');

    // Send to ... (research bridge)
    Route::match(['get', 'post'], '/favorites/send-to-collection', [FavoritesController::class, 'sendToCollection'])->name('favorites.send-to-collection');
    Route::match(['get', 'post'], '/favorites/send-to-project', [FavoritesController::class, 'sendToProject'])->name('favorites.send-to-project');
    Route::match(['get', 'post'], '/favorites/send-to-bibliography', [FavoritesController::class, 'sendToBibliography'])->name('favorites.send-to-bibliography');

    // Export (all favourites)
    Route::get('/favorites/export/csv', [FavoritesController::class, 'exportCsv'])->name('favorites.export.csv');
    Route::get('/favorites/export/json', [FavoritesController::class, 'exportJson'])->name('favorites.export.json');

    // Export (single folder, multi-format)
    Route::get('/favorites/folder/{id}/export', [FavoritesController::class, 'exportFolder'])->name('favorites.folder.export')->where('id', '[0-9]+');

    // Import
    Route::post('/favorites/import', [FavoritesController::class, 'importFavorites'])->name('favorites.import');
});
