<?php

use AhgSharePoint\Controllers\SharePointPushController;
use Illuminate\Support\Facades\Route;

// Phase 2.B — Manual push endpoints. AAD bearer auth (validated in controller).
// Mounted at /api/v2/sharepoint/push/* by the service provider's Route::prefix('api').
//
// CSRF must be excluded for these (Laravel's VerifyCsrfToken).
// Mirror of atom-ahg-plugins/ahgSharePointPlugin /api/v2/sharepoint/push/* routes.

Route::prefix('v2/sharepoint/push')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->group(function () {
        Route::post('/projection', [SharePointPushController::class, 'projection'])->name('sharepoint.push.projection');
        Route::post('/',           [SharePointPushController::class, 'commit'])->name('sharepoint.push.commit');
        Route::get('/jobs/{id}',   [SharePointPushController::class, 'job'])->whereNumber('id')->name('sharepoint.push.job');
    });

// Phase 3 — M365-side connector feed (placeholder; routes added when shipped).
