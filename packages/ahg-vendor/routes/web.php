<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/vendor')->middleware(['web'])->group(function () {
    Route::get('/add', [\AhgIcip\Controllers\IcipController::class, 'add'])->name('ahgvendor.add');
    Route::get('/add-transaction', [\AhgIcip\Controllers\IcipController::class, 'addTransaction'])->name('ahgvendor.add-transaction');
    Route::get('/edit', [\AhgIcip\Controllers\IcipController::class, 'edit'])->name('ahgvendor.edit');
    Route::get('/edit-transaction', [\AhgIcip\Controllers\IcipController::class, 'editTransaction'])->name('ahgvendor.edit-transaction');
    Route::get('/index', [\AhgIcip\Controllers\IcipController::class, 'index'])->name('ahgvendor.index');
    Route::get('/list', [\AhgIcip\Controllers\IcipController::class, 'list'])->name('ahgvendor.list');
    Route::get('/service-types', [\AhgIcip\Controllers\IcipController::class, 'serviceTypes'])->name('ahgvendor.service-types');
    Route::get('/transactions', [\AhgIcip\Controllers\IcipController::class, 'transactions'])->name('ahgvendor.transactions');
    Route::get('/view', [\AhgIcip\Controllers\IcipController::class, 'view'])->name('ahgvendor.view');
    Route::get('/view-transaction', [\AhgIcip\Controllers\IcipController::class, 'viewTransaction'])->name('ahgvendor.view-transaction');
    Route::get('/browse', [\AhgIcip\Controllers\IcipController::class, 'browse'])->name('ahgvendor.browse');
});
