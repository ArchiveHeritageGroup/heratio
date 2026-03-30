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

];
