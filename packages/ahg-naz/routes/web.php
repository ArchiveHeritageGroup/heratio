<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/naz')->middleware(['web', 'auth'])->group(function () {
    Route::get('/closure-create', [\AhgNaz\Controllers\NazController::class, 'closureCreate'])->name('ahgnaz.closure-create');
    Route::get('/closure-edit', [\AhgNaz\Controllers\NazController::class, 'closureEdit'])->name('ahgnaz.closure-edit');
    Route::get('/closures', [\AhgNaz\Controllers\NazController::class, 'closures'])->name('ahgnaz.closures');
    Route::get('/config', [\AhgNaz\Controllers\NazController::class, 'config'])->name('ahgnaz.config');
    Route::get('/index', [\AhgNaz\Controllers\NazController::class, 'index'])->name('ahgnaz.index');
    Route::get('/permit-create', [\AhgNaz\Controllers\NazController::class, 'permitCreate'])->name('ahgnaz.permit-create');
    Route::get('/permit-view', [\AhgNaz\Controllers\NazController::class, 'permitView'])->name('ahgnaz.permit-view');
    Route::get('/permits', [\AhgNaz\Controllers\NazController::class, 'permits'])->name('ahgnaz.permits');
    Route::get('/protected-records', [\AhgNaz\Controllers\NazController::class, 'protectedRecords'])->name('ahgnaz.protected-records');
    Route::get('/reports', [\AhgNaz\Controllers\NazController::class, 'reports'])->name('ahgnaz.reports');
    Route::get('/researcher-create', [\AhgNaz\Controllers\NazController::class, 'researcherCreate'])->name('ahgnaz.researcher-create');
    Route::get('/researcher-view', [\AhgNaz\Controllers\NazController::class, 'researcherView'])->name('ahgnaz.researcher-view');
    Route::get('/researchers', [\AhgNaz\Controllers\NazController::class, 'researchers'])->name('ahgnaz.researchers');
    Route::get('/schedule-create', [\AhgNaz\Controllers\NazController::class, 'scheduleCreate'])->name('ahgnaz.schedule-create');
    Route::get('/schedule-view', [\AhgNaz\Controllers\NazController::class, 'scheduleView'])->name('ahgnaz.schedule-view');
    Route::get('/schedules', [\AhgNaz\Controllers\NazController::class, 'schedules'])->name('ahgnaz.schedules');
    Route::get('/transfer-create', [\AhgNaz\Controllers\NazController::class, 'transferCreate'])->name('ahgnaz.transfer-create');
    Route::get('/transfer-view', [\AhgNaz\Controllers\NazController::class, 'transferView'])->name('ahgnaz.transfer-view');
    Route::get('/transfers', [\AhgNaz\Controllers\NazController::class, 'transfers'])->name('ahgnaz.transfers');
});
