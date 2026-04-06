<?php

use AhgIntegrity\Controllers\IntegrityController;
use Illuminate\Support\Facades\Route;

// Legacy URL alias: /admin/integrity → /integrity/index
Route::get('/admin/integrity', fn () => redirect('/integrity/index', 301));

Route::middleware('admin')->group(function () {
    Route::get('/integrity/index', [IntegrityController::class, 'index'])->name('integrity.index');
    Route::get('/integrity/alerts', [IntegrityController::class, 'alerts'])->name('integrity.alerts');
    Route::get('/integrity/dead-letter', [IntegrityController::class, 'deadLetter'])->name('integrity.dead-letter');
    Route::get('/integrity/disposition', [IntegrityController::class, 'disposition'])->name('integrity.disposition');
    Route::get('/integrity/export', [IntegrityController::class, 'export'])->name('integrity.export');
    Route::get('/integrity/holds', [IntegrityController::class, 'holds'])->name('integrity.holds');
    Route::get('/integrity/ledger', [IntegrityController::class, 'ledger'])->name('integrity.ledger');
    Route::get('/integrity/policies', [IntegrityController::class, 'policies'])->name('integrity.policies');
    Route::get('/integrity/policies/{id}/edit', [IntegrityController::class, 'policyEdit'])->name('integrity.policies.edit')->where('id', '[0-9]+');
    Route::put('/integrity/policies/{id}', [IntegrityController::class, 'policyUpdate'])->name('integrity.policies.update')->where('id', '[0-9]+');
    Route::get('/integrity/report', [IntegrityController::class, 'report'])->name('integrity.report');
    Route::get('/integrity/runs', [IntegrityController::class, 'runs'])->name('integrity.runs');
    Route::get('/integrity/runs/{id}', [IntegrityController::class, 'runDetail'])->name('integrity.run-detail')->where('id', '[0-9]+');
    Route::get('/integrity/schedules', [IntegrityController::class, 'schedules'])->name('integrity.schedules');
    Route::get('/integrity/schedules/{id}/edit', [IntegrityController::class, 'scheduleEdit'])->name('integrity.schedules.edit')->where('id', '[0-9]+');
    Route::put('/integrity/schedules/{id}', [IntegrityController::class, 'scheduleUpdate'])->name('integrity.schedules.update')->where('id', '[0-9]+');

    // Legal Holds
    Route::get('/integrity/holds/create', [IntegrityController::class, 'holdCreate'])->name('integrity.holds.create');
    Route::post('/integrity/holds', [IntegrityController::class, 'holdStore'])->name('integrity.holds.store');
    Route::post('/integrity/holds/{id}/release', [IntegrityController::class, 'holdRelease'])->name('integrity.holds.release')->where('id', '[0-9]+');
    Route::get('/integrity/holds/{ioId}/history', [IntegrityController::class, 'holdHistory'])->name('integrity.holds.history')->where('ioId', '[0-9]+');
    Route::get('/integrity/holds/check/{ioId}', [IntegrityController::class, 'holdCheck'])->name('integrity.holds.check')->where('ioId', '[0-9]+');

    // Destruction Certificates
    Route::get('/integrity/certificates', [IntegrityController::class, 'certificates'])->name('integrity.certificates');
    Route::get('/integrity/certificates/generate/{dispositionId}', [IntegrityController::class, 'certificateGenerate'])->name('integrity.certificates.generate')->where('dispositionId', '[0-9]+');
    Route::post('/integrity/certificates', [IntegrityController::class, 'certificateStore'])->name('integrity.certificates.store');
    Route::get('/integrity/certificates/{id}', [IntegrityController::class, 'certificateView'])->name('integrity.certificates.view')->where('id', '[0-9]+');

    // Retention Events (P1.4)
    Route::get('/integrity/retention-events', [IntegrityController::class, 'retentionEvents'])->name('integrity.retention-events');
    Route::post('/integrity/retention-events', [IntegrityController::class, 'retentionEventStore'])->name('integrity.retention-events.store');

    // Record Declarations (P1.5)
    Route::get('/integrity/declarations', [IntegrityController::class, 'declarations'])->name('integrity.declarations');
    Route::post('/integrity/declare-record', [IntegrityController::class, 'declareRecord'])->name('integrity.declare-record');
    Route::post('/integrity/declarations/{ioId}/approve', [IntegrityController::class, 'approveDeclaration'])->name('integrity.declarations.approve')->where('ioId', '[0-9]+');

    // Vital Records (P1.6)
    Route::get('/integrity/vital-records', [IntegrityController::class, 'vitalRecords'])->name('integrity.vital-records');
    Route::get('/integrity/vital-records/overdue', [IntegrityController::class, 'vitalRecordsOverdue'])->name('integrity.vital-records.overdue');
    Route::post('/integrity/vital-records/flag', [IntegrityController::class, 'vitalRecordFlag'])->name('integrity.vital-records.flag');
    Route::post('/integrity/vital-records/{ioId}/unflag', [IntegrityController::class, 'vitalRecordUnflag'])->name('integrity.vital-records.unflag')->where('ioId', '[0-9]+');
    Route::post('/integrity/vital-records/{id}/review', [IntegrityController::class, 'vitalRecordReview'])->name('integrity.vital-records.review')->where('id', '[0-9]+');
});
