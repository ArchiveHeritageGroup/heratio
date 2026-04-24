<?php

/**
 * ahg-scan API routes — Heratio /api/v2/scan/*
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use AhgScan\Controllers\Api\ScanApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v2/scan')
    ->middleware(['api.cors', 'api.auth:scan:write', 'api.ratelimit', 'api.log'])
    ->group(function () {
        Route::get('destinations', [ScanApiController::class, 'destinations']);
        Route::post('sessions', [ScanApiController::class, 'createSession']);
        Route::get('sessions/{token}', [ScanApiController::class, 'showSession'])->where('token', '[A-Za-z0-9_-]+');
        Route::post('sessions/{token}/files', [ScanApiController::class, 'uploadFile'])->where('token', '[A-Za-z0-9_-]+');
        Route::post('sessions/{token}/commit', [ScanApiController::class, 'commit'])->where('token', '[A-Za-z0-9_-]+');
        Route::delete('sessions/{token}', [ScanApiController::class, 'abandon'])->where('token', '[A-Za-z0-9_-]+');
    });
