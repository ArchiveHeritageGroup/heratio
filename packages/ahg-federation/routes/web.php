<?php

use AhgFederation\Controllers\FederationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('federation')->group(function () {
    Route::get('/', [FederationController::class, 'index'])->name('federation.index');
    Route::get('/peers', [FederationController::class, 'peers'])->name('federation.peers');
    Route::get('/peers/add', [FederationController::class, 'editPeer'])->name('federation.addPeer');
    Route::get('/peers/{id}/edit', [FederationController::class, 'editPeer'])->name('federation.editPeer');
    Route::post('/peers/save', [FederationController::class, 'savePeer'])->name('federation.savePeer');
    Route::get('/harvest', [FederationController::class, 'harvest'])->name('federation.harvest');
    Route::post('/harvest/run', [FederationController::class, 'runHarvest'])->name('federation.runHarvest');
    Route::get('/log', [FederationController::class, 'log'])->name('federation.log');
    Route::post('/peers/{id}/test', [FederationController::class, 'testPeer'])->name('federation.testPeer');
});

