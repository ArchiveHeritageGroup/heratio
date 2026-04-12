<?php

use AhgUserManage\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/users', [UserController::class, 'browse'])->name('user.browse');
    Route::get('/user/list', [UserController::class, 'browse'])->name('user.list'); // AtoM menu alias
    Route::get('/user/browse', [UserController::class, 'browse']); // Heratio alias

    Route::get('/user/add', [UserController::class, 'create'])->name('user.add');
    Route::post('/user/add', [UserController::class, 'store'])->name('user.store')->middleware('acl:create');

    Route::get('/user/{slug}/edit', [UserController::class, 'edit'])->name('user.edit')
        ->where('slug', '(?!profile|password|passwordEdit|passwordReset|register|clipboard|registration|view|verify|add|list|browse)[a-zA-Z0-9][a-zA-Z0-9._-]*');
    Route::post('/user/{slug}/edit', [UserController::class, 'update'])->name('user.update')->middleware('acl:update')
        ->where('slug', '(?!profile|password|passwordEdit|passwordReset|register|clipboard|registration|view|verify|add|list|browse)[a-zA-Z0-9][a-zA-Z0-9._-]*');

    Route::get('/user/{slug}/delete', [UserController::class, 'confirmDelete'])->name('user.confirmDelete')
        ->where('slug', '(?!profile|password|register|clipboard|registration|view|verify|add|list|browse)[a-zA-Z0-9][a-zA-Z0-9._-]*');
    Route::delete('/user/{slug}/delete', [UserController::class, 'destroy'])->name('user.destroy')->middleware('acl:delete')
        ->where('slug', '(?!profile|password|register|clipboard|registration|view|verify|add|list|browse)[a-zA-Z0-9][a-zA-Z0-9._-]*');

    // ACL permission pages
    Route::get('/user/{slug}/indexActorAcl', [UserController::class, 'indexActorAcl'])->name('user.indexActorAcl');
    Route::match(['get', 'post'], '/user/{slug}/editActorAcl', [UserController::class, 'editActorAcl'])->name('user.editActorAcl'); // ACL check in controller for POST only

    Route::get('/user/{slug}/indexInformationObjectAcl', [UserController::class, 'indexInformationObjectAcl'])->name('user.indexInformationObjectAcl');
    Route::match(['get', 'post'], '/user/{slug}/editInformationObjectAcl', [UserController::class, 'editInformationObjectAcl'])->name('user.editInformationObjectAcl'); // ACL check in controller for POST only

    Route::get('/user/{slug}/indexRepositoryAcl', [UserController::class, 'indexRepositoryAcl'])->name('user.indexRepositoryAcl');
    Route::match(['get', 'post'], '/user/{slug}/editRepositoryAcl', [UserController::class, 'editRepositoryAcl'])->name('user.editRepositoryAcl'); // ACL check in controller for POST only

    Route::get('/user/{slug}/indexTermAcl', [UserController::class, 'indexTermAcl'])->name('user.indexTermAcl');
    Route::match(['get', 'post'], '/user/{slug}/editTermAcl', [UserController::class, 'editTermAcl'])->name('user.editTermAcl'); // ACL check in controller for POST only

    Route::match(['get', 'post'], '/user/{slug}/editResearcherAcl', [UserController::class, 'editResearcherAcl'])->name('user.editResearcherAcl'); // ACL check in controller for POST only

    Route::get('/user/{slug}', [UserController::class, 'show'])
        ->name('user.show')
        ->where('slug', '(?!register|profile|password|passwordEdit|passwordReset|add|list|browse|clipboard|registration|view|verify)[a-zA-Z0-9][a-zA-Z0-9._-]*');
});
Route::match(['get','post'], '/user/register', [UserController::class, 'register'])->name('user.register');
Route::get('/user/verify/{token}', [UserController::class, 'verify'])->name('user.verify');
Route::get('/user/view/{slug}', [UserController::class, 'userView'])->name('user.view');

// Authenticated user self-service routes
Route::middleware('auth')->group(function () {
    Route::get('/user/profile', [UserController::class, 'profile'])->name('user.profile');
    Route::get('/user/profile/edit', [UserController::class, 'profileEdit'])->name('user.profile.edit');
    Route::get('/user/passwordEdit', [UserController::class, 'passwordEdit'])->name('user.passwordEdit');
    Route::match(['get', 'post'], '/user/passwordReset', [UserController::class, 'passwordReset'])->name('user.passwordReset');
    Route::get('/user/clipboard', [UserController::class, 'clipboard'])->name('user.clipboard');
});

// Legacy browse redirect
Route::get('/user', fn () => redirect('/admin/users', 301));

Route::middleware('admin')->group(function () {
    Route::get('/user/registration/pending', [UserController::class, 'registrationPending'])->name('user.registration.pending');
    Route::post('/user/registration/approve', [UserController::class, 'registrationApprove'])->name('user.registration.approve')->middleware('acl:update');
    Route::post('/user/registration/reject', [UserController::class, 'registrationReject'])->name('user.registration.reject')->middleware('acl:delete');
});

