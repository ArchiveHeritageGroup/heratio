<?php

use AhgMenuManage\Controllers\MenuController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/menus', [MenuController::class, 'browse'])->name('menu.browse');
Route::get('/admin/menus/{id}', [MenuController::class, 'show'])->name('menu.show')->whereNumber('id');
