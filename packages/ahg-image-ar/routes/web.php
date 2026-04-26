<?php

use AhgImageAr\Controllers\ImageArController;
use Illuminate\Support\Facades\Route;

// Auth-only — admin/curator can build / rebuild / delete an animation.
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/image-ar/generate/{ioId}', [ImageArController::class, 'userGenerate'])
        ->where('ioId', '[0-9]+')
        ->name('image-ar.generate');

    Route::post('/image-ar/{id}/delete', [ImageArController::class, 'delete'])
        ->where('id', '[0-9]+')
        ->name('image-ar.delete');
});

// Admin-only — settings page.
Route::middleware(['web', 'admin'])->group(function () {
    Route::match(['get', 'post'], '/admin/image-ar/settings', [ImageArController::class, 'settings'])
        ->name('admin.image-ar.settings');
});
