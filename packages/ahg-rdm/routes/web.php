<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

use AhgRdm\Controllers\DatasetController;
use Illuminate\Support\Facades\Route;

// All paths sit under /research/datasets (the 'research' prefix is excluded
// from the locked /{slug} catch-all, and every route here is >=2 segments).
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/research/datasets', [DatasetController::class, 'index'])->name('rdm.datasets.index');
    Route::get('/research/datasets/create', [DatasetController::class, 'create'])->name('rdm.datasets.create');
    Route::post('/research/datasets', [DatasetController::class, 'store'])->name('rdm.datasets.store');
    Route::get('/research/datasets/{id}', [DatasetController::class, 'show'])->name('rdm.datasets.show')->where('id', '[0-9]+');
    Route::post('/research/datasets/{id}/deposit', [DatasetController::class, 'deposit'])->name('rdm.datasets.deposit')->where('id', '[0-9]+');
    Route::post('/research/datasets/{id}/scan', [DatasetController::class, 'scan'])->name('rdm.datasets.scan')->where('id', '[0-9]+');
});
