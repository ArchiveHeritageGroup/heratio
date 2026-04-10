<?php

use AhgResearcherManage\Controllers\ResearcherSubmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/researcher/dashboard', [ResearcherSubmissionController::class, 'dashboard'])->name('researcher.dashboard');
    Route::get('/researcher/submissions', [ResearcherSubmissionController::class, 'submissions'])->name('researcher.submissions');
    Route::get('/researcher/pending', [ResearcherSubmissionController::class, 'pending'])->name('researcher.pending');
    Route::get('/researcher/submission/new', [ResearcherSubmissionController::class, 'newSubmission'])->name('researcher.new-submission');
    Route::get('/researcher/import', [ResearcherSubmissionController::class, 'importExchange'])->name('researcher.import');
    Route::post('/researcher/import', [ResearcherSubmissionController::class, 'importExchangeStore'])->name('researcher.import.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/researcher/browse', [ResearcherSubmissionController::class, 'researcherBrowse'])->name('researcher.browse');
    Route::match(['get','post'], '/researcher/add', [ResearcherSubmissionController::class, 'researcherAdd'])->name('researcher.add');
    Route::match(['get','post'], '/researcher/{id}/edit', [ResearcherSubmissionController::class, 'researcherEdit'])->name('researcher.edit')->whereNumber('id');
    Route::get('/researcher/{id}/view', [ResearcherSubmissionController::class, 'researcherView'])->name('researcher.view')->whereNumber('id');
    Route::get('/researcher/submission/{id}', [ResearcherSubmissionController::class, 'submissionView'])->name('researcher.submission.view')->whereNumber('id');
});

Route::middleware('admin')->group(function () {
    Route::post('/researcher/{id}/delete', [ResearcherSubmissionController::class, 'researcherDelete'])->name('researcher.delete')->whereNumber('id');
});

Route::middleware('auth')->group(function () {
    Route::match(['get','post'], '/api-upload', function() { return view('researchermanage::api-upload'); })->name('researcher.apiUpload');
    Route::match(['get','post'], '/api-delete-file', function() { return view('researchermanage::api-delete-file'); })->name('researcher.apiDeleteFile');
    Route::match(['get','post'], '/api-autocomplete', function() { return view('researchermanage::api-autocomplete'); })->name('researcher.apiAutocomplete');
});
