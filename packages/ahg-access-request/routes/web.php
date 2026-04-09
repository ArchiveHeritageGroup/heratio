<?php

use AhgAccessRequest\Controllers\AccessRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('access-request')->group(function () {
    Route::get('/browse', [AccessRequestController::class, 'browse'])->name('accessRequest.browse');
});

Route::middleware('auth')->prefix('access-request')->group(function () {
    Route::get('/new', [AccessRequestController::class, 'create'])->name('accessRequest.create');
    Route::post('/new', [AccessRequestController::class, 'store'])->name('accessRequest.store');
    Route::get('/my-requests', [AccessRequestController::class, 'myRequests'])->name('accessRequest.myRequests');
    Route::get('/request/{slug}', [AccessRequestController::class, 'requestObject'])->name('accessRequest.requestObject');
    Route::get('/{id}', [AccessRequestController::class, 'view'])->name('accessRequest.view')->where('id', '[0-9]+');
});

Route::middleware(['auth', 'admin'])->prefix('access-request')->group(function () {
    Route::get('/pending', [AccessRequestController::class, 'pending'])->name('accessRequest.pending');
    Route::post('/{id}/approve', [AccessRequestController::class, 'approve'])->name('accessRequest.approve');
    Route::post('/{id}/deny', [AccessRequestController::class, 'deny'])->name('accessRequest.deny');
    Route::get('/approvers', [AccessRequestController::class, 'approvers'])->name('accessRequest.approvers');
    Route::post('/approvers/add', [AccessRequestController::class, 'addApprover'])->name('accessRequest.addApprover');
    Route::delete('/approvers/{id}', [AccessRequestController::class, 'removeApprover'])->name('accessRequest.removeApprover');
});

Route::middleware('auth')->prefix('access-request')->group(function () {
    Route::post('/{id}/cancel', [AccessRequestController::class, 'cancel'])->name('accessRequest.cancel')->where('id', '[0-9]+');
    Route::post('/request-object/create', [AccessRequestController::class, 'storeObjectRequest'])->name('accessRequest.storeObjectRequest');
});

// ── Legacy URL redirects (ahgAccessRequestPlugin compatibility) ─────────────
Route::get('/admin/accessRequests', fn () => redirect('/security/access-requests', 301))->middleware(['auth', 'admin']);
Route::get('/accessRequest', fn () => redirect()->route('accessRequest.pending', [], 301));
Route::get('/security/request', fn () => redirect()->route('accessRequest.pending', [], 301));
Route::get('/security/request-access', fn () => redirect()->route('accessRequest.create', [], 301));
Route::post('/security/request-access/create', [AccessRequestController::class, 'store'])->middleware('auth')->name('accessRequest.legacyStore');
Route::get('/security/request-object', fn () => redirect()->route('accessRequest.create', [], 301));
Route::post('/security/request-object/create', [AccessRequestController::class, 'storeObjectRequest'])->middleware('auth')->name('accessRequest.legacyObjectStore');
Route::post('/security/request/{id}/cancel', [AccessRequestController::class, 'cancel'])->middleware('auth')->name('accessRequest.legacyCancel')->where('id', '[0-9]+');
Route::get('/security/request/{id}', [AccessRequestController::class, 'view'])->middleware('auth')->name('accessRequest.legacyView')->where('id', '[0-9]+');
Route::post('/security/request/{id}/approve', [AccessRequestController::class, 'approve'])->middleware('admin')->name('accessRequest.legacyApprove')->where('id', '[0-9]+');
Route::post('/security/request/{id}/deny', [AccessRequestController::class, 'deny'])->middleware('admin')->name('accessRequest.legacyDeny')->where('id', '[0-9]+');
Route::get('/security/request/{id}/review', [AccessRequestController::class, 'view'])->middleware('admin')->name('accessRequest.legacyReview')->where('id', '[0-9]+');
Route::get('/security/approvers', [AccessRequestController::class, 'approvers'])->middleware('admin')->name('accessRequest.legacyApprovers');
Route::post('/security/approvers/add', [AccessRequestController::class, 'addApprover'])->middleware('admin')->name('accessRequest.legacyAddApprover');
Route::post('/security/approvers/{id}/remove', [AccessRequestController::class, 'removeApprover'])->middleware('admin')->name('accessRequest.legacyRemoveApprover')->where('id', '[0-9]+');
Route::get('/security/requests', fn () => redirect()->route('accessRequest.pending', [], 301));

