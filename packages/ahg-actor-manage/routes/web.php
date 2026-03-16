<?php

use AhgActorManage\Controllers\ActorController;
use Illuminate\Support\Facades\Route;

Route::get('/actor/browse', [ActorController::class, 'browse'])->name('actor.browse');

Route::middleware('auth')->group(function () {
    Route::get('/actor/add', [ActorController::class, 'create'])->name('actor.create');
    Route::post('/actor/add', [ActorController::class, 'store'])->name('actor.store');
    Route::get('/actor/{slug}/edit', [ActorController::class, 'edit'])->name('actor.edit');
    Route::post('/actor/{slug}/edit', [ActorController::class, 'update'])->name('actor.update');
    Route::get('/actor/{slug}/delete', [ActorController::class, 'confirmDelete'])->name('actor.confirmDelete');
    Route::delete('/actor/{slug}/delete', [ActorController::class, 'destroy'])->name('actor.destroy');
});

Route::get('/actor/{slug}', [ActorController::class, 'show'])->name('actor.show');
