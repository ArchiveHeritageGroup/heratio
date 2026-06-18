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
     * #1208 (culture = language): an optional $culture scope constrains the hits
     * to the records that are described IN the selected language(s) - the SAME
     * definition the LanguageCorpusService uses (published, non-root
     * information_object rows whose information_object_i18n.culture base-subtag is
     * one of the selected languages). $culture may be a single culture string OR an
     * array of culture codes; with several the scope is the UNION of their corpora
     * (a record in ANY selected language is in scope). When $culture is
     * null/empty/all-unknown the behaviour is byte-identical to before (unscoped).
     *
     * The live Qdrant payload (written by QdrantIndexCommand) carries slug / title /
     * parent_id / has_scope but NOT culture - and each point id IS the
     * information_object id. So we cannot filter inside Qdrant; instead we over-fetch
     * a candidate pool and post-filter the hits against the in-language id set from
     * the DB (mirroring LanguageCorpusService). The Elasticsearch fallback applies
     * the same id-set filter. Both fail soft: if the in-language id set cannot be
     * resolved we return the unscoped hits rather than an empty result or a 500.
     *
     * @param  string|array<int,string>|null  $culture
     * @return array{records: array, query: string}
     */
    public function search(string $query, int $limit = 0, $culture = null): array
    {
        $limit = $limit > 0 ? $limit : $this->topK;

        $scope = $this->resolveCultureScope($culture);

        // #1208 cross-language corpus blending (soft blend, default on): when the
        // conversation is scoped, in-language records remain the PRIMARY tier, but
        // if they do not fill $limit the remaining slots are filled with the top
        // cross-language records about the same topic, so a sparse language stops
        // returning "nothing in this corpus". Disable to restore the hard in-corpus
        // filter (in-language records only).
        $blend = (bool) config('ahg-ai-chatbot.cross_language_blend', true);

        // Over-fetch when scoped so post-filtering / blending still yields up to
        // $limit hits (and a cross-language tail to fill from).
        $fetch = $scope === null ? $limit : max($limit * 6, 30);

        // Try Qdrant vector search first
        $records = $this->searchQdrant($query, $fetch);
        $records = $this->applyCultureScope($records, $scope, $limit, $blend);
        if (!empty($records)) {
            return ['query' => $query, 'records' => array_slice($records, 0, $limit)];
        }

        // Fallback: Elasticsearch keyword search on IO metadata
        $es = $this->searchElasticsearch($query, $fetch);
        $es = $this->applyCultureScope($es, $scope, $limit, $blend);

        return ['query' => $query, 'records' => array_slice($es, 0, $limit)];
    }

    /**
     * Resolve a requested culture scope into the normalised list of base subtags +
     * the UNION set of information_object ids that are described IN any of them (the
     * LanguageCorpusService definition). Accepts a single culture string OR an array
     * of culture codes. Returns null when there is no usable scope (no/blank
     * culture, all codes unknown, the LanguageCorpusService is unavailable, or the
     * lookup found no in-language records) so callers fall back to fully unscoped
     * behaviour. Never throws.
     *
     * @param  string|array<int,string>|null  $culture
     * @return array{bases:array<int,string>,ids:array<int,bool>}|null
     */
    private function resolveCultureScope($culture): ?array
    {
        $requested = is_array($culture) ? $culture : ($culture === null ? [] : [$culture]);
        if (empty($requested)) {
            return null;
        }

        $svcClass = '\\AhgSemanticSearch\\Services\\LanguageCorpusService';
        if (!class_exists($svcClass)) {
            return null;
        }

        try {
            $svc = new $svcClass();

            // Normalise + de-dupe the requested codes to usable base subtags.
            $bases = [];
            foreach ($requested as $c) {
                $base = $svc->sanitiseCulture(is_string($c) ? $c : null);
                if ($base !== null) {
                    $bases[$base] = true;
                }
            }
            $bases = array_keys($bases);
            if (empty($bases)) {
                return null; // all unknown / malformed -> unscoped (fail soft)
            }

            // Union id set across all selected languages (service does the union).
            $ids = $svc->describedRecordIds($bases);
            if (empty($ids)) {
                return null; // nothing published in any selected language -> unscoped
            }

            return ['bases' => $bases, 'ids' => array_fill_keys($ids, true)];
        } catch (\Throwable $e) {
            $label = is_array($culture) ? implode(',', $culture) : (string) $culture;
            Log::info('[chatbot] culture scope resolve failed for ' . $label . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Apply the in-language scope to a ranked hit list. Hits carry the IO id as
     * their point id (Qdrant) or on the parsed record (ES); a hit with no
     * resolvable id is dropped under scope (it cannot be attributed). Returns the
     * input unchanged when there is no scope.
     *
     * Each retained hit is tagged `in_corpus` (true = described in a selected
     * language, false = a related cross-language record), so the prompt can label
     * the two tiers honestly.
     *
     * - Hard filter ($blend = false): keep only in-language hits (legacy behaviour).
     * - Soft blend ($blend = true, #1208): in-language hits first (PRIMARY), then,
     *   only if they do not fill $limit, fill the remaining slots with the top
     *   cross-language hits (preserving relevance rank). When in-language hits
     *   already meet $limit, no cross-language hits are added.
     *
     * @param array<int,array<string,mixed>> $records
     * @param array{bases:array<int,string>,ids:array<int,bool>}|null $scope
     * @return array<int,array<string,mixed>>
     */
    private function applyCultureScope(array $records, ?array $scope, int $limit = 0, bool $blend = true): array
    {
        if ($scope === null || empty($records)) {
            return $records;
        }

        $ids = $scope['ids'];
        $inCorpus = [];
        $cross = [];
        foreach ($records as $r) {
            $id = isset($r['id']) && is_numeric($r['id']) ? (int) $r['id'] : 0;
            if ($id <= 0) {
                continue; // unattributable under scope
            }
            if (isset($ids[$id])) {
                $r['in_corpus'] = true;
                $inCorpus[] = $r;
            } else {
                $r['in_corpus'] = false;
                $cross[] = $r;
            }
        }

        if (! $blend) {
            return $inCorpus; // hard in-corpus filter (legacy)
        }

        // Soft blend: in-language first, fill the remainder with cross-language.
        $need = $limit > 0 ? $limit - count($inCorpus) : 0;
        if ($need <= 0) {
            return $inCorpus;
        }

        return array_merge($inCorpus, array_slice($cross, 0, $need));
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
     * Map a set of slugs to their information_object ids via the slug table, in one
     * batched query. Only used by the ES fallback to give hits an id for culture
     * scoping. Best-effort: returns an empty map on any failure (never throws).
     *
     * @param array<int,string> $slugs
     * @return array<string,int>
     */
    private function resolveIdsBySlug(array $slugs): array
    {
        $slugs = array_values(array_unique(array_filter($slugs, fn ($s) => is_string($s) && $s !== '')));
        if (empty($slugs)) {
            return [];
        }

        try {
            $rows = DB::table('slug')->whereIn('slug', $slugs)->select('slug', 'object_id')->get();
            $out = [];
            foreach ($rows as $r) {
                $out[(string) $r->slug] = (int) $r->object_id;
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
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

            // Resolve information_object ids from slugs in one batched query so the
            // #1208 culture scope can post-filter ES hits the same way as Qdrant
            // hits. Best-effort: a missing slug table just leaves id null (the hit
            // is then dropped only when a culture scope is active).
            $slugs = array_values(array_filter(array_map(
                fn ($h) => $h['_source']['slug'] ?? null,
                $hits
            )));
            $idBySlug = $this->resolveIdsBySlug($slugs);

            return array_map(function ($h) use ($idBySlug) {
                $src = $h['_source'] ?? [];
                $excerpt = $src['scope_and_content'] ?? $src['description'] ?? '';
                if (mb_strlen($excerpt) > 350) {
                    $excerpt = mb_substr($excerpt, 0, 347) . '...';
                }
                $slug = $src['slug'] ?? null;
                return [
                    'id'         => ($slug !== null && isset($idBySlug[$slug])) ? $idBySlug[$slug] : null,
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
