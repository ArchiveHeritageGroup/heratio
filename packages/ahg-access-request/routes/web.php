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

Route::middleware(['web'])->group(function () {

// Auto-registered stub routes
Route::match(['get','post'], '/homepage', function() { return view('accessrequest::homepage'); })->name('homepage');
});

// Access Request routes
Route::middleware(['web'])->prefix('access-request')->group(function () {
    Route::get('/my-requests', fn() => view('access-request::my-requests'))->name('accessRequest.myRequests');
    Route::get('/create', fn() => view('access-request::create'))->name('accessRequest.create');
    Route::post('/store', fn() => redirect()->back()->with('success', 'Request submitted'))->name('accessRequest.store');
    Route::get('/{id}', fn($id) => view('access-request::view', ['id' => $id]))->name('accessRequest.view');
    Route::get('/{id}/object', fn($id) => view('access-request::view', ['id' => $id]))->name('accessRequest.requestObject');
    Route::post('/{id}/approve', fn($id) => redirect()->back()->with('success', 'Approved'))->name('accessRequest.approve');
    Route::post('/{id}/deny', fn($id) => redirect()->back()->with('success', 'Denied'))->name('accessRequest.deny');
    Route::get('/pending', fn() => view('access-request::pending'))->name('accessRequest.pending');
    Route::get('/approvers', fn() => view('access-request::approvers'))->name('accessRequest.approvers');
    Route::post('/approvers/add', fn() => redirect()->back())->name('accessRequest.addApprover');
    Route::post('/approvers/{id}/remove', fn($id) => redirect()->back())->name('accessRequest.removeApprover');
});
