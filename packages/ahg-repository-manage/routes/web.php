<?php

use AhgRepositoryManage\Controllers\RepositoryController;
use Illuminate\Support\Facades\Route;

Route::get('/repository/browse', [RepositoryController::class, 'browse'])->name('repository.browse');

Route::middleware('auth')->group(function () {
    Route::get('/repository/add', [RepositoryController::class, 'create'])->name('repository.create');
    Route::post('/repository/add', [RepositoryController::class, 'store'])->name('repository.store');
    Route::get('/repository/{slug}/edit', [RepositoryController::class, 'edit'])->name('repository.edit');
    Route::post('/repository/{slug}/edit', [RepositoryController::class, 'update'])->name('repository.update');
});

Route::middleware('admin')->group(function () {
    Route::get('/repository/{slug}/delete', [RepositoryController::class, 'confirmDelete'])->name('repository.confirmDelete');
    Route::delete('/repository/{slug}/delete', [RepositoryController::class, 'destroy'])->name('repository.destroy');
});

Route::get('/repository/{slug}/print', [RepositoryController::class, 'print'])->name('repository.print');
Route::get('/repository/{slug}', [RepositoryController::class, 'show'])->name('repository.show');
