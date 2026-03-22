<?php

use AhgAccessionManage\Controllers\AccessionController;
use Illuminate\Support\Facades\Route;

Route::get('/accession/browse', [AccessionController::class, 'browse'])->name('accession.browse');

Route::middleware('auth')->group(function () {
    Route::get('/accession/add', [AccessionController::class, 'create'])->name('accession.create');
    Route::post('/accession/add', [AccessionController::class, 'store'])->name('accession.store');
    Route::get('/accession/{slug}/edit', [AccessionController::class, 'edit'])->name('accession.edit');
    Route::post('/accession/{slug}/edit', [AccessionController::class, 'update'])->name('accession.update');
});

Route::middleware('admin')->group(function () {
    Route::get('/accession/{slug}/delete', [AccessionController::class, 'confirmDelete'])->name('accession.confirmDelete');
    Route::delete('/accession/{slug}/delete', [AccessionController::class, 'destroy'])->name('accession.destroy');
    Route::get('/accession/export-csv', [AccessionController::class, 'exportCsv'])->name('accession.export-csv');
    Route::get('/accession/intake-queue', [AccessionController::class, 'intakeQueue'])->name('accession.intake-queue');
    Route::get('/accession/dashboard', [AccessionController::class, 'dashboard'])->name('accession.dashboard');
    Route::get('/accession/valuation-report', [AccessionController::class, 'valuationReport'])->name('accession.valuation-report');

    // Appraisal & valuation
    Route::get('/accession/{id}/appraisal', [AccessionController::class, 'appraisal'])->name('accession.appraisal')->where('id', '[0-9]+');
    Route::post('/accession/{id}/appraisal', [AccessionController::class, 'appraisalStore'])->name('accession.appraisal-store')->where('id', '[0-9]+');
    Route::get('/accession/appraisal-templates', [AccessionController::class, 'appraisalTemplates'])->name('accession.appraisal-templates');
    Route::get('/accession/{id}/valuation', [AccessionController::class, 'valuation'])->name('accession.valuation')->where('id', '[0-9]+');

    // Containers & rights
    Route::get('/accession/{id}/containers', [AccessionController::class, 'containers'])->name('accession.containers')->where('id', '[0-9]+');
    Route::get('/accession/{id}/rights', [AccessionController::class, 'rights'])->name('accession.rights')->where('id', '[0-9]+');

    // Intake workflow
    Route::get('/accession/{id}/attachments', [AccessionController::class, 'attachments'])->name('accession.attachments')->where('id', '[0-9]+');
    Route::post('/accession/{id}/attachments', [AccessionController::class, 'attachmentsStore'])->name('accession.attachments-store')->where('id', '[0-9]+');
    Route::get('/accession/{id}/checklist', [AccessionController::class, 'checklist'])->name('accession.checklist')->where('id', '[0-9]+');
    Route::post('/accession/{id}/checklist', [AccessionController::class, 'checklistStore'])->name('accession.checklist-store')->where('id', '[0-9]+');
    Route::get('/accession/intake-config', [AccessionController::class, 'intakeConfig'])->name('accession.intake-config');
    Route::post('/accession/intake-config', [AccessionController::class, 'intakeConfigStore'])->name('accession.intake-config-store');
    Route::get('/accession/numbering', [AccessionController::class, 'numbering'])->name('accession.numbering');
    Route::post('/accession/numbering', [AccessionController::class, 'numberingStore'])->name('accession.numbering-store');
    Route::get('/accession/queue', [AccessionController::class, 'queue'])->name('accession.queue');
    Route::get('/accession/{id}/queue-detail', [AccessionController::class, 'queueDetail'])->name('accession.queue-detail')->where('id', '[0-9]+');
    Route::get('/accession/{id}/timeline', [AccessionController::class, 'timeline'])->name('accession.timeline')->where('id', '[0-9]+');
});

Route::get('/accession/{slug}', [AccessionController::class, 'show'])->name('accession.show');

Route::middleware(['web'])->group(function () {

// Auto-registered stub routes
Route::match(['get','post'], '/donor/autocomplete', function() { return view('accessionmanage::autocomplete'); })->name('donor.autocomplete');
Route::match(['get','post'], '/donor/add', function() { return view('accessionmanage::add'); })->name('donor.add');
});
