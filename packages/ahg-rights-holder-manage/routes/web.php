<?php

use AhgRightsHolderManage\Controllers\RightsHolderController;
use Illuminate\Support\Facades\Route;

Route::get('/rightsholder/browse', [RightsHolderController::class, 'browse'])->name('rightsholder.browse');
Route::get('/rightsholder/{slug}', [RightsHolderController::class, 'show'])->name('rightsholder.show');
