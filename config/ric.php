<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Apache Jena Fuseki Triplestore
    |--------------------------------------------------------------------------
    */
    'fuseki' => [
        'url'      => env('FUSEKI_URL', 'http://localhost:3030/ric'),
        'user'     => env('FUSEKI_USER', ''),
        'password' => env('FUSEKI_PASSWORD', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Qdrant Vector Search
    |--------------------------------------------------------------------------
    */
    'qdrant' => [
        'url'        => env('QDRANT_URL', 'http://localhost:6333'),
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
    | External OpenRiC Service (Phase 4.3 split)
    |--------------------------------------------------------------------------
    | When api_url is null (or points at the same host as app.url), Heratio
    | calls its own in-process RiC module and forwards the admin session
    | cookie for auth. When api_url points at a different host (post-split),
    | Heratio switches to X-API-Key auth using service_key.
    */
    'api_url'      => env('RIC_API_URL'),
    'service_key'  => env('RIC_SERVICE_API_KEY'),
    'http_timeout' => (int) env('RIC_HTTP_TIMEOUT', 5),

];
