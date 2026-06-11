<?php

/**
 * Research Living Field Alerts routes - Heratio ahg-research
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * heratio#1235 - Research OS Stage 3: per-project Living Field Alerts. Watches
 * the works a project cites and alerts on retractions, updates and new related
 * work. Kept in a separate file (loaded from the research ServiceProvider) to
 * avoid heavy edits to the shared routes/web.php.
 *
 * All paths are two-segment-or-deeper and live under /research/projects/{id}/...
 * so the global /{slug} catch-all never intercepts them. Names live under the
 * 'research.' group as research.alerts.*.
 */

use AhgResearch\Controllers\FieldAlertController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    Route::prefix('projects/{projectId}/alerts')
        ->name('alerts.')
        ->whereNumber('projectId')
        ->group(function () {
            // Alerts panel (retractions prominent)
            Route::get('/', [FieldAlertController::class, 'index'])->name('index');

            // Mark read
            Route::match(['post', 'patch'], '/{id}/read', [FieldAlertController::class, 'markRead'])->whereNumber('id')->name('read');
            Route::match(['post', 'patch'], '/read-all', [FieldAlertController::class, 'markAllRead'])->name('read-all');

            // Watch list
            Route::get('/watches', [FieldAlertController::class, 'watches'])->name('watches');
            Route::post('/watches', [FieldAlertController::class, 'addWatch'])->name('watches.add');
            Route::match(['post', 'delete'], '/watches/{id}/delete', [FieldAlertController::class, 'removeWatch'])->whereNumber('id')->name('watches.remove');
        });
});
