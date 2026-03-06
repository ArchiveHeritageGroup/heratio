<?php

use AhgDonorManage\Controllers\DonorController;
use Illuminate\Support\Facades\Route;

Route::get('/donor/browse', [DonorController::class, 'browse'])->name('donor.browse');
Route::get('/donor/{slug}', [DonorController::class, 'show'])->name('donor.show');
