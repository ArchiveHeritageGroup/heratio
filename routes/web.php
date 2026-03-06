<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// Authentication routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Registration
Route::get('/user/register', [LoginController::class, 'showRegister'])->name('register');
Route::post('/user/register', [LoginController::class, 'register']);
Route::get('/research/register', [LoginController::class, 'showResearcherRegister'])->name('researcher.register');
Route::post('/research/register', [LoginController::class, 'researcherRegister']);
Route::get('/research/registration-complete', [LoginController::class, 'registrationComplete'])->name('researcher.register.complete');

// Password reset (public)
Route::get('/user/password-reset', [LoginController::class, 'showPasswordReset'])->name('password.reset');
Route::post('/user/password-reset', [LoginController::class, 'submitPasswordReset']);
Route::get('/user/password-reset/{token}', [LoginController::class, 'showPasswordResetConfirm'])->name('password.reset.confirm');
Route::post('/user/password-reset/{token}', [LoginController::class, 'submitPasswordResetConfirm']);

// User profile and password change (auth required)
Route::middleware('auth.required')->group(function () {
    Route::get('/user/profile', [LoginController::class, 'showProfile'])->name('user.profile');
    Route::get('/user/profile/edit', [LoginController::class, 'showProfileEdit'])->name('user.profile.edit');
    Route::put('/user/profile', [LoginController::class, 'updateProfile'])->name('user.profile.update');
    Route::get('/user/password', [LoginController::class, 'showPasswordEdit'])->name('user.password.edit');
    Route::put('/user/password', [LoginController::class, 'updatePassword'])->name('user.password.update');
});

// Homepage
Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
