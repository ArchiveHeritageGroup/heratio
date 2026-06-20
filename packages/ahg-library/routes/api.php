<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * Library acquisitions JSON:API (heratio#1100). Mounted under /api/library and
 * guarded by the shared ahg-api key-auth middleware (api.auth:read|write|delete);
 * controllers additionally enforce AclService permissions for the acting account.
 *
 * Also hosts: serials JSON:API (#1092), the OpenURL resolver (#1097) and the
 * MARC cataloguing API (#1098).
 */

use AhgLibrary\Controllers\Api\LibraryBudgetApiController;
use AhgLibrary\Controllers\Api\LibraryOrderApiController;
use AhgLibrary\Controllers\Api\LibraryOrderLineApiController;
use AhgLibrary\Controllers\Api\LibrarySerialApiController;
use AhgLibrary\Controllers\Api\LibrarySerialIssueApiController;
use AhgLibrary\Controllers\Api\LibrarySerialSubscriptionApiController;
use AhgLibrary\Controllers\Api\LibraryVendorApiController;
use AhgLibrary\Controllers\Api\MarcApiController;
use AhgLibrary\Controllers\Api\MarcMergeApiController;
use AhgLibrary\Controllers\Api\MarcValidationApiController;
use AhgLibrary\Controllers\ResolverController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/library')
    // SubstituteBindings is required for implicit route-model binding
    // ({vendor}/{budget}/{order} -> the type-hinted model). These routes are
    // loaded standalone (not via the global `api` group), so without it every
    // PUT/PATCH/GET-by-id received an empty model and silently no-op'd.
    ->middleware(['api.auth:read', 'api.ratelimit', \Illuminate\Routing\Middleware\SubstituteBindings::class])
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
        Route::patch('orders/{order}/receive', [LibraryOrderApiController::class, 'receive'])->whereNumber('order')->middleware('api.auth:write'); // #1091 receipt
        Route::delete('orders/{order}', [LibraryOrderApiController::class, 'destroy'])->whereNumber('order')->middleware('api.auth:delete');

        // Order lines (nested for index/store; flat by id for show/update/destroy)
        Route::get('orders/{order}/lines', [LibraryOrderLineApiController::class, 'index'])->whereNumber('order');
        Route::post('orders/{order}/lines', [LibraryOrderLineApiController::class, 'store'])->whereNumber('order')->middleware('api.auth:write');
        Route::get('order-lines/{line}', [LibraryOrderLineApiController::class, 'show'])->whereNumber('line');
        Route::match(['put', 'patch'], 'order-lines/{line}', [LibraryOrderLineApiController::class, 'update'])->whereNumber('line')->middleware('api.auth:write');
        Route::delete('order-lines/{line}', [LibraryOrderLineApiController::class, 'destroy'])->whereNumber('line')->middleware('api.auth:delete');

        // Serials (#1092)
        Route::get('serials', [LibrarySerialApiController::class, 'index']);
        Route::get('serials/{serial}', [LibrarySerialApiController::class, 'show'])->whereNumber('serial');
        Route::post('serials', [LibrarySerialApiController::class, 'store'])->middleware('api.auth:write');
        Route::match(['put', 'patch'], 'serials/{serial}', [LibrarySerialApiController::class, 'update'])->whereNumber('serial')->middleware('api.auth:write');
        Route::delete('serials/{serial}', [LibrarySerialApiController::class, 'destroy'])->whereNumber('serial')->middleware('api.auth:delete');

        // Serial issues (nested for index/store; flat by id otherwise)
        Route::get('serials/{serial}/issues', [LibrarySerialIssueApiController::class, 'index'])->whereNumber('serial');
        Route::post('serials/{serial}/issues', [LibrarySerialIssueApiController::class, 'store'])->whereNumber('serial')->middleware('api.auth:write');
        Route::get('serial-issues/{issue}', [LibrarySerialIssueApiController::class, 'show'])->whereNumber('issue');
        Route::match(['put', 'patch'], 'serial-issues/{issue}', [LibrarySerialIssueApiController::class, 'update'])->whereNumber('issue')->middleware('api.auth:write');
        Route::delete('serial-issues/{issue}', [LibrarySerialIssueApiController::class, 'destroy'])->whereNumber('issue')->middleware('api.auth:delete');

        // Serial subscriptions (one per serial - POST upserts)
        Route::get('serial-subscriptions', [LibrarySerialSubscriptionApiController::class, 'index']);
        Route::post('serials/{serial}/subscription', [LibrarySerialSubscriptionApiController::class, 'store'])->whereNumber('serial')->middleware('api.auth:write');
        Route::get('serial-subscriptions/{subscription}', [LibrarySerialSubscriptionApiController::class, 'show'])->whereNumber('subscription');
        Route::match(['put', 'patch'], 'serial-subscriptions/{subscription}', [LibrarySerialSubscriptionApiController::class, 'update'])->whereNumber('subscription')->middleware('api.auth:write');
        Route::delete('serial-subscriptions/{subscription}', [LibrarySerialSubscriptionApiController::class, 'destroy'])->whereNumber('subscription')->middleware('api.auth:delete');
    });

// OpenURL 1.0 link resolver (#1097) - public, for discovery tools / citation managers.
Route::get('/api/resolver', [ResolverController::class, 'resolve'])->name('library.openurl-resolver');

// MARC cataloguing API (#1098) - validate / merge / export / import.
Route::prefix('api/cataloguing/marc')
    ->middleware(['api.auth:read', 'api.ratelimit'])
    ->group(function () {
        Route::post('validate', [MarcValidationApiController::class, 'validateRecord']);
        Route::post('merge', [MarcMergeApiController::class, 'merge']);
        Route::get('export', [MarcApiController::class, 'export']);
        Route::post('import', [MarcApiController::class, 'import'])->middleware('api.auth:create');
    });
