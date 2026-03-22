<?php

use AhgIiifCollection\Controllers\IiifCollectionController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/manifest-collections', [IiifCollectionController::class, 'index'])->name('iiif-collection.index');
Route::get('/manifest-collection/{id}/view', [IiifCollectionController::class, 'view'])->name('iiif-collection.view');
Route::get('/manifest-collection/{slug}/manifest.json', [IiifCollectionController::class, 'manifest'])->name('iiif-collection.manifest');
Route::get('/iiif-manifest/{slug}', [IiifCollectionController::class, 'objectManifest'])->name('iiif-collection.object-manifest');

// IIIF Auth endpoints (public, for IIIF Auth API)
Route::get('/iiif-auth/access-service-close', function () { return view('ahg-iiif-collection::iiifAuth.access-service-close'); })->name('iiif-auth.access-service-close');
Route::get('/iiif-auth/access-token-iframe', function (\Illuminate\Http\Request $r) { return view('ahg-iiif-collection::iiifAuth.access-token-iframe', ['tokenData' => $r->input('tokenData', []), 'origin' => $r->input('origin', '*')]); })->name('iiif-auth.access-token-iframe');
Route::get('/iiif-auth/auth-failed', function () { return view('ahg-iiif-collection::iiifAuth.auth-failed'); })->name('iiif-auth.auth-failed');
Route::get('/iiif-auth/auth-success', function () { return view('ahg-iiif-collection::iiifAuth.auth-success'); })->name('iiif-auth.auth-success');
Route::get('/iiif-auth/clickthrough', function () { return view('ahg-iiif-collection::iiifAuth.clickthrough', ['terms' => '', 'acceptUrl' => '']); })->name('iiif-auth.clickthrough');
Route::get('/iiif-auth/logout-success', function () { return view('ahg-iiif-collection::iiifAuth.logout-success'); })->name('iiif-auth.logout-success');

// IIIF viewer/compare/validation (public)
Route::get('/iiif/viewer/{slug}', [IiifCollectionController::class, 'viewer'])->name('iiif.viewer');
Route::get('/iiif/compare', [IiifCollectionController::class, 'compare'])->name('iiif.compare');

// Authenticated routes
Route::middleware('auth.required')->group(function () {
    Route::get('/manifest-collection/new', [IiifCollectionController::class, 'create'])->name('iiif-collection.create');
    Route::post('/manifest-collection', [IiifCollectionController::class, 'store'])->name('iiif-collection.store');
    Route::get('/manifest-collection/{id}/edit', [IiifCollectionController::class, 'edit'])->name('iiif-collection.edit');
    Route::put('/manifest-collection/{id}', [IiifCollectionController::class, 'update'])->name('iiif-collection.update');
    Route::delete('/manifest-collection/{id}', [IiifCollectionController::class, 'destroy'])->name('iiif-collection.destroy');
    Route::match(['get', 'post'], '/manifest-collection/{id}/items/add', [IiifCollectionController::class, 'addItems'])->name('iiif-collection.add-items');
    Route::get('/manifest-collection/remove-item', [IiifCollectionController::class, 'removeItem'])->name('iiif-collection.remove-item');
    Route::post('/manifest-collection/reorder', [IiifCollectionController::class, 'reorder'])->name('iiif-collection.reorder');
    Route::get('/manifest-collections/autocomplete', [IiifCollectionController::class, 'autocomplete'])->name('iiif-collection.autocomplete');

    // IIIF Settings
    Route::get('/iiif/settings', [IiifCollectionController::class, 'settings'])->name('iiif.settings');
    Route::post('/iiif/settings', [IiifCollectionController::class, 'settingsUpdate'])->name('iiif.settings.update');

    // IIIF Validation
    Route::get('/iiif/validation-dashboard', [IiifCollectionController::class, 'validationDashboard'])->name('iiif.validation-dashboard');

    // Media Settings
    Route::get('/iiif/media-settings/queue', [IiifCollectionController::class, 'mediaQueue'])->name('iiif.media-settings.queue');
    Route::get('/iiif/media-settings/test', [IiifCollectionController::class, 'mediaTest'])->name('iiif.media-settings.test');
    Route::post('/iiif/media-settings/test', [IiifCollectionController::class, 'mediaTestRun'])->name('iiif.media-settings.test.run');

    // 3D Reports
    Route::get('/iiif/three-d-reports', [IiifCollectionController::class, 'threeDIndex'])->name('iiif.three-d-reports.index');
    Route::get('/iiif/three-d-reports/digital-objects', [IiifCollectionController::class, 'threeDDigitalObjects'])->name('iiif.three-d-reports.digital-objects');
    Route::get('/iiif/three-d-reports/hotspots', [IiifCollectionController::class, 'threeDHotspots'])->name('iiif.three-d-reports.hotspots');
    Route::get('/iiif/three-d-reports/models', [IiifCollectionController::class, 'threeDModels'])->name('iiif.three-d-reports.models');
    Route::get('/iiif/three-d-reports/settings', [IiifCollectionController::class, 'threeDSettings'])->name('iiif.three-d-reports.settings');
    Route::get('/iiif/three-d-reports/thumbnails', [IiifCollectionController::class, 'threeDThumbnails'])->name('iiif.three-d-reports.thumbnails');
});
