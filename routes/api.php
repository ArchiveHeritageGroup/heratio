<?php

use App\Http\Controllers\Api\V1\AccessionApiController;
use App\Http\Controllers\Api\V1\ActorApiController;
use App\Http\Controllers\Api\V1\DigitalObjectApiController;
use App\Http\Controllers\Api\V1\DonorApiController;
use App\Http\Controllers\Api\V1\FunctionApiController;
use App\Http\Controllers\Api\V1\InformationObjectApiController;
use App\Http\Controllers\Api\V1\PhysicalObjectApiController;
use App\Http\Controllers\Api\V1\RepositoryApiController;
use App\Http\Controllers\Api\V1\TaxonomyApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| REST API for external consumers to access archival data.
| All endpoints are public (no auth required) with rate limiting.
|
| Base URL: /api/v1
|
*/

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {

    // Information Objects (Archival Descriptions)
    Route::get('informationobjects/search', [InformationObjectApiController::class, 'search']);
    Route::get('informationobjects', [InformationObjectApiController::class, 'index']);
    Route::get('informationobjects/{slug}', [InformationObjectApiController::class, 'show']);

    // Actors (Authority Records)
    Route::get('actors', [ActorApiController::class, 'index']);
    Route::get('actors/{slug}', [ActorApiController::class, 'show']);

    // Repositories (Archival Institutions)
    Route::get('repositories', [RepositoryApiController::class, 'index']);
    Route::get('repositories/{slug}', [RepositoryApiController::class, 'show']);

    // Accessions
    Route::get('accessions', [AccessionApiController::class, 'index']);
    Route::get('accessions/{slug}', [AccessionApiController::class, 'show']);

    // Donors
    Route::get('donors', [DonorApiController::class, 'index']);
    Route::get('donors/{slug}', [DonorApiController::class, 'show']);

    // Functions (ISDF)
    Route::get('functions', [FunctionApiController::class, 'index']);
    Route::get('functions/{slug}', [FunctionApiController::class, 'show']);

    // Physical Objects (Storage)
    Route::get('physicalobjects', [PhysicalObjectApiController::class, 'index']);
    Route::get('physicalobjects/{slug}', [PhysicalObjectApiController::class, 'show']);

    // Digital Objects
    Route::get('digitalobjects', [DigitalObjectApiController::class, 'index']);

    // Taxonomies & Terms
    Route::get('taxonomies', [TaxonomyApiController::class, 'index']);
    Route::get('taxonomies/{id}/terms', [TaxonomyApiController::class, 'terms'])->where('id', '[0-9]+');
});
