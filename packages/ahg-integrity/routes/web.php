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
});
