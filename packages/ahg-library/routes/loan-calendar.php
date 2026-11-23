<?php

/**
 * Loan-rule editing and the circulation calendar - #1475 item 1.
 */

use AhgLibrary\Controllers\LoanCalendarController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('library-manage/circulation')->group(function () {
    Route::get('/loan-rules/{id}/edit', [LoanCalendarController::class, 'editRule'])
        ->name('library.loan-rules.edit')->whereNumber('id')->middleware('acl:update');
    Route::put('/loan-rules/{id}', [LoanCalendarController::class, 'updateRule'])
        ->name('library.loan-rules.update')->whereNumber('id')->middleware('acl:update');

    Route::get('/calendar', [LoanCalendarController::class, 'calendar'])->name('library.calendar');
    Route::put('/calendar/hours', [LoanCalendarController::class, 'saveHours'])
        ->name('library.calendar.hours')->middleware('acl:update');
    Route::post('/calendar/hours/reset', [LoanCalendarController::class, 'resetHours'])
        ->name('library.calendar.hours.reset')->middleware('acl:update');
    Route::post('/calendar/closures', [LoanCalendarController::class, 'storeClosure'])
        ->name('library.calendar.closures.store')->middleware('acl:create');
    Route::get('/calendar/closures/{id}/edit', [LoanCalendarController::class, 'editClosure'])
        ->name('library.calendar.closures.edit')->whereNumber('id')->middleware('acl:update');
    Route::put('/calendar/closures/{id}', [LoanCalendarController::class, 'updateClosure'])
        ->name('library.calendar.closures.update')->whereNumber('id')->middleware('acl:update');
    Route::post('/calendar/closures/{id}/delete', [LoanCalendarController::class, 'deleteClosure'])
        ->name('library.calendar.closures.delete')->whereNumber('id')->middleware('acl:delete');
});
