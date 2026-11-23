<?php

/**
 * ILL supplier routing + inbound EDI (#1475).
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

use AhgLibrary\Controllers\IllRoutingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'acl:update'])->prefix('library-manage')->group(function () {
    Route::get('ill-routing', [IllRoutingController::class, 'index'])->name('library.ill-routing.index');
    Route::post('ill-routing/auto', [IllRoutingController::class, 'toggleAuto'])->name('library.ill-routing.auto');
    Route::post('ill-routing', [IllRoutingController::class, 'store'])->name('library.ill-routing.store')->middleware('acl:create');
    Route::patch('ill-routing/{id}', [IllRoutingController::class, 'update'])->name('library.ill-routing.update')->whereNumber('id');
    Route::delete('ill-routing/{id}', [IllRoutingController::class, 'destroy'])->name('library.ill-routing.destroy')->whereNumber('id')->middleware('acl:delete');
    Route::post('ill-routing/{id}/token', [IllRoutingController::class, 'rotateToken'])->name('library.ill-routing.token')->whereNumber('id');
    Route::get('ill-requests/{id}/routing', [IllRoutingController::class, 'requestRouting'])->name('library.ill-routing.request')->whereNumber('id');
    Route::post('ill-requests/{id}/routing', [IllRoutingController::class, 'route'])->name('library.ill-routing.route')->whereNumber('id');
    Route::post('ill-requests/{id}/routing/stop', [IllRoutingController::class, 'stopWaiting'])->name('library.ill-routing.stop')->whereNumber('id');
    Route::post('ill-requests/{id}/routing/retry', [IllRoutingController::class, 'retry'])->name('library.ill-routing.retry')->whereNumber('id');
    Route::post('ill-requests/{id}/routing/close', [IllRoutingController::class, 'closeUnreachable'])->name('library.ill-routing.close')->whereNumber('id');
    Route::post('ill-requests/{id}/routing/reply', [IllRoutingController::class, 'recordReply'])->name('library.ill-routing.reply')->whereNumber('id');
});

// Called by partner systems: bearer token, no session and no CSRF.
Route::post('api/library/ill/edi/inbound', [IllRoutingController::class, 'receive'])
    ->middleware('throttle:120,1')
    ->name('library.ill-routing.inbound');
