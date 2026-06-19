<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Apache Jena Fuseki Triplestore
    |--------------------------------------------------------------------------
    */
    'fuseki' => [
        'url' => env('FUSEKI_URL', 'http://localhost:3030/ric'),
        'user' => env('FUSEKI_USER', ''),
        'password' => env('FUSEKI_PASSWORD', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Qdrant Vector Search
    |--------------------------------------------------------------------------
    */
    'qdrant' => [
        'url' => env('QDRANT_URL', 'http://localhost:6333'),
        'collection' => env('QDRANT_COLLECTION', 'archive_records'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch
    |--------------------------------------------------------------------------
    */
    'elasticsearch' => [
        'url' => env('ELASTICSEARCH_URL', 'http://localhost:9200'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default View Mode
    |--------------------------------------------------------------------------
    | 'heratio' = traditional archival view (default)
    | 'ric'     = RiC contextual view
    */
    'default_view' => 'heratio',

    /*
    |--------------------------------------------------------------------------
    | Canonical entity-IRI base (ontology governance pin, 2026-06-19)
    |--------------------------------------------------------------------------
    | THE single source for every RiC entity @id. IRIs are minted as
    | <base_uri>/<type>/<stable-id> and are permanent once published, so this
    | must be the stable, dereferenceable public host (= the SPARQL/REST host).
    | Supersedes the old app.url-based + archives.theahg.co.za + heratio.theahg
    | minting. The AHG extension predicate namespace (openric:) is the public
    | https://openric.org/ns/v1# and is fixed in code, not configured here.
    */
    'base_uri' => env('RIC_BASE_URI', 'https://ric.theahg.co.za/ric'),

    /*
    |--------------------------------------------------------------------------
    | External OpenRiC Service (Phase 4.3 split)
    |--------------------------------------------------------------------------
    | When api_url is null (or points at the same host as app.url), Heratio
    | calls its own in-process RiC module and forwards the admin session
    | cookie for auth. When api_url points at a different host (post-split),
    | Heratio switches to X-API-Key auth using service_key.
    */
    'api_url' => env('RIC_API_URL'),
    'service_key' => env('RIC_SERVICE_API_KEY'),
    'http_timeout' => (int) env('RIC_HTTP_TIMEOUT', 5),

];
