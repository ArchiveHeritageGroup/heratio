<?php

use AhgDonorManage\Controllers\DonorController;
use Illuminate\Support\Facades\Route;

Route::get('/donor/browse', [DonorController::class, 'browse'])->name('donor.browse');

Route::middleware('auth')->group(function () {
    Route::get('/donor/add', [DonorController::class, 'create'])->name('donor.create');
    Route::post('/donor/add', [DonorController::class, 'store'])->name('donor.store');
    Route::get('/donor/{slug}/edit', [DonorController::class, 'edit'])->name('donor.edit');
    Route::post('/donor/{slug}/edit', [DonorController::class, 'update'])->name('donor.update');
});

Route::middleware('admin')->group(function () {
    Route::get('/donor/{slug}/delete', [DonorController::class, 'confirmDelete'])->name('donor.confirmDelete');
    Route::delete('/donor/{slug}/delete', [DonorController::class, 'destroy'])->name('donor.destroy');
});

Route::get('/donor/{slug}', [DonorController::class, 'show'])->name('donor.show');
