<?php

use AhgGallery\Controllers\GalleryController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/gallery/browse', [GalleryController::class, 'browse'])->name('gallery.browse');
Route::get('/gallery/artists', [GalleryController::class, 'artists'])->name('gallery.artists');
Route::get('/gallery/artists/{id}', [GalleryController::class, 'showArtist'])->name('gallery.artists.show')->where('id', '[0-9]+');

// Public dashboard and index
Route::get('/gallery/dashboard', [GalleryController::class, 'dashboard'])->name('gallery.dashboard');
Route::get('/gallery/index', [GalleryController::class, 'galleryIndex'])->name('gallery.index');
Route::get('/gallery/loans', [GalleryController::class, 'loans'])->name('gallery.loans');
Route::get('/gallery/loans/{id}', [GalleryController::class, 'showLoan'])->name('gallery.loans.show')->where('id', '[0-9]+');
Route::get('/gallery/valuations', [GalleryController::class, 'valuations'])->name('gallery.valuations');
Route::get('/gallery/valuations/{id}', [GalleryController::class, 'showValuation'])->name('gallery.valuations.show')->where('id', '[0-9]+');
Route::get('/gallery/venues', [GalleryController::class, 'venues'])->name('gallery.venues');
Route::get('/gallery/venues/{id}', [GalleryController::class, 'showVenue'])->name('gallery.venues.show')->where('id', '[0-9]+');
Route::get('/gallery/facility-report/{id}', [GalleryController::class, 'facilityReport'])->name('gallery.facility-report')->where('id', '[0-9]+');

// Gallery Reports
Route::get('/gallery-reports', [GalleryController::class, 'reportsIndex'])->name('gallery-reports.index');
Route::get('/gallery-reports/exhibitions', [GalleryController::class, 'reportsExhibitions'])->name('gallery-reports.exhibitions');
Route::get('/gallery-reports/facility-reports', [GalleryController::class, 'reportsFacilityReports'])->name('gallery-reports.facility-reports');
Route::get('/gallery-reports/loans', [GalleryController::class, 'reportsLoans'])->name('gallery-reports.loans');
Route::get('/gallery-reports/spaces', [GalleryController::class, 'reportsSpaces'])->name('gallery-reports.spaces');
Route::get('/gallery-reports/valuations', [GalleryController::class, 'reportsValuations'])->name('gallery-reports.valuations');

// Authenticated routes (before slug catch-all)
Route::middleware('auth')->group(function () {
    Route::get('/gallery/add', [GalleryController::class, 'create'])->name('gallery.create');
    Route::post('/gallery/store', [GalleryController::class, 'store'])->name('gallery.store');
    Route::get('/gallery/artists/create', [GalleryController::class, 'createArtist'])->name('gallery.artists.create');
    Route::post('/gallery/artists/store', [GalleryController::class, 'storeArtist'])->name('gallery.artists.store');
    Route::get('/gallery/{slug}/edit', [GalleryController::class, 'edit'])->name('gallery.edit')->where('slug', '[a-z0-9\-]+');
    Route::put('/gallery/{slug}', [GalleryController::class, 'update'])->name('gallery.update')->where('slug', '[a-z0-9\-]+');
    Route::post('/gallery/{slug}/delete', [GalleryController::class, 'destroy'])->name('gallery.destroy')->where('slug', '[a-z0-9\-]+');

    Route::get('/gallery/loans/create', [GalleryController::class, 'createLoan'])->name('gallery.loans.create');
    Route::post('/gallery/loans/store', [GalleryController::class, 'storeLoan'])->name('gallery.loans.store');
    Route::get('/gallery/valuations/create', [GalleryController::class, 'createValuation'])->name('gallery.valuations.create');
    Route::post('/gallery/valuations/store', [GalleryController::class, 'storeValuation'])->name('gallery.valuations.store');
    Route::get('/gallery/venues/create', [GalleryController::class, 'createVenue'])->name('gallery.venues.create');
    Route::post('/gallery/venues/store', [GalleryController::class, 'storeVenue'])->name('gallery.venues.store');
});

// Slug catch-all (must be last)
Route::get('/gallery/{slug}', [GalleryController::class, 'show'])->name('gallery.show')->where('slug', '[a-z0-9\-]+');
