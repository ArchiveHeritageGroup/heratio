<?php

/**
 * VectorSearchService — embedding-based semantic search backed by Qdrant.
 *
 * Two-step pipeline:
 *
 *   1. Ask the embedding service (via the AHG AI gateway) for a vector
 *      representation of the user's query string.
 *   2. Send that vector to Qdrant /collections/{name}/points/search and return
 *      the top-N nearest neighbours with score + payload (slug, title, etc).
 *
 * #1247: embeddings route through the gateway (AiServicesSettings::apiUrl() ?:
 * https://ai.theahg.co.za/ai/v1) at {base}/ollama/api/embeddings - never a
 * direct GPU node port. A stale semantic_embedding_url pointing at a :11434
 * node is ignored so it cannot re-introduce the bypass.
 *
 * All endpoints + model + collection are configurable via ahg_settings:
 *   semantic_embedding_url       (gateway override; node-port values ignored)
 *   semantic_embedding_model     (default all-minilm — 384-dim, matches anc_records)
 *   semantic_qdrant_url          (default http://localhost:6333)
 *   semantic_qdrant_collection   (default anc_records)
 *   semantic_timeout_ms          (default 5000)
 *
 * Returns gracefully on AI-server-offline / Qdrant-down — never throws to the
 * caller; instead returns ['ok' => false, 'reason' => '...'].
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgSearch\Services;

use AhgAiServices\Support\AiServicesSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class VectorSearchService
{
    /**
     * Search Qdrant for the nearest neighbours of a free-text query.
     *
     * @return array{
     *   ok: bool,
     *   query?: string,
     *   collection?: string,
     *   hits?: array<int, array{id:int, score:float, slug:?string, title:?string, has_scope:?bool, payload?:array}>,
     *   reason?: string,
     *   error?: string,
     * }
     */
    public function searchSimilar(string $query, int $limit = 20, ?string $collection = null, ?array $filter = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['ok' => false, 'reason' => 'empty query'];
        }

        $collection = $collection ?: $this->setting('semantic_qdrant_collection', 'anc_records');

        $vector = $this->embedQuery($query);
        if ($vector === null) {
            return ['ok' => false, 'reason' => 'embedding service unavailable'];
        }

        $hits = $this->qdrantSearch($collection, $vector, $limit, $filter);
        if ($hits === null) {
            return ['ok' => false, 'reason' => 'qdrant unavailable'];
        }

        return [
            'ok' => true,
            'query' => $query,
            'collection' => $collection,
            'hits' => $hits,
        ];
    }

    /**
     * Search Qdrant for points similar to an existing point id (e.g. an IO id).
     * Used by the "find similar records" button on entity show pages.
     */
    public function searchSimilarToPoint(int $pointId, int $limit = 12, ?string $collection = null, ?array $filter = null): array
    {
        $collection = $collection ?: $this->setting('semantic_qdrant_collection', 'anc_records');

        $vector = $this->fetchPointVector($collection, $pointId);
        if ($vector === null) {
            return ['ok' => false, 'reason' => 'point not in collection'];
        }

        $hits = $this->qdrantSearch($collection, $vector, $limit + 1, $filter);
        if ($hits === null) {
            return ['ok' => false, 'reason' => 'qdrant unavailable'];
        }

        // Drop the source point itself if it appears in the result set.
        $hits = array_values(array_filter($hits, fn ($h) => (int) $h['id'] !== $pointId));
        if (count($hits) > $limit) {
            $hits = array_slice($hits, 0, $limit);
        }

        return [
            'ok' => true,
            'point_id' => $pointId,
            'collection' => $collection,
            'hits' => $hits,
        ];
    }

    /**
     * Tell whether the AI + Qdrant stack is reachable. Used by the API to
     * communicate degraded state to the caller without throwing.
     */
    public function health(): array
    {
        $emb = $this->embeddingHealth();
        $qd = $this->qdrantHealth();

        return [
            'embedding' => $emb,
            'qdrant' => $qd,
            'ok' => $emb['ok'] && $qd['ok'],
        ];
    }

    /* ====================================================================
     *  Embedding
     * ==================================================================== */

    /**
     * Ask the embedding service for a vector. Returns null on failure.
     *
     * @return array<int, float>|null
     */
    public function embedQuery(string $text): ?array
    {
        // #1247: embeddings MUST route through the AHG AI gateway, never a
        // direct GPU node port. The gateway exposes Ollama transparently at
        // {base}/ollama/{path}, so the legacy /api/embeddings request +
        // 'embedding' response shape is preserved end-to-end.
        $base = $this->gatewayBase();
        $key  = $this->resolveApiKey();
        // KEEP the model exactly as currently resolved - anc_records is a
        // 384-dim all-minilm collection; a model change would force a re-index.
        $model = $this->setting('semantic_embedding_model', 'all-minilm');
        $timeout = (int) $this->setting('semantic_timeout_ms', 5000);

        $payload = json_encode(['model' => $model, 'prompt' => $text]);
        $resp = $this->httpPost($base.'/ollama/api/embeddings', $payload, $timeout, $key);
        if ($resp === null) {
            return null;
        }
        $decoded = json_decode($resp, true);
        if (! is_array($decoded) || empty($decoded['embedding']) || ! is_array($decoded['embedding'])) {
            return null;
        }

        return array_map('floatval', $decoded['embedding']);
    }

    /**
     * Resolve the AI gateway base URL (#1247).
     *
     * The gateway is the default and the canonical embedding door. The legacy
     * 'semantic_embedding_url' setting is still honoured as an override, but a
     * stale row pointing at a :11434 node port is IGNORED so it cannot
     * re-introduce the bypass this issue closes.
     */
    protected function gatewayBase(): string
    {
        $gateway = rtrim(AiServicesSettings::apiUrl() ?: 'https://ai.theahg.co.za/ai/v1', '/');

        $override = $this->setting('semantic_embedding_url');
        if ($override !== null && $override !== '') {
            $override = rtrim($override, '/');
            // Skip any direct node-port URL (:11434 Ollama, LAN host, raw IP)
            // so a stale DB row can't bypass the gateway.
            $isNode = str_contains($override, ':11434')
                || preg_match('~//192\.168\.~', $override)
                || preg_match('~/api/embeddings?$~', $override);
            if (! $isNode) {
                return $override;
            }
        }

        return $gateway;
    }

    /**
     * Resolve the gateway API key the same way QdrantRetriever / NER / HTR do.
     *
     * setting_key 'api_key' on ahg_ner_settings, then ahg_ai_settings
     * feature='general', then the AiServicesSettings accessor.
     */
    protected function resolveApiKey(): ?string
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
        } catch (Throwable $e) {
            // settings tables absent during boot - fall through.
        }

        return AiServicesSettings::apiKey();
    }

    /* ====================================================================
     *  Qdrant
     * ==================================================================== */

    /**
     * Run a /points/search against a collection.
     *
     * @return array<int, array>|null
     */
    public function qdrantSearch(string $collection, array $vector, int $limit, ?array $filter = null): ?array
    {
        $url = rtrim($this->setting('semantic_qdrant_url', 'http://localhost:6333'), '/');
        $body = [
            'vector' => $vector,
            'limit' => max(1, min(100, $limit)),
            'with_payload' => true,
            'with_vector' => false,
        ];
        // #69: honour qdrant_min_score so the operator's filter floor reaches
        // the engine instead of being ignored. Empty / 0 means no floor.
        $minScore = (float) ($this->setting('semantic_qdrant_min_score', '0') ?? '0');
        if ($minScore > 0) {
            $body['score_threshold'] = $minScore;
        }
        if ($filter !== null) {
            $body['filter'] = $filter;
        }
        $resp = $this->httpPost($url.'/collections/'.urlencode($collection).'/points/search',
            json_encode($body),
            (int) $this->setting('semantic_timeout_ms', 5000));
        if ($resp === null) {
            return null;
        }
        $decoded = json_decode($resp, true);
        if (! is_array($decoded) || ! isset($decoded['result']) || ! is_array($decoded['result'])) {
            return null;
        }

        return array_map(function ($p) {
            $payload = is_array($p['payload'] ?? null) ? $p['payload'] : [];

            return [
                'id' => (int) ($p['id'] ?? 0),
                'score' => round((float) ($p['score'] ?? 0), 6),
                'slug' => $payload['slug'] ?? null,
                'title' => $payload['title'] ?? null,
                'has_scope' => isset($payload['has_scope']) ? (bool) $payload['has_scope'] : null,
                'parent_id' => isset($payload['parent_id']) ? (int) $payload['parent_id'] : null,
                'database' => $payload['database'] ?? null,
                'payload' => $payload,
            ];
        }, $decoded['result']);
    }

    /**
     * Fetch a single point's vector — used for similar-to-this-point queries.
     *
     * @return array<int, float>|null
     */
    public function fetchPointVector(string $collection, int $pointId): ?array
    {
        $url = rtrim($this->setting('semantic_qdrant_url', 'http://localhost:6333'), '/');
        $resp = $this->httpGet($url.'/collections/'.urlencode($collection).'/points/'.$pointId,
            ['with_vector' => 'true', 'with_payload' => 'false'],
            (int) $this->setting('semantic_timeout_ms', 5000));
        if ($resp === null) {
            return null;
        }
        $decoded = json_decode($resp, true);
        $vec = $decoded['result']['vector'] ?? null;
        if (! is_array($vec)) {
            return null;
        }

        return array_map('floatval', $vec);
    }

    protected function embeddingHealth(): array
    {
        // #1247: probe the gateway, not a node port. The gateway proxies
        // Ollama's /api/version transparently at {base}/ollama/api/version.
        $base = $this->gatewayBase();
        $key  = $this->resolveApiKey();
        $resp = $this->httpGet($base.'/ollama/api/version', [], 2000, $key);

        return ['ok' => $resp !== null, 'url' => $base];
    }

    protected function qdrantHealth(): array
    {
        $url = rtrim($this->setting('semantic_qdrant_url', 'http://localhost:6333'), '/');
        $resp = $this->httpGet($url.'/collections', [], 2000);

        return ['ok' => $resp !== null, 'url' => $url];
    }

    /* ====================================================================
     *  Settings + HTTP plumbing
     * ==================================================================== */

    protected function setting(string $key, ?string $default = null): ?string
    {
        try {
            $row = DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');
            if ($row !== null && $row !== '') {
                return (string) $row;
            }
        } catch (Throwable $e) {
            // setting table might not exist on a brand-new install
        }
        // #69: fall back to the AI Services settings tile (ahg_ner_settings).
        // The semantic_* keys are the canonical ones VectorSearchService was
        // built around; the AI Services form exposes qdrant_url / qdrant_collection
        // which mean the same thing - try them when the canonical key is unset
        // so the operator doesn't have to know about both.
        $alias = match ($key) {
            'semantic_qdrant_url' => 'qdrant_url',
            'semantic_qdrant_collection' => 'qdrant_collection',
            'semantic_embedding_model' => 'qdrant_model',
            'semantic_qdrant_min_score' => 'qdrant_min_score',
            default => null,
        };
        if ($alias) {
            try {
                $row = DB::table('ahg_ner_settings')->where('setting_key', $alias)->value('setting_value');
                if ($row !== null && $row !== '') {
                    return (string) $row;
                }
            } catch (Throwable $e) {
                // ahg_ner_settings absent - fall through
            }
        }

        return $default;
    }

    protected function httpGet(string $url, array $query, int $timeoutMs, ?string $key = null): ?string
    {
        if (! empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        return $this->curl('GET', $url, null, $timeoutMs, $key);
    }

    protected function httpPost(string $url, string $body, int $timeoutMs, ?string $key = null): ?string
    {
        return $this->curl('POST', $url, $body, $timeoutMs, $key);
    }

    protected function curl(string $method, string $url, ?string $body, int $timeoutMs, ?string $key = null): ?string
    {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($key !== null && $key !== '') {
            $headers[] = 'Authorization: Bearer '.$key;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => max(500, $timeoutMs),
            CURLOPT_CONNECTTIMEOUT_MS => max(500, min(2000, $timeoutMs)),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $code < 200 || $code >= 300) {
            if ($err) {
                Log::debug('VectorSearchService curl: '.$method.' '.$url.' err='.$err);
            }

            return null;
        }

        return is_string($resp) ? $resp : null;
    }
}
