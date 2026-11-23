<?php

use AhgLibrary\Controllers\CashDeskController;
use Illuminate\Support\Facades\Route;

// Fine payment, waiver and cash desk (#1474). Waiving and reconciling move or
// write off money, so they need more than the desk's ordinary update right.
Route::middleware(['auth', 'acl:update'])->group(function () {
    Route::get('/library-manage/circulation/patron/{patronId}/fines', [CashDeskController::class, 'fines'])
        ->name('library.fines.index')->where('patronId', '[0-9]+');
    Route::post('/library-manage/circulation/patron/{patronId}/fines/pay', [CashDeskController::class, 'pay'])
        ->name('library.fines.pay')->where('patronId', '[0-9]+');
    Route::get('/library-manage/circulation/fines/receipt/{id}', [CashDeskController::class, 'receipt'])
        ->name('library.fines.receipt')->where('id', '[0-9]+');

    Route::get('/library-manage/cash-desk', [CashDeskController::class, 'index'])->name('library.cash-desk.index');
    Route::post('/library-manage/cash-desk/open', [CashDeskController::class, 'open'])->name('library.cash-desk.open');
    Route::get('/library-manage/cash-desk/end-of-day', [CashDeskController::class, 'endOfDay'])->name('library.cash-desk.end-of-day');
    Route::get('/library-manage/cash-desk/session/{id}', [CashDeskController::class, 'session'])
        ->name('library.cash-desk.session')->where('id', '[0-9]+');
    Route::post('/library-manage/cash-desk/session/{id}/close', [CashDeskController::class, 'close'])
        ->name('library.cash-desk.close')->where('id', '[0-9]+');
});

Route::middleware(['auth', 'acl:delete'])->group(function () {
    Route::post('/library-manage/circulation/fines/{fineId}/waive', [CashDeskController::class, 'waive'])
        ->name('library.fines.waive')->where('fineId', '[0-9]+');
    Route::post('/library-manage/cash-desk/session/{id}/reconcile', [CashDeskController::class, 'reconcile'])
        ->name('library.cash-desk.reconcile')->where('id', '[0-9]+');
});
