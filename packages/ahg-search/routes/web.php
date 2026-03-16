<?php

use AhgSearch\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/search', [SearchController::class, 'search'])->name('search');
Route::get('/search/advanced', [SearchController::class, 'advanced'])->name('search.advanced');
Route::get('/search/autocomplete', [SearchController::class, 'autocomplete'])->name('search.autocomplete');
