<?php

use AhgTermTaxonomy\Controllers\TermController;
use Illuminate\Support\Facades\Route;

Route::get('/taxonomy/browse', [TermController::class, 'taxonomyIndex'])->name('taxonomy.browse');
Route::get('/term/browse', [TermController::class, 'browse'])->name('term.browse');
Route::get('/term/{slug}', [TermController::class, 'show'])->name('term.show');
