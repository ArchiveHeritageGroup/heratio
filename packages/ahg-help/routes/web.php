<?php

use AhgHelp\Controllers\HelpController;
use Illuminate\Support\Facades\Route;

Route::get('/help', [HelpController::class, 'index'])->name('help.index');
Route::get('/help/system-map', [HelpController::class, 'systemMap'])->name('help.system-map');
Route::get('/help/system-breakdown', [HelpController::class, 'systemBreakdown'])->name('help.system-breakdown');
Route::get('/help/search', [HelpController::class, 'search'])->name('help.search');
Route::get('/help/category/{category}', [HelpController::class, 'category'])->name('help.category');
Route::get('/help/article/{slug}', [HelpController::class, 'article'])->name('help.article');

// Article cross-link manager (admin) — heratio#1399
Route::middleware('admin')->group(function () {
    Route::get('/help/article/{slug}/links', [HelpController::class, 'manageLinks'])->name('help.article.links');
    Route::post('/help/article/{slug}/links', [HelpController::class, 'addLink'])->name('help.article.links.add');
    Route::delete('/help/article/{slug}/links/{targetId}', [HelpController::class, 'removeLink'])->name('help.article.links.remove');
});
