<?php

/**
 * ahg-scan web-archive routes - Heratio
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 *
 * Single-page web archiving (WARC 1.1). All routes sit under the admin/
 * prefix, which the /{slug} catch-all already excludes, so they are
 * catch-all safe.
 *
 * @copyright Plain Sailing Information Systems
 */

use AhgScan\Controllers\WebArchiveController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])
    ->prefix('admin/web-archive')
    ->name('web-archive.')
    ->group(function () {
        Route::get('/', [WebArchiveController::class, 'index'])->name('index');
        Route::post('/', [WebArchiveController::class, 'store'])->name('store');
        Route::get('/{id}', [WebArchiveController::class, 'show'])->name('show')->whereNumber('id');
        Route::get('/{id}/download', [WebArchiveController::class, 'download'])->name('download')->whereNumber('id');
    });
