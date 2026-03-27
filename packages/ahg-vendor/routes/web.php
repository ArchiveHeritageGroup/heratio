<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/vendor')->middleware(['web', 'auth'])->group(function () {
    Route::get('/add', [\AhgVendor\Controllers\VendorController::class, 'add'])->name('ahgvendor.add');
    Route::get('/add-transaction', [\AhgVendor\Controllers\VendorController::class, 'addTransaction'])->name('ahgvendor.add-transaction');
    Route::get('/edit', [\AhgVendor\Controllers\VendorController::class, 'edit'])->name('ahgvendor.edit');
    Route::get('/edit-transaction', [\AhgVendor\Controllers\VendorController::class, 'editTransaction'])->name('ahgvendor.edit-transaction');
    Route::get('/index', [\AhgVendor\Controllers\VendorController::class, 'index'])->name('ahgvendor.index');
    Route::get('/list', [\AhgVendor\Controllers\VendorController::class, 'list'])->name('ahgvendor.list');
    Route::get('/service-types', [\AhgVendor\Controllers\VendorController::class, 'serviceTypes'])->name('ahgvendor.service-types');
    Route::get('/transactions', [\AhgVendor\Controllers\VendorController::class, 'transactions'])->name('ahgvendor.transactions');
    Route::get('/view', [\AhgVendor\Controllers\VendorController::class, 'view'])->name('ahgvendor.view');
    Route::get('/view-transaction', [\AhgVendor\Controllers\VendorController::class, 'viewTransaction'])->name('ahgvendor.view-transaction');
    Route::get('/browse', [\AhgVendor\Controllers\VendorController::class, 'browse'])->name('ahgvendor.browse');
});
