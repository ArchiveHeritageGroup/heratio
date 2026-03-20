<?php

use AhgLibrary\Controllers\LibraryController;
use Illuminate\Support\Facades\Route;

Route::get('/library', [LibraryController::class, 'browse'])->name('library.browse');

Route::middleware('auth')->group(function () {
    Route::get('/library/add', [LibraryController::class, 'create'])->name('library.create');
    Route::post('/library/store', [LibraryController::class, 'store'])->name('library.store');
    Route::get('/library/{slug}/edit', [LibraryController::class, 'edit'])->name('library.edit');
    Route::put('/library/{slug}', [LibraryController::class, 'update'])->name('library.update');
    Route::post('/library/{slug}/delete', [LibraryController::class, 'destroy'])->name('library.destroy');
});

Route::get('/library/{slug}', [LibraryController::class, 'show'])->name('library.show');
