<?php

use AhgActorManage\Controllers\ActorController;
use Illuminate\Support\Facades\Route;

Route::get('/actor/browse', [ActorController::class, 'browse'])->name('actor.browse');

// Actor create returns 403 to anon (matches AtoM behavior), other auth routes redirect to login
Route::middleware('auth.forbid')->group(function () {
    Route::get('/actor/add', [ActorController::class, 'create'])->name('actor.add');
    Route::post('/actor/add', [ActorController::class, 'store'])->name('actor.store')->middleware('acl:create');
});

Route::middleware('auth')->group(function () {
    Route::get('/actor/{slug}/edit', [ActorController::class, 'edit'])->name('actor.edit');
    Route::post('/actor/{slug}/edit', [ActorController::class, 'update'])->name('actor.update')->middleware('acl:update');
    Route::get('/actor/{slug}/rename', [ActorController::class, 'rename'])->name('actor.rename');
    Route::post('/actor/{slug}/rename', [ActorController::class, 'processRename'])->name('actor.processRename')->middleware('acl:update');

    // =========================================================================
    // Authority Dashboard & Workqueue
    // =========================================================================
    Route::get('/actor/authority/dashboard', [ActorController::class, 'dashboard'])->name('actor.dashboard');
    Route::get('/actor/authority/workqueue', [ActorController::class, 'workqueue'])->name('actor.workqueue');

    // =========================================================================
    // External Identifiers
    // =========================================================================
    Route::get('/actor/authority/identifiers/{actorId}', [ActorController::class, 'identifiers'])->name('actor.identifiers');
    Route::post('/api/authority/identifier/save', [ActorController::class, 'apiIdentifierSave'])->name('actor.api.identifier.save')->middleware('acl:update');
    Route::post('/api/authority/identifier/{id}/delete', [ActorController::class, 'apiIdentifierDelete'])->name('actor.api.identifier.delete')->middleware('acl:delete');
    Route::post('/api/authority/identifier/{id}/verify', [ActorController::class, 'apiIdentifierVerify'])->name('actor.api.identifier.verify');

    // =========================================================================
    // External Authority Lookup
    // =========================================================================
    Route::get('/api/authority/wikidata/search', [ActorController::class, 'apiWikidataSearch'])->name('actor.api.wikidata.search');
    Route::get('/api/authority/viaf/search', [ActorController::class, 'apiViafSearch'])->name('actor.api.viaf.search');
    Route::get('/api/authority/ulan/search', [ActorController::class, 'apiUlanSearch'])->name('actor.api.ulan.search');
    Route::get('/api/authority/lcnaf/search', [ActorController::class, 'apiLcnafSearch'])->name('actor.api.lcnaf.search');

    // =========================================================================
    // Completeness
    // =========================================================================
    Route::post('/api/authority/completeness/{actorId}/recalc', [ActorController::class, 'apiCompletenessRecalc'])->name('actor.api.completeness.recalc');
    Route::post('/api/authority/completeness/batch-assign', [ActorController::class, 'apiCompletenessBatchAssign'])->name('actor.api.completeness.batch-assign');

    // =========================================================================
    // Relationship Graph
    // =========================================================================
    Route::get('/api/authority/graph/{actorId}', [ActorController::class, 'apiGraphData'])->name('actor.api.graph.data');

    // =========================================================================
    // Merge / Split
    // =========================================================================
    Route::get('/actor/authority/merge/{id}', [ActorController::class, 'merge'])->name('actor.merge');
    Route::get('/actor/authority/split/{id}', [ActorController::class, 'split'])->name('actor.split');
    Route::post('/api/authority/merge/preview', [ActorController::class, 'apiMergePreview'])->name('actor.api.merge.preview');
    Route::post('/api/authority/merge/execute', [ActorController::class, 'apiMergeExecute'])->name('actor.api.merge.execute')->middleware('acl:update');
    Route::post('/api/authority/split/execute', [ActorController::class, 'apiSplitExecute'])->name('actor.api.split.execute')->middleware('acl:update');

    // =========================================================================
    // Occupations
    // =========================================================================
    Route::get('/actor/authority/occupations/{actorId}', [ActorController::class, 'occupations'])->name('actor.occupations');
    Route::post('/api/authority/occupation/save', [ActorController::class, 'apiOccupationSave'])->name('actor.api.occupation.save')->middleware('acl:update');
    Route::post('/api/authority/occupation/{id}/delete', [ActorController::class, 'apiOccupationDelete'])->name('actor.api.occupation.delete')->middleware('acl:delete');

    // =========================================================================
    // Functions
    // =========================================================================
    Route::get('/actor/authority/functions/{actorId}', [ActorController::class, 'functions'])->name('actor.functions');
    Route::get('/actor/authority/function-browse', [ActorController::class, 'functionBrowse'])->name('actor.function.browse');
    Route::post('/api/authority/function/save', [ActorController::class, 'apiFunctionSave'])->name('actor.api.function.save')->middleware('acl:update');
    Route::post('/api/authority/function/{id}/delete', [ActorController::class, 'apiFunctionDelete'])->name('actor.api.function.delete')->middleware('acl:delete');

    // =========================================================================
    // Deduplication
    // =========================================================================
    Route::get('/actor/authority/dedup', [ActorController::class, 'dedupIndex'])->name('actor.dedup');
    Route::match(['get', 'post'], '/actor/authority/dedup/scan', [ActorController::class, 'dedupScan'])->name('actor.dedup.scan'); // ACL check in controller for POST only
    Route::get('/actor/authority/dedup/compare/{id}', [ActorController::class, 'dedupCompare'])->name('actor.dedup.compare');
    Route::post('/api/authority/dedup/{id}/dismiss', [ActorController::class, 'apiDedupDismiss'])->name('actor.api.dedup.dismiss')->middleware('acl:update');
    Route::post('/api/authority/dedup/{id}/merge', [ActorController::class, 'apiDedupMerge'])->name('actor.api.dedup.merge')->middleware('acl:update');

    // =========================================================================
    // NER Pipeline
    // =========================================================================
    Route::get('/actor/authority/ner', [ActorController::class, 'nerIndex'])->name('actor.ner');
    Route::post('/api/authority/ner/create-stub', [ActorController::class, 'apiNerCreateStub'])->name('actor.api.ner.create-stub')->middleware('acl:create');
    Route::post('/api/authority/ner/{id}/promote', [ActorController::class, 'apiNerPromote'])->name('actor.api.ner.promote')->middleware('acl:update');
    Route::post('/api/authority/ner/{id}/reject', [ActorController::class, 'apiNerReject'])->name('actor.api.ner.reject')->middleware('acl:delete');

    // =========================================================================
    // Contact Information
    // =========================================================================
    Route::get('/actor/authority/contact/{actorId}', [ActorController::class, 'contact'])->name('actor.contact')->whereNumber('actorId');

    // =========================================================================
    // EAC-CPF Export
    // =========================================================================
    Route::get('/api/authority/eac-export/{actorId}', [ActorController::class, 'apiEacExport'])->name('actor.api.eac.export')->whereNumber('actorId');

    // =========================================================================
    // Configuration (admin only)
    // =========================================================================
    Route::match(['get', 'post'], '/actor/authority/config', [ActorController::class, 'config'])->name('actor.config'); // ACL check in controller for POST only
});

Route::middleware('admin')->group(function () {
    Route::get('/actor/{slug}/delete', [ActorController::class, 'confirmDelete'])->name('actor.confirmDelete');
    Route::delete('/actor/{slug}/delete', [ActorController::class, 'destroy'])->name('actor.destroy')->middleware('acl:delete');
});

// Autocomplete (used by AJAX lookups)
Route::get('/actor/autocomplete', [ActorController::class, 'autocomplete'])->name('actor.autocomplete');

Route::get('/actor/{slug}/print', [ActorController::class, 'print'])->name('actor.print');
Route::get('/actor/{slug}', [ActorController::class, 'show'])->name('actor.show');
