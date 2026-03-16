<?php

use AhgRightsHolderManage\Controllers\RightsHolderController;
use Illuminate\Support\Facades\Route;

Route::get('/rightsholder/browse', [RightsHolderController::class, 'browse'])->name('rightsholder.browse');

Route::middleware('auth')->group(function () {
    Route::get('/rightsholder/add', [RightsHolderController::class, 'create'])->name('rightsholder.create');
    Route::post('/rightsholder/add', [RightsHolderController::class, 'store'])->name('rightsholder.store');
    Route::get('/rightsholder/{slug}/edit', [RightsHolderController::class, 'edit'])->name('rightsholder.edit');
    Route::post('/rightsholder/{slug}/edit', [RightsHolderController::class, 'update'])->name('rightsholder.update');
});

Route::middleware('admin')->group(function () {
    Route::get('/rightsholder/{slug}/delete', [RightsHolderController::class, 'confirmDelete'])->name('rightsholder.confirmDelete');
    Route::delete('/rightsholder/{slug}/delete', [RightsHolderController::class, 'destroy'])->name('rightsholder.destroy');
});

Route::get('/rightsholder/{slug}', [RightsHolderController::class, 'show'])->name('rightsholder.show');
