<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/naz')->middleware(['web', 'auth'])->group(function () {
    $c = \AhgNaz\Controllers\NazController::class;

    // Dashboard
    Route::get('/', [$c, 'index'])->name('ahgnaz.index');

    // Config (settings)
    Route::get('/config', [$c, 'config'])->name('ahgnaz.config');
    Route::post('/config', [$c, 'configStore'])->name('ahgnaz.config.store');

    // Closure periods
    Route::get('/closures', [$c, 'closures'])->name('ahgnaz.closures');
    Route::get('/closures/create', [$c, 'closureCreate'])->name('ahgnaz.closure-create');
    Route::post('/closures/create', [$c, 'closureStore'])->name('ahgnaz.closure-store');
    Route::get('/closures/{id}/edit', [$c, 'closureEdit'])->name('ahgnaz.closure-edit');
    Route::post('/closures/{id}/edit', [$c, 'closureUpdate'])->name('ahgnaz.closure-update');

    // Protected records
    Route::get('/protected-records', [$c, 'protectedRecords'])->name('ahgnaz.protected-records');

    // Retention schedules
    Route::get('/schedules', [$c, 'schedules'])->name('ahgnaz.schedules');
    Route::get('/schedules/create', [$c, 'scheduleCreate'])->name('ahgnaz.schedule-create');
    Route::post('/schedules/create', [$c, 'scheduleStore'])->name('ahgnaz.schedule-store');
    Route::get('/schedules/{id}', [$c, 'scheduleView'])->name('ahgnaz.schedule-view');
    Route::post('/schedules/{id}', [$c, 'scheduleUpdate'])->name('ahgnaz.schedule-update');

    // Research permits
    Route::get('/permits', [$c, 'permits'])->name('ahgnaz.permits');
    Route::get('/permits/create', [$c, 'permitCreate'])->name('ahgnaz.permit-create');
    Route::post('/permits/create', [$c, 'permitStore'])->name('ahgnaz.permit-store');
    Route::get('/permits/{id}', [$c, 'permitView'])->name('ahgnaz.permit-view');
    Route::post('/permits/{id}', [$c, 'permitUpdate'])->name('ahgnaz.permit-update');

    // Researchers
    Route::get('/researchers', [$c, 'researchers'])->name('ahgnaz.researchers');
    Route::get('/researchers/create', [$c, 'researcherCreate'])->name('ahgnaz.researcher-create');
    Route::post('/researchers/create', [$c, 'researcherStore'])->name('ahgnaz.researcher-store');
    Route::get('/researchers/{id}', [$c, 'researcherView'])->name('ahgnaz.researcher-view');
    Route::post('/researchers/{id}', [$c, 'researcherUpdate'])->name('ahgnaz.researcher-update');

    // Transfers
    Route::get('/transfers', [$c, 'transfers'])->name('ahgnaz.transfers');
    Route::get('/transfers/create', [$c, 'transferCreate'])->name('ahgnaz.transfer-create');
    Route::post('/transfers/create', [$c, 'transferStore'])->name('ahgnaz.transfer-store');
    Route::get('/transfers/{id}', [$c, 'transferView'])->name('ahgnaz.transfer-view');
    Route::post('/transfers/{id}', [$c, 'transferUpdate'])->name('ahgnaz.transfer-update');

    // Reports
    Route::get('/reports', [$c, 'reports'])->name('ahgnaz.reports');

    // Audit log
    Route::get('/audit-log', [$c, 'auditLog'])->name('ahgnaz.audit-log');
});
