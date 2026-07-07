<?php

/**
 * api.php - Archivematica connector API routes for Heratio
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

use AhgArchivematica\Controllers\DipController;
use Illuminate\Support\Facades\Route;

// --- Direction 1 (mode B): inbound DIP push from Archivematica ---
// AM (or an SS callback) POSTs a DIP tarball / package reference here; the
// DipController unpacks -> matches -> ingests. Key-auth via ahg-api's
// api.auth middleware alias (X-API-Key / Bearer token), same as the rest of
// the REST surface. Controller is owned/delivered by another agent.
Route::post('/api/archivematica/dip', [DipController::class, 'receive'])
    ->middleware('api.auth')
    ->name('archivematica.dip.receive');
