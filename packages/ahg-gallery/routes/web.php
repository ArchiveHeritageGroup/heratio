<?php

use AhgGallery\Controllers\GalleryController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/gallery/browse', [GalleryController::class, 'browse'])->name('gallery.browse');
Route::get('/gallery/artists', [GalleryController::class, 'artists'])->name('gallery.artists');
Route::get('/gallery/artists/{id}', [GalleryController::class, 'showArtist'])->name('gallery.artists.show')->where('id', '[0-9]+');

// Authenticated routes (before slug catch-all)
Route::middleware('auth')->group(function () {
    Route::get('/gallery/add', [GalleryController::class, 'create'])->name('gallery.create');
    Route::post('/gallery/store', [GalleryController::class, 'store'])->name('gallery.store');
    Route::get('/gallery/artists/create', [GalleryController::class, 'createArtist'])->name('gallery.artists.create');
    Route::post('/gallery/artists/store', [GalleryController::class, 'storeArtist'])->name('gallery.artists.store');
    Route::get('/gallery/{slug}/edit', [GalleryController::class, 'edit'])->name('gallery.edit')->where('slug', '[a-z0-9\-]+');
    Route::put('/gallery/{slug}', [GalleryController::class, 'update'])->name('gallery.update')->where('slug', '[a-z0-9\-]+');
    Route::post('/gallery/{slug}/delete', [GalleryController::class, 'destroy'])->name('gallery.destroy')->where('slug', '[a-z0-9\-]+');
});

// Slug catch-all (must be last)
Route::get('/gallery/{slug}', [GalleryController::class, 'show'])->name('gallery.show')->where('slug', '[a-z0-9\-]+');
