<?php

/**
 * ahg-search package configuration.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Discovery API enrichment (issue #1095)
    |--------------------------------------------------------------------------
    | Both optional enrichments default OFF so the discovery search hot path
    | is never slowed in production. Flip them on per-deployment once the
    | backing services (Ollama / query-log volume) are validated.
    */

    'discovery' => [

        // Ollama-backed natural-language query expansion. When true,
        // POST /api/discovery/search runs the query through
        // QueryExpansionService before hitting Elasticsearch. Falls back to
        // thesaurus expansion, then to the raw query, on any failure.
        'query_expansion' => env('AHG_DISCOVERY_QUERY_EXPANSION', false),

        // History-based personalised re-ranking. When true AND a user_id is
        // present (or the caller is authenticated), the current result page is
        // reordered to favour records matching the user's recent searches.
        'history_rerank' => env('AHG_DISCOVERY_HISTORY_RERANK', false),
    ],

];
