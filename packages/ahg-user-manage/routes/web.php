<?php

use AhgUserManage\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/users', [UserController::class, 'browse'])->name('user.browse');
});

Route::get('/user/{slug}', [UserController::class, 'show'])
    ->name('user.show')
    ->where('slug', '^(?!register|profile|password|password-reset).*$');
