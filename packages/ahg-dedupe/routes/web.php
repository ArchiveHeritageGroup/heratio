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

    // Scan
    Route::get('/admin/dedupe/scan', [DedupeController::class, 'scan'])->name('dedupe.scan');
    Route::post('/admin/dedupe/scan', [DedupeController::class, 'scanStart'])->name('dedupe.scan.start');

    // Merge
    Route::get('/admin/dedupe/merge/{id}', [DedupeController::class, 'merge'])->name('dedupe.merge')->whereNumber('id');
    Route::post('/admin/dedupe/merge/{id}', [DedupeController::class, 'mergeExecute'])->name('dedupe.merge.execute')->whereNumber('id');

    // Rule CRUD
    Route::get('/admin/dedupe/rule/create', [DedupeController::class, 'ruleCreate'])->name('dedupe.rule.create');
    Route::post('/admin/dedupe/rule/create', [DedupeController::class, 'ruleStore'])->name('dedupe.rule.store');
    Route::get('/admin/dedupe/rule/{id}/edit', [DedupeController::class, 'ruleEdit'])->name('dedupe.rule.edit')->whereNumber('id');
    Route::post('/admin/dedupe/rule/{id}/edit', [DedupeController::class, 'ruleUpdate'])->name('dedupe.rule.update')->whereNumber('id');
    Route::get('/admin/dedupe/rule/{id}/delete', [DedupeController::class, 'ruleDelete'])->name('dedupe.rule.delete')->whereNumber('id');
});
    Route::match(['get','post'], '/admin/dedupe/config', [DedupeController::class, 'config'])->name('dedupe.config');
    Route::get('/admin/dedupe/contact/{id}', [DedupeController::class, 'contact'])->name('dedupe.contact')->whereNumber('id');
    Route::get('/admin/dedupe/dashboard', [DedupeController::class, 'dashboard'])->name('dedupe.dashboard');
    Route::get('/admin/dedupe/function-browse', [DedupeController::class, 'functionBrowse'])->name('dedupe.function-browse');
    Route::get('/admin/dedupe/functions/{id}', [DedupeController::class, 'functions'])->name('dedupe.functions')->whereNumber('id');
    Route::get('/admin/dedupe/identifiers', [DedupeController::class, 'identifiers'])->name('dedupe.identifiers');
    Route::get('/admin/dedupe/occupations', [DedupeController::class, 'occupations'])->name('dedupe.occupations');
    Route::match(['get','post'], '/admin/dedupe/split/{id}', [DedupeController::class, 'split'])->name('dedupe.split')->whereNumber('id');
    Route::get('/admin/dedupe/workqueue', [DedupeController::class, 'workqueue'])->name('dedupe.workqueue');

// API: Real-time duplicate check (AJAX, used by JS widgets during data entry)
Route::get('/api/dedupe/realtime', [DedupeController::class, 'apiRealtime'])->name('dedupe.api.realtime');
