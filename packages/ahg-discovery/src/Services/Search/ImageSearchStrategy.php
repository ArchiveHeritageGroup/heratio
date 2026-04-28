<?php

/**
 * ImageSearchStrategy — image similarity via Qdrant CLIP collection.
 *
 * Two query modes:
 *   1. By uploaded image bytes — the controller hashes + sends to a CLIP embed
 *      endpoint, then calls {@see searchByVector()}.
 *   2. By an existing IO with a known representative image — call
 *      {@see searchByExistingObject()} which fetches that point's vector from
 *      Qdrant and finds nearest neighbours.
 *
 * Configurable via ahg_settings:
 *   ahg_discovery_image_enabled       (bool, default 1)
 *   ahg_discovery_image_min_score     (float 0..1, default 0.30)
 *   ahg_discovery_image_pool_size     (int, default 50)
 *   ahg_discovery_image_collection    (default archive_images)
 *   ahg_discovery_image_embed_url     (default http://192.168.0.78:11434)
 *   ahg_discovery_image_embed_model   (default clip-vit-b-32)
 *   semantic_timeout_ms               (default 5000)
 *
 * Graceful when image embed endpoint is offline. Implements
 * SearchStrategyInterface but its search() always returns [] for plain text
 * queries — image search needs an image input. The Discovery controller
 * is expected to call {@see searchByExistingObject()} or {@see searchByVector()}
 * directly when an image context is available.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgDiscovery\Services\Search;

use AhgSearch\Services\VectorSearchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImageSearchStrategy implements SearchStrategyInterface
{
    public function __construct(protected VectorSearchService $vector)
    {
    }

    public function name(): string
    {
        return 'image';
    }

    public function isEnabled(): bool
    {
        return $this->setting('ahg_discovery_image_enabled', '1') !== '0';
    }

    /**
     * Plain-text invocation is a no-op — image search needs an image input.
     * The controller sets $context['image_path'] or $context['similar_to_object_id']
     * to drive the real image pipeline; for the standard text branch return [].
     */
    public function search(string $query, array $context): array
    {
        if (! $this->isEnabled()) {
            return [];
        }
        if (! empty($context['similar_to_object_id'])) {
            return $this->searchByExistingObject((int) $context['similar_to_object_id'], (int) ($context['limit'] ?? 50));
        }
        if (! empty($context['image_path'])) {
            return $this->searchByImagePath((string) $context['image_path'], (int) ($context['limit'] ?? 50));
        }
        return [];
    }

    /**
     * Find images visually similar to one already indexed in Qdrant.
     *
     * @return array<int, array{object_id:int, score:float, source:string}>
     */
    public function searchByExistingObject(int $objectId, int $limit = 50): array
    {
        $collection = $this->collection();
        $vec = $this->vector->fetchPointVector($collection, $objectId);
        if ($vec === null) {
            return [];
        }
        return $this->searchByVector($vec, $limit, $objectId);
    }

    /**
     * Search by raw image file (path on disk). Embeds via the configured CLIP endpoint.
     *
     * @return array<int, array{object_id:int, score:float, source:string}>
     */
    public function searchByImagePath(string $imagePath, int $limit = 50): array
    {
        if (! is_file($imagePath) || ! is_readable($imagePath)) {
            return [];
        }
        $vec = $this->embedImage($imagePath);
        if ($vec === null) {
            return [];
        }
        return $this->searchByVector($vec, $limit);
    }

    /**
     * Run a Qdrant search with a pre-computed image vector.
     *
     * @return array<int, array{object_id:int, score:float, source:string}>
     */
    public function searchByVector(array $vector, int $limit = 50, ?int $excludeId = null): array
    {
        $collection = $this->collection();
        $minScore   = (float) $this->setting('ahg_discovery_image_min_score', '0.30');
        $pool       = max(1, min(200, $limit));

        $hits = $this->vector->qdrantSearch($collection, $vector, $pool);
        if ($hits === null) {
            return [];
        }

        $out = [];
        foreach ($hits as $h) {
            $id    = (int) ($h['id'] ?? 0);
            $score = (float) ($h['score'] ?? 0);
            if ($id <= 0 || $score < $minScore) {
                continue;
            }
            if ($excludeId !== null && $id === $excludeId) {
                continue;
            }
            $out[] = [
                'object_id' => $id,
                'score'     => round($score, 6),
                'source'    => 'image',
                'slug'      => $h['slug'] ?? null,
                'title'     => $h['title'] ?? null,
            ];
        }
        return $out;
    }

    /* -------------------------------------------------------------------- */

    protected function collection(): string
    {
        return (string) $this->setting('ahg_discovery_image_collection', 'archive_images');
    }

    /**
     * Call the image-embedding endpoint with raw image bytes.
     * Expects an Ollama-compatible /api/embeddings endpoint that accepts a base64 image.
     *
     * @return array<int, float>|null
     */
    protected function embedImage(string $imagePath): ?array
    {
        $url   = rtrim((string) $this->setting('ahg_discovery_image_embed_url', 'http://192.168.0.78:11434'), '/');
        $model = (string) $this->setting('ahg_discovery_image_embed_model', 'clip-vit-b-32');
        $timeout = (int) $this->setting('semantic_timeout_ms', '5000');

        $bytes = @file_get_contents($imagePath);
        if ($bytes === false) {
            return null;
        }
        $payload = json_encode([
            'model'  => $model,
            'prompt' => '',
            'images' => [base64_encode($bytes)],
        ]);

        $ch = curl_init($url . '/api/embeddings');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POSTFIELDS      => $payload,
            CURLOPT_HTTPHEADER      => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT_MS      => max(1000, $timeout * 4),
            CURLOPT_CONNECTTIMEOUT_MS => max(500, min(2000, $timeout)),
            CURLOPT_CUSTOMREQUEST   => 'POST',
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false || $code < 200 || $code >= 300) {
            Log::debug('Discovery image embed failed: ' . $url . ' code=' . $code);
            return null;
        }
        $decoded = json_decode((string) $resp, true);
        if (! is_array($decoded) || empty($decoded['embedding']) || ! is_array($decoded['embedding'])) {
            return null;
        }
        return array_map('floatval', $decoded['embedding']);
    }

    protected function setting(string $key, ?string $default = null): ?string
    {
        try {
            $v = DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');
            if ($v !== null && $v !== '') {
                return (string) $v;
            }
        } catch (Throwable $e) {
        }
        return $default;
    }
}
