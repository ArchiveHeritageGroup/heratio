<?php

use AhgRic\Controllers\RicController;
use Illuminate\Support\Facades\Route;

// /ric/ is a static SPA in public/ric/index.html — no Laravel route needed
// The /admin/ric/explorer route still exists as a Blade-based fallback

Route::middleware('admin')->group(function () {
    Route::get('/admin/ric', [RicController::class, 'index'])->name('ric.index');
    Route::get('/admin/ric/sync-status', [RicController::class, 'syncStatus'])->name('ric.sync-status');
    Route::get('/admin/ric/orphans', [RicController::class, 'orphans'])->name('ric.orphans');
    Route::get('/admin/ric/queue', [RicController::class, 'queue'])->name('ric.queue');
    Route::get('/admin/ric/logs', [RicController::class, 'logs'])->name('ric.logs');
    Route::match(['get', 'post'], '/admin/ric/config', [RicController::class, 'config'])->name('ric.config');

    // RIC Explorer
    Route::get('/admin/ric/explorer', [RicController::class, 'explorer'])->name('ric.explorer');
    Route::get('/admin/ric/autocomplete', [RicController::class, 'autocomplete'])->name('ric.autocomplete');
    Route::get('/admin/ric/data', [RicController::class, 'getData'])->name('ric.data');

    // RIC Semantic Search
    Route::get('/admin/ric/semantic-search', [RicController::class, 'semanticSearch'])->name('ric.semantic-search');

    // AJAX endpoints for dashboard
    Route::get('/admin/ric/ajax-dashboard', [RicController::class, 'ajaxDashboard'])->name('ric.ajax-dashboard');
    Route::post('/admin/ric/ajax-sync', [RicController::class, 'ajaxSync'])->name('ric.ajax-sync');
    Route::post('/admin/ric/ajax-integrity-check', [RicController::class, 'ajaxIntegrityCheck'])->name('ric.ajax-integrity');
    Route::post('/admin/ric/ajax-cleanup-orphans', [RicController::class, 'ajaxCleanupOrphans'])->name('ric.ajax-cleanup');
    Route::get('/admin/ric/ajax-sync-progress', [RicController::class, 'ajaxSyncProgress'])->name('ric.ajax-sync-progress');
});
