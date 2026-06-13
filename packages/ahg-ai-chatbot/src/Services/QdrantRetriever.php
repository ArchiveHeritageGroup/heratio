<?php

/**
 * QdrantRetriever
 *
 * RAG retrieval — searches the Qdrant vector store for information objects
 * relevant to a user query, falls back to Elasticsearch keyword search.
 *
 * Copyright (C) 2026 Johan Pieterse
 * AGPL-3.0
 */

namespace AhgAiChatbot\Services;

use AhgAiServices\Support\AiServicesSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QdrantRetriever
{
    private string $url;
    private string $collection;
    private int $topK;

    public function __construct()
    {
        // Default: KM host as a proxy to Qdrant. Operator can override.
        $this->url       = rtrim(env('QDRANT_URL', 'http://localhost:6333'), '/');
        $this->collection = $this->resolveCollection();
        $this->topK      = (int) config('ahg-ai-chatbot.max_context_records', 5);
    }

    /**
     * Resolve the Qdrant collection to query (#1245).
     *
     * The retriever MUST query the collection the indexer actually populated,
     * otherwise vector search silently returns nothing against a missing
     * collection. Resolution order:
     *   1. AiServicesSettings::qdrantCollection() (ahg_ner_settings.qdrant_collection)
     *   2. The indexer's own setting (ahg_settings.semantic_qdrant_collection),
     *      read the same way QdrantIndexCommand does.
     *   3. Final default 'anc_records' (the live, populated collection).
     */
    private function resolveCollection(): string
    {
        $collection = AiServicesSettings::qdrantCollection();
        if (!empty($collection)) {
            return $collection;
        }

        try {
            $indexed = DB::table('ahg_settings')
                ->where('setting_key', 'semantic_qdrant_collection')
                ->value('setting_value');
            if ($indexed !== null && $indexed !== '') {
                return (string) $indexed;
            }
        } catch (\Throwable $e) {
            // ahg_settings absent on a fresh install - fall through to default.
        }

        return 'anc_records';
    }

    /**
     * Search catalogue records for a query.
     *
     * @return array{records: array, query: string}
     */
    public function search(string $query, int $limit = 0): array
    {
        $limit = $limit > 0 ? $limit : $this->topK;

        // Try Qdrant vector search first
        $records = $this->searchQdrant($query, $limit);
        if (!empty($records)) {
            return ['query' => $query, 'records' => $records];
        }

        // Fallback: Elasticsearch keyword search on IO metadata
        return ['query' => $query, 'records' => $this->searchElasticsearch($query, $limit)];
    }

    /**
     * Vector similarity search via Qdrant REST API.
     *
     * @return array[]
     */
    private function searchQdrant(string $query, int $limit): array
    {
        try {
            $embedding = $this->embedQuery($query);
            if ($embedding === null) {
                return [];
            }

            $response = Http::timeout(15)->post(
                "{$this->url}/collections/{$this->collection}/points/search",
                [
                    'vector'   => $embedding,
                    'limit'    => $limit,
                    'with_payload' => true,
                ]
            );

            if (!$response->successful()) {
                return [];
            }

            $body = $response->json();
            $hits = $body['result'] ?? [];

            return array_map(fn ($h) => $this->parseHit($h), $hits);
        } catch (\Throwable $e) {
            Log::warning('[chatbot] Qdrant search failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Embed a query string via the AHG AI gateway (#1245).
     *
     * Routes through ai.theahg.co.za/ai/v1/ollama/api/embed - never a direct
     * node port. The gateway exposes Ollama transparently at /ollama/{path}.
     * Uses the same embedding model the Qdrant collection was indexed with
     * (AiServicesSettings::qdrantModel()), otherwise vector search returns
     * nothing. Returns null on any failure.
     *
     * @return float[]|null
     */
    private function embedQuery(string $query): ?array
    {
        $base    = rtrim(AiServicesSettings::apiUrl() ?: 'https://ai.theahg.co.za/ai/v1', '/');
        $key     = $this->resolveApiKey();
        $model   = AiServicesSettings::qdrantModel();
        $timeout = AiServicesSettings::apiTimeout();

        try {
            $req = Http::timeout($timeout)->asJson();
            if (!empty($key)) {
                $req = $req->withToken($key);
            }
            $resp = $req->post($base . '/ollama/api/embed', [
                'model' => $model,
                'input' => $query,
            ]);

            if (!$resp->successful()) {
                return null;
            }

            $embeddings = $resp->json('embeddings') ?? [];
            return is_array($embeddings) && !empty($embeddings)
                ? (is_numeric($embeddings[0]) ? $embeddings : $embeddings[0])
                : null;
        } catch (\Throwable $e) {
            Log::warning('[chatbot] embedQuery failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve the gateway API key the same way NER/HTR do (#1245).
     *
     * AiServicesSettings::apiKey() reads ahg_ner_settings.ai_services_api_key,
     * which is empty on this deployment - the working ahg_live_* key the rest
     * of the AI services authenticate with lives under setting_key 'api_key'
     * (ahg_ner_settings, then ahg_ai_settings feature='general'). Mirror that
     * lookup so the embed call is authenticated, then fall back to the
     * AiServicesSettings accessor if a deployment populates that instead.
     */
    private function resolveApiKey(): ?string
    {
        try {
            $key = DB::table('ahg_ner_settings')
                ->where('setting_key', 'api_key')
                ->value('setting_value');
            if ($key !== null && $key !== '') {
                return (string) $key;
            }

            $key = DB::table('ahg_ai_settings')
                ->where('feature', 'general')
                ->where('setting_key', 'api_key')
                ->value('setting_value');
            if ($key !== null && $key !== '') {
                return (string) $key;
            }
        } catch (\Throwable $e) {
            // settings tables absent during boot - fall through.
        }

        return AiServicesSettings::apiKey();
    }

    /**
     * Parse a Qdrant hit into a canonical record shape.
     */
    private function parseHit(array $hit): array
    {
        $payload = $hit['payload'] ?? [];

        $title = $payload['title']
            ?? $payload['title_and_detail'] ?? $payload['description'] ?? 'Untitled';
        $identifier = $payload['identifier'] ?? '';
        $slug = $payload['slug'] ?? $payload['url'] ?? '';

        $url = $slug !== ''
            ? url('/informationobject/' . $slug)
            : null;

        $excerpt = $payload['scope_and_content']
            ?? $payload['description'] ?? $payload['title_and_detail'] ?? '';
        if (mb_strlen($excerpt) > 350) {
            $excerpt = mb_substr($excerpt, 0, 347) . '...';
        }

        return [
            'id'          => $hit['id'] ?? null,
            'title'       => $title,
            'identifier'  => $identifier,
            'url'         => $url,
            'excerpt'     => $excerpt,
            'score'       => $hit['score'] ?? null,
        ];
    }

    /**
     * Elasticsearch keyword/KNN fallback for when Qdrant is unavailable.
     *
     * @return array[]
     */
    private function searchElasticsearch(string $query, int $limit): array
    {
        try {
            $esUrl = config('heratio.es_url', 'http://localhost:9200');
            $index = config('heratio.es_index', 'heratio-io');

            $response = Http::timeout(15)
                ->asJson()
                ->post("{$esUrl}/{$index}/_search", [
                    'size'  => $limit,
                    'query' => [
                        'multi_match' => [
                            'query'  => $query,
                            'fields' => 'title^3,identifier^2,scope_and_content,description,subject,creator',
                            'type'   => 'best_fields',
                        ],
                    ],
                    '_source' => ['title', 'identifier', 'slug', 'scope_and_content', 'description'],
                ]);

            if (!$response->successful()) {
                return [];
            }

            $hits = $response->json('hits.hits') ?? [];
            return array_map(function ($h) {
                $src = $h['_source'] ?? [];
                $excerpt = $src['scope_and_content'] ?? $src['description'] ?? '';
                if (mb_strlen($excerpt) > 350) {
                    $excerpt = mb_substr($excerpt, 0, 347) . '...';
                }
                return [
                    'id'         => null,
                    'title'      => $src['title'] ?? 'Untitled',
                    'identifier' => $src['identifier'] ?? '',
                    'url'        => isset($src['slug']) ? url('/informationobject/' . $src['slug']) : null,
                    'excerpt'    => $excerpt,
                    'score'      => $h['_score'] ?? null,
                ];
            }, $hits);
        } catch (\Throwable $e) {
            Log::warning('[chatbot] Elasticsearch fallback failed: ' . $e->getMessage());
            return [];
        }
    }
}
