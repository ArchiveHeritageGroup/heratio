<?php

use AhgProvenance\Controllers\ProvenanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('provenance')->group(function () {
    Route::get('/', [ProvenanceController::class, 'index'])->name('provenance.index');
    Route::get('/{slug}', [ProvenanceController::class, 'view'])->name('provenance.view');
    Route::get('/{slug}/timeline', [ProvenanceController::class, 'timeline'])->name('provenance.timeline');
    Route::get('/{slug}/edit', [ProvenanceController::class, 'edit'])->name('provenance.edit');
    Route::post('/{slug}/edit', [ProvenanceController::class, 'update'])->name('provenance.update');
    Route::post('/{slug}/event', [ProvenanceController::class, 'addEvent'])->name('provenance.addEvent');
    Route::delete('/{slug}/event/{eventId}', [ProvenanceController::class, 'deleteEvent'])->name('provenance.deleteEvent');
});

