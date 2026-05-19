<?php

/**
 * ScoreEvidenceCommand - Console command for Heratio
 *
 * Task-4 demo command. Runs EvidenceScorer against either:
 *   - a single candidate (--candidate-id=)
 *   - every candidate of one mention (mention_id positional argument)
 *   - every mention of one information_object (--object-id=)
 *
 * Persists evidence_signals + evidence_data + composite_score and re-ranks
 * the mention's candidate list. With --show, prints a per-candidate signal
 * table: one row per evaluator with the signal kind and a short data summary.
 *
 * Async path: --async dispatches ScoreMentionEvidenceJob instead of running
 * inline. Single-candidate runs ignore --async (no point spinning a queue
 * job for one row).
 *
 * Usage:
 *   php artisan auth-res:score-evidence 138
 *   php artisan auth-res:score-evidence 138 --show
 *   php artisan auth-res:score-evidence --candidate-id=7 --show
 *   php artisan auth-res:score-evidence --object-id=901990 --show
 *   php artisan auth-res:score-evidence 138 --async
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

namespace AhgAuthorityResolution\Console\Commands;

use AhgAuthorityResolution\Jobs\ScoreMentionEvidenceJob;
use AhgAuthorityResolution\Services\EvidenceScorer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScoreEvidenceCommand extends Command
{
    protected $signature = 'auth-res:score-evidence
                            {mention_id? : Single ahg_mention.id to score every candidate for}
                            {--candidate-id= : Score a single ahg_mention_candidate.id (skips re-rank)}
                            {--object-id= : Score every mention on this information_object}
                            {--show : Print a signal table per scored candidate}
                            {--async : Dispatch a ScoreMentionEvidenceJob instead of running inline (requires queue worker)}';

    protected $description = 'Compute Task-4 evidence signals + composite_score for one candidate, one mention, or every mention of an information_object.';

    public function handle(EvidenceScorer $scorer): int
    {
        $mentionId = $this->argument('mention_id') !== null ? (int) $this->argument('mention_id') : null;
        $candidateId = $this->option('candidate-id') !== null ? (int) $this->option('candidate-id') : null;
        $objectId = $this->option('object-id') !== null ? (int) $this->option('object-id') : null;
        $show = (bool) $this->option('show');
        $async = (bool) $this->option('async');

        $supplied = (int) ($mentionId !== null) + (int) ($candidateId !== null) + (int) ($objectId !== null);
        if ($supplied !== 1) {
            $this->error('Provide exactly one of: mention_id arg, --candidate-id=, --object-id=.');
            return self::FAILURE;
        }

        if ($candidateId !== null) {
            return $this->handleCandidate($scorer, $candidateId, $show);
        }
        if ($objectId !== null) {
            return $this->handleObject($scorer, $objectId, $show, $async);
        }
        return $this->handleMention($scorer, (int) $mentionId, $show, $async);
    }

    private function handleCandidate(EvidenceScorer $scorer, int $candidateId, bool $show): int
    {
        $result = $scorer->scoreCandidate($candidateId);
        if ($result === null) {
            $this->error("Candidate #{$candidateId} not found.");
            return self::FAILURE;
        }
        $this->info(sprintf(
            'Candidate #%d scored: composite=%s (name=%s, delta=%s)',
            $candidateId,
            number_format($result['composite'], 4),
            number_format($result['name_similarity'], 4),
            number_format($result['composite'] - $result['name_similarity'], 4)
        ));
        if ($show) {
            $this->printSignalTable([$result]);
        }
        return self::SUCCESS;
    }

    private function handleMention(EvidenceScorer $scorer, int $mentionId, bool $show, bool $async): int
    {
        $mention = DB::table('ahg_mention as m')
            ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->where('m.id', $mentionId)
            ->first(['m.id', 'm.object_id', 'm.entity_type', 'n.entity_value']);
        if (!$mention) {
            $this->error("Mention #{$mentionId} not found.");
            return self::FAILURE;
        }

        $before = $this->snapshotRanks($mentionId);

        if ($async) {
            ScoreMentionEvidenceJob::dispatch($mentionId);
            $this->info(sprintf(
                'Mention #%d [%s] "%s": ScoreMentionEvidenceJob dispatched.',
                $mention->id,
                $mention->entity_type,
                $mention->entity_value
            ));
            $this->warn('Reminder: queue worker (`php artisan queue:work`) must be running for async jobs to flush.');
            return self::SUCCESS;
        }

        $result = $scorer->scoreAllForMention($mentionId);
        $this->info(sprintf(
            'Mention #%d [%s] "%s" (object %d): %d candidate(s) scored.',
            $mention->id,
            $mention->entity_type,
            $mention->entity_value,
            $mention->object_id,
            $result['scored_count']
        ));

        if ($result['scored_count'] === 0) {
            $this->warn('No candidates exist for this mention. Run auth-res:generate-candidates first.');
            return self::SUCCESS;
        }

        $after = $this->snapshotRanks($mentionId);
        $this->printRankChange($before, $after);

        if ($show) {
            $this->printSignalTable($result['results']);
        }
        return self::SUCCESS;
    }

    private function handleObject(EvidenceScorer $scorer, int $objectId, bool $show, bool $async): int
    {
        $mentionIds = DB::table('ahg_mention_candidate as c')
            ->join('ahg_mention as m', 'm.id', '=', 'c.mention_id')
            ->where('m.object_id', $objectId)
            ->distinct()
            ->pluck('c.mention_id')
            ->map(fn($v) => (int) $v)
            ->all();

        if (empty($mentionIds)) {
            $this->error("No mentions with candidates found for object_id={$objectId}.");
            return self::FAILURE;
        }

        $this->info(sprintf('Object %d: scoring %d mention(s)...', $objectId, count($mentionIds)));
        $totalScored = 0;
        foreach ($mentionIds as $mid) {
            if ($async) {
                ScoreMentionEvidenceJob::dispatch($mid);
                $this->line("  mention #{$mid}: job dispatched.");
                continue;
            }
            $r = $scorer->scoreAllForMention($mid);
            $totalScored += $r['scored_count'];
            if ($show) {
                $mention = DB::table('ahg_mention as m')
                    ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
                    ->where('m.id', $mid)
                    ->first(['m.entity_type', 'n.entity_value']);
                $this->line(sprintf(
                    "  mention #%d [%s] \"%s\": %d scored",
                    $mid,
                    $mention->entity_type ?? '?',
                    $mention->entity_value ?? '?',
                    $r['scored_count']
                ));
                $this->printSignalTable($r['results']);
            }
        }
        $this->info($async ? 'All jobs dispatched.' : "Done. {$totalScored} candidate(s) scored across " . count($mentionIds) . ' mention(s).');
        return self::SUCCESS;
    }

    /**
     * @return array<int,int>  candidate_id => rank_position
     */
    private function snapshotRanks(int $mentionId): array
    {
        $rows = DB::table('ahg_mention_candidate')
            ->where('mention_id', $mentionId)
            ->get(['id', 'rank_position']);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = (int) $r->rank_position;
        }
        return $out;
    }

    /**
     * @param array<int,int> $before
     * @param array<int,int> $after
     */
    private function printRankChange(array $before, array $after): void
    {
        $changes = [];
        foreach ($after as $cid => $newRank) {
            $old = $before[$cid] ?? null;
            if ($old === null || $old === $newRank) {
                continue;
            }
            $changes[] = sprintf('  cand #%d: rank %d -> %d', $cid, $old, $newRank);
        }
        if (!empty($changes)) {
            $this->line('Rank changes:');
            foreach ($changes as $c) {
                $this->line($c);
            }
        } else {
            $this->line('Rank changes: none.');
        }
    }

    /**
     * @param list<array<string,mixed>> $results
     */
    private function printSignalTable(array $results): void
    {
        foreach ($results as $res) {
            $cid = $res['candidate_id'] ?? 0;
            $row = DB::table('ahg_mention_candidate')->where('id', $cid)->first([
                'mention_id', 'rank_position', 'candidate_source',
                'candidate_authority_id', 'candidate_display_name',
                'name_similarity_score', 'composite_score',
            ]);
            if (!$row) {
                continue;
            }
            $this->newLine();
            $this->line(sprintf(
                'cand #%d  rank=%d  src=%s  auth=%s  name=%s  sim=%s  composite=%s',
                $cid,
                (int) $row->rank_position,
                $row->candidate_source,
                $row->candidate_authority_id ?? '-',
                $row->candidate_display_name,
                number_format((float) $row->name_similarity_score, 4),
                number_format((float) $row->composite_score, 4)
            ));
            $signals = is_array($res['signals'] ?? null) ? $res['signals'] : [];
            $data = is_array($res['data'] ?? null) ? $res['data'] : [];
            if (empty($signals)) {
                $this->line('    (no evaluators ran)');
                continue;
            }
            foreach ($signals as $dim => $sig) {
                $summary = $this->summariseData($data[$dim] ?? []);
                $this->line(sprintf('    %-16s %-9s %s', $dim, $sig, $summary));
            }
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function summariseData(array $data): string
    {
        if (empty($data)) {
            return '';
        }
        // Limit to the most informative top-level keys; collapse arrays
        $pairs = [];
        $count = 0;
        foreach ($data as $k => $v) {
            if ($count >= 3) {
                break;
            }
            if (is_array($v)) {
                $pairs[] = $k . '=[' . count($v) . ']';
            } elseif (is_scalar($v) || $v === null) {
                $s = (string) $v;
                if (mb_strlen($s) > 50) {
                    $s = mb_substr($s, 0, 47) . '...';
                }
                $pairs[] = $k . '=' . $s;
            }
            $count++;
        }
        return implode('  ', $pairs);
    }
}
