<?php

/**
 * Replication Pack routes - Heratio ahg-research (heratio#1238, moonshot 22).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * Self-contained: this file declares its own research-prefixed, web+auth group
 * and is loaded directly by the service provider. All names are
 * research.replication.* and all paths sit under
 * /research/projects/{projectId}/replication, so they are matched before the IO
 * slug catch-all (which excludes the 'research' prefix).
 */

use AhgResearch\Controllers\ReplicationPackController;
use Illuminate\Support\Facades\Route;

Route::prefix('research')->name('research.')->middleware(['web', 'auth'])->group(function () {

    // Replication Pack page: what is included + a Build/Download button.
    Route::get('/projects/{projectId}/replication', [ReplicationPackController::class, 'index'])
        ->name('replication.index')->where('projectId', '[0-9]+');

    // Build the pack and stream the ZIP (temp file deleted after send).
    Route::match(['get', 'post'], '/projects/{projectId}/replication/build', [ReplicationPackController::class, 'build'])
        ->name('replication.build')->where('projectId', '[0-9]+');
});
