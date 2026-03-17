<?php

use AhgResearcherManage\Controllers\ResearcherSubmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/researcher/dashboard', [ResearcherSubmissionController::class, 'dashboard'])->name('researcher.dashboard');
    Route::get('/researcher/submissions', [ResearcherSubmissionController::class, 'submissions'])->name('researcher.submissions');
    Route::get('/researcher/pending', [ResearcherSubmissionController::class, 'pending'])->name('researcher.pending');
    Route::get('/researcher/import', [ResearcherSubmissionController::class, 'importExchange'])->name('researcher.import');
    Route::post('/researcher/import', [ResearcherSubmissionController::class, 'importExchangeStore'])->name('researcher.import.store');
});
