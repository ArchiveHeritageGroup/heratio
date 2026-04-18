<?php

/**
 * RIC-O Configuration
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

return [

    /*
    |--------------------------------------------------------------------------
    | Fuseki Triplestore Endpoint
    |--------------------------------------------------------------------------
    |
    | The URL of the Apache Jena Fuseki server endpoint.
    |
    */
    'fuseki_endpoint' => env('FUSEKI_ENDPOINT', 'http://localhost:3030/heratio'),

    /*
    |--------------------------------------------------------------------------
    | RIC-O Instance ID
    |--------------------------------------------------------------------------
    |
    | A unique identifier for this RIC-O instance.
    |
    */
    'instance_id' => env('RICO_INSTANCE_ID', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Base URI
    |--------------------------------------------------------------------------
    |
    | The base URI for generating entity URIs.
    |
    */
    'base_uri' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | SHACL Shapes Path
    |--------------------------------------------------------------------------
    |
    | Path to the SHACL shapes file for validation.
    |
    */
    'shacl_shapes_path' => __DIR__ . '/../tools/ric_shacl_shapes.ttl',

    /*
    |--------------------------------------------------------------------------
    | SPARQL Cache Minutes
    |--------------------------------------------------------------------------
    |
    | Number of minutes to cache SPARQL query results.
    |
    */
    'sparql_cache_minutes' => env('SPARQL_CACHE_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting
    |--------------------------------------------------------------------------
    |
    | API rate limit (requests per minute).
    |
    */
    'api_rate_limit' => env('RIC_API_RATE_LIMIT', 60),

    /*
    |--------------------------------------------------------------------------
    | Supported Standards
    |--------------------------------------------------------------------------
    |
    | List of ICA standards to include in serialization.
    |
    */
    'standards' => [
        'isaar' => true,   // ISAAR-CPF for Agents
        'isdf' => true,    // ISDF for Functions
        'isad' => true,    // ISAD for Records
        'isdiah' => true,  // ISDIAH for Repositories
        'iscap' => true,   // ISCAP for Security
    ],

    /*
    |--------------------------------------------------------------------------
    | Namespace Prefixes
    |--------------------------------------------------------------------------
    |
    | RDF namespace prefixes for serialization.
    |
    */
    'namespaces' => [
        'rico' => 'https://www.ica.org/standards/RiC/ontology#',
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
        'xsd' => 'http://www.w3.org/2001/XMLSchema#',
        'skos' => 'http://www.w3.org/2004/02/skos/core#',
        'owl' => 'http://www.w3.org/2002/07/owl#',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fuseki Triplestore (split form for sync runner)
    |--------------------------------------------------------------------------
    |
    | The sync shell runner and controller preflight need the base URL and
    | dataset separately. `fuseki_endpoint` above is the combined URL kept
    | for backward compatibility with the SPARQL services.
    |
    */
    'fuseki' => [
        'url'      => env('RIC_FUSEKI_URL'),
        'dataset'  => env('RIC_FUSEKI_DATASET', 'ric'),
        'user'     => env('RIC_FUSEKI_USER'),
        'pass'     => env('RIC_FUSEKI_PASS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Source Database for RiC Extraction
    |--------------------------------------------------------------------------
    |
    | RiC-O triples are generated from a source relational database. For a
    | Heratio-only install, this is the primary Heratio DB. For hybrid
    | installs (legacy AtoM + Heratio), it may point at the legacy DB.
    | Falls back to the main DB connection when not explicitly set.
    |
    */
    'source_db' => [
        'host'     => env('RIC_SOURCE_DB_HOST'),
        'user'     => env('RIC_SOURCE_DB_USER'),
        'password' => env('RIC_SOURCE_DB_PASSWORD'),
        'name'     => env('RIC_SOURCE_DB_NAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Script Path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the RiC sync shell runner. When null, the controller
    | falls back to packages/ahg-ric/bin/ric_sync.sh relative to the app.
    |
    */
    'sync_script' => env('RIC_SYNC_SCRIPT'),

];
