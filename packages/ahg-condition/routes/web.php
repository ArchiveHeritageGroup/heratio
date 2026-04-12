<?php

use AhgCondition\Controllers\ConditionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('condition')->group(function () {
    Route::get('/check/{slug}', [ConditionController::class, 'conditionCheck'])->name('condition.check');
    Route::get('/{id}/view', [ConditionController::class, 'view'])->name('condition.view')->where('id', '[0-9]+');
    Route::get('/check/{id}/photos', [ConditionController::class, 'photos'])->name('condition.photos');
    Route::get('/photo/{id}/annotate', [ConditionController::class, 'annotate'])->name('condition.annotate');
    Route::get('/export/{id}', [ConditionController::class, 'exportReport'])->name('condition.export');
    Route::get('/templates', [ConditionController::class, 'templateList'])->name('condition.templates');
    Route::get('/template/{id}', [ConditionController::class, 'templateView'])->name('condition.template.view');

    // AJAX
    Route::get('/annotation', [ConditionController::class, 'getAnnotation'])->name('condition.annotation.get');
    Route::post('/annotation/save', [ConditionController::class, 'saveAnnotation'])->name('condition.annotation.save');
    Route::post('/photo/upload', [ConditionController::class, 'upload'])->name('condition.photo.upload');
    Route::post('/photo/{id}/delete', [ConditionController::class, 'deletePhoto'])->name('condition.photo.delete');
});

Route::middleware(['auth', 'admin'])->prefix('condition')->group(function () {
    Route::get('/admin', [ConditionController::class, 'admin'])->name('condition.admin');
    Route::get('/list', [ConditionController::class, 'list'])->name('condition.list');
});

// Dashboard URL alias under /admin/condition (matches reports dashboard link)
Route::middleware(['auth', 'admin'])->prefix('admin/condition')->group(function () {
    Route::get('/', [ConditionController::class, 'admin'])->name('admin.condition');
});

// Legacy AtoM base-path aliases (AJAX JSON responses)
Route::middleware('auth')->group(function () {
    // GET /condition/check — base path returns JSON listing of recent condition checks
    Route::get('/condition/check', [ConditionController::class, 'checkIndex'])->name('condition.check.index');
    // POST /condition/photo — base path for photo upload (alias to upload route)
    Route::post('/condition/photo', [ConditionController::class, 'upload'])->name('condition.photo.base');
    // GET /condition/photo — base path returns JSON error directing to proper endpoint
    Route::get('/condition/photo', function () {
        return response()->json([
            'error' => 'Specify a photo action: /condition/photo/upload, /condition/photo/{id}/annotate, /condition/photo/{id}/delete',
        ], 400);
    })->name('condition.photo.index');
});
