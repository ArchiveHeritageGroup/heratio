<?php

/**
 * web.php - Archivematica connector web routes for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

use AhgArchivematica\Controllers\SettingsController;
use AhgArchivematica\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

// Authenticated admin routes.
Route::middleware('auth')->group(function () {

    // --- Admin settings page (AHG Settings > Archivematica) ---
    Route::get('/admin/archivematica', [SettingsController::class, 'edit'])
        ->name('archivematica.settings');
    Route::post('/admin/archivematica', [SettingsController::class, 'update'])
        ->name('archivematica.settings.update')
        ->middleware('acl:update');

    // --- Direction 2: Heratio -> Archivematica (drive transfers) ---
    // Trigger a transfer for an information_object, and read its status.
    // Controllers are owned/delivered by other agents; referenced by FQCN.
    Route::post('/admin/archivematica/transfer/{objectId}', [TransferController::class, 'trigger'])
        ->whereNumber('objectId')
        ->name('archivematica.transfer.trigger')
        ->middleware('acl:update');

    Route::get('/admin/archivematica/status/{objectId}', [TransferController::class, 'status'])
        ->whereNumber('objectId')
        ->name('archivematica.transfer.status');
});
