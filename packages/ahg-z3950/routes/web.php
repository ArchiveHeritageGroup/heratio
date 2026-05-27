<?php

use AhgZ3950\Controllers\Z3950Controller;
use Illuminate\Support\Facades\Route;

// Z39.50 client and server — bibliographic search via the Z39.50 protocol.
// Client searches remote targets; server exposes the Heratio catalogue.

Route::middleware('web')->group(function () {
    // Public: dashboard
    Route::get('/z3950', [Z3950Controller::class, 'index'])
        ->name('z3950.index');

    // Auth-gated: search + import
    Route::middleware('auth')->group(function () {
        Route::get('/z3950/search', [Z3950Controller::class, 'search'])
            ->name('z3950.search');
        Route::post('/z3950/search', [Z3950Controller::class, 'searchRun'])
            ->name('z3950.search-run');

        Route::get('/z3950/result/{resultSet}', [Z3950Controller::class, 'result'])
            ->name('z3950.result');
        Route::get('/z3950/import/{resultSet}/{recordNumber}', [Z3950Controller::class, 'import'])
            ->name('z3950.import');
        Route::post('/z3950/import', [Z3950Controller::class, 'importBatch'])
            ->name('z3950.import-batch');

        // Admin: target management + stats
        Route::get('/z3950/admin', [Z3950Controller::class, 'admin'])
            ->name('z3950.admin');
        Route::get('/z3950/target/create', [Z3950Controller::class, 'createTarget'])
            ->name('z3950.target.create');
        Route::post('/z3950/target', [Z3950Controller::class, 'storeTarget'])
            ->name('z3950.target.store');
        Route::delete('/z3950/target/{id}', [Z3950Controller::class, 'deleteTarget'])
            ->name('z3950.target.delete');
    });
});