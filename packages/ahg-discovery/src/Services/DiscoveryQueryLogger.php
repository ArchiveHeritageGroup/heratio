<?php

/**
 * DiscoveryQueryLogger — captures per-query telemetry for the Discovery
 * pipeline into ahg_discovery_log so ablation analysis is essentially free.
 *
 * Per query the logger records:
 *   strategy_breakdown JSON  — {vector:{hits, ms, top_ids[]}, keyword:{...}, entity:{...}, ...}
 *   pre_merge_ranks    JSON  — {keyword:[ids], vector:[ids], ...} ordered as each strategy returned
 *   post_merge_ranks   JSON  — final user-facing order after merge+enrich
 *   response_ms              — total wall time
 *   expanded_terms     text  — query expansion as a JSON-encoded array
 *   keywords           JSON  — original + expansion words
 *
 * Then on click-through ({@see logClick()}) it sets clicked_object + clicked_at.
 * Dwell time is set client-side via a follow-up beacon on page-leave.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgDiscovery\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiscoveryQueryLogger
{
    /**
     * Record a completed query. Returns the new log row id (so the caller can
     * stash it in the session for click-through correlation).
     *
     * @param array{
     *   query: string,
     *   user_id?: ?int,
     *   session_id?: ?string,
     *   expanded?: array,
     *   keywords?: array,
     *   strategy_results?: array<string, array{hits:array, ms:int}>,
     *   merged_ids?: array,
     *   final_ids?: array,
     *   response_ms?: int,
     * } $payload
     */
    public function logQuery(array $payload): ?int
    {
        try {
            $strategyBreakdown = [];
            $preMergeRanks = [];
            foreach (($payload['strategy_results'] ?? []) as $name => $info) {
                $hits = $info['hits'] ?? [];
                $ms   = (int) ($info['ms'] ?? 0);
                $ids  = array_map(fn($h) => (int) ($h['object_id'] ?? $h['id'] ?? 0), $hits);
                $strategyBreakdown[$name] = [
                    'hits'    => count($hits),
                    'ms'      => $ms,
                    'top_ids' => array_slice($ids, 0, 50),
                ];
                $preMergeRanks[$name] = array_slice($ids, 0, 100);
            }

            $row = [
                'user_id'           => $payload['user_id'] ?? null,
                'session_id'        => $payload['session_id'] ?? null,
                'query_text'        => mb_substr((string) ($payload['query'] ?? ''), 0, 8000),
                'expanded_terms'    => isset($payload['expanded']) ? json_encode($payload['expanded']) : null,
                'keywords'          => isset($payload['keywords']) ? json_encode($payload['keywords']) : null,
                'result_count'      => count($payload['final_ids'] ?? $payload['merged_ids'] ?? []),
                'response_ms'       => (int) ($payload['response_ms'] ?? 0),
                'strategy_breakdown'=> $strategyBreakdown ? json_encode($strategyBreakdown) : null,
                'pre_merge_ranks'   => $preMergeRanks ? json_encode($preMergeRanks) : null,
                'post_merge_ranks'  => isset($payload['final_ids'])
                    ? json_encode(array_slice(array_map('intval', $payload['final_ids']), 0, 200))
                    : null,
            ];
            $id = DB::table('ahg_discovery_log')->insertGetId($row);
            return (int) $id;
        } catch (Throwable $e) {
            Log::warning('Discovery: failed to log query: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Record a click on a result. Caller passes the log_id (from logQuery)
     * so we attach to the right row; falls back to the most recent log row
     * for the same session_id when log_id is unknown.
     */
    public function logClick(?int $logId, int $clickedObjectId, ?string $sessionId = null): bool
    {
        try {
            $q = DB::table('ahg_discovery_log');
            if ($logId !== null && $logId > 0) {
                $q->where('id', $logId);
            } elseif ($sessionId) {
                $q->where('session_id', $sessionId)
                  ->whereNull('clicked_object')
                  ->orderByDesc('id')
                  ->limit(1);
            } else {
                return false;
            }
            $q->update([
                'clicked_object' => $clickedObjectId,
                'clicked_at'     => now(),
            ]);
            return true;
        } catch (Throwable $e) {
            Log::warning('Discovery: failed to log click: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Record dwell-time signal. Best-effort; called from a page-leave beacon.
     */
    public function logDwell(int $logId, int $dwellMs): bool
    {
        try {
            DB::table('ahg_discovery_log')
                ->where('id', $logId)
                ->update(['dwell_ms' => max(0, min(3600000, $dwellMs))]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
