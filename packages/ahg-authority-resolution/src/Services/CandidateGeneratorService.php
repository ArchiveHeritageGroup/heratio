<?php

/**
 * CandidateGeneratorService - Service for Heratio
 *
 * Task 3 of the authority-resolution engine. Given a promoted mention
 * (ahg_mention row), asks every adapter that supports the mention's
 * entity_type for matching candidates, scores them by name similarity,
 * and persists the top-N into ahg_mention_candidate.
 *
 * Scoring is name-only at Task 3 (composite_score == name_similarity_score).
 * evidence_signals + evidence_data are populated by Task 4; both remain
 * NULL here.
 *
 * Persistence is wrapped in a transaction: existing ahg_mention_candidate
 * rows for the mention are deleted, then the top-N are re-inserted with
 * rank_position 1..N. This keeps re-runs idempotent.
 *
 * Top-N default comes from ahg_settings.authority_resolution.candidate_top_n
 * (auto-seeded to 5 by the service provider). Caller can override per-call.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgAuthorityResolution\Services;

use AhgAuthorityResolution\Services\Adapters\CandidateAdapterInterface;
use Illuminate\Support\Facades\DB;

class CandidateGeneratorService
{
    private const DEFAULT_TOP_N = 5;
    private const PER_ADAPTER_LIMIT = 50;

    /** @var list<CandidateAdapterInterface> */
    private array $adapters;

    /**
     * @param iterable<CandidateAdapterInterface> $adapters
     */
    public function __construct(iterable $adapters)
    {
        $this->adapters = [];
        foreach ($adapters as $adapter) {
            if ($adapter instanceof CandidateAdapterInterface) {
                $this->adapters[] = $adapter;
            }
        }
    }

    /**
     * Generate (and persist) ranked candidates for one mention.
     *
     * @return list<int> Inserted ahg_mention_candidate.id values, in rank order.
     */
    public function generate(int $mentionId, ?int $topN = null): array
    {
        $mention = DB::table('ahg_mention as m')
            ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->where('m.id', $mentionId)
            ->first(['m.id', 'm.entity_type', 'n.entity_value']);

        if (!$mention) {
            return [];
        }

        $entityType = (string) $mention->entity_type;
        $entityValue = (string) $mention->entity_value;
        $topN = $topN ?? $this->resolveTopN();

        // Gather raw candidates from every adapter that supports the type.
        $raw = [];
        foreach ($this->adapters as $adapter) {
            if (!$adapter->supports($entityType)) {
                continue;
            }
            $rows = $adapter->search($entityValue, $entityType, self::PER_ADAPTER_LIMIT);
            foreach ($rows as $row) {
                $raw[] = $row;
            }
        }

        // Score each raw candidate by name similarity.
        $scored = [];
        foreach ($raw as $row) {
            $displayName = (string) ($row['display_name'] ?? '');
            $scored[] = [
                'source' => (string) ($row['source'] ?? ''),
                'authority_id' => $row['authority_id'] ?? null,
                'fuseki_uri' => $row['fuseki_uri'] ?? null,
                'display_name' => $displayName,
                'score' => $this->scoreName($entityValue, $displayName),
            ];
        }

        // Sort descending by score, tie-break by display_name asc.
        usort($scored, function (array $a, array $b): int {
            if ($a['score'] === $b['score']) {
                return strcmp($a['display_name'], $b['display_name']);
            }
            return $a['score'] < $b['score'] ? 1 : -1;
        });

        $top = array_slice($scored, 0, max(0, (int) $topN));

        return DB::transaction(function () use ($mentionId, $top) {
            DB::table('ahg_mention_candidate')
                ->where('mention_id', $mentionId)
                ->delete();

            $now = now();
            $inserted = [];
            $rank = 1;
            foreach ($top as $cand) {
                $authorityId = $cand['authority_id'];
                $fusekiUri = $cand['fuseki_uri'];

                $id = DB::table('ahg_mention_candidate')->insertGetId([
                    'mention_id' => $mentionId,
                    'rank_position' => $rank,
                    'candidate_source' => $cand['source'],
                    'candidate_authority_id' => $authorityId !== null ? (int) $authorityId : null,
                    'candidate_fuseki_uri' => $fusekiUri !== null ? (string) $fusekiUri : null,
                    'candidate_display_name' => $cand['display_name'],
                    'name_similarity_score' => $cand['score'],
                    'evidence_signals' => null,
                    'evidence_data' => null,
                    'composite_score' => $cand['score'],
                    'computed_at' => $now,
                ]);
                $inserted[] = (int) $id;
                $rank++;
            }

            return $inserted;
        });
    }

    /**
     * Name-only similarity score. Spec from Task 3 brief.
     */
    private function scoreName(string $mentionValue, string $candidateDisplayName): float
    {
        $q = trim(mb_strtolower($mentionValue, 'UTF-8'));
        $c = trim(mb_strtolower($candidateDisplayName, 'UTF-8'));
        if ($q === '' || $c === '') {
            return 0.0;
        }
        similar_text($q, $c, $percent);
        $score = $percent / 100.0;
        if (strpos($c, $q) !== false) {
            $score = min(1.0, $score + 0.05);
        }
        if ($q === $c) {
            $score = 1.0;
        }
        return round($score, 4);
    }

    private function resolveTopN(): int
    {
        $row = DB::table('ahg_settings')
            ->where('setting_group', 'authority_resolution')
            ->where('setting_key', 'authority_resolution.candidate_top_n')
            ->first();

        if (!$row || $row->setting_value === null || $row->setting_value === '') {
            return self::DEFAULT_TOP_N;
        }

        $parsed = (int) $row->setting_value;
        return $parsed > 0 ? $parsed : self::DEFAULT_TOP_N;
    }
}
