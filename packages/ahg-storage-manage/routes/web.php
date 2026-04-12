<?php

use AhgStorageManage\Controllers\StorageController;
use Illuminate\Support\Facades\Route;

Route::get('/physicalobject/browse', [StorageController::class, 'browse'])->name('physicalobject.browse');

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
    Route::post('/physicalobject/link-to/{slug}', [StorageController::class, 'linkToStore'])->name('physicalobject.link-to.store');
    Route::post('/physicalobject/unlink/{relationId}', [StorageController::class, 'unlink'])->name('physicalobject.unlink');
});

// Specific routes MUST come before /{slug} or they get swallowed.
Route::get('/physicalobject/autocomplete', [StorageController::class, 'autocomplete'])->name('physicalobject.autocomplete');
Route::get('/physicalobject/boxList', fn () => redirect('/physicalobject/box-list', 301));

Route::get('/physicalobject/{slug}', [StorageController::class, 'show'])
    ->name('physicalobject.show')
    ->where('slug', '(?!browse|add|autocomplete|box-list|boxList|holdingsReportExport|link-to|unlink)[a-z0-9][a-z0-9-]*');
