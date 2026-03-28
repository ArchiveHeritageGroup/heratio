<?php

use AhgRepositoryManage\Controllers\RepositoryController;
use Illuminate\Support\Facades\Route;

Route::get('/repository/browse', [RepositoryController::class, 'browse'])->name('repository.browse');

Route::middleware('auth')->group(function () {
    Route::get('/repository/add', [RepositoryController::class, 'create'])->name('repository.create');
    Route::post('/repository/add', [RepositoryController::class, 'store'])->name('repository.store')->middleware('acl:create');
    Route::get('/repository/{slug}/edit', [RepositoryController::class, 'edit'])->name('repository.edit');
    Route::post('/repository/{slug}/edit', [RepositoryController::class, 'update'])->name('repository.update')->middleware('acl:update');

    // Theme editing
    Route::get('/repository/{slug}/editTheme', [RepositoryController::class, 'editTheme'])->name('repository.editTheme');
    Route::post('/repository/{slug}/editTheme', [RepositoryController::class, 'updateTheme'])->name('repository.editTheme.update')->middleware('acl:update');

    // Upload limit editing
    Route::post('/repository/{slug}/editUploadLimit', [RepositoryController::class, 'editUploadLimit'])->name('repository.editUploadLimit')->middleware('acl:update');
});

Route::middleware('admin')->group(function () {
    Route::get('/repository/{slug}/delete', [RepositoryController::class, 'confirmDelete'])->name('repository.confirmDelete');
    Route::delete('/repository/{slug}/delete', [RepositoryController::class, 'destroy'])->name('repository.destroy')->middleware('acl:delete');
});

// Autocomplete (used by AJAX lookups)
Route::get('/repository/autocomplete', [RepositoryController::class, 'autocomplete'])->name('repository.autocomplete');

// Upload limit exceeded page
Route::get('/repository/{slug}/uploadLimitExceeded', [RepositoryController::class, 'uploadLimitExceeded'])->name('repository.uploadLimitExceeded');

Route::get('/repository/{slug}/print', [RepositoryController::class, 'print'])->name('repository.print');
Route::get('/repository/{slug}', [RepositoryController::class, 'show'])->name('repository.show');
