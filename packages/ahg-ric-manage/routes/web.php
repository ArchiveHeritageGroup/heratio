<?php

/**
 * ahg-ric-manage routes.
 *
 * (c) 2026 Johan Pieterse / Plain Sailing iSystems. AGPL-3.0-or-later.
 */

use Illuminate\Support\Facades\Route;

// The edit-route middleware is config-driven (ric-manage.edit_middleware) so
// the same route serves the Heratio ACL and the OpenRiC token auth. Default
// 'acl:update' here; OpenRiC overrides via env. See config/ric-manage.php.
Route::prefix('admin/ric-manage')
    ->middleware(['web', 'auth'])
    ->group(function () {
        Route::match(['get', 'post'], '/edit/{slug}', [\AhgRicManage\Controllers\RicManageController::class, 'edit'])
            ->name('ahgricmanage.edit')
            ->middleware(config('ric-manage.edit_middleware', 'acl:update'));
    });
