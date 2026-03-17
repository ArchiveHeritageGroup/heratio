<?php

use AhgIntegrity\Controllers\IntegrityController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/integrity/index', [IntegrityController::class, 'index'])->name('integrity.index');
});
