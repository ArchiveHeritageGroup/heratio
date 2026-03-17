<?php

use AhgOai\Controllers\OaiPmhController;
use Illuminate\Support\Facades\Route;

Route::get('/oai', [OaiPmhController::class, 'handle'])->name('oai');
