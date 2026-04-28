<?php

/**
 * BlendedSearchService — fuses keyword (Elasticsearch / MySQL FULLTEXT) results
 * with vector (Qdrant) results via Reciprocal Rank Fusion (RRF).
 *
 * RRF formula:  score(d) = Σ 1 / (k + rank(d))
 * The constant k smooths out very-high ranks; k=60 is standard (Cormack et al.,
 * SIGIR 2009).
 *
 * Use: caller already has a keyword-search result list (in original rank order),
 * passes it to {@see blend()} along with the user's query string. The service
 * fetches Qdrant matches for the query, fuses, returns the reordered ids.
 *
 * Graceful: if Qdrant or the embedding service is offline, returns the keyword
 * list unchanged (no crash, no degraded ranking).
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgSearch\Services;

class BlendedSearchService
{
    public const RRF_K = 60;

    public function __construct(protected VectorSearchService $vector)
    {
    }

    /**
     * Reorder $keywordIds by blending with Qdrant similarity for $query.
     *
     * @param array<int, int>  $keywordIds  IO ids in the order ES/MySQL returned them
     * @param string           $query       The user's free-text query
     * @param int              $candidatePool  How many Qdrant hits to consider (top-N)
     * @param float            $vectorWeight  How much to weight the vector branch (0..1+); 1.0 = equal
     * @param string|null      $collection  Qdrant collection name (default from settings)
     *
     * @return array{
     *   ok: bool,
     *   ids: array<int,int>,           // new ID order
     *   scores: array<int, array{rrf:float,kw_rank:?int,vec_rank:?int,vec_score:?float}>,
     *   stats: array{kw:int, vec:int, blended:int, used_vector:bool}
     * }
     */
    public function blend(array $keywordIds, string $query, int $candidatePool = 100, float $vectorWeight = 1.0, ?string $collection = null): array
    {
        $keywordIds = array_values(array_unique(array_map('intval', $keywordIds)));

        // Vector branch — pull a candidate pool ordered by similarity.
        $vectorIds = [];
        $vectorMap = []; // id => raw similarity score
        $usedVector = false;
        if (trim($query) !== '') {
            $vec = $this->vector->searchSimilar($query, $candidatePool, $collection);
            if (! empty($vec['ok']) && ! empty($vec['hits'])) {
                $usedVector = true;
                foreach ($vec['hits'] as $h) {
                    $id = (int) $h['id'];
                    if ($id <= 0) continue;
                    $vectorIds[] = $id;
                    $vectorMap[$id] = (float) ($h['score'] ?? 0.0);
                }
            }
        }

        // If vector branch is dead, return keyword-as-is — no degradation.
        if (! $usedVector) {
            $scores = [];
            foreach ($keywordIds as $rank => $id) {
                $scores[$id] = [
                    'rrf'       => $this->rrfTerm($rank),
                    'kw_rank'   => $rank,
                    'vec_rank'  => null,
                    'vec_score' => null,
                ];
            }
            return [
                'ok'     => true,
                'ids'    => $keywordIds,
                'scores' => $scores,
                'stats'  => [
                    'kw'         => count($keywordIds),
                    'vec'        => 0,
                    'blended'    => count($keywordIds),
                    'used_vector'=> false,
                ],
            ];
        }

        // RRF blend.
        $kwRank = array_flip($keywordIds);   // id => rank
        $vecRank = array_flip($vectorIds);   // id => rank

        $allIds = array_keys($kwRank + $vecRank);
        $scores = [];
        foreach ($allIds as $id) {
            $rrf = 0.0;
            $kr  = $kwRank[$id]  ?? null;
            $vr  = $vecRank[$id] ?? null;
            if ($kr !== null) $rrf += $this->rrfTerm($kr);
            if ($vr !== null) $rrf += $this->rrfTerm($vr) * $vectorWeight;
            $scores[$id] = [
                'rrf'       => $rrf,
                'kw_rank'   => $kr,
                'vec_rank'  => $vr,
                'vec_score' => $vectorMap[$id] ?? null,
            ];
        }

        // Sort by descending RRF, with kw_rank as a tiebreaker so identical-score
        // pairs preserve keyword order.
        $rankedIds = array_keys($scores);
        usort($rankedIds, function ($a, $b) use ($scores, $kwRank) {
            $cmp = $scores[$b]['rrf'] <=> $scores[$a]['rrf'];
            if ($cmp !== 0) return $cmp;
            $aKw = $kwRank[$a] ?? PHP_INT_MAX;
            $bKw = $kwRank[$b] ?? PHP_INT_MAX;
            return $aKw <=> $bKw;
        });

        return [
            'ok'     => true,
            'ids'    => array_map('intval', $rankedIds),
            'scores' => $scores,
            'stats'  => [
                'kw'          => count($keywordIds),
                'vec'         => count($vectorIds),
                'blended'     => count($rankedIds),
                'used_vector' => true,
            ],
        ];
    }

    protected function rrfTerm(int $rank): float
    {
        return 1.0 / (self::RRF_K + $rank + 1); // +1 because rank is 0-indexed
    }
}
