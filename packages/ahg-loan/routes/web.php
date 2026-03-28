<?php

use AhgLoan\Controllers\LoanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/loan', [LoanController::class, 'index'])->name('loan.index');
    Route::get('/loan/create', [LoanController::class, 'create'])->name('loan.create');
    Route::post('/loan/create', [LoanController::class, 'store'])->name('loan.store')->middleware('acl:create');
    Route::get('/loan/search-objects', [LoanController::class, 'searchObjects'])->name('loan.search-objects');
    Route::get('/loan/{id}', [LoanController::class, 'show'])->name('loan.show');
    Route::get('/loan/{id}/edit', [LoanController::class, 'edit'])->name('loan.edit');
    Route::post('/loan/{id}/edit', [LoanController::class, 'update'])->name('loan.update')->middleware('acl:update');
    Route::post('/loan/{id}/delete', [LoanController::class, 'delete'])->name('loan.delete')->middleware('acl:delete');
    Route::post('/loan/{id}/add-object', [LoanController::class, 'addObject'])->name('loan.add-object')->middleware('acl:create');
    Route::post('/loan/{id}/remove-object/{objectId}', [LoanController::class, 'removeObject'])->name('loan.remove-object')->middleware('acl:delete');
    Route::post('/loan/{id}/transition', [LoanController::class, 'transition'])->name('loan.transition')->middleware('acl:update');
    Route::post('/loan/{id}/extend', [LoanController::class, 'extend'])->name('loan.extend')->middleware('acl:update');
    Route::post('/loan/{id}/return', [LoanController::class, 'returnLoan'])->name('loan.return')->middleware('acl:update');
    Route::post('/loan/{id}/upload-document', [LoanController::class, 'uploadDocument'])->name('loan.upload-document')->middleware('acl:create');
    Route::get('/loan/dashboard', [LoanController::class, 'dashboard'])->name('loan.dashboard');
});
