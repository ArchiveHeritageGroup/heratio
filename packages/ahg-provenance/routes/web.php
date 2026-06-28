<?php

use AhgProvenance\Controllers\ProvenanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('provenance')->group(function () {
    // AJAX: Search agents for autocomplete (must be before {slug} catch-all)
    Route::get('/searchAgents', [ProvenanceController::class, 'searchAgents'])->name('provenance.searchAgents');

    Route::get('/', [ProvenanceController::class, 'index'])->name('provenance.index');
    Route::get('/{slug}', [ProvenanceController::class, 'view'])->name('provenance.view');
    Route::get('/{slug}/timeline', [ProvenanceController::class, 'timeline'])->name('provenance.timeline');
    Route::get('/{slug}/edit', [ProvenanceController::class, 'edit'])->name('provenance.edit');
    Route::post('/{slug}/edit', [ProvenanceController::class, 'update'])->name('provenance.update')->middleware('acl:update');
    Route::post('/{slug}/event', [ProvenanceController::class, 'addEvent'])->name('provenance.addEvent')->middleware('acl:update');
    Route::delete('/{slug}/event/{eventId}', [ProvenanceController::class, 'deleteEvent'])->name('provenance.deleteEvent')->middleware('acl:delete');
    Route::post('/{slug}/document/{id}/delete', [ProvenanceController::class, 'deleteDocument'])->name('provenance.deleteDocument')->where('id', '[0-9]+')->middleware('acl:delete');
});

// Legacy camelCase aliases
Route::middleware('auth')->group(function () {
    Route::post('/provenance/addEvent', [ProvenanceController::class, 'addEventLegacy'])->name('provenance.addEvent.legacy')->middleware('acl:update');
    Route::post('/provenance/deleteEvent', [ProvenanceController::class, 'deleteEventLegacy'])->name('provenance.deleteEvent.legacy')->middleware('acl:delete');
    Route::post('/provenance/deleteDocument/{id}', [ProvenanceController::class, 'deleteDocument'])->name('provenance.deleteDocument.legacy')->where('id', '[0-9]+')->middleware('acl:delete');
});
