<?php

/**
 * Routes for the AHG Observability package.
 *
 * /metrics is intentionally registered OUTSIDE the standard web middleware
 * group: no session, no CSRF, no auth alias. The controller handles its
 * own bearer-token + IP allow-list authentication so Prometheus can scrape
 * without holding a session cookie.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

use AhgObservability\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/metrics', [MetricsController::class, 'show'])->name('observability.metrics');
