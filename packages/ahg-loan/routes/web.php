<?php

use AhgLoan\Controllers\LoanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.required')->group(function () {
    Route::get('/loan', [LoanController::class, 'index'])->name('loan.index');
    Route::get('/loan/create', [LoanController::class, 'create'])->name('loan.create');
    Route::post('/loan/create', [LoanController::class, 'store'])->name('loan.store');
    Route::get('/loan/search-objects', [LoanController::class, 'searchObjects'])->name('loan.search-objects');
    Route::get('/loan/{id}', [LoanController::class, 'show'])->name('loan.show');
    Route::get('/loan/{id}/edit', [LoanController::class, 'edit'])->name('loan.edit');
    Route::post('/loan/{id}/edit', [LoanController::class, 'update'])->name('loan.update');
    Route::post('/loan/{id}/delete', [LoanController::class, 'delete'])->name('loan.delete');
    Route::post('/loan/{id}/add-object', [LoanController::class, 'addObject'])->name('loan.add-object');
    Route::post('/loan/{id}/remove-object/{objectId}', [LoanController::class, 'removeObject'])->name('loan.remove-object');
    Route::post('/loan/{id}/transition', [LoanController::class, 'transition'])->name('loan.transition');
    Route::post('/loan/{id}/extend', [LoanController::class, 'extend'])->name('loan.extend');
    Route::post('/loan/{id}/return', [LoanController::class, 'returnLoan'])->name('loan.return');
    Route::post('/loan/{id}/upload-document', [LoanController::class, 'uploadDocument'])->name('loan.upload-document');
});
