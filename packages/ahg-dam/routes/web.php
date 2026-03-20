<?php

use AhgDam\Controllers\DamController;
use Illuminate\Support\Facades\Route;

Route::get('/dam', [DamController::class, 'dashboard'])->name('dam.dashboard');
Route::get('/dam/browse', [DamController::class, 'browse'])->name('dam.browse');

Route::middleware('auth')->group(function () {
    Route::get('/dam/create', [DamController::class, 'create'])->name('dam.create');
    Route::post('/dam/store', [DamController::class, 'store'])->name('dam.store');
    Route::get('/dam/{slug}/edit', [DamController::class, 'edit'])->name('dam.edit')
        ->where('slug', '[a-z0-9\-]+');
    Route::put('/dam/{slug}', [DamController::class, 'update'])->name('dam.update')
        ->where('slug', '[a-z0-9\-]+');
    Route::post('/dam/{slug}/delete', [DamController::class, 'destroy'])->name('dam.destroy')
        ->where('slug', '[a-z0-9\-]+');
});

Route::get('/dam/{slug}', [DamController::class, 'show'])->name('dam.show')
    ->where('slug', '(?!browse|create|dashboard)[a-z0-9\-]+');
