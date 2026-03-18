<?php

use AhgHelp\Controllers\HelpController;
use Illuminate\Support\Facades\Route;

Route::get('/help', [HelpController::class, 'index'])->name('help.index');
Route::get('/help/search', [HelpController::class, 'search'])->name('help.search');
Route::get('/help/category/{category}', [HelpController::class, 'category'])->name('help.category');
Route::get('/help/article/{slug}', [HelpController::class, 'article'])->name('help.article');
