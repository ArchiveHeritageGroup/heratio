<?php

use AhgFunctionManage\Controllers\FunctionController;
use Illuminate\Support\Facades\Route;

Route::get('/function/browse', [FunctionController::class, 'browse'])->name('function.browse');

Route::middleware('auth')->group(function () {
    Route::get('/function/add', [FunctionController::class, 'create'])->name('function.create');
    Route::post('/function/add', [FunctionController::class, 'store'])->name('function.store')->middleware('acl:create');
    Route::get('/function/{slug}/edit', [FunctionController::class, 'edit'])->name('function.edit');
    Route::post('/function/{slug}/edit', [FunctionController::class, 'update'])->name('function.update')->middleware('acl:update');
});

Route::middleware('admin')->group(function () {
    Route::get('/function/{slug}/delete', [FunctionController::class, 'confirmDelete'])->name('function.confirmDelete');
    Route::delete('/function/{slug}/delete', [FunctionController::class, 'destroy'])->name('function.destroy')->middleware('acl:delete');
});

Route::get('/function/{slug}', [FunctionController::class, 'show'])->name('function.show');

Route::middleware(['web'])->group(function () {

// Auto-registered stub routes
Route::match(['get','post'], '/autocomplete', function() { return view('ahg-function-manage::autocomplete'); })->name('function.autocomplete');
});
