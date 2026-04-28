<?php

/**
 * VectorSearchStrategy — semantic similarity via Qdrant.
 *
 * Wraps {@see \AhgSearch\Services\VectorSearchService} so the Discovery pipeline
 * can ablation-test vector contributions independently. Returns hits in the
 * shape Discovery's merger expects:
 *   {object_id, score, source:'vector', slug?, title?}
 *
 * Configurable via ahg_settings:
 *   ahg_discovery_vector_enabled    (bool, default 1)
 *   ahg_discovery_vector_min_score  (float 0..1, default 0.25)
 *   ahg_discovery_vector_pool_size  (int, default 100)
 *   semantic_qdrant_collection      (defaulted in VectorSearchService)
 *
 * Graceful: if Ollama or Qdrant is offline, returns []; isEnabled() reflects.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgDiscovery\Services\Search;

use AhgSearch\Services\VectorSearchService;
use Illuminate\Support\Facades\DB;
use Throwable;

class VectorSearchStrategy implements SearchStrategyInterface
{
    public function __construct(protected VectorSearchService $vector)
    {
    }

    public function name(): string
    {
        return 'vector';
    }

    public function isEnabled(): bool
    {
        if (! class_exists(VectorSearchService::class)) {
            return false;
        }
        return $this->setting('ahg_discovery_vector_enabled', '1') !== '0';
    }

    public function search(string $query, array $context): array
    {
        if (! $this->isEnabled() || trim($query) === '') {
            return [];
        }

        $minScore = (float) $this->setting('ahg_discovery_vector_min_score', '0.25');
        $pool     = (int)   $this->setting('ahg_discovery_vector_pool_size', '100');
        $pool     = max(1, min(200, $pool));
        $collection = $context['vector_collection'] ?? null;

        try {
            $r = $this->vector->searchSimilar($query, $pool, $collection);
        } catch (Throwable $e) {
            return [];
        }
        if (empty($r['ok']) || empty($r['hits'])) {
            return [];
        }

        $out = [];
        foreach ($r['hits'] as $h) {
            $score = (float) ($h['score'] ?? 0);
            if ($score < $minScore) {
                continue;
            }
            $out[] = [
                'object_id' => (int) $h['id'],
                'score'     => round($score, 6),
                'source'    => 'vector',
                'slug'      => $h['slug'] ?? null,
                'title'     => $h['title'] ?? null,
            ];
        }
        return $out;
    }

    protected function setting(string $key, ?string $default = null): ?string
    {
        try {
            $v = DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');
            if ($v !== null && $v !== '') {
                return (string) $v;
            }
        } catch (Throwable $e) {
            // settings not yet seeded
        }
        return $default;
    }
}
