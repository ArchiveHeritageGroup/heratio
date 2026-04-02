<?php

use AhgRic\Controllers\RicController;
use AhgRic\Controllers\RicEntityController;
use Illuminate\Support\Facades\Route;

// Public RiC API — web middleware for session support (view-mode toggle needs session)
Route::prefix('ric-api')->middleware('web')->group(function () {
    Route::get('/data', [RicController::class, 'getData'])->name('ric.public-data');
    Route::get('/autocomplete', [RicController::class, 'autocomplete'])->name('ric.public-autocomplete');
    Route::get('/dashboard', [RicController::class, 'ajaxDashboard'])->name('ric.public-dashboard');
    Route::get('/stats', [RicController::class, 'ajaxStats'])->name('ric.public-stats');
    Route::post('/view-mode', [RicController::class, 'setViewMode'])->name('ric.set-view-mode');
    Route::get('/relations/{id}', [RicController::class, 'getRelations'])->where('id', '[0-9]+')->name('ric.public-relations');
    Route::get('/graph-summary/{id}', [RicController::class, 'getGraphSummary'])->where('id', '[0-9]+')->name('ric.public-graph-summary');
    Route::get('/timeline/{id}', [RicController::class, 'getTimeline'])->where('id', '[0-9]+')->name('ric.public-timeline');
    Route::get('/explain/{sourceId}/{targetId}', [RicController::class, 'explainRelation'])->name('ric.public-explain');
});

Route::middleware('admin')->group(function () {
    Route::get('/admin/ric', [RicController::class, 'index'])->name('ric.index');
    Route::get('/admin/ric/sync-status', [RicController::class, 'syncStatus'])->name('ric.sync-status');
    Route::get('/admin/ric/orphans', [RicController::class, 'orphans'])->name('ric.orphans');
    Route::get('/admin/ric/queue', [RicController::class, 'queue'])->name('ric.queue');
    Route::get('/admin/ric/logs', [RicController::class, 'logs'])->name('ric.logs');
    Route::match(['get', 'post'], '/admin/ric/config', [RicController::class, 'config'])->name('ric.config');

    // RIC Explorer
    Route::get('/admin/ric/explorer', [RicController::class, 'explorer'])->name('ric.explorer');
    Route::post('/admin/ric/create-entity', [RicController::class, 'createEntity'])->name('ric.create-entity');
    Route::get('/admin/ric/autocomplete', [RicController::class, 'autocomplete'])->name('ric.autocomplete');
    Route::get('/admin/ric/data', [RicController::class, 'getData'])->name('ric.data');

    // RIC Semantic Search
    Route::get('/admin/ric/semantic-search', [RicController::class, 'semanticSearch'])->name('ric.semantic-search');

    // RiC-O Community Features: SHACL validation, JSON-LD export, external authority linking
    Route::get('/admin/ric/shacl-validate', [RicController::class, 'shaclValidate'])->name('ric.shacl-validate');
    Route::get('/admin/ric/export/jsonld', [RicController::class, 'exportJsonLd'])->name('ric.export-jsonld');
    Route::get('/admin/ric/lookup-external', [RicController::class, 'lookupExternal'])->name('ric.lookup-external');

    // AJAX endpoints for dashboard
    Route::get('/admin/ric/ajax-dashboard', [RicController::class, 'ajaxDashboard'])->name('ric.ajax-dashboard');
    Route::post('/admin/ric/ajax-sync', [RicController::class, 'ajaxSync'])->name('ric.ajax-sync');
    Route::post('/admin/ric/ajax-integrity-check', [RicController::class, 'ajaxIntegrityCheck'])->name('ric.ajax-integrity');
    Route::post('/admin/ric/ajax-cleanup-orphans', [RicController::class, 'ajaxCleanupOrphans'])->name('ric.ajax-cleanup');
    Route::get('/admin/ric/ajax-sync-progress', [RicController::class, 'ajaxSyncProgress'])->name('ric.ajax-sync-progress');

    // AJAX endpoints: resync, queue item management, orphan updates, stats
    Route::post('/admin/ric/ajax-resync', [RicController::class, 'ajaxResync'])->name('ric.ajax-resync');
    Route::post('/admin/ric/ajax-clear-queue-item', [RicController::class, 'ajaxClearQueueItem'])->name('ric.ajax-clear-queue-item');
    Route::post('/admin/ric/ajax-update-orphan', [RicController::class, 'ajaxUpdateOrphan'])->name('ric.ajax-update-orphan');
    Route::get('/admin/ric/ajax-stats', [RicController::class, 'ajaxStats'])->name('ric.ajax-stats');

    // Legacy camelCase AJAX aliases (ahgRicExplorerPlugin compatibility)
    Route::get('/admin/ric/ajax/stats', [RicController::class, 'ajaxStats'])->name('ric.ajax-stats-legacy');
    Route::get('/admin/ric/ajax/integrity-check', [RicController::class, 'ajaxIntegrityCheck'])->name('ric.ajax-integrity-legacy');
    Route::post('/admin/ric/ajax/cleanup-orphans', [RicController::class, 'ajaxCleanupOrphans'])->name('ric.ajax-cleanup-legacy');
    Route::post('/admin/ric/ajax/resync', [RicController::class, 'ajaxResync'])->name('ric.ajax-resync-legacy');
    Route::post('/admin/ric/ajax/queue-item', [RicController::class, 'ajaxClearQueueItem'])->name('ric.ajax-queue-item-legacy');
    Route::post('/admin/ric/ajax/update-orphan', [RicController::class, 'ajaxUpdateOrphan'])->name('ric.ajax-update-orphan-legacy');
});

// Legacy camelCase public endpoints (ricExplorer prefix)
Route::get('/ricExplorer/getData', [RicController::class, 'getData'])->name('ric.getData-legacy');
Route::get('/ricExplorer/autocomplete', [RicController::class, 'autocomplete'])->name('ric.autocomplete-legacy');

// ================================================================
// RiC Entity CRUD — Record-level AJAX + Standalone browse
// ================================================================

// Record-level AJAX endpoints (admin/ric/entity-api/ — registered in routing.yml for Symfony compatibility)
Route::prefix('admin/ric/entity-api')->middleware('web')->group(function () {
    Route::get('/for-record/{id}', [RicEntityController::class, 'entitiesForRecord'])->name('ric.entities.for-record');
    Route::match(['get', 'post'], '/store', [RicEntityController::class, 'storeEntity'])->name('ric.entities.store');
    Route::match(['get', 'post'], '/update/{id}', [RicEntityController::class, 'updateEntity'])->name('ric.entities.update');
    Route::match(['get', 'post'], '/delete/{id}', [RicEntityController::class, 'destroyEntity'])->name('ric.entities.destroy');
    Route::get('/autocomplete', [RicEntityController::class, 'autocompleteEntities'])->name('ric.entities.autocomplete');
    Route::get('/info/{id}', [RicEntityController::class, 'getEntityInfo'])->name('ric.entities.info');
    Route::get('/dropdown/{taxonomy}', [RicEntityController::class, 'dropdownChoices'])->name('ric.entities.dropdown');
    Route::get('/relations/{id}', [RicEntityController::class, 'relationsForRecord'])->name('ric.relations.for-record');
    Route::match(['get', 'post'], '/relation-store', [RicEntityController::class, 'storeRelation'])->name('ric.relations.store');
    Route::match(['get', 'post'], '/relation-delete/{id}', [RicEntityController::class, 'destroyRelation'])->name('ric.relations.destroy');
    Route::get('/relation-types', [RicEntityController::class, 'getRelationTypes'])->name('ric.relations.types');
});

// Standalone browse/show/edit pages (admin)
Route::middleware('admin')->group(function () {
    Route::get('/admin/ric/entities/places', [RicEntityController::class, 'browsePlaces'])->name('ric.places.browse');
    Route::get('/admin/ric/entities/rules', [RicEntityController::class, 'browseRules'])->name('ric.rules.browse');
    Route::get('/admin/ric/entities/activities', [RicEntityController::class, 'browseActivities'])->name('ric.activities.browse');
    Route::get('/admin/ric/entities/instantiations', [RicEntityController::class, 'browseInstantiations'])->name('ric.instantiations.browse');

    Route::get('/admin/ric/entities/{type}/{slug}', [RicEntityController::class, 'showEntity'])->name('ric.entities.show');
    Route::get('/admin/ric/entities/{type}/{slug}/edit', [RicEntityController::class, 'editEntity'])->name('ric.entities.edit');
    Route::put('/admin/ric/entities/{type}/{slug}', [RicEntityController::class, 'updateEntityForm'])->name('ric.entities.update-form');
    Route::delete('/admin/ric/entities/{type}/{slug}', [RicEntityController::class, 'destroyEntityForm'])->name('ric.entities.destroy-form');
});
