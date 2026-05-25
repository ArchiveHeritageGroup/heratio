<?php

/**
 * EvidenceScorer - Service for Heratio
 *
 * Task-4 orchestrator. Given a mention or a single candidate, dispatches
 * every registered Evaluator that supports the mention's entity_type,
 * collects each evaluator's Signal, writes evidence_signals + evidence_data
 * + composite_score back to ahg_mention_candidate, and re-ranks the
 * mention's candidate list by composite_score (desc).
 *
 * Composite scoring:
 *   composite = clamp(name_similarity_score + Sum(weight(signal_i)), 0, 1)
 *   weight(match)    = +0.10
 *   weight(conflict) = -0.30
 *   weight(silent)   =  0.0
 *   weight(absent)   =  0.0
 *
 * Re-ranking: after scoring every candidate for a mention, we re-assign
 * rank_position 1..N in a transaction. Tie-break is name_similarity_score
 * desc, then candidate_display_name asc (deterministic).
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

use AhgAuthorityResolution\Services\Evidence\DocumentPriorService;
use AhgAuthorityResolution\Services\Evidence\EvaluatorInterface;
use AhgAuthorityResolution\Services\Evidence\EvidenceSignal;
use Illuminate\Support\Facades\DB;

class EvidenceScorer
{
    /** @var list<EvaluatorInterface> */
    private array $evaluators;

    /**
     * @param  iterable<EvaluatorInterface>  $evaluators
     */
    public function __construct(iterable $evaluators, private DocumentPriorService $prior)
    {
        $this->evaluators = [];
        foreach ($evaluators as $e) {
            if ($e instanceof EvaluatorInterface) {
                $this->evaluators[] = $e;
            }
        }
    }

    /**
     * Score one candidate row. Writes evidence_signals / evidence_data /
     * composite_score back into ahg_mention_candidate. Does NOT touch rank.
     *
     * @return array{
     *   candidate_id:int,
     *   signals:array<string,string>,
     *   data:array<string,array<string,mixed>>,
     *   name_similarity:float,
     *   composite:float
     * }|null  null if candidate row missing
     */
    public function scoreCandidate(int $candidateId): ?array
    {
        $candidate = DB::table('ahg_mention_candidate')->where('id', $candidateId)->first();
        if (! $candidate) {
            return null;
        }

        $mention = DB::table('ahg_mention as m')
            ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->where('m.id', $candidate->mention_id)
            ->first(['m.id', 'm.object_id', 'm.entity_type', 'm.state', 'n.entity_value']);
        if (! $mention) {
            return null;
        }

        $context = DB::table('ahg_mention_context')
            ->where('mention_id', $candidate->mention_id)
            ->first();
        if (! $context) {
            // Synthesise an empty context object so evaluators can still emit "absent".
            $context = (object) [
                'mention_id' => $candidate->mention_id,
                'co_occurring_entities' => null,
                'nearby_dates' => null,
                'nearby_places' => null,
                'role_language_tokens' => null,
                'surrounding_text_before' => null,
                'surrounding_text_after' => null,
            ];
        }

        $entityType = (string) ($mention->entity_type ?? '');
        $signals = [];
        $data = [];

        foreach ($this->evaluators as $evaluator) {
            if (! $evaluator->supports($entityType)) {
                continue;
            }
            try {
                $result = $evaluator->evaluate($mention, $context, $candidate);
            } catch (\Throwable $e) {
                $result = EvidenceSignal::make(EvidenceSignal::ABSENT, [
                    'reason' => 'evaluator_threw',
                    'exception' => $e->getMessage(),
                ]);
            }
            $dim = $evaluator->dimension();
            $signals[$dim] = (string) ($result['signal'] ?? EvidenceSignal::ABSENT);
            $data[$dim] = is_array($result['data'] ?? null) ? $result['data'] : [];
        }

        $nameSim = (float) ($candidate->name_similarity_score ?? 0.0);
        $delta = 0.0;
        foreach ($signals as $sig) {
            $delta += EvidenceSignal::WEIGHT[$sig] ?? 0.0;
        }
        $composite = max(0.0, min(1.0, $nameSim + $delta));
        $composite = round($composite, 4);

        DB::table('ahg_mention_candidate')->where('id', $candidateId)->update([
            'evidence_signals' => json_encode($signals, JSON_UNESCAPED_UNICODE),
            'evidence_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'composite_score' => $composite,
            'computed_at' => now(),
        ]);

        return [
            'candidate_id' => $candidateId,
            'signals' => $signals,
            'data' => $data,
            'name_similarity' => $nameSim,
            'composite' => $composite,
        ];
    }

    /**
     * Score every candidate for a mention, then re-rank by composite_score.
     *
     * @return array{
     *   mention_id:int,
     *   scored_count:int,
     *   results:list<array<string,mixed>>
     * }
     */
    public function scoreAllForMention(int $mentionId): array
    {
        $candidateIds = DB::table('ahg_mention_candidate')
            ->where('mention_id', $mentionId)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $results = [];
        foreach ($candidateIds as $cid) {
            $res = $this->scoreCandidate($cid);
            if ($res !== null) {
                $results[] = $res;
            }
        }

        if (! empty($results)) {
            $this->reRank($mentionId);
        }

        return [
            'mention_id' => $mentionId,
            'scored_count' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Re-assign rank_position based on (composite_score desc,
     * name_similarity_score desc, candidate_display_name asc).
     */
    private function reRank(int $mentionId): void
    {
        DB::transaction(function () use ($mentionId) {
            $rows = DB::table('ahg_mention_candidate')
                ->where('mention_id', $mentionId)
                ->orderByDesc('composite_score')
                ->orderByDesc('name_similarity_score')
                ->orderBy('candidate_display_name')
                ->get(['id']);

            $rank = 1;
            foreach ($rows as $r) {
                DB::table('ahg_mention_candidate')
                    ->where('id', $r->id)
                    ->update(['rank_position' => $rank]);
                $rank++;
            }
        });
    }
}
