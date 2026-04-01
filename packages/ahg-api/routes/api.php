<?php

use AhgApi\Controllers\LegacyApiController;
use AhgApi\Controllers\V1\AccessionApiController;
use AhgApi\Controllers\V1\ActorApiController;
use AhgApi\Controllers\V1\DigitalObjectApiController;
use AhgApi\Controllers\V1\DonorApiController;
use AhgApi\Controllers\V1\FunctionApiController;
use AhgApi\Controllers\V1\InformationObjectApiController;
use AhgApi\Controllers\V1\PhysicalObjectApiController;
use AhgApi\Controllers\V1\RepositoryApiController;
use AhgApi\Controllers\V1\TaxonomyApiController;
use AhgApi\Controllers\V2\ApiKeyController;
use AhgApi\Controllers\V2\ApiRootController;
use AhgApi\Controllers\V2\AssetController;
use AhgApi\Controllers\V2\AuditController;
use AhgApi\Controllers\V2\AuthorityController;
use AhgApi\Controllers\V2\BatchController;
use AhgApi\Controllers\V2\ConditionController;
use AhgApi\Controllers\V2\DescriptionController;
use AhgApi\Controllers\V2\EventController;
use AhgApi\Controllers\V2\PrivacyController;
use AhgApi\Controllers\V2\PublishController;
use AhgApi\Controllers\V2\RepositoryController as V2RepositoryController;
use AhgApi\Controllers\V2\SearchController;
use AhgApi\Controllers\V2\SyncController;
use AhgApi\Controllers\V2\TaxonomyController as V2TaxonomyController;
use AhgApi\Controllers\V2\UploadController;
use AhgApi\Controllers\V2\IdentifierController;
use AhgApi\Controllers\V2\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes (read-only + CRUD)
|--------------------------------------------------------------------------
*/

Route::prefix('api/v1')->middleware(['throttle:60,1', 'api.cors'])->group(function () {

    // Information Objects — READ
    Route::get('informationobjects/search', [InformationObjectApiController::class, 'search']);
    Route::get('informationobjects/tree/{slug}', [InformationObjectApiController::class, 'tree']);
    Route::get('informationobjects/{slug}/digitalobject', [InformationObjectApiController::class, 'digitalObject']);
    Route::get('informationobjects', [InformationObjectApiController::class, 'index']);
    Route::get('informationobjects/{slug}', [InformationObjectApiController::class, 'show']);

    // Information Objects — CRUD (authenticated)
    Route::middleware('api.auth:write')->group(function () {
        Route::post('informationobjects', [InformationObjectApiController::class, 'store']);
        Route::put('informationobjects/{slug}', [InformationObjectApiController::class, 'update']);
    });
    Route::delete('informationobjects/{slug}', [InformationObjectApiController::class, 'destroy'])
        ->middleware('api.auth:delete');

    // Actors
    Route::get('actors', [ActorApiController::class, 'index']);
    Route::get('actors/{slug}', [ActorApiController::class, 'show']);

    // Repositories
    Route::get('repositories', [RepositoryApiController::class, 'index']);
    Route::get('repositories/{slug}', [RepositoryApiController::class, 'show']);

    // Accessions
    Route::get('accessions', [AccessionApiController::class, 'index']);
    Route::get('accessions/{slug}', [AccessionApiController::class, 'show']);

    // Donors
    Route::get('donors', [DonorApiController::class, 'index']);
    Route::get('donors/{slug}', [DonorApiController::class, 'show']);

    // Functions
    Route::get('functions', [FunctionApiController::class, 'index']);
    Route::get('functions/{slug}', [FunctionApiController::class, 'show']);

    // Physical Objects
    Route::get('physicalobjects', [PhysicalObjectApiController::class, 'index']);
    Route::get('physicalobjects/{slug}', [PhysicalObjectApiController::class, 'show']);
    Route::post('physicalobjects', [PhysicalObjectApiController::class, 'store'])
        ->middleware('api.auth:write');

    // Digital Objects
    Route::get('digitalobjects', [DigitalObjectApiController::class, 'index']);
    Route::post('digitalobjects', [DigitalObjectApiController::class, 'store'])
        ->middleware('api.auth:write');

    // Taxonomies
    Route::get('taxonomies', [TaxonomyApiController::class, 'index']);
    Route::get('taxonomies/{id}/terms', [TaxonomyApiController::class, 'terms'])->where('id', '[0-9]+');
});

/*
|--------------------------------------------------------------------------
| API v2 Routes (full REST)
|--------------------------------------------------------------------------
*/

Route::prefix('api/v2')->middleware(['api.cors', 'api.auth:read', 'api.ratelimit', 'api.log'])->group(function () {

    // Root — endpoint listing
    Route::get('/', [ApiRootController::class, 'index'])->withoutMiddleware('api.auth:read');

    // Descriptions — full CRUD
    Route::get('descriptions', [DescriptionController::class, 'index']);
    Route::get('descriptions/{slug}', [DescriptionController::class, 'show']);
    Route::post('descriptions', [DescriptionController::class, 'store'])->middleware('api.auth:write');
    Route::match(['put', 'patch'], 'descriptions/{slug}', [DescriptionController::class, 'update'])->middleware('api.auth:write');
    Route::delete('descriptions/{slug}', [DescriptionController::class, 'destroy'])->middleware('api.auth:delete');

    // Authorities
    Route::get('authorities', [AuthorityController::class, 'index']);
    Route::get('authorities/{slug}', [AuthorityController::class, 'show']);

    // Repositories
    Route::get('repositories', [V2RepositoryController::class, 'index']);

    // Taxonomies
    Route::get('taxonomies', [V2TaxonomyController::class, 'index']);
    Route::get('taxonomies/{id}/terms', [V2TaxonomyController::class, 'terms'])->where('id', '[0-9]+');

    // Search
    Route::match(['get', 'post'], 'search', [SearchController::class, 'search']);

    // Batch operations
    Route::post('batch', [BatchController::class, 'process'])->middleware('api.auth:write');

    // API Keys management
    Route::get('keys', [ApiKeyController::class, 'index']);
    Route::post('keys', [ApiKeyController::class, 'store']);
    Route::delete('keys/{id}', [ApiKeyController::class, 'destroy'])->where('id', '[0-9]+');

    // Webhooks
    Route::get('webhooks', [WebhookController::class, 'index']);
    Route::post('webhooks', [WebhookController::class, 'store'])->middleware('api.auth:write');
    Route::get('webhooks/{id}', [WebhookController::class, 'show'])->where('id', '[0-9]+');
    Route::match(['put', 'patch'], 'webhooks/{id}', [WebhookController::class, 'update'])->where('id', '[0-9]+')->middleware('api.auth:write');
    Route::delete('webhooks/{id}', [WebhookController::class, 'destroy'])->where('id', '[0-9]+')->middleware('api.auth:delete');
    Route::get('webhooks/{id}/deliveries', [WebhookController::class, 'deliveries'])->where('id', '[0-9]+');
    Route::post('webhooks/{id}/regenerate-secret', [WebhookController::class, 'regenerateSecret'])->where('id', '[0-9]+')->middleware('api.auth:write');

    // Events (webhook delivery audit trail)
    Route::get('events', [EventController::class, 'index']);
    Route::get('events/{id}', [EventController::class, 'show'])->where('id', '[0-9]+');
    Route::get('events/correlation/{id}', [EventController::class, 'correlation'])->where('id', '[0-9]+');

    // Audit (API request log)
    Route::get('audit', [AuditController::class, 'index']);
    Route::get('audit/{id}', [AuditController::class, 'show'])->where('id', '[0-9]+');

    // Publishing
    Route::get('publish/readiness/{slug}', [PublishController::class, 'readiness']);
    Route::post('publish/execute/{slug}', [PublishController::class, 'execute'])->middleware('api.auth:write');

    // File Uploads
    Route::post('upload', [UploadController::class, 'upload'])->middleware('api.auth:write');
    Route::post('descriptions/{slug}/upload', [UploadController::class, 'uploadForDescription'])->middleware('api.auth:write');

    // Conditions
    Route::get('conditions', [ConditionController::class, 'index']);
    Route::post('conditions', [ConditionController::class, 'store'])->middleware('api.auth:write');
    Route::get('conditions/{id}', [ConditionController::class, 'show'])->where('id', '[0-9]+');
    Route::match(['put', 'patch'], 'conditions/{id}', [ConditionController::class, 'update'])->where('id', '[0-9]+')->middleware('api.auth:write');
    Route::delete('conditions/{id}', [ConditionController::class, 'destroy'])->where('id', '[0-9]+')->middleware('api.auth:delete');
    Route::get('descriptions/{slug}/conditions', [ConditionController::class, 'forDescription']);
    Route::get('conditions/{id}/photos', [ConditionController::class, 'photos'])->where('id', '[0-9]+');
    Route::post('conditions/{id}/photos', [ConditionController::class, 'uploadPhoto'])->where('id', '[0-9]+')->middleware('api.auth:write');
    Route::delete('conditions/{id}/photos/{photoId}', [ConditionController::class, 'deletePhoto'])->where(['id' => '[0-9]+', 'photoId' => '[0-9]+'])->middleware('api.auth:delete');

    // Heritage Assets & Valuations
    Route::get('assets', [AssetController::class, 'index']);
    Route::post('assets', [AssetController::class, 'store'])->middleware('api.auth:write');
    Route::get('assets/{id}', [AssetController::class, 'show'])->where('id', '[0-9]+');
    Route::match(['put', 'patch'], 'assets/{id}', [AssetController::class, 'update'])->where('id', '[0-9]+')->middleware('api.auth:write');
    Route::get('descriptions/{slug}/asset', [AssetController::class, 'forDescription']);
    Route::get('valuations', [AssetController::class, 'valuations']);
    Route::post('valuations', [AssetController::class, 'storeValuation'])->middleware('api.auth:write');
    Route::get('assets/{id}/valuations', [AssetController::class, 'assetValuations'])->where('id', '[0-9]+');

    // Privacy / DSAR / Breaches
    Route::get('privacy/dsars', [PrivacyController::class, 'dsarIndex']);
    Route::post('privacy/dsars', [PrivacyController::class, 'dsarStore'])->middleware('api.auth:write');
    Route::get('privacy/dsars/{id}', [PrivacyController::class, 'dsarShow'])->where('id', '[0-9]+');
    Route::match(['put', 'patch'], 'privacy/dsars/{id}', [PrivacyController::class, 'dsarUpdate'])->where('id', '[0-9]+')->middleware('api.auth:write');
    Route::get('privacy/breaches', [PrivacyController::class, 'breachIndex']);
    Route::post('privacy/breaches', [PrivacyController::class, 'breachStore'])->middleware('api.auth:write');

    // Mobile Sync
    Route::get('sync/changes', [SyncController::class, 'changes']);
    Route::post('sync/batch', [SyncController::class, 'batch'])->middleware('api.auth:write');

    // Identifier API (ISBN/ISSN lookup, validation, barcode generation)
    Route::get('identifiers/lookup', [IdentifierController::class, 'lookup']);
    Route::get('identifiers/validate', [IdentifierController::class, 'validate']);
    Route::get('identifiers/detect', [IdentifierController::class, 'detect']);
    Route::get('identifiers/barcode/{objectId}', [IdentifierController::class, 'barcode'])->where('objectId', '[0-9]+');
    Route::get('identifiers/types/{objectId}', [IdentifierController::class, 'types'])->where('objectId', '[0-9]+');
    Route::get('identifiers/all/{objectId}', [IdentifierController::class, 'all'])->where('objectId', '[0-9]+');
});

/*
|--------------------------------------------------------------------------
| Legacy / Additional API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api')->middleware(['throttle:60,1', 'api.cors'])->group(function () {
    Route::match(['get', 'post'], 'search/io', [LegacyApiController::class, 'searchIo']);
    Route::match(['get', 'post'], 'autocomplete/glam', [LegacyApiController::class, 'autocompleteGlam']);
    Route::get('export-preview', [LegacyApiController::class, 'exportPreview']);
    Route::get('reports/pending-counts', [LegacyApiController::class, 'pendingCounts']);
});

/*
|--------------------------------------------------------------------------
| API 404 Fallback — catch unmatched /api/* requests (excluding /api/ric/*)
|--------------------------------------------------------------------------
*/

Route::any('api/{any}', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'success' => false,
        'error' => 'Not Found',
        'message' => 'API endpoint not found: /' . $request->path(),
        'timestamp' => now()->toIso8601String(),
    ], 404);
})->where('any', '^(?!ric(/|$)).*')->middleware('api.cors');
