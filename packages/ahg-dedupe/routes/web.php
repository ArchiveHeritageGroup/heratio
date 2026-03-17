<?php

use AhgDedupe\Controllers\DedupeController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/dedupe', [DedupeController::class, 'index'])->name('dedupe.index');
    Route::get('/admin/dedupe/browse', [DedupeController::class, 'browse'])->name('dedupe.browse');
    Route::get('/admin/dedupe/compare/{id}', [DedupeController::class, 'compare'])->name('dedupe.compare')->whereNumber('id');
    Route::post('/admin/dedupe/dismiss/{id}', [DedupeController::class, 'dismiss'])->name('dedupe.dismiss')->whereNumber('id');
    Route::get('/admin/dedupe/rules', [DedupeController::class, 'rules'])->name('dedupe.rules');
    Route::get('/admin/dedupe/report', [DedupeController::class, 'report'])->name('dedupe.report');
});
