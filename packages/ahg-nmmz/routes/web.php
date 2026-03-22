<?php

use AhgNmmz\Controllers\NmmzController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('nmmz')->group(function () {
    Route::get('/', [NmmzController::class, 'index'])->name('nmmz.index');

    // Monuments
    Route::get('/monuments', [NmmzController::class, 'monuments'])->name('nmmz.monuments');
    Route::match(['get', 'post'], '/monument/create', [NmmzController::class, 'monumentCreate'])->name('nmmz.monument.create');
    Route::get('/monument/{id}', [NmmzController::class, 'monumentView'])->name('nmmz.monument.view');

    // Antiquities
    Route::get('/antiquities', [NmmzController::class, 'antiquities'])->name('nmmz.antiquities');
    Route::match(['get', 'post'], '/antiquity/create', [NmmzController::class, 'antiquityCreate'])->name('nmmz.antiquity.create');
    Route::get('/antiquity/{id}', [NmmzController::class, 'antiquityView'])->name('nmmz.antiquity.view');

    // Permits
    Route::get('/permits', [NmmzController::class, 'permits'])->name('nmmz.permits');
    Route::match(['get', 'post'], '/permit/create', [NmmzController::class, 'permitCreate'])->name('nmmz.permit.create');
    Route::match(['get', 'post'], '/permit/{id}', [NmmzController::class, 'permitView'])->name('nmmz.permit.view');

    // Sites
    Route::get('/sites', [NmmzController::class, 'sites'])->name('nmmz.sites');
    Route::match(['get', 'post'], '/site/create', [NmmzController::class, 'siteCreate'])->name('nmmz.site.create');
    Route::get('/site/{id}', [NmmzController::class, 'siteView'])->name('nmmz.site.view');

    // HIA
    Route::get('/hia', [NmmzController::class, 'hia'])->name('nmmz.hia');
    Route::match(['get', 'post'], '/hia/create', [NmmzController::class, 'hiaCreate'])->name('nmmz.hia.create');

    // Reports & Config
    Route::get('/reports', [NmmzController::class, 'reports'])->name('nmmz.reports');
    Route::match(['get', 'post'], '/config', [NmmzController::class, 'config'])->name('nmmz.config');
});
