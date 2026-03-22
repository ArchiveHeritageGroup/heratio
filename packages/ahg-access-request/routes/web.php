<?php

use AhgAccessRequest\Controllers\AccessRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('access-request')->group(function () {
    Route::get('/new', [AccessRequestController::class, 'create'])->name('accessRequest.create');
    Route::post('/new', [AccessRequestController::class, 'store'])->name('accessRequest.store');
    Route::get('/my-requests', [AccessRequestController::class, 'myRequests'])->name('accessRequest.myRequests');
    Route::get('/request/{slug}', [AccessRequestController::class, 'requestObject'])->name('accessRequest.requestObject');
    Route::get('/{id}', [AccessRequestController::class, 'view'])->name('accessRequest.view');
});

Route::middleware(['auth', 'admin'])->prefix('access-request')->group(function () {
    Route::get('/pending', [AccessRequestController::class, 'pending'])->name('accessRequest.pending');
    Route::post('/{id}/approve', [AccessRequestController::class, 'approve'])->name('accessRequest.approve');
    Route::post('/{id}/deny', [AccessRequestController::class, 'deny'])->name('accessRequest.deny');
    Route::get('/approvers', [AccessRequestController::class, 'approvers'])->name('accessRequest.approvers');
    Route::post('/approvers/add', [AccessRequestController::class, 'addApprover'])->name('accessRequest.addApprover');
    Route::delete('/approvers/{id}', [AccessRequestController::class, 'removeApprover'])->name('accessRequest.removeApprover');
});
