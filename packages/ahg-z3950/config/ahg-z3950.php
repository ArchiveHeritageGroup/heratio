<?php

/**
 * Z39.50 package configuration.
 *
 * @see https://www.loc.gov/standards/sru/
 * @see https://www.loc.gov/z3950/agency/
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Z39.50 Server
    |--------------------------------------------------------------------------
    |
    | Enable the built-in Z39.50 server to expose the Heratio catalogue
    | to Z39.50 clients (e.g. NCIP, ILL systems, legacy catalogue clients).
    |
    */
    'server' => [
        'enabled' => env('Z3950_SERVER_ENABLED', false),
        'host'    => env('Z3950_SERVER_HOST', '0.0.0.0'),
        'port'    => env('Z3950_SERVER_PORT', 9999),

        // Server Gils profile support
        'gils'    => env('Z3950_SERVER_GILS', false),

        // Maximum concurrent connections
        'max_connections' => env('Z3950_SERVER_MAX_CONNECTIONS', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Z39.50 Client defaults
    |--------------------------------------------------------------------------
    |
    | Default settings for outbound Z39.50 client searches.
    |
    */
    'client' => [
        // Default connection timeout in seconds
        'timeout' => env('Z3950_CLIENT_TIMEOUT', 30),

        // Maximum records to request per search
        'max_records' => env('Z3950_CLIENT_MAX_RECORDS', 100),

        // Default record syntax for import
        'syntax' => env('Z3950_CLIENT_SYNTAX', 'USmarc'),

        // Element set name (F = full, B = brief, S = suggested)
        'element_set' => env('Z3950_CLIENT_ELEMENT_SET', 'F'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default target profiles
    |--------------------------------------------------------------------------
    |
    | Pre-configured Z39.50 targets. Operators can add more via the admin UI.
    | Format: host, port, database, syntax, element_set.
    |
    */
    'default_targets' => [
        // Library of Congress Z39.50
        // [
        //     'name'   => 'Library of Congress',
        //     'host'   => 'lx2.loc.gov',
        //     'port'   => 210,
        //     'db'     => 'LCDB',
        //     'syntax' => 'USmarc',
        //     'element_set' => 'F',
        // ],

        // OCLC Z39.50
        // [
        //     'name'   => 'WorldCat',
        //     'host'   => 'zcat.oclc.org',
        //     'port'   => 210,
        //     'db'     => 'WorldCat',
        //     'syntax' => 'USmarc',
        //     'element_set' => 'F',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attribute set: bib-1
    |--------------------------------------------------------------------------
    |
    | bib-1 attribute set is the standard Z39.50 query model.
    | See: https://www.loc.gov/standards/sru/bib-1.html
    |
    */
    'bib1' => [
        'use' => [
            'title'          => 4,
            'author'         => 1003,
            'subject'       => 21,
            'ISBN'           => 7,
            'ISSN'           => 8,
            'LCCN'           => 9,
            'local_number'   => 12,
            'name'           => 1002,
            'any'            => 1016,
        ],
        'relation' => [
            'exact'  => 1,
            'less'   => 2,
            'greater' => 3,
            'within' => 5,
        ],
        'position' => [
            'first_in_field'   => 1,
            'any_in_field'     => 2,
            'first_in_subfield' => 3,
        ],
        'truncation' => [
            'none'       => 1,
            'right'      => 2,
            'left'       => 3,
            'both'       => 4,
            'regex'      => 100,
        ],
        'completeness' => [
            'incomplete' => 1,
            'partial'    => 2,
            'complete'   => 3,
        ],
    ],
];