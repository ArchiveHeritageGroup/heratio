<?php

use AhgRequestPublish\Controllers\RequestPublishController;
use Illuminate\Support\Facades\Route;

// Public alias for DB menu path
Route::middleware('auth')->group(function () {
    Route::get('/requesttopublish/browse', [RequestPublishController::class, 'browse']);
});

Route::middleware('admin')->group(function () {
    Route::get('/admin/request-publish', [RequestPublishController::class, 'browse'])->name('request-publish.browse');
    Route::get('/admin/request-publish/{id}/edit', [RequestPublishController::class, 'edit'])->name('request-publish.edit')->whereNumber('id');
    Route::post('/admin/request-publish/{id}/update', [RequestPublishController::class, 'update'])->name('request-publish.update')->whereNumber('id');
    Route::post('/admin/request-publish/{id}/delete', [RequestPublishController::class, 'destroy'])->name('request-publish.destroy')->whereNumber('id');
    Route::match(['get','post'], '/admin/request-publish/{id}/edit-request', [RequestPublishController::class, 'editRequest'])->name('request-publish.edit-request')->whereNumber('id');
});

Route::middleware('auth')->group(function () {
    Route::post('/request-publish/submit/{slug}', [RequestPublishController::class, 'submit'])->name('request-publish.submit');
});
