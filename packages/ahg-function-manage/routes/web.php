<?php

use AhgFunctionManage\Controllers\FunctionController;
use Illuminate\Support\Facades\Route;

Route::get('/function/browse', [FunctionController::class, 'browse'])->name('function.browse');
Route::get('/function/{slug}', [FunctionController::class, 'show'])->name('function.show');
