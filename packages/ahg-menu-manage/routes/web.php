<?php

use AhgMenuManage\Controllers\MenuController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/menu/browse', [MenuController::class, 'browse'])->name('menu.browse');
    Route::get('/admin/menu/add', [MenuController::class, 'create'])->name('menu.create');
    Route::post('/admin/menu/add', [MenuController::class, 'store'])->name('menu.store');
    Route::get('/admin/menu/{id}/edit', [MenuController::class, 'edit'])->name('menu.edit')->whereNumber('id');
    Route::post('/admin/menu/{id}/edit', [MenuController::class, 'update'])->name('menu.update')->whereNumber('id');
    Route::get('/admin/menu/{id}/delete', [MenuController::class, 'confirmDelete'])->name('menu.confirmDelete')->whereNumber('id');
    Route::delete('/admin/menu/{id}/delete', [MenuController::class, 'destroy'])->name('menu.destroy')->whereNumber('id');
    Route::post('/admin/menu/{id}/move-up', [MenuController::class, 'moveUp'])->name('menu.moveUp')->whereNumber('id');
    Route::post('/admin/menu/{id}/move-down', [MenuController::class, 'moveDown'])->name('menu.moveDown')->whereNumber('id');
    Route::get('/admin/menu/{id}', [MenuController::class, 'show'])->name('menu.show')->whereNumber('id');
});

// Legacy AtoM URL aliases — redirect to real Laravel auth routes
Route::middleware(['web'])->group(function () {
    Route::get('/cas/login', fn () => redirect()->route('login'));
    Route::get('/oidc/login', fn () => redirect()->route('login'));
    Route::get('/user/login', fn () => redirect()->route('login'));
    Route::get('/user/logout', fn () => redirect()->route('logout'));
    Route::get('/donor/dashboard', fn () => redirect('/'));
});
