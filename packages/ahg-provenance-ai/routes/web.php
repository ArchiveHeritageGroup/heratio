<?php

/**
 * AI Governance dashboard routes - Heratio
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

/*
|--------------------------------------------------------------------------
| AI Inventory & Governance dashboard (heratio#137)
|--------------------------------------------------------------------------
| Admin-only operator visibility into LLM configs + recent inference
| activity. The two JSON endpoints back future JS enhancements.
*/

use AhgProvenanceAi\Controllers\GovernanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin/governance')->group(function () {
    Route::get('/', [GovernanceController::class, 'index'])->name('admin.governance.index');
    Route::get('/models', [GovernanceController::class, 'models'])->name('admin.governance.models');
    Route::get('/inferences', [GovernanceController::class, 'inferences'])->name('admin.governance.inferences');
});
