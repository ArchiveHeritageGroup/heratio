<?php

use AhgSearch\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/search', [SearchController::class, 'search'])->name('search');
Route::get('/search/advanced', [SearchController::class, 'advanced'])->name('search.advanced');
Route::get('/search/autocomplete', [SearchController::class, 'autocomplete'])->name('search.autocomplete');

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
