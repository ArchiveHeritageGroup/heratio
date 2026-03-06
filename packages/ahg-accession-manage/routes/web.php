<?php

use AhgAccessionManage\Controllers\AccessionController;
use Illuminate\Support\Facades\Route;

Route::get('/accession/browse', [AccessionController::class, 'browse'])->name('accession.browse');
Route::get('/accession/{slug}', [AccessionController::class, 'show'])->name('accession.show');
