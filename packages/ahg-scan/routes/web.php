<?php

/**
 * ahg-scan routes — Heratio
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use AhgScan\Controllers\CataloguerController;
use AhgScan\Controllers\ScanDashboardController;
use AhgScan\Controllers\ScanFolderController;
use AhgScan\Controllers\ScanInboxController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin/scan')->name('scan.')->group(function () {
    Route::get('/', [ScanDashboardController::class, 'index'])->name('dashboard');

    // heratio#1196 - AI Cataloguer: scan in, draft archival record out (HTR -> NER -> LLM)
    Route::get('/cataloguer', [CataloguerController::class, 'index'])->name('cataloguer');
    Route::post('/cataloguer/draft', [CataloguerController::class, 'draftAjax'])->name('cataloguer.draft');

    Route::get('/folders', [ScanFolderController::class, 'index'])->name('folders.index');
    Route::get('/folders/create', [ScanFolderController::class, 'create'])->name('folders.create');
    Route::post('/folders', [ScanFolderController::class, 'store'])->name('folders.store');
    Route::get('/folders/{id}/edit', [ScanFolderController::class, 'edit'])->name('folders.edit');
    Route::put('/folders/{id}', [ScanFolderController::class, 'update'])->name('folders.update');
    Route::delete('/folders/{id}', [ScanFolderController::class, 'destroy'])->name('folders.destroy');
    Route::post('/folders/{id}/run', [ScanFolderController::class, 'runNow'])->name('folders.run');

    Route::get('/inbox', [ScanInboxController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/{id}', [ScanInboxController::class, 'show'])->name('inbox.show');
    Route::post('/inbox/{id}/retry', [ScanInboxController::class, 'retry'])->name('inbox.retry');
    Route::post('/inbox/{id}/discard', [ScanInboxController::class, 'discard'])->name('inbox.discard');
    Route::post('/inbox/{id}/release-rights', [ScanInboxController::class, 'releaseRights'])->name('inbox.releaseRights');
    Route::post('/inbox/{id}/restore', [ScanInboxController::class, 'restore'])->name('inbox.restore');
    Route::post('/inbox/bulk', [ScanInboxController::class, 'bulk'])->name('inbox.bulk');
});
