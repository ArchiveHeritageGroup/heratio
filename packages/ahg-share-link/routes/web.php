<?php

use AhgShareLink\Controllers\ShareLinkAdminController;
use AhgShareLink\Controllers\ShareLinkIssueController;
use AhgShareLink\Controllers\ShareLinkRecipientController;
use Illuminate\Support\Facades\Route;

// Recipient route — anonymous bearer-token access. NO auth middleware.
// Token is the credential; AccessService runs all validation guards.
Route::middleware(['web'])
    ->get('/share/{token}', [ShareLinkRecipientController::class, 'show'])
    ->where('token', '[A-Za-z0-9_\-]{32,64}')
    ->name('share-link.recipient');

// Issuance endpoint — requires an authenticated user. Phase E.
Route::middleware(['web', 'auth'])
    ->post('/share-link/issue', [ShareLinkIssueController::class, 'store'])
    ->name('share-link.issue');

// v0.2 — bookmarkable issuance form (GET) + post-issue success (GET).
// Complements the inline modal: same backend, different surface so curators
// can land directly on a record's share form from a saved link.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/share-link/new', [ShareLinkIssueController::class, 'newForm'])
        ->name('share-link.new');
    Route::get('/share-link/issued/{tokenId}', [ShareLinkIssueController::class, 'issued'])
        ->where('tokenId', '\d+')
        ->name('share-link.issued');
});

// Admin index + detail — gated by share_link.list_all ACL (admin bypass).
// Phase F.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/admin/share-links', [ShareLinkAdminController::class, 'index'])
        ->name('share-link.admin.index');
    Route::get('/admin/share-links/{id}', [ShareLinkAdminController::class, 'show'])
        ->where('id', '\d+')
        ->name('share-link.admin.show');
    Route::post('/admin/share-links/{id}/revoke', [ShareLinkAdminController::class, 'revoke'])
        ->where('id', '\d+')
        ->name('share-link.admin.revoke');
});
