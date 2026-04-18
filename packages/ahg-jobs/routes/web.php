<?php

/**
 * Routes for AHG Jobs
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

use Illuminate\Support\Facades\Route;
use Ahg\Jobs\Http\Controllers\JobsController;

/*
|--------------------------------------------------------------------------
| AHG Jobs Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->prefix('jobs')->name('jobs.')->group(function () {
    Route::get('/', [JobsController::class, 'browse'])->name('browse');
    Route::get('/show/{id}', [JobsController::class, 'show'])->name('show');
    Route::post('/clear-inactive', [JobsController::class, 'clearInactive'])->name('clear-inactive');
    Route::get('/export-csv', [JobsController::class, 'exportCsv'])->name('export-csv');
});
