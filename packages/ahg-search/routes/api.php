<?php

/**
 * Discovery API routes (issue #1095).
 *
 * JSON discovery surface, stateless, rate-limited. Mounted on the `api`
 * middleware group by the service provider. Public read-only - the response
 * scope is already constrained to published records inside the service layer.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

use AhgSearch\Controllers\DiscoveryController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:100,1')->group(function () {
    Route::post('/api/discovery/search', [DiscoveryController::class, 'search'])
        ->name('discovery.search');
    Route::post('/api/discovery/recommend', [DiscoveryController::class, 'recommend'])
        ->name('discovery.recommend');
});
