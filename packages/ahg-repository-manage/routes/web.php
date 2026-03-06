<?php

use AhgRepositoryManage\Controllers\RepositoryController;
use Illuminate\Support\Facades\Route;

Route::get('/repository/browse', [RepositoryController::class, 'browse'])->name('repository.browse');
Route::get('/repository/{slug}', [RepositoryController::class, 'show'])->name('repository.show');
