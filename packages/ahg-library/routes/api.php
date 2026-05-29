<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * Library acquisitions JSON:API (heratio#1100). Mounted under /api/library and
 * guarded by the shared ahg-api key-auth middleware (api.auth:read|write|delete);
 * controllers additionally enforce AclService permissions for the acting account.
 */

use AhgLibrary\Controllers\Api\LibraryBudgetApiController;
use AhgLibrary\Controllers\Api\LibraryOrderApiController;
use AhgLibrary\Controllers\Api\LibraryOrderLineApiController;
use AhgLibrary\Controllers\Api\LibraryVendorApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/library')
    ->middleware(['api.auth:read', 'api.ratelimit'])
    ->group(function () {
        // Vendors
        Route::get('vendors', [LibraryVendorApiController::class, 'index']);
        Route::get('vendors/{vendor}', [LibraryVendorApiController::class, 'show'])->whereNumber('vendor');
        Route::post('vendors', [LibraryVendorApiController::class, 'store'])->middleware('api.auth:write');
        Route::match(['put', 'patch'], 'vendors/{vendor}', [LibraryVendorApiController::class, 'update'])->whereNumber('vendor')->middleware('api.auth:write');
        Route::delete('vendors/{vendor}', [LibraryVendorApiController::class, 'destroy'])->whereNumber('vendor')->middleware('api.auth:delete');

        // Budgets
        Route::get('budgets', [LibraryBudgetApiController::class, 'index']);
        Route::get('budgets/{budget}', [LibraryBudgetApiController::class, 'show'])->whereNumber('budget');
        Route::post('budgets', [LibraryBudgetApiController::class, 'store'])->middleware('api.auth:write');
        Route::match(['put', 'patch'], 'budgets/{budget}', [LibraryBudgetApiController::class, 'update'])->whereNumber('budget')->middleware('api.auth:write');
        Route::delete('budgets/{budget}', [LibraryBudgetApiController::class, 'destroy'])->whereNumber('budget')->middleware('api.auth:delete');

        // Orders
        Route::get('orders', [LibraryOrderApiController::class, 'index']);
        Route::get('orders/{order}', [LibraryOrderApiController::class, 'show'])->whereNumber('order');
        Route::post('orders', [LibraryOrderApiController::class, 'store'])->middleware('api.auth:write');
        Route::match(['put', 'patch'], 'orders/{order}', [LibraryOrderApiController::class, 'update'])->whereNumber('order')->middleware('api.auth:write');
        Route::delete('orders/{order}', [LibraryOrderApiController::class, 'destroy'])->whereNumber('order')->middleware('api.auth:delete');

        // Order lines (nested for index/store; flat by id for show/update/destroy)
        Route::get('orders/{order}/lines', [LibraryOrderLineApiController::class, 'index'])->whereNumber('order');
        Route::post('orders/{order}/lines', [LibraryOrderLineApiController::class, 'store'])->whereNumber('order')->middleware('api.auth:write');
        Route::get('order-lines/{line}', [LibraryOrderLineApiController::class, 'show'])->whereNumber('line');
        Route::match(['put', 'patch'], 'order-lines/{line}', [LibraryOrderLineApiController::class, 'update'])->whereNumber('line')->middleware('api.auth:write');
        Route::delete('order-lines/{line}', [LibraryOrderLineApiController::class, 'destroy'])->whereNumber('line')->middleware('api.auth:delete');
    });
