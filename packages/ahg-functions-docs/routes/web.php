<?php

/**
 * Routes for the auto-generated function / route catalogues (issue #126).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Public surface:
 *   GET /docs/functions/                  -> index (list 5 catalogues)
 *   GET /docs/functions/{kind}            -> render catalogue, page=1 by default
 *   GET /docs/functions/{kind}?page=N     -> paginated render (PHP / blade / routes)
 *
 * Gated behind `admin` middleware - this is an operator/dev reference
 * surface, not a public-facing browse page.
 */

use AhgFunctionsDocs\Controllers\FunctionsDocsController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/docs/functions', [FunctionsDocsController::class, 'index'])
        ->name('functionsDocs.index');

    Route::get('/docs/functions/{kind}', [FunctionsDocsController::class, 'show'])
        ->name('functionsDocs.show')
        ->where('kind', 'php|js|blade|py|routes');
});
