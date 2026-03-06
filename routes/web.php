<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Authentication routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Homepage (temporary — will be replaced by theme)
Route::get('/', function () {
    $user = Auth::user();

    if (! $user) {
        return view('welcome');
    }

    return view('home', ['user' => $user]);
})->name('home');
