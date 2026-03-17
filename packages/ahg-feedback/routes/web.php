<?php

use AhgFeedback\Controllers\FeedbackController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/feedback/general', [FeedbackController::class, 'general'])->name('feedback.general');
Route::get('/feedback/submit/{slug?}', [FeedbackController::class, 'general'])->name('feedback.submit');
