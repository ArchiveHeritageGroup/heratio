<?php

use AhgFederation\Controllers\FederationController;
use AhgFederation\Middleware\EnsureFederationEnabled;
use Illuminate\Support\Facades\Route;

// Protect the federation UI/API with the federation_enabled setting via middleware.
Route::middleware(['auth', 'admin', EnsureFederationEnabled::class])->prefix('federation')->group(function () {
    Route::get('/', [FederationController::class, 'index'])->name('federation.index');
    Route::get('/peers', [FederationController::class, 'peers'])->name('federation.peers');
    Route::get('/peers/add', [FederationController::class, 'editPeer'])->name('federation.addPeer');
    Route::get('/peers/{id}/edit', [FederationController::class, 'editPeer'])->name('federation.editPeer');
    Route::post('/peers/save', [FederationController::class, 'savePeer'])->name('federation.savePeer');
    Route::post('/peers/{id}/search-config', [FederationController::class, 'savePeerSearchConfig'])->name('federation.savePeerSearchConfig');
    Route::get('/harvest', [FederationController::class, 'harvest'])->name('federation.harvest');
    Route::post('/harvest/run', [FederationController::class, 'runHarvest'])->name('federation.runHarvest');
    Route::get('/log', [FederationController::class, 'log'])->name('federation.log');
    Route::post('/peers/{id}/test', [FederationController::class, 'testPeer'])->name('federation.testPeer');
});

// Federated search is anonymous-readable so the GLAM browse page can embed it.
// Still gated by federation_enabled so the toggle disables the feature globally.
Route::middleware([EnsureFederationEnabled::class])->prefix('federation')->group(function () {
    Route::get('/search', [FederationController::class, 'search'])->name('federation.search');
    Route::get('/provenance/{objectId}', [FederationController::class, 'provenance'])
        ->whereNumber('objectId')
        ->name('federation.provenance');
});

// Legacy AtoM URL aliases (AJAX endpoints used by JS widgets)
Route::middleware(['auth', 'admin', EnsureFederationEnabled::class])->group(function () {
    Route::post('/admin/federation/api/test-peer', [FederationController::class, 'testPeer'])->name('federation.api.testPeer');
    Route::post('/admin/federation/harvest', [FederationController::class, 'runHarvest'])->name('federation.api.harvest');
    // GET /admin/federation/harvest/ — legacy page URL (AtoM used admin prefix)
    Route::get('/admin/federation/harvest', [FederationController::class, 'harvest'])->name('federation.harvest.legacy');
});
