<?php

use AhgAcl\Controllers\AclController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/acl', [AclController::class, 'groups'])->name('acl.groups');
    Route::match(['get', 'post'], '/admin/acl/group/{id}', [AclController::class, 'editGroup'])->name('acl.edit-group')->where('id', '[0-9]+');
    Route::post('/admin/acl/group/{groupId}/add-member', [AclController::class, 'addMember'])->name('acl.add-member')->where('groupId', '[0-9]+');
    Route::post('/admin/acl/group/{groupId}/remove-member/{userId}', [AclController::class, 'removeMember'])->name('acl.remove-member')->where(['groupId' => '[0-9]+', 'userId' => '[0-9]+']);
    Route::get('/admin/acl/classifications', [AclController::class, 'classifications'])->name('acl.classifications');
    Route::get('/admin/acl/clearances', [AclController::class, 'clearances'])->name('acl.clearances');
    Route::post('/admin/acl/clearances', [AclController::class, 'setClearance'])->name('acl.set-clearance');
    Route::get('/admin/acl/access-requests', [AclController::class, 'accessRequests'])->name('acl.access-requests');
    Route::post('/admin/acl/access-requests/{id}/review', [AclController::class, 'reviewRequest'])->name('acl.review-request')->where('id', '[0-9]+');
    Route::get('/admin/acl/audit-log', [AclController::class, 'auditLog'])->name('acl.audit-log');
    Route::get('/admin/acl/approvers', [AclController::class, 'approvers'])->name('acl.approvers');
    Route::post('/admin/acl/approvers/add', [AclController::class, 'addApprover'])->name('acl.add-approver');
    Route::post('/admin/acl/approvers/{id}/remove', [AclController::class, 'removeApprover'])->name('acl.remove-approver')->where('id', '[0-9]+');
});
