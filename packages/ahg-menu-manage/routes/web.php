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

Route::middleware(['web'])->group(function () {

// Auto-registered stub routes
Route::match(['get','post'], '/cas/login', function() { return view('menumanage::login'); })->name('cas.login');
Route::match(['get','post'], '/oidc/login', function() { return view('menumanage::login'); })->name('oidc.login');
Route::match(['get','post'], '/user/login', function() { return view('menumanage::login'); })->name('user.login');
Route::match(['get','post'], '/user/password-reset', function() { return view('menumanage::password-reset'); })->name('user.passwordReset');
Route::match(['get','post'], '/user/password-edit', function() { return view('menumanage::password-edit'); })->name('user.passwordEdit');
Route::match(['get','post'], '/user/logout', function() { return view('menumanage::logout'); })->name('user.logout');
Route::match(['get','post'], '/donor/dashboard', function() { return view('menumanage::dashboard'); })->name('donor.dashboard');
});
