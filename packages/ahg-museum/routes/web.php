<?php

use AhgMuseum\Controllers\MuseumController;
use Illuminate\Support\Facades\Route;

Route::get('/museum/browse', [MuseumController::class, 'browse'])->name('museum.browse');

Route::middleware('auth')->group(function () {
    Route::get('/museum/add', [MuseumController::class, 'create'])->name('museum.create');
    Route::post('/museum/store', [MuseumController::class, 'store'])->name('museum.store');
    Route::get('/museum/{slug}/edit', [MuseumController::class, 'edit'])->name('museum.edit')->where('slug', '[a-z0-9][a-z0-9-]*');
    Route::put('/museum/{slug}', [MuseumController::class, 'update'])->name('museum.update')->where('slug', '[a-z0-9][a-z0-9-]*');
    Route::post('/museum/{slug}/delete', [MuseumController::class, 'destroy'])->name('museum.destroy')->where('slug', '[a-z0-9][a-z0-9-]*');
});

Route::get('/museum/{slug}', [MuseumController::class, 'show'])->name('museum.show')->where('slug', '[a-z0-9][a-z0-9-]*');
