<?php

use AhgAccessionManage\Controllers\AccessionController;
use Illuminate\Support\Facades\Route;

// Accession create form requires auth
Route::middleware('auth')->group(function () {
    Route::get('/accession/add', [AccessionController::class, 'create'])->name('accession.create');
});

Route::middleware('auth')->group(function () {
    Route::get('/accession/browse', [AccessionController::class, 'browse'])->name('accession.browse');
    Route::post('/accession/add', [AccessionController::class, 'store'])->name('accession.store')->middleware('acl:create');
    Route::get('/accession/{slug}/edit', [AccessionController::class, 'edit'])->name('accession.edit');
    Route::post('/accession/{slug}/edit', [AccessionController::class, 'update'])->name('accession.update')->middleware('acl:update');
    // Finalise transition. Sets accession_v2.status='accepted' + accepted_at,
    // gated on AccessionService::finalisationBlockers() (which honours
    // accession_require_donor_agreement + accession_require_appraisal).
    Route::post('/accession/{slug}/finalise', [AccessionController::class, 'finalise'])->name('accession.finalise')->middleware('acl:update');
});

Route::middleware('admin')->group(function () {
    Route::get('/accession/{slug}/delete', [AccessionController::class, 'confirmDelete'])->name('accession.confirmDelete');
    Route::delete('/accession/{slug}/delete', [AccessionController::class, 'destroy'])->name('accession.destroy')->middleware('acl:delete');
    Route::get('/accession/export-csv', [AccessionController::class, 'exportCsv'])->name('accession.export-csv');
    Route::get('/accession/intake-queue', [AccessionController::class, 'intakeQueue'])->name('accession.intake-queue');
    Route::get('/accession/dashboard', [AccessionController::class, 'dashboard'])->name('accession.dashboard');
    Route::get('/accession/valuation-report', [AccessionController::class, 'valuationReport'])->name('accession.valuation-report');

    // Appraisal & valuation
    Route::get('/accession/{id}/appraisal', [AccessionController::class, 'appraisal'])->name('accession.appraisal')->where('id', '[0-9]+');
    Route::post('/accession/{id}/appraisal', [AccessionController::class, 'appraisalStore'])->name('accession.appraisal-store')->middleware('acl:update')->where('id', '[0-9]+');
    Route::get('/accession/appraisal-templates', [AccessionController::class, 'appraisalTemplates'])->name('accession.appraisal-templates');
    Route::get('/accession/{id}/valuation', [AccessionController::class, 'valuation'])->name('accession.valuation')->where('id', '[0-9]+');

    // Containers & rights
    Route::get('/accession/{id}/containers', [AccessionController::class, 'containers'])->name('accession.containers')->where('id', '[0-9]+');
    Route::get('/accession/{id}/rights', [AccessionController::class, 'rights'])->name('accession.rights')->where('id', '[0-9]+');

    // Intake workflow
    Route::get('/accession/{id}/attachments', [AccessionController::class, 'attachments'])->name('accession.attachments')->where('id', '[0-9]+');
    Route::post('/accession/{id}/attachments', [AccessionController::class, 'attachmentsStore'])->name('accession.attachments-store')->middleware('acl:update')->where('id', '[0-9]+');
    Route::get('/accession/{id}/checklist', [AccessionController::class, 'checklist'])->name('accession.checklist')->where('id', '[0-9]+');
    Route::post('/accession/{id}/checklist', [AccessionController::class, 'checklistStore'])->name('accession.checklist-store')->middleware('acl:update')->where('id', '[0-9]+');
    Route::get('/accession/intake-config', [AccessionController::class, 'intakeConfig'])->name('accession.intake-config');
    Route::post('/accession/intake-config', [AccessionController::class, 'intakeConfigStore'])->name('accession.intake-config-store')->middleware('acl:update');
    Route::get('/accession/numbering', [AccessionController::class, 'numbering'])->name('accession.numbering');
    Route::post('/accession/numbering', [AccessionController::class, 'numberingStore'])->name('accession.numbering-store')->middleware('acl:update');
    Route::get('/accession/queue', [AccessionController::class, 'queue'])->name('accession.queue');
    Route::get('/accession/{id}/queue-detail', [AccessionController::class, 'queueDetail'])->name('accession.queue-detail')->where('id', '[0-9]+');
    Route::get('/accession/{id}/timeline', [AccessionController::class, 'timeline'])->name('accession.timeline')->where('id', '[0-9]+');
});

Route::get('/accession/{slug}', [AccessionController::class, 'show'])->name('accession.show');

Route::middleware(['web'])->group(function () {

// Auto-registered stub routes
Route::match(['get','post'], '/donor/autocomplete', function() { return view('ahg-accession-manage::autocomplete'); })->name('donor.autocomplete');
Route::match(['get','post'], '/donor/add', function() { return view('ahg-accession-manage::add'); })->name('donor.add');
});
