<?php

use AhgTermTaxonomy\Controllers\TermController;
use Illuminate\Support\Facades\Route;

Route::get('/taxonomy/index', [TermController::class, 'taxonomyIndex'])->name('taxonomy.index');
Route::get('/taxonomy/index/id/{id}', [TermController::class, 'taxonomyIndexById']);
Route::get('/taxonomy/{id}', [TermController::class, 'taxonomyIndexById'])->name('taxonomy.show')->where('id', '[0-9]+');

Route::middleware('auth')->group(function () {
    Route::get('/taxonomy/browse', [TermController::class, 'taxonomyIndex'])->name('taxonomy.browse');
    Route::get('/taxonomy/list', [TermController::class, 'taxonomyIndex'])->name('taxonomy.list');
});

Route::get('/term/browse', [TermController::class, 'browse'])->name('term.browse');

Route::middleware('auth')->group(function () {
    Route::get('/term/add', [TermController::class, 'create'])->name('term.create');
    Route::post('/term/store', [TermController::class, 'store'])->name('term.store')->middleware('acl:createTerm');
    Route::get('/term/{slug}/edit', [TermController::class, 'edit'])->name('term.edit');
    Route::put('/term/{slug}', [TermController::class, 'update'])->name('term.update')->middleware('acl:update');
    Route::get('/term/{slug}/delete', [TermController::class, 'confirmDelete'])->name('term.confirmDelete');
    Route::delete('/term/{slug}', [TermController::class, 'destroy'])->name('term.destroy')->middleware('acl:delete');
});

Route::get('/term/autocomplete', [TermController::class, 'autocomplete'])->name('term.autocomplete');
Route::get('/term/{slug}', [TermController::class, 'show'])->name('term.show');
