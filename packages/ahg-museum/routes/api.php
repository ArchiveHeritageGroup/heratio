<?php

/**
 * Museum vocabulary autocomplete API routes.
 *
 * Issue: #739
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed under the GNU AGPL v3 or
 * later. See <https://www.gnu.org/licenses/>.
 */

use AhgMuseum\Controllers\VocabularyApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('api/museum')->group(function () {
    Route::get('/getty-aat', [VocabularyApiController::class, 'gettyAat'])
        ->name('museum.api.getty-aat');

    Route::get('/vocabulary-search', [VocabularyApiController::class, 'vocabularySearch'])
        ->name('museum.api.vocabulary-search');

    Route::get('/authority-search', [VocabularyApiController::class, 'authoritySearch'])
        ->name('museum.api.authority-search');
});
