<?php

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
});

// AJAX endpoints (legacy camelCase aliases)
Route::middleware(['auth', 'admin'])->group(function () {
    Route::post('/semanticSearchAdmin/runSync', [SemanticSearchController::class, 'runSync'])->name('semantic-search.runSync');
    Route::post('/semanticSearchAdmin/testExpand', [SemanticSearchController::class, 'testExpand'])->name('semantic-search.testExpand');
});

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
