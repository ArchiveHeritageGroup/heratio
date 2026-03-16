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
    Route::get('/admin/menu/{id}', [MenuController::class, 'show'])->name('menu.show')->whereNumber('id');
});
