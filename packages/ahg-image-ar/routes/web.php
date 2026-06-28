<?php

use AhgImageAr\Controllers\ImageArController;
use Illuminate\Support\Facades\Route;

// Auth-only — admin/curator can build / rebuild / delete an animation.
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/image-ar/generate/{ioId}', [ImageArController::class, 'userGenerate'])
        ->where('ioId', '[0-9]+')
        ->name('image-ar.generate');

    // #1361: deleting an animation (removes the row + its MP4) was reachable by ANY
    // authenticated user by id (no ownership/admin check) — require admin.
    Route::post('/image-ar/{id}/delete', [ImageArController::class, 'delete'])
        ->where('id', '[0-9]+')
        ->middleware('admin')
        ->name('image-ar.delete');
});

// #1361 — gated MP4 delivery (public, but publication-status + ODRL gated inside,
// then X-Accel-Redirect to the `internal` /uploads/ar/ nginx location). Replaces
// the direct static /uploads/ar/ URL in the IO viewer so draft/restricted
// animations aren't fetchable by anon.
Route::middleware('web')->get('/image-ar/{ioId}/video', [ImageArController::class, 'streamVideo'])
    ->where('ioId', '[0-9]+')
    ->name('image-ar.video');

// Admin-only — settings page.
Route::middleware(['web', 'admin'])->group(function () {
    Route::match(['get', 'post'], '/admin/image-ar/settings', [ImageArController::class, 'settings'])
        ->name('admin.image-ar.settings');
});
