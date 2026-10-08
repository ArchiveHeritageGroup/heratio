<?php

use AhgStorageManage\Controllers\StorageController;
use AhgStorageManage\Controllers\StorageLocationController;
use AhgStorageManage\Controllers\StrongroomController;
use Illuminate\Support\Facades\Route;

// Physical storage locations + security levels are staff-only - require auth on
// the read surface so anon can't map the building/vault layout (#1364).
Route::get('/physicalobject/browse', [StorageController::class, 'browse'])->name('physicalobject.browse')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/physicalobject/add', [StorageController::class, 'create'])->name('physicalobject.create');
    Route::post('/physicalobject/add', [StorageController::class, 'store'])->name('physicalobject.store')->middleware('acl:create');
    Route::get('/physicalobject/{slug}/edit', [StorageController::class, 'edit'])->name('physicalobject.edit');
    Route::post('/physicalobject/{slug}/edit', [StorageController::class, 'update'])->name('physicalobject.update')->middleware('acl:update');
});

Route::middleware('admin')->group(function () {
    Route::get('/physicalobject/{slug}/delete', [StorageController::class, 'confirmDelete'])->name('physicalobject.confirmDelete');
    Route::delete('/physicalobject/{slug}/delete', [StorageController::class, 'destroy'])->name('physicalobject.destroy')->middleware('acl:delete');
});

Route::middleware('auth')->group(function () {
    Route::get('/physicalobject/holdingsReportExport', [StorageController::class, 'holdingsReportExport'])->name('physicalobject.holdings-export');
    Route::get('/physicalobject/box-list', [StorageController::class, 'boxList'])->name('physicalobject.box-list');
    Route::get('/physicalobject/link-to/{slug}', [StorageController::class, 'linkTo'])->name('physicalobject.link-to');
    Route::post('/physicalobject/link-to/{slug}', [StorageController::class, 'linkToStore'])->name('physicalobject.link-to.store')->middleware('acl:create'); // #1395(A)
    Route::post('/physicalobject/unlink/{relationId}', [StorageController::class, 'unlink'])->name('physicalobject.unlink')->middleware('acl:delete'); // #1395(A) was auth-only → any user deleted any relation row
});

// Specific routes MUST come before /{slug} or they get swallowed.
Route::get('/physicalobject/autocomplete', [StorageController::class, 'autocomplete'])->name('physicalobject.autocomplete')->middleware('auth');
Route::get('/physicalobject/boxList', fn () => redirect('/physicalobject/box-list', 301));

// heratio#1514 - move one object between storage locations, or out of storage.
Route::middleware('auth')->group(function () {
    Route::get('/physicalobject/{slug}/move', [StorageController::class, 'move'])->name('physicalobject.move');
    Route::post('/physicalobject/{slug}/move', [StorageController::class, 'moveStore'])->name('physicalobject.move.store')->middleware('acl:update');
});

Route::get('/physicalobject/{slug}', [StorageController::class, 'show'])
    ->name('physicalobject.show')
    ->middleware('auth')
    ->where('slug', '(?!browse|add|autocomplete|box-list|boxList|holdingsReportExport|link-to|unlink)[a-z0-9][a-z0-9-]*');

// ---------------------------------------------------------------------------
// heratio#144 - Strongroom space allocation (rebuild 2026-05-23).
// Mirrors the PSIS Symfony pattern shipped in atom-ahg-plugins v3.40.0:
// standalone CRUD only, no physicalobject-form integration.
// ---------------------------------------------------------------------------
Route::get('/strongroom/browse', [StrongroomController::class, 'browse'])->name('strongroom.browse')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/strongroom/add', [StrongroomController::class, 'create'])->name('strongroom.create');
    Route::post('/strongroom/add', [StrongroomController::class, 'store'])->name('strongroom.store')->middleware('acl:create');
    Route::get('/strongroom/{slug}/edit', [StrongroomController::class, 'edit'])->name('strongroom.edit');
    Route::post('/strongroom/{slug}/edit', [StrongroomController::class, 'update'])->name('strongroom.update')->middleware('acl:update');
});

Route::middleware('admin')->group(function () {
    Route::get('/strongroom/{slug}/delete', [StrongroomController::class, 'confirmDelete'])->name('strongroom.confirmDelete');
    Route::delete('/strongroom/{slug}/delete', [StrongroomController::class, 'destroy'])->name('strongroom.destroy')->middleware('acl:delete');
});

// Specific routes (browse, add) declared above; the catch-all {slug} must come
// last and excludes those paths so they aren't swallowed.
Route::get('/strongroom/{slug}', [StrongroomController::class, 'show'])
    ->name('strongroom.show')
    ->middleware('auth')
    ->where('slug', '(?!browse|add)[a-z0-9][a-z0-9-]*');

// ---------------------------------------------------------------------------
// heratio#1514 / atom-ahg-plugins#193 - Hierarchical storage locations.
// Port of the AtoM storageLocation module. Staff-only like the rest of the
// storage surface (#1364): reads need auth, writes acl, delete admin.
// ---------------------------------------------------------------------------
Route::middleware('auth')->group(function () {
    Route::get('/storagelocation/browse', [StorageLocationController::class, 'browse'])->name('storagelocation.browse');
    Route::get('/storagelocation/add', [StorageLocationController::class, 'create'])->name('storagelocation.create');
    Route::post('/storagelocation/add', [StorageLocationController::class, 'store'])->name('storagelocation.store')->middleware('acl:create');
    Route::get('/storagelocation/api/locations', [StorageLocationController::class, 'apiLocations'])->name('storagelocation.api.locations');
    Route::get('/storagelocation/api/tree', [StorageLocationController::class, 'apiTree'])->name('storagelocation.api.tree');
    Route::get('/storagelocation/api/search', [StorageLocationController::class, 'apiSearch'])->name('storagelocation.api.search');
    Route::get('/storagelocation/{slug}/edit', [StorageLocationController::class, 'edit'])->name('storagelocation.edit');
    Route::post('/storagelocation/{slug}/edit', [StorageLocationController::class, 'update'])->name('storagelocation.update')->middleware('acl:update');
    // Bulk move of the objects held here (heratio#1514).
    Route::post('/storagelocation/{slug}/move-objects', [StorageLocationController::class, 'moveObjects'])->name('storagelocation.move-objects')->middleware('acl:update');
});

Route::middleware('admin')->group(function () {
    Route::get('/storagelocation/{slug}/delete', [StorageLocationController::class, 'confirmDelete'])->name('storagelocation.confirmDelete');
    Route::delete('/storagelocation/{slug}/delete', [StorageLocationController::class, 'destroy'])->name('storagelocation.destroy')->middleware('acl:delete');
    // heratio#1528 - first placement of unplaced objects, editors and administrators.
    Route::post('/storagelocation/{slug}/place-objects', [StorageLocationController::class, 'placeObjects'])->name('storagelocation.place-objects');
});

Route::get('/storagelocation/{slug}', [StorageLocationController::class, 'show'])
    ->name('storagelocation.show')
    ->middleware('auth')
    ->where('slug', '(?!browse|add|api)[a-z0-9][a-z0-9-]*');
