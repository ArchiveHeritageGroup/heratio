<?php

/**
 * web.php - ResourceSync routes for Heratio
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

use AhgResourceSync\Controllers\ResourceSyncController;
use Illuminate\Support\Facades\Route;

// throttle:120,1 mirrors the OAI-PMH endpoint shape: 120 requests per minute
// per IP is generous enough for a polite aggregator walking the full chain
// (SourceDescription -> CapabilityList -> ResourceList pages -> ChangeList)
// in a single sweep but still catches a misbehaving scraper.

// .well-known/resourcesync discovery file (ResourceSync spec section 11).
Route::get('/.well-known/resourcesync', [ResourceSyncController::class, 'sourceDescription'])
    ->middleware('throttle:120,1')
    ->name('resourcesync.source-description');

// CapabilityList: lists ResourceList + ChangeList back to the SourceDescription.
Route::get('/resourcesync/capabilitylist.xml', [ResourceSyncController::class, 'capabilityList'])
    ->middleware('throttle:120,1')
    ->name('resourcesync.capability-list');

// ResourceList: full inventory, paged via ?page=.
Route::get('/resourcesync/resourcelist.xml', [ResourceSyncController::class, 'resourceList'])
    ->middleware('throttle:120,1')
    ->name('resourcesync.resource-list');

// ChangeList: recent updates + tombstones, paged via ?page=, horizon configurable.
Route::get('/resourcesync/changelist.xml', [ResourceSyncController::class, 'changeList'])
    ->middleware('throttle:120,1')
    ->name('resourcesync.change-list');
