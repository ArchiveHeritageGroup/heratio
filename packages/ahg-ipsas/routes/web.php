<?php

use AhgIpsas\Controllers\IpsasController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('ipsas')->group(function () {
    Route::get('/', [IpsasController::class, 'index'])->name('ipsas.index');

    Route::get('/assets', [IpsasController::class, 'assets'])->name('ipsas.assets');
    Route::match(['get', 'post'], '/asset/create', [IpsasController::class, 'assetCreate'])->name('ipsas.asset.create');
    Route::get('/asset/{id}', [IpsasController::class, 'assetView'])->name('ipsas.asset.view');
    Route::match(['get', 'post'], '/asset/{id}/edit', [IpsasController::class, 'assetEdit'])->name('ipsas.asset.edit');

    Route::get('/valuations', [IpsasController::class, 'valuations'])->name('ipsas.valuations');
    Route::match(['get', 'post'], '/valuation/create', [IpsasController::class, 'valuationCreate'])->name('ipsas.valuation.create');

    Route::get('/impairments', [IpsasController::class, 'impairments'])->name('ipsas.impairments');
    Route::get('/insurance', [IpsasController::class, 'insurance'])->name('ipsas.insurance');
    Route::get('/reports', [IpsasController::class, 'reports'])->name('ipsas.reports');
    Route::get('/financial-year', [IpsasController::class, 'financialYear'])->name('ipsas.financialYear');
    Route::match(['get', 'post'], '/config', [IpsasController::class, 'config'])->name('ipsas.config');
});
