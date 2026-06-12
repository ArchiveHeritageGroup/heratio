<?php

/**
 * ahg-scan web-archive routes - Heratio
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 *
 * Web archiving (WARC 1.1) - the SINGLE web-archive admin surface (heratio#1244). One
 * controller (AhgScan\Controllers\WebArchiveController) offers BOTH capture modes over
 * ONE list / detail / download / replay / asset surface, backed by the reusable ahg-core
 * engines (WarcCaptureService + WarcReplayService) and the single warc_capture table.
 *
 * Two capture modes:
 *   - POST /            store()         archive an operator-submitted general URL (url mode)
 *   - POST /capture     captureRecord() snapshot a published record's own page (record mode)
 *
 * All routes sit under the admin/ prefix, which the /{slug} catch-all already excludes, so
 * they are catch-all safe. The static /capture segment is registered before the numeric
 * /{id} wildcard so it is never mistaken for an id.
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
        Route::post('/capture', [WebArchiveController::class, 'captureRecord'])->name('capture');
        Route::get('/{id}', [WebArchiveController::class, 'show'])->name('show')->whereNumber('id');
        Route::get('/{id}/replay', [WebArchiveController::class, 'replay'])->name('replay')->whereNumber('id');
        Route::get('/{id}/asset', [WebArchiveController::class, 'asset'])->name('asset')->whereNumber('id');
        Route::get('/{id}/download', [WebArchiveController::class, 'download'])->name('download')->whereNumber('id');
    });
