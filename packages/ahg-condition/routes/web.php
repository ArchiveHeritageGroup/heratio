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
