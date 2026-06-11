<?php

use AhgSemanticSearch\Controllers\DisplacedHeritageController;
use AhgSemanticSearch\Controllers\RepatriationClaimController;
use AhgSemanticSearch\Controllers\ScholarshipController;
use AhgSemanticSearch\Controllers\SemanticSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('semantic-search')->group(function () {
    Route::get('/saved-searches', [SemanticSearchController::class, 'savedSearches'])->name('semantic-search.savedSearches');
    Route::get('/history', [SemanticSearchController::class, 'history'])->name('semantic-search.history');
});

Route::middleware(['auth', 'admin'])->prefix('semantic-search/admin')->group(function () {
    Route::get('/', [SemanticSearchController::class, 'index'])->name('semantic-search.index');
    Route::match(['get', 'post'], '/config', [SemanticSearchController::class, 'config'])->name('semantic-search.config');
    Route::get('/terms', [SemanticSearchController::class, 'terms'])->name('semantic-search.terms');
    Route::match(['get', 'post'], '/term/add', [SemanticSearchController::class, 'termAdd'])->name('semantic-search.term.add');
    Route::get('/term/{id}', [SemanticSearchController::class, 'termView'])->name('semantic-search.term.view');
    Route::get('/search-logs', [SemanticSearchController::class, 'searchLogs'])->name('semantic-search.searchLogs');
    Route::get('/sync-logs', [SemanticSearchController::class, 'syncLogs'])->name('semantic-search.syncLogs');
    Route::get('/templates', [SemanticSearchController::class, 'adminTemplates'])->name('semantic-search.admin.templates');
    Route::match(['get', 'post'], '/template/edit/{id?}', [SemanticSearchController::class, 'adminTemplateEdit'])->name('semantic-search.admin.template.edit');

    // heratio#1210 - generative scholarship: discovered-connections report for one
    // record. Accepts a numeric id or a slug. Admin-gated like the rest of this group.
    Route::get('/scholarship/{objectId}', [ScholarshipController::class, 'show'])
        ->name('semantic-search.scholarship.show')
        ->where('objectId', '[A-Za-z0-9][A-Za-z0-9_-]*');

    // heratio#1207 - repatriation engine, first slice (detection): the
    // "potentially displaced heritage" review register. Origin-vs-holding
    // mismatch flags for curatorial review only - not a repatriation claim.
    Route::get('/displaced-heritage', [DisplacedHeritageController::class, 'index'])
        ->name('semantic-search.displaced-heritage.index');
});

// heratio#1207 - repatriation engine, next slice: structured repatriation-claim
// workflow on top of the displaced-heritage register. Admin-gated CRUD over the
// new displaced_heritage_claim table (the public virtual-return view is bound
// separately in the provider's register()). All paths are 2-segment+ so they
// never collide with the single-segment /{slug} archival-record catch-all.
Route::middleware(['auth', 'admin'])->prefix('repatriation')->group(function () {
    Route::get('/claims', [RepatriationClaimController::class, 'index'])
        ->name('repatriation.claims.index');
    Route::get('/claims/create', [RepatriationClaimController::class, 'create'])
        ->name('repatriation.claims.create');
    Route::post('/claims', [RepatriationClaimController::class, 'store'])
        ->name('repatriation.claims.store');
    Route::get('/claims/{id}/edit', [RepatriationClaimController::class, 'edit'])
        ->where('id', '[0-9]+')
        ->name('repatriation.claims.edit');
    Route::post('/claims/{id}', [RepatriationClaimController::class, 'update'])
        ->where('id', '[0-9]+')
        ->name('repatriation.claims.update');
    Route::post('/claims/{id}/status', [RepatriationClaimController::class, 'status'])
        ->where('id', '[0-9]+')
        ->name('repatriation.claims.status');
});

// AJAX endpoints (legacy camelCase aliases)
Route::middleware(['auth', 'admin'])->group(function () {
    Route::post('/semanticSearchAdmin/runSync', [SemanticSearchController::class, 'runSync'])->name('semantic-search.runSync');
});
// testExpand is public (called from browse semantic search modal)
Route::match(['get', 'post'], '/semanticSearchAdmin/testExpand', [SemanticSearchController::class, 'testExpand'])->name('semantic-search.testExpand');

// Legacy admin URL aliases
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/semantic-search', fn () => redirect('/semantic-search/admin', 301));
    Route::get('/admin/semantic-search/config', fn () => redirect('/semantic-search/admin/config', 301));
    Route::get('/admin/semantic-search/terms', fn () => redirect('/semantic-search/admin/terms', 301));
    Route::get('/admin/semantic-search/term/{id}', fn ($id) => redirect("/semantic-search/admin/term/{$id}", 301));
    Route::get('/admin/semantic-search/term/add', fn () => redirect('/semantic-search/admin/term/add', 301));
    Route::get('/admin/semantic-search/sync-logs', fn () => redirect('/semantic-search/admin/sync-logs', 301));
    Route::get('/admin/semantic-search/search-logs', fn () => redirect('/semantic-search/admin/search-logs', 301));
});
