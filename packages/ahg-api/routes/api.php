<?php

use AhgApi\Controllers\V1\AccessionApiController;
use AhgApi\Controllers\V1\ActorApiController;
use AhgApi\Controllers\V1\DigitalObjectApiController;
use AhgApi\Controllers\V1\DonorApiController;
use AhgApi\Controllers\V1\FunctionApiController;
use AhgApi\Controllers\V1\InformationObjectApiController;
use AhgApi\Controllers\V1\PhysicalObjectApiController;
use AhgApi\Controllers\V1\RepositoryApiController;
use AhgApi\Controllers\V1\TaxonomyApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware('throttle:60,1')->group(function () {

    // Information Objects
    Route::get('informationobjects/search', [InformationObjectApiController::class, 'search']);
    Route::get('informationobjects', [InformationObjectApiController::class, 'index']);
    Route::get('informationobjects/{slug}', [InformationObjectApiController::class, 'show']);

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

    // Digital Objects
    Route::get('digitalobjects', [DigitalObjectApiController::class, 'index']);

    // Taxonomies
    Route::get('taxonomies', [TaxonomyApiController::class, 'index']);
    Route::get('taxonomies/{id}/terms', [TaxonomyApiController::class, 'terms'])->where('id', '[0-9]+');
});
