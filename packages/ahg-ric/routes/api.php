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
use AhgRic\Http\Controllers\OaiPmhController;
use AhgRic\Http\Controllers\KeyRequestController;
use AhgRic\Http\Controllers\ImportController;

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

Route::prefix('api/ric/v1')->middleware(['throttle:60,1', 'api.cors'])->group(function () {
    
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

    // Thumbnails — derivative of /uploads/. Generates + caches on first call.
    // Public (no auth) since the /uploads/ files themselves are public.
    Route::get('/thumbnail/{id}', [LinkedDataApiController::class, 'thumbnail'])
        ->where('id', '[0-9]+');

    // OAI-PMH v2.0 — standard archival harvest protocol.
    // Accepts both GET and POST per the OAI-PMH spec.
    Route::match(['get', 'post'], '/oai', [OaiPmhController::class, 'handle']);

    // Self-service API key request flow. Reads are already public, so this
    // is only useful for acquiring write/delete scope. GET shows an HTML
    // form; POST queues a pending request for admin review.
    Route::get ('/keys/request',        [KeyRequestController::class, 'form'])->name('openric.keys.form');
    Route::post('/keys/request',        [KeyRequestController::class, 'submit']);
    Route::get ('/keys/request/{id}',   [KeyRequestController::class, 'status'])->where('id', '[0-9]+');
    
    // Validation
    Route::post('/validate', [LinkedDataApiController::class, 'validate']);
    
    // Vocabulary
    Route::get('/vocabulary', [LinkedDataApiController::class, 'vocabulary']);
    Route::get('/vocabulary/{taxonomy}', [LinkedDataApiController::class, 'vocabularyByTaxonomy']);

    // ------------------------------------------------------------
    // API-1 read gaps (docs/ric-api-read-gaps.md)
    // ------------------------------------------------------------

    // Relations (API-R-1, R-2)
    Route::get('/relations', [LinkedDataApiController::class, 'listRelations']);
    Route::get('/relations-for/{id}', [LinkedDataApiController::class, 'relationsFor'])->where('id', '[0-9]+');

    // Hierarchy walk (API-R-3)
    Route::get('/hierarchy/{id}', [LinkedDataApiController::class, 'hierarchy'])->where('id', '[0-9]+');

    // Cross-entity autocomplete (API-R-4)
    Route::get('/autocomplete', [LinkedDataApiController::class, 'autocomplete']);

    // Aggregated linked-RiC for a record (API-R-6)
    Route::get('/records/{id}/entities', [LinkedDataApiController::class, 'entitiesForRecord'])->where('id', '[0-9]+');

    // Entity info card (API-R-7)
    Route::get('/entities/{id}/info', [LinkedDataApiController::class, 'entityInfo'])->where('id', '[0-9]+');

    // Audit-log revisions for one entity.
    Route::get('/{type}/{id}/revisions', [LinkedDataApiController::class, 'entityRevisions'])
        ->where('type', 'places|rules|activities|instantiations|agents|records|repositories|functions|relations')
        ->where('id', '[0-9]+');

    // Relation types with domain/range filter (API-R-8)
    Route::get('/relation-types', [LinkedDataApiController::class, 'relationTypes']);

    // Flat places picker (API-R-9)
    Route::get('/places/flat', [LinkedDataApiController::class, 'placesFlat']);

    // ------------------------------------------------------------
    // API-2 write surface — gated by api.auth:write
    // ------------------------------------------------------------
    Route::middleware(['api.auth:write'])->group(function () {
        // Bulk import (CSV or JSON). See AhgRic\Http\Controllers\ImportController
        // for query params; supports ?type=places|agents|records|... and &dry_run=1.
        Route::post('/import', [ImportController::class, 'import']);

        // File / content upload (images, PDFs, audio, any binary)
        // Returns {id, url, mime, size, filename}; the url is publicly reachable
        // without the API key (for embedding in UIs, IIIF manifests, etc.).
        Route::post('/upload', [LinkedDataApiController::class, 'uploadContent']);

        Route::post('/relations', [LinkedDataApiController::class, 'createRelation']);
        Route::match(['patch', 'put'], '/relations/{id}', [LinkedDataApiController::class, 'updateRelation'])->where('id', '[0-9]+');
        Route::delete('/relations/{id}', [LinkedDataApiController::class, 'deleteRelation'])->where('id', '[0-9]+');

        // Agents (rico:Agent / rico:Person / rico:CorporateBody / rico:Family)
        Route::post('/agents', [LinkedDataApiController::class, 'createAgent']);
        Route::match(['patch', 'put'], '/agents/{id}', [LinkedDataApiController::class, 'updateAgent'])->where('id', '[0-9]+');
        Route::delete('/agents/{id}', [LinkedDataApiController::class, 'deleteAgent'])->where('id', '[0-9]+');

        // Records (rico:Record / rico:RecordSet — information_object)
        Route::post('/records', [LinkedDataApiController::class, 'createRecord']);
        Route::match(['patch', 'put'], '/records/{id}', [LinkedDataApiController::class, 'updateRecord'])->where('id', '[0-9]+');
        Route::delete('/records/{id}', [LinkedDataApiController::class, 'deleteRecord'])->where('id', '[0-9]+');

        // Repositories (ISDIAH — rico:CorporateBody with repository extension)
        Route::post('/repositories', [LinkedDataApiController::class, 'createRepository']);
        Route::match(['patch', 'put'], '/repositories/{id}', [LinkedDataApiController::class, 'updateRepository'])->where('id', '[0-9]+');
        Route::delete('/repositories/{id}', [LinkedDataApiController::class, 'deleteRepository'])->where('id', '[0-9]+');

        // Functions (ISDF — rico:Function)
        Route::post('/functions', [LinkedDataApiController::class, 'createFunction']);
        Route::match(['patch', 'put'], '/functions/{id}', [LinkedDataApiController::class, 'updateFunction'])->where('id', '[0-9]+');
        Route::delete('/functions/{id}', [LinkedDataApiController::class, 'deleteFunctionEntity'])->where('id', '[0-9]+');

        // Generic delete-by-id (looks up class_name and dispatches) — useful
        // for UIs that hold only an id, not a type.
        Route::delete('/entities/{id}', [LinkedDataApiController::class, 'deleteEntityById'])->where('id', '[0-9]+');

        Route::post('/{type}', [LinkedDataApiController::class, 'createEntity'])->where('type', 'places|rules|activities|instantiations');
        Route::match(['patch', 'put'], '/{type}/{id}', [LinkedDataApiController::class, 'updateEntity'])
            ->where('type', 'places|rules|activities|instantiations')->where('id', '[0-9]+');
        Route::delete('/{type}/{id}', [LinkedDataApiController::class, 'deleteEntity'])
            ->where('type', 'places|rules|activities|instantiations')->where('id', '[0-9]+');
    });
    
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
            'docs' => url('/api/ric/v1/docs'),
            'openapi' => url('/api/ric/v1/openapi.json'),
        ]);
    });

    // OpenAPI 3.0 spec — single source of truth is AhgRic\Support\OpenApiSpec.
    Route::get('/openapi.json', function (\Illuminate\Http\Request $r) {
        $spec = \AhgRic\Support\OpenApiSpec::build(url('/api/ric/v1'));
        return response()->json($spec, 200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ]);
    });

    // Swagger UI explorer — loads the spec above and lets the developer
    // "Try it out" on any endpoint with their own X-API-Key. Inlined (not
    // Blade) so it works on minimal deployments without a writable view
    // cache. Single source of truth for the HTML lives in AhgRic\Support\SwaggerUiHtml.
    Route::get('/docs', function () {
        $html = \AhgRic\Support\SwaggerUiHtml::render(
            specUrl: url('/api/ric/v1/openapi.json'),
            baseUrl: url('/api/ric/v1')
        );
        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    });
});
