<?php

/**
 * Invoicing sub-ledger routes (heratio#1475 item 5). Supplier and financial
 * data, so even the read pages require the update permission.
 */

use AhgLibrary\Controllers\LibraryLedgerController as C;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'acl:update'])->prefix('library-manage/acquisition/ledger')->name('library.ledger.')->group(function () {
    Route::get('/', [C::class, 'index'])->name('index');
    Route::get('/aged-creditors', [C::class, 'aged'])->name('aged');
    Route::get('/budget-match', [C::class, 'budgetMatch'])->name('budget-match');

    Route::get('/export', [C::class, 'exports'])->name('exports');
    Route::post('/export', [C::class, 'createExport'])->name('export-create');
    Route::get('/export/{batch}', [C::class, 'downloadExport'])->name('export-download')->where('batch', 'LEDGER-[0-9]{8}-[0-9]{6}-[a-z0-9]{6}');

    Route::get('/invoice/create', [C::class, 'create'])->name('create');
    Route::post('/invoice', [C::class, 'store'])->name('store')->middleware('acl:create');
    Route::get('/document/{id}', [C::class, 'show'])->name('show')->where('id', '[0-9]+');
    Route::get('/document/{id}/edit', [C::class, 'edit'])->name('edit')->where('id', '[0-9]+');
    Route::put('/document/{id}', [C::class, 'update'])->name('update')->where('id', '[0-9]+');
    Route::post('/document/{id}/post', [C::class, 'post'])->name('post')->where('id', '[0-9]+');
    Route::post('/document/{id}/void', [C::class, 'void'])->name('void')->where('id', '[0-9]+')->middleware('acl:delete');
    Route::get('/document/{id}/credit', [C::class, 'creditForm'])->name('credit')->where('id', '[0-9]+');
    Route::post('/document/{id}/credit', [C::class, 'creditStore'])->name('credit-store')->where('id', '[0-9]+')->middleware('acl:create');
    Route::post('/document/{id}/payment', [C::class, 'paymentStore'])->name('payment')->where('id', '[0-9]+');
    Route::post('/document/{id}/payment/{paymentId}/reverse', [C::class, 'paymentReverse'])->name('payment-reverse')->where(['id' => '[0-9]+', 'paymentId' => '[0-9]+']);
});

// Tax configuration decides what every future invoice calculates: administrators only.
Route::middleware(['auth', 'admin'])->prefix('library-manage/acquisition/ledger/tax')->name('library.ledger.')->group(function () {
    Route::get('/', [C::class, 'rates'])->name('rates');
    Route::post('/', [C::class, 'rateStore'])->name('rate-store');
    Route::post('/rounding', [C::class, 'roundingStore'])->name('rounding');
    Route::post('/{id}/end', [C::class, 'rateEnd'])->name('rate-end')->where('id', '[0-9]+');
});
