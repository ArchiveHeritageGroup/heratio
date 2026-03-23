<?php

use AhgSearch\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/search', [SearchController::class, 'search'])->name('search');
Route::get('/search/advanced', [SearchController::class, 'advanced'])->name('search.advanced');
Route::get('/search/autocomplete', [SearchController::class, 'autocomplete'])->name('search.autocomplete');

// Admin search pages — routes resolve without auth for menu, but controller enforces admin
Route::get('/search/descriptionUpdates', [SearchController::class, 'descriptionUpdates'])->name('search.descriptionUpdates');
Route::match(['get', 'post'], '/search/globalReplace', [SearchController::class, 'globalReplace'])->name('search.globalReplace');
