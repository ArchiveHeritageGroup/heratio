<?php

/**
 * RIC Linked Data API Routes
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

use Illuminate\Support\Facades\Route;
use AhgRic\Http\Controllers\LinkedDataApiController;

/*
|--------------------------------------------------------------------------
| RIC-O Linked Data API Routes
|--------------------------------------------------------------------------
|
| These routes provide Linked Data publication endpoints for RIC-O
| compliant serialization of archival entities.
|
| Endpoints:
| - /api/ric/v1/agents - List/search agents
| - /api/ric/v1/records - List/search records
| - /api/ric/v1/functions - List/search functions
| - /api/ric/v1/repositories - List/search repositories
| - /api/ric/v1/sparql - SPARQL query endpoint
| - /api/ric/v1/graph - Entity relationship graph
| - /api/ric/v1/validate - SHACL validation
| - /api/ric/v1/vocabulary - RIC-O vocabulary
|
*/

Route::prefix('api/ric/v1')->middleware(['throttle:60,1'])->group(function () {
    
    // Agents (ISAAR-CPF)
    Route::get('/agents', [LinkedDataApiController::class, 'listAgents']);
    Route::get('/agents/{slug}', [LinkedDataApiController::class, 'showAgent']);
    
    // Records (ISAD)
    Route::get('/records', [LinkedDataApiController::class, 'listRecords']);
    Route::get('/records/{slug}', [LinkedDataApiController::class, 'showRecord']);
    Route::get('/records/{slug}/export', [LinkedDataApiController::class, 'exportRecordSet']);
    
    // Functions (ISDF)
    Route::get('/functions', [LinkedDataApiController::class, 'listFunctions']);
    Route::get('/functions/{id}', [LinkedDataApiController::class, 'showFunction']);
    
    // Repositories (ISDIAH)
    Route::get('/repositories', [LinkedDataApiController::class, 'listRepositories']);
    Route::get('/repositories/{slug}', [LinkedDataApiController::class, 'showRepository']);

    // RiC-native Places
    Route::get('/places', [LinkedDataApiController::class, 'listPlaces']);
    Route::get('/places/{id}', [LinkedDataApiController::class, 'showPlace'])->where('id', '[0-9]+');

    // RiC-native Instantiations (digital/physical manifestations)
    Route::get('/instantiations', [LinkedDataApiController::class, 'listInstantiations']);
    Route::get('/instantiations/{id}', [LinkedDataApiController::class, 'showInstantiation'])->where('id', '[0-9]+');

    // RiC-native Activities (Production/Accumulation/Activity)
    Route::get('/activities', [LinkedDataApiController::class, 'listActivities']);
    Route::get('/activities/{id}', [LinkedDataApiController::class, 'showActivity'])->where('id', '[0-9]+');

    // RiC-native Rules (mandates, laws, policies)
    Route::get('/rules', [LinkedDataApiController::class, 'listRules']);
    Route::get('/rules/{id}', [LinkedDataApiController::class, 'showRule'])->where('id', '[0-9]+');

    // SPARQL & Graph
    Route::get('/sparql', [LinkedDataApiController::class, 'sparql']);
    Route::get('/graph', [LinkedDataApiController::class, 'graph']);
    
    // Validation
    Route::post('/validate', [LinkedDataApiController::class, 'validate']);
    
    // Vocabulary
    Route::get('/vocabulary', [LinkedDataApiController::class, 'vocabulary']);
    
    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'RIC-O Linked Data API',
            'version' => '1.0',
        ]);
    });
    
    // API Info endpoint
    Route::get('/', function () {
        return response()->json([
            'name' => 'RIC-O Linked Data API',
            'version' => '1.0',
            'description' => 'Linked Data publication endpoints for RIC-O compliant serialization',
        ]);
    });
    
    // OpenAPI spec placeholder
    Route::get('/openapi.json', function () {
        return response()->json([
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'RIC-O Linked Data API',
                'version' => '1.0',
            ],
            'paths' => [],
            'components' => [],
        ]);
    });
});
