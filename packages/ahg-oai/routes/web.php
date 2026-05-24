<?php

use AhgOai\Controllers\OaiDocsController;
use AhgOai\Controllers\OaiPmhController;
use Illuminate\Support\Facades\Route;

// OAI-PMH 2.0 spec mandates both GET and POST; same handler dispatches both.
// throttle:120,1 = 120 requests per minute per IP — generous enough that a
// resumption-token-honouring harvester won't hit it, strict enough to catch
// a runaway scraper.
Route::match(['get', 'post'], '/oai', [OaiPmhController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('oai');

Route::get('/oai/docs', [OaiDocsController::class, 'index'])
    ->name('oai.docs');
