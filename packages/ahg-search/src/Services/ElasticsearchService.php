<?php

namespace AhgSearch\Services;

use Illuminate\Support\Facades\Http;

class ElasticsearchService
{
    protected string $host;
    protected string $indexPrefix;

    public function __construct()
    {
        $this->host = config('services.elasticsearch.host', 'http://localhost:9200');
        $this->indexPrefix = config('services.elasticsearch.prefix', 'archive_');
    }

    /**
     * Run a raw search against a specific index.
     */
    public function search(string $index, array $body, int $from = 0, int $size = 30): array
    {
        $url = "{$this->host}/{$this->indexPrefix}{$index}/_search";
        $response = Http::post($url, array_merge($body, ['from' => $from, 'size' => $size]));

        return $response->json();
    }

    /**
     * Search across all main indices (IO, actor, repository, term).
     */
    public function globalSearch(string $query, string $culture = 'en', int $from = 0, int $size = 30): array
    {
        $indices = implode(',', [
            "{$this->indexPrefix}qubitinformationobject",
            "{$this->indexPrefix}qubitactor",
            "{$this->indexPrefix}qubitrepository",
            "{$this->indexPrefix}qubitterm",
        ]);

        $url = "{$this->host}/{$indices}/_search";

        $body = [
            'from' => $from,
            'size' => $size,
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'query_string' => [
                                'query' => $query,
                                'fields' => [
                                    "i18n.{$culture}.title^3",
                                    "i18n.{$culture}.authorizedFormOfName^3",
                                    "i18n.{$culture}.scopeAndContent",
                                    "i18n.{$culture}.history",
                                    "identifier^2",
                                    "referenceCode^2",
                                ],
                                'default_operator' => 'AND',
                            ],
                        ],
                    ],
                ],
            ],
            'highlight' => [
                'fields' => [
                    "i18n.{$culture}.title" => (object) [],
                    "i18n.{$culture}.authorizedFormOfName" => (object) [],
                    "i18n.{$culture}.scopeAndContent" => [
                        'fragment_size' => 200,
                        'number_of_fragments' => 1,
                    ],
                ],
                'pre_tags' => ['<mark>'],
                'post_tags' => ['</mark>'],
            ],
        ];

        $response = Http::post($url, $body);

        return $response->json();
    }

    /**
     * Prefix-based autocomplete across all main indices.
     */
    public function autocomplete(string $query, string $culture = 'en', int $size = 10): array
    {
        $indices = implode(',', [
            "{$this->indexPrefix}qubitinformationobject",
            "{$this->indexPrefix}qubitactor",
            "{$this->indexPrefix}qubitrepository",
            "{$this->indexPrefix}qubitterm",
        ]);

        $url = "{$this->host}/{$indices}/_search";

        $body = [
            'size' => $size,
            '_source' => ['slug', "i18n.{$culture}", 'identifier'],
            'query' => [
                'bool' => [
                    'should' => [
                        [
                            'match_phrase_prefix' => [
                                "i18n.{$culture}.title" => [
                                    'query' => $query,
                                    'boost' => 3,
                                ],
                            ],
                        ],
                        [
                            'match_phrase_prefix' => [
                                "i18n.{$culture}.authorizedFormOfName" => [
                                    'query' => $query,
                                    'boost' => 3,
                                ],
                            ],
                        ],
                        [
                            'match_phrase_prefix' => [
                                'identifier' => [
                                    'query' => $query,
                                    'boost' => 2,
                                ],
                            ],
                        ],
                    ],
                    'minimum_should_match' => 1,
                ],
            ],
        ];

        $response = Http::post($url, $body);

        return $response->json();
    }
}
