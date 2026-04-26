<?php

use AhgRic\Controllers\RdfImportController;
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
    Route::get('/relations/types', [RicEntityController::class, 'getRelationTypes'])->name('ric.public-relation-types');
    Route::get('/relations/{id}', [RicController::class, 'getRelations'])->where('id', '[0-9]+')->name('ric.public-relations');
    Route::get('/graph-summary/{id}', [RicController::class, 'getGraphSummary'])->where('id', '[0-9]+')->name('ric.public-graph-summary');
    Route::get('/timeline/{id}', [RicController::class, 'getTimeline'])->where('id', '[0-9]+')->name('ric.public-timeline');
    Route::get('/explain/{sourceId}/{targetId}', [RicController::class, 'explainRelation'])->name('ric.public-explain');
});

Route::middleware('web')->group(function () {
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
    Route::get('/admin/ric/timeline-data', [RicController::class, 'getTimelineData'])->name('ric.timeline-data');

    // RIC Semantic Search
    Route::get('/admin/ric/semantic-search', [RicController::class, 'semanticSearch'])->name('ric.semantic-search');

    // RiC-O Community Features: SHACL validation, JSON-LD export, external authority linking
    Route::get('/admin/ric/shacl-validate', [RicController::class, 'shaclValidate'])->name('ric.shacl-validate');
    Route::get('/admin/ric/export/jsonld', [RicController::class, 'exportJsonLd'])->name('ric.export-jsonld');
    Route::get('/admin/ric/lookup-external', [RicController::class, 'lookupExternal'])->name('ric.lookup-external');

    // RDF inbound — TTL / JSON-LD / RDF-XML import (dry-run + commit)
    Route::get('/admin/ric/import',  [RdfImportController::class, 'form'])->name('ric.import');
    Route::post('/admin/ric/import', [RdfImportController::class, 'run'])->middleware('acl:create')->name('ric.import.run');

    // AJAX endpoints for dashboard
    Route::get('/admin/ric/ajax-dashboard', [RicController::class, 'ajaxDashboard'])->name('ric.ajax-dashboard');
    Route::post('/admin/ric/ajax-sync', [RicController::class, 'ajaxSync'])->name('ric.ajax-sync');
    Route::get('/admin/ric/ajax-sync-readiness', [RicController::class, 'ajaxSyncReadiness'])->name('ric.ajax-sync-readiness');
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

// Read-only SPARQL proxy → Fuseki (SELECT / ASK / CONSTRUCT / DESCRIBE only).
// Federated linked-data clients can query Heratio's RiC graph here.
Route::match(['get', 'post'], '/api/sparql', [RdfImportController::class, 'sparqlProxy'])->name('ric.sparql-proxy');

// ================================================================
// RiC Entity CRUD — Record-level AJAX + Standalone browse
// ================================================================

// NOTE: The admin/ric/entity-api/* AJAX route group was removed 2026-04-18.
// All views/front-end JS now call /api/ric/v1/* directly (Phase 4.1 of the
// Heratio/RiC split). The form-based create/update/destroy routes (below)
// remain because they perform server-side redirects after submit and are
// distinct from the pure-JSON API endpoints.

// Standalone browse/show/edit pages
Route::middleware('web')->group(function () {
    Route::get('/admin/ric/entities/places', [RicEntityController::class, 'browsePlaces'])->name('ric.places.browse');
    Route::get('/admin/ric/entities/rules', [RicEntityController::class, 'browseRules'])->name('ric.rules.browse');
    Route::get('/admin/ric/entities/activities', [RicEntityController::class, 'browseActivities'])->name('ric.activities.browse');
    Route::get('/admin/ric/entities/instantiations', [RicEntityController::class, 'browseInstantiations'])->name('ric.instantiations.browse');

    // Create route must come before show route with wildcard {slug}
    Route::get('/admin/ric/entities/{type}/create', [RicEntityController::class, 'createEntityForm'])->name('ric.entities.create');
    Route::post('/admin/ric/entities/{type}', [RicEntityController::class, 'storeEntityForm'])->name('ric.entities.store-form');

    // Create route above takes precedence; "create" will not match as a slug because route order wins.
    Route::get('/admin/ric/entities/{type}/{slug}', [RicEntityController::class, 'showEntity'])->name('ric.entities.show');
    Route::get('/admin/ric/entities/{type}/{slug}/edit', [RicEntityController::class, 'editEntity'])->name('ric.entities.edit');
    Route::put('/admin/ric/entities/{type}/{slug}', [RicEntityController::class, 'updateEntityForm'])->name('ric.entities.update-form');
    Route::delete('/admin/ric/entities/{type}/{slug}', [RicEntityController::class, 'destroyEntityForm'])->name('ric.entities.destroy-form');

    // Global relations browse (G8)
    Route::get('/admin/ric/relations', [RicEntityController::class, 'browseRelations'])->name('ric.relations.browse');

    // Capture workflow moved to https://capture.openric.org — the neutral
    // browser-only client. Old URL preserved as a 302 for any bookmarks/links.
    Route::get('/ric-capture', function () {
        return redirect('https://capture.openric.org/', 302);
    })->name('ric.capture.studio');
});

// Linked-data IRI resolver. OpenRiC emits @id values shaped as
// https://host/{instance}/{type}/{key} (see RicController::buildRecordUri).
// Those IRIs are stable SPARQL subject identifiers but were not HTTP-routable.
// This route content-negotiates Accept and 303-redirects to either the
// JSON-LD API endpoint (machine clients) or the public slug page (browsers).
Route::get('/{instance}/{type}/{key}', function (string $instance, string $type, string $key) {
    $accept  = (string) request()->header('Accept', '');
    $wantsLd = request()->wantsJson()
        || str_contains($accept, 'ld+json')
        || str_contains($accept, 'application/json')
        || str_contains($accept, 'rdf+xml')
        || str_contains($accept, 'turtle');

    $apiMap = [
        'record' => 'records', 'recordset' => 'records', 'informationobject' => 'records',
        'agent' => 'agents', 'actor' => 'agents', 'person' => 'agents',
        'corporatebody' => 'agents', 'family' => 'agents',
        'repository' => 'repositories',
        'function' => 'functions',
        'place' => 'places',
        'activity' => 'activities',
        'rule' => 'rules',
        'instantiation' => 'instantiations',
    ];
    $apiType = $apiMap[strtolower($type)] ?? null;
    if (!$apiType) {
        abort(404);
    }

    $slugTypes = ['records', 'agents', 'repositories'];
    if (ctype_digit($key) && in_array($apiType, $slugTypes, true)) {
        $resolvedSlug = \DB::table('slug')->where('object_id', (int) $key)->value('slug');
        if ($resolvedSlug) {
            $key = $resolvedSlug;
        }
    }

    $headers = ['Vary' => 'Accept'];

    if ($wantsLd) {
        return redirect('/api/ric/v1/' . $apiType . '/' . $key, 303, $headers);
    }

    if ($apiType === 'records' || $apiType === 'agents') {
        return redirect('/' . $key, 303, $headers);
    }
    if ($apiType === 'repositories') {
        return redirect('/repository/' . $key, 303, $headers);
    }

    $singular = [
        'functions' => 'function',
        'places' => 'place',
        'activities' => 'activity',
        'rules' => 'rule',
        'instantiations' => 'instantiation',
    ][$apiType];
    return redirect('/admin/ric/entities/' . $singular . '/' . $key, 303, $headers);
})->where('instance', '[a-z0-9_-]+')
  ->where('type', 'record|recordset|informationobject|agent|actor|person|corporatebody|family|repository|function|place|activity|rule|instantiation')
  ->where('key', '[A-Za-z0-9_-]+')
  ->name('openric.id-resolver');
