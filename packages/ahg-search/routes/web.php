<?php

use AhgSearch\Controllers\SearchController;
use AhgSearch\Controllers\VectorSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/search', [SearchController::class, 'search'])->name('search');
Route::get('/search/advanced', [SearchController::class, 'advanced'])->name('search.advanced');
Route::get('/search/autocomplete', [SearchController::class, 'autocomplete'])->name('search.autocomplete');

// Legacy aliases
Route::get('/search/index', fn () => redirect('/search', 301));
Route::get('/search/semantic', [SearchController::class, 'search'])->name('search.semantic');

// Vector-similarity API (Qdrant-backed). Public — read-only.
Route::get('/api/search/semantic', [VectorSearchController::class, 'search'])
    ->name('search.api.semantic');
Route::get('/api/search/semantic/health', [VectorSearchController::class, 'health'])
    ->name('search.api.semantic.health');
Route::get('/api/search/semantic/similar/{ioId}', [VectorSearchController::class, 'similar'])
    ->where('ioId', '[0-9]+')
    ->name('search.api.semantic.similar');

// Admin search pages
Route::middleware('admin')->group(function () {
    Route::get('/search/descriptionUpdates', [SearchController::class, 'descriptionUpdates'])->name('search.descriptionUpdates');
    Route::match(['get', 'post'], '/search/globalReplace', [SearchController::class, 'globalReplace'])->name('search.globalReplace');
});

Route::middleware('auth')->group(function () {
    Route::match(['get','post'], '/search-enhancement/admin-templates', function() { return view('search::admin-templates'); })->name('searchEnhancement.adminTemplates');
    Route::match(['get','post'], '/search-enhancement/saved-searches', function() { return view('search::saved-searches'); })->name('searchEnhancement.savedSearches');
    Route::match(['get','post'], '/search-enhancement/history', function() { return view('search::history'); })->name('searchEnhancement.history');
    Route::match(['get','post'], '/semantic-search-admin/test-expand', function() { return view('search::test-expand'); })->name('semanticSearchAdmin.testExpand');
    Route::match(['get','post'], '/search-enhancement/save-search', function() { return view('search::save-search'); })->name('searchEnhancement.saveSearch');
});
