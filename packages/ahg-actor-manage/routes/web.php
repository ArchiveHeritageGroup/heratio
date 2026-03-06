<?php

use AhgActorManage\Controllers\ActorController;
use Illuminate\Support\Facades\Route;

Route::get('/actor/browse', [ActorController::class, 'browse'])->name('actor.browse');
Route::get('/actor/{slug}', [ActorController::class, 'show'])->name('actor.show');
