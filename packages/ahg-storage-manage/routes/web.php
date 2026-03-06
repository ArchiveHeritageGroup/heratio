<?php

use AhgStorageManage\Controllers\StorageController;
use Illuminate\Support\Facades\Route;

Route::get('/physicalobject/browse', [StorageController::class, 'browse'])->name('physicalobject.browse');
Route::get('/physicalobject/{slug}', [StorageController::class, 'show'])->name('physicalobject.show');
