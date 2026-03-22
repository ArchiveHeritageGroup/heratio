<?php

use AhgDam\Controllers\DamController;
use Illuminate\Support\Facades\Route;

Route::get('/dam', [DamController::class, 'dashboard'])->name('dam.dashboard');
Route::get('/dam/browse', [DamController::class, 'browse'])->name('dam.browse');

Route::middleware('auth')->group(function () {
    Route::get('/dam/create', [DamController::class, 'create'])->name('dam.create');
    Route::post('/dam/store', [DamController::class, 'store'])->name('dam.store');
    Route::get('/dam/{slug}/edit', [DamController::class, 'edit'])->name('dam.edit')
        ->where('slug', '[a-z0-9\-]+');
    Route::put('/dam/{slug}', [DamController::class, 'update'])->name('dam.update')
        ->where('slug', '[a-z0-9\-]+');
    Route::post('/dam/{slug}/delete', [DamController::class, 'destroy'])->name('dam.destroy')
        ->where('slug', '[a-z0-9\-]+');
});

Route::get('/dam/{slug}', [DamController::class, 'show'])->name('dam.show')
    ->where('slug', '(?!browse|create|dashboard)[a-z0-9\-]+');
    Route::get('/dam/bulk-create', [DamController::class, 'bulkCreate'])->name('dam.bulk-create');
    Route::match(['get','post'], '/dam/{slug}/edit-iptc', [DamController::class, 'editIptc'])->name('dam.edit-iptc');
    Route::get('/dam/index', [DamController::class, 'damIndex'])->name('dam.index');
    Route::get('/dam/reports', [DamController::class, 'reportIndex'])->name('dam.reports');
    Route::get('/dam/reports/assets', [DamController::class, 'reportAssets'])->name('dam.reports.assets');
    Route::get('/dam/reports/iptc', [DamController::class, 'reportIptc'])->name('dam.reports.iptc');
    Route::get('/dam/reports/metadata', [DamController::class, 'reportMetadata'])->name('dam.reports.metadata');
    Route::get('/dam/reports/storage', [DamController::class, 'reportStorage'])->name('dam.reports.storage');
