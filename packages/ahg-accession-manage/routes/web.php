<?php

use AhgAccessionManage\Controllers\AccessionController;
use Illuminate\Support\Facades\Route;

Route::get('/accession/browse', [AccessionController::class, 'browse'])->name('accession.browse');

Route::middleware('auth')->group(function () {
    Route::get('/accession/add', [AccessionController::class, 'create'])->name('accession.create');
    Route::post('/accession/add', [AccessionController::class, 'store'])->name('accession.store');
    Route::get('/accession/{slug}/edit', [AccessionController::class, 'edit'])->name('accession.edit');
    Route::post('/accession/{slug}/edit', [AccessionController::class, 'update'])->name('accession.update');
});

Route::middleware('admin')->group(function () {
    Route::get('/accession/{slug}/delete', [AccessionController::class, 'confirmDelete'])->name('accession.confirmDelete');
    Route::delete('/accession/{slug}/delete', [AccessionController::class, 'destroy'])->name('accession.destroy');
});

Route::get('/accession/{slug}', [AccessionController::class, 'show'])->name('accession.show');
