<?php

use AhgUserManage\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/users', [UserController::class, 'browse'])->name('user.browse');
    Route::get('/user/list', [UserController::class, 'browse'])->name('user.list'); // AtoM menu alias

    Route::get('/user/add', [UserController::class, 'create'])->name('user.add');
    Route::post('/user/add', [UserController::class, 'store'])->name('user.store');

    Route::get('/user/{slug}/edit', [UserController::class, 'edit'])->name('user.edit');
    Route::post('/user/{slug}/edit', [UserController::class, 'update'])->name('user.update');

    Route::get('/user/{slug}/delete', [UserController::class, 'confirmDelete'])->name('user.confirmDelete');
    Route::delete('/user/{slug}/delete', [UserController::class, 'destroy'])->name('user.destroy');

    // ACL permission pages
    Route::get('/user/{slug}/indexActorAcl', [UserController::class, 'indexActorAcl'])->name('user.indexActorAcl');
    Route::match(['get', 'post'], '/user/{slug}/editActorAcl', [UserController::class, 'editActorAcl'])->name('user.editActorAcl');

    Route::get('/user/{slug}/indexInformationObjectAcl', [UserController::class, 'indexInformationObjectAcl'])->name('user.indexInformationObjectAcl');
    Route::match(['get', 'post'], '/user/{slug}/editInformationObjectAcl', [UserController::class, 'editInformationObjectAcl'])->name('user.editInformationObjectAcl');

    Route::get('/user/{slug}/indexRepositoryAcl', [UserController::class, 'indexRepositoryAcl'])->name('user.indexRepositoryAcl');
    Route::match(['get', 'post'], '/user/{slug}/editRepositoryAcl', [UserController::class, 'editRepositoryAcl'])->name('user.editRepositoryAcl');

    Route::get('/user/{slug}/indexTermAcl', [UserController::class, 'indexTermAcl'])->name('user.indexTermAcl');
    Route::match(['get', 'post'], '/user/{slug}/editTermAcl', [UserController::class, 'editTermAcl'])->name('user.editTermAcl');

    Route::match(['get', 'post'], '/user/{slug}/editResearcherAcl', [UserController::class, 'editResearcherAcl'])->name('user.editResearcherAcl');

    Route::get('/user/{slug}', [UserController::class, 'show'])
        ->name('user.show')
        ->where('slug', '^(?!register|profile|password|password-reset|add).*$');
});
    Route::get('/user/registration/pending', [UserController::class, 'registrationPending'])->name('user.registration.pending');
    Route::match(['get','post'], '/user/register', [UserController::class, 'register'])->name('user.register');
    Route::get('/user/verify/{token}', [UserController::class, 'verify'])->name('user.verify');
    Route::get('/user/view/{slug}', [UserController::class, 'userView'])->name('user.view');

