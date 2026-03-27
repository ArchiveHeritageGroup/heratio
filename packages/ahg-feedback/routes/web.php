<?php

use AhgFeedback\Controllers\FeedbackController;
use Illuminate\Support\Facades\Route;

// Public feedback submission
Route::match(['get', 'post'], '/feedback/general', [FeedbackController::class, 'general'])->name('feedback.general');
Route::get('/feedback/submit/{slug?}', [FeedbackController::class, 'general'])->name('feedback.submit');
Route::get('/feedback/submit-success', [FeedbackController::class, 'submitSuccess'])->name('feedback.submit-success');

Route::middleware('admin')->group(function () {
    Route::get('/feedback/index', [FeedbackController::class, 'browse']);
    Route::get('/admin/feedback', [FeedbackController::class, 'browse'])->name('feedback.browse');
    Route::get('/admin/feedback/{id}/edit', [FeedbackController::class, 'edit'])->name('feedback.edit')->whereNumber('id');
    Route::post('/admin/feedback/{id}/update', [FeedbackController::class, 'update'])->name('feedback.update')->whereNumber('id');
    Route::post('/admin/feedback/{id}/delete', [FeedbackController::class, 'destroy'])->name('feedback.destroy')->whereNumber('id');
    Route::get('/feedback/view/{id}', [FeedbackController::class, 'view'])->name('feedback.view')->whereNumber('id');
});
