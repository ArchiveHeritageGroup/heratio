<?php

/**
 * ImageSearchStrategy - image similarity over the Qdrant image collection
 * (heratio#1272).
 *
 * Query modes:
 *   - by image (uploaded bytes or a file on disk)  - image-to-image
 *   - by text ("a steam train on a bridge")         - text-to-image
 *   - by a record already in the index              - "more like this"
 * All embedding goes through the AHG AI gateway via ImageEmbeddingService.
 *
 * Points are master digital objects (point id = digital_object.id); every hit
 * is mapped back to its record (payload information_object_id) and folded so
 * a record appears once, at its best score.
 *
 * Visitors only get records that DisclosureGate allows (published, no ICIP,
 * ODRL, embargo or classification restriction), that carry no PII redaction
 * regions, and whose files FileScreeningGate does not withhold - a visual
 * match is itself a disclosure of the image. Staff see every hit.
 *
 * Settings (ahg_settings):
 *   ahg_discovery_image_enabled      (default 1)
 *   ahg_discovery_image_min_score    (default 0.30)
 *   ahg_discovery_image_collection   (default archive_images)
 *   semantic_timeout_ms              (default 5000)
 * plus the embedder settings read by ImageEmbeddingService.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgDiscovery\Services\Search;

use AhgCore\Services\AclService;
use AhgCore\Services\DisclosureGate;
use AhgDiscovery\Services\ImageEmbeddingService;
use AhgSearch\Services\VectorSearchService;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImageSearchStrategy implements SearchStrategyInterface
{
    public function __construct(protected VectorSearchService $vector, protected ImageEmbeddingService $embedder) {}

    public function name(): string
    {
        return 'image';
    }

    public function isEnabled(): bool
    {
        return $this->setting('ahg_discovery_image_enabled', '1') !== '0';
    }

    /**
     * Pipeline entry. With no image context a plain text query is NOT run
     * here (the text branch of discovery has its own strategies); the
     * controller sets image_path / similar_to_object_id to drive this one.
     */
    public function search(string $query, array $context): array
    {
        if (! $this->isEnabled()) {
            return [];
        }
        $limit = (int) ($context['limit'] ?? 50);
        if (! empty($context['similar_to_object_id'])) {
            return $this->searchByExistingObject((int) $context['similar_to_object_id'], $limit);
        }
        if (! empty($context['image_path'])) {
            return $this->searchByImagePath((string) $context['image_path'], $limit);
        }

        return [];
    }

    /**
     * Images like an indexed point (a master digital object id).
     *
     * @return array<int, array{object_id:int, score:float, source:string}>
     */
    public function searchByExistingObject(int $pointId, int $limit = 50): array
    {
        $vec = $this->vector->fetchPointVector($this->collection(), $pointId);

        return $vec === null ? [] : $this->searchByVector($vec, $limit, $pointId);
    }

    /** Records whose images look like this record's image. */
    public function searchSimilarToRecord(int $ioId, int $limit = 50): array
    {
        if (! $this->visible([$ioId])) {
            return []; // the seed image itself is not disclosable to this viewer
        }
        $pointIds = $this->embedder->candidates()->where('d.object_id', $ioId)->orderBy('d.id')->pluck('d.id');
        foreach ($pointIds as $pointId) {
            $vec = $this->vector->fetchPointVector($this->collection(), (int) $pointId);
            if ($vec !== null) {
                return array_values(array_filter(
                    $this->searchByVector($vec, $limit + 1, (int) $pointId),
                    fn ($h) => $h['object_id'] !== $ioId
                ));
            }
        }

        return [];
    }

    public function searchByImagePath(string $imagePath, int $limit = 50): array
    {
        if (! is_file($imagePath) || ! is_readable($imagePath)) {
            return [];
        }
        $bytes = @file_get_contents($imagePath);

        return $bytes === false ? [] : $this->searchByImageBytes($bytes, $limit);
    }

    public function searchByImageBytes(string $bytes, int $limit = 50): array
    {
        $vec = $this->embedder->embedImageBytes($bytes, max(5, (int) ceil($this->timeoutMs() * 4 / 1000)));

        return $vec === null ? [] : $this->searchByVector($vec, $limit);
    }

    public function searchByText(string $text, int $limit = 50): array
    {
        $vec = $this->embedder->embedText($text, max(2, (int) ceil($this->timeoutMs() / 1000)));

        return $vec === null ? [] : $this->searchByVector($vec, $limit);
    }

    /** True when the last embed failed because the gateway could not serve it. */
    public function embedderUnavailable(): bool
    {
        return $this->embedder->unavailable;
    }

    /**
     * Nearest images to a vector, one row per record, gated for visitors.
     *
     * @return array<int, array{object_id:int, score:float, source:string, slug:?string, title:?string}>
     */
    public function searchByVector(array $vector, int $limit = 50, ?int $excludePointId = null): array
    {
        $limit = max(1, min(200, $limit));
        $minScore = (float) $this->setting('ahg_discovery_image_min_score', '0.30');
        $staff = $this->viewerSeesAll();

        $hits = $this->vector->qdrantSearch($this->collection(), $vector, $staff ? $limit * 2 : min(300, $limit * 4));
        if ($hits === null) {
            return [];
        }

        $best = [];
        foreach ($hits as $h) {
            $pointId = (int) ($h['id'] ?? 0);
            $score = (float) ($h['score'] ?? 0);
            if ($pointId <= 0 || $score < $minScore || $pointId === $excludePointId) {
                continue;
            }
            $p = $h['payload'] ?? [];
            $ioId = (int) ($p['information_object_id'] ?? $p['object_id'] ?? $pointId);
            if ($ioId <= 1 || (isset($best[$ioId]) && $best[$ioId]['score'] >= $score)) {
                continue;
            }
            $best[$ioId] = [
                'object_id' => $ioId,
                'score' => round($score, 6),
                'source' => 'image',
                'slug' => $p['slug'] ?? $h['slug'] ?? null,
                'title' => $p['title'] ?? $h['title'] ?? null,
            ];
        }

        if (! $staff && $best !== []) {
            $best = array_intersect_key($best, array_flip($this->visible(array_keys($best))));
        }
        usort($best, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($best, 0, $limit);
    }

    /**
     * The record ids of $ioIds this viewer may be shown an image match for.
     *
     * @param  int[]  $ioIds
     * @return int[]
     */
    public function visible(array $ioIds): array
    {
        if ($this->viewerSeesAll()) {
            return $ioIds;
        }
        $gate = new DisclosureGate;
        $redacted = array_flip($gate->redactedIds());
        $screen = class_exists(\AhgPrivacy\Services\FileScreeningGate::class) ? app(\AhgPrivacy\Services\FileScreeningGate::class) : null;

        return array_values(array_filter($gate->filterIds($ioIds), function ($id) use ($redacted, $screen) {
            return ! isset($redacted[$id]) && ! ($screen && $screen->withholds($id));
        }));
    }

    /* -------------------------------------------------------------------- */

    protected function viewerSeesAll(): bool
    {
        $u = auth()->user();

        return $u !== null && (AclService::isAdministrator($u) || AclService::isEditor($u) || AclService::isContributor($u));
    }

    protected function collection(): string
    {
        return $this->embedder->collection();
    }

    protected function timeoutMs(): int
    {
        return max(500, (int) $this->setting('semantic_timeout_ms', '5000'));
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
