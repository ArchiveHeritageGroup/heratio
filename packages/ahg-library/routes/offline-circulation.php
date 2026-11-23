<?php

/** Offline circulation desk (#1474 item 4). */

use AhgLibrary\Controllers\OfflineCirculationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'acl:update'])->prefix('library-manage/circulation/offline')->group(function () {
    Route::get('/', [OfflineCirculationController::class, 'desk'])->name('library.circulation.offline');
    Route::get('/app.js', [OfflineCirculationController::class, 'script'])->name('library.circulation.offline.script');
    Route::get('/sw.js', [OfflineCirculationController::class, 'worker'])->name('library.circulation.offline.worker');
    Route::get('/session', [OfflineCirculationController::class, 'session'])->name('library.circulation.offline.session');
    Route::get('/package', [OfflineCirculationController::class, 'package'])->name('library.circulation.offline.package');
    Route::post('/sync', [OfflineCirculationController::class, 'sync'])->name('library.circulation.offline.sync');
    Route::get('/conflicts', [OfflineCirculationController::class, 'conflicts'])->name('library.circulation.offline.conflicts');
    Route::post('/conflicts/{id}/resolve', [OfflineCirculationController::class, 'resolve'])
        ->whereNumber('id')->name('library.circulation.offline.resolve');
});
