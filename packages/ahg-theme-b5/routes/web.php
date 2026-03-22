<?php

use Illuminate\Support\Facades\Route;


Route::middleware(['web'])->group(function () {

// Auto-registered stub routes
Route::match(['get','post'], '/logout', function() { return view('themeb5::logout'); })->name('logout');
Route::match(['get','post'], '/register', function() { return view('themeb5::register'); })->name('register');
});
