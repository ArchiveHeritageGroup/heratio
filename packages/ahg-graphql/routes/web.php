<?php

use Illuminate\Support\Facades\Route;

// SECURITY (#1378): the GraphQL surface bulk-reads archive + research data.
// 'auth' alone let ANY authenticated user (incl. a self-registered researcher)
// run it. Require the 'read' ACL grant (CheckAcl -> AclService::hasPermission(
// $userId, 'read')) so only granted staff reach the resolver; the resolver
// then still applies publication/visibility/owner filtering per row.
Route::prefix('admin/graphql')->middleware(['web', 'auth', 'acl:read'])->group(function () {
    Route::get('/playground', [\AhgGraphql\Controllers\GraphqlController::class, 'playground'])->name('ahggraphql.playground');
    Route::post('/execute', [\AhgGraphql\Controllers\GraphqlController::class, 'execute'])->name('ahggraphql.execute');
});
