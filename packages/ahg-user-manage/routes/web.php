<?php

use AhgUserManage\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/users', [UserController::class, 'browse'])->name('user.browse');
    Route::get('/user/list', [UserController::class, 'browse']); // AtoM menu alias

    Route::get('/user/add', [UserController::class, 'create'])->name('user.create');
    Route::post('/user/add', [UserController::class, 'store'])->name('user.store');

    Route::get('/user/{slug}/edit', [UserController::class, 'edit'])->name('user.edit');
    Route::post('/user/{slug}/edit', [UserController::class, 'update'])->name('user.update');

    Route::get('/user/{slug}/delete', [UserController::class, 'confirmDelete'])->name('user.confirmDelete');
    Route::delete('/user/{slug}/delete', [UserController::class, 'destroy'])->name('user.destroy');

    Route::get('/user/{slug}', [UserController::class, 'show'])
        ->name('user.show')
        ->where('slug', '^(?!register|profile|password|password-reset|add).*$');
});
    Route::get('/user/registration/pending', [UserController::class, 'registrationPending'])->name('user.registration.pending');
    Route::match(['get','post'], '/user/register', [UserController::class, 'register'])->name('user.register');
    Route::get('/user/verify/{token}', [UserController::class, 'verify'])->name('user.verify');
    Route::get('/user/view/{slug}', [UserController::class, 'userView'])->name('user.view');
