<?php

/**
 * HistoryRerankService - opt-in personalised re-ranking of a result page.
 *
 * Boosts results whose title / snippet overlaps with the terms the
 * authenticated user has searched for recently (last N rows of
 * ahg_search_query_log). Pure in-memory, stable reorder of a single page of
 * results - it never re-queries Elasticsearch and never changes the total
 * count, so it is cheap and safe to apply per request.
 *
 * Scoring: each result keeps its original ordinal position (lower = better).
 * A boost is subtracted from that position for every recent-query term the
 * result matches, weighted by term recency. Ties fall back to the original
 * order (stable sort).
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgSemanticSearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HistoryRerankService
{
    /** How many recent query rows to mine for a user. */
    protected int $historyDepth = 25;

    /** Boost (in ordinal positions) applied per matched term. */
    protected float $perTermBoost = 1.5;

    /** Stop words excluded from the user's interest profile. */
    protected array $stopWords = [
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
        'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'be',
    ];

    /**
     * Re-rank a single page of discovery results for a user.
     *
     * @param array $results Result rows (as returned by ElasticsearchService::transformIoHits)
     * @param int   $userId  Authenticated user id
     * @return array         Re-ordered results (same elements, possibly reordered)
     */
    public function rerank(array $results, int $userId): array
    {
        if (count($results) < 2 || $userId <= 0) {
            return $results;
        }

        $profile = $this->buildInterestProfile($userId);
        if (empty($profile)) {
            return $results; // nothing to personalise on
        }

        // Decorate each result with its original index + computed score.
        $decorated = [];
        foreach ($results as $i => $row) {
            $haystack = mb_strtolower(
                ($row['title'] ?? '').' '.
                ($row['snippet'] ?? '').' '.
                ($row['identifier'] ?? '')
            );

            $boost = 0.0;
            foreach ($profile as $term => $weight) {
                if ($term !== '' && str_contains($haystack, $term)) {
                    $boost += $this->perTermBoost * $weight;
                }
            }

            // Lower effective position sorts first. Original index is the
            // baseline; boost pulls a matched result forward.
            $decorated[] = [
                'orig'  => $i,
                'score' => $i - $boost,
                'row'   => $row,
            ];
        }

        // Stable sort: primary by score asc, tie-break by original index.
        usort($decorated, function ($a, $b) {
            return $a['score'] <=> $b['score']
                ?: $a['orig'] <=> $b['orig'];
        });

        return array_map(fn ($d) => $d['row'], $decorated);
    }

    /**
     * Build a term -> recency-weight map from the user's recent searches.
     * More-recent queries weigh slightly more (linear decay over the window).
     *
     * @return array<string,float>
     */
    protected function buildInterestProfile(int $userId): array
    {
        try {
            if (! Schema::hasTable('ahg_search_query_log')) {
                return [];
            }

            $rows = DB::table('ahg_search_query_log')
                ->where('user_id', $userId)
                ->orderByDesc('executed_at')
                ->limit($this->historyDepth)
                ->pluck('query')
                ->all();
        } catch (\Throwable $e) {
            Log::debug('[history-rerank] profile build failed: '.$e->getMessage());

            return [];
        }

        if (empty($rows)) {
            return [];
        }

        $profile = [];
        $total = count($rows);
        foreach ($rows as $idx => $query) {
            // Recency weight 1.0 (newest) .. ~0.3 (oldest in window).
            $recency = 1.0 - (0.7 * ($idx / max(1, $total)));
            foreach ($this->tokenize((string) $query) as $term) {
                $profile[$term] = ($profile[$term] ?? 0.0) + $recency;
            }
        }

        // Cap influence of any single term.
        foreach ($profile as $k => $v) {
            $profile[$k] = min(3.0, $v);
        }

        return $profile;
    }

    /**
     * @return array<int,string>
     */
    protected function tokenize(string $query): array
    {
        $query = mb_strtolower($query);
        $query = preg_replace('/[^\w\s]/u', ' ', $query);
        $words = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $words,
            fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, $this->stopWords, true)
        ));
    }
}
