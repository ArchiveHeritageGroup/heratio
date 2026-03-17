<?php

use AhgStaticPage\Controllers\StaticPageController;
use Illuminate\Support\Facades\Route;

Route::get('/pages/{slug}', [StaticPageController::class, 'show'])->name('staticpage.show');
Route::get('/about', [StaticPageController::class, 'show'])->defaults('slug', 'about')->name('staticpage.about');
Route::get('/privacy', [StaticPageController::class, 'show'])->defaults('slug', 'privacy')->name('staticpage.privacy');
Route::get('/terms', [StaticPageController::class, 'show'])->defaults('slug', 'terms')->name('staticpage.terms');

Route::middleware('admin')->group(function () {
    Route::get('/staticpage/browse', [StaticPageController::class, 'browse'])->name('staticpage.browse');
});
