<?php

use AhgImageAnimate\Controllers\ImageAnimateController;
use Illuminate\Support\Facades\Route;

// Auth-only — user-triggered animation generation from the IO show page.
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/image-animate/generate/{ioId}', [ImageAnimateController::class, 'userGenerate'])
        ->where('ioId', '[0-9]+')
        ->name('image-animate.generate');

    Route::post('/image-animate/{id}/delete', [ImageAnimateController::class, 'delete'])
        ->where('id', '[0-9]+')
        ->name('image-animate.delete');
});

// Admin-only — settings page.
Route::middleware(['web', 'admin'])->group(function () {
    Route::match(['get', 'post'], '/admin/image-animate/settings', [ImageAnimateController::class, 'settings'])
        ->name('admin.image-animate.settings');
});
