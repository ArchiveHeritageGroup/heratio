<?php

use AhgStorageManage\Controllers\StorageController;
use Illuminate\Support\Facades\Route;

Route::get('/physicalobject/browse', [StorageController::class, 'browse'])->name('physicalobject.browse');

Route::middleware('auth')->group(function () {
    Route::get('/physicalobject/add', [StorageController::class, 'create'])->name('physicalobject.create');
    Route::post('/physicalobject/add', [StorageController::class, 'store'])->name('physicalobject.store');
    Route::get('/physicalobject/{slug}/edit', [StorageController::class, 'edit'])->name('physicalobject.edit');
    Route::post('/physicalobject/{slug}/edit', [StorageController::class, 'update'])->name('physicalobject.update');
});

Route::middleware('admin')->group(function () {
    Route::get('/physicalobject/{slug}/delete', [StorageController::class, 'confirmDelete'])->name('physicalobject.confirmDelete');
    Route::delete('/physicalobject/{slug}/delete', [StorageController::class, 'destroy'])->name('physicalobject.destroy');
});

Route::get('/physicalobject/holdingsReportExport', [StorageController::class, 'holdingsReportExport'])->name('physicalobject.holdings-export');
Route::get('/physicalobject/{slug}', [StorageController::class, 'show'])->name('physicalobject.show');
