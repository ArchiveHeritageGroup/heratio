<?php

/**
 * ReprocessParkedCommand - Console command for Heratio
 *
 * Task 10 (CLI consolidation). Bulk re-review of every parked mention parked
 * after a given date (or all parked mentions if --since is omitted). For each
 * parked row this calls ParkQueueService::unparkAndRereview() which:
 *
 *   1. deletes the ahg_mention_park row
 *   2. flips ahg_mention.state back to 'pending'
 *   3. re-runs candidate generation + evidence scoring
 *
 * After the loop, we count how many newly-pending mentions now have at least
 * one ahg_mention_candidate row (i.e. "new candidate available" since parking).
 * Use this when external authority sources (VIAF/Wikidata/...) have been
 * updated and you want to flush the park queue against the new state.
 *
 * Usage:
 *   php artisan auth-res:reprocess-parked
 *   php artisan auth-res:reprocess-parked --since=2026-05-01
 *   php artisan auth-res:reprocess-parked --since=2026-05-01 --limit=50
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

use AhgAuthorityResolution\Services\ParkQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReprocessParkedCommand extends Command
{
    protected $signature = 'auth-res:reprocess-parked
                            {--since= : Re-review every mention parked on/after YYYY-MM-DD (omit for all parked)}
                            {--limit=0 : Cap the number of parked rows processed (0 = no cap)}
                            {--user-id=0 : archivist_user_id to attribute the unpark to (0 = system)}';

    protected $description = 'Bulk re-review every parked mention; emit a count of newly-flagged new_candidate_available mentions.';

    public function handle(ParkQueueService $parkService): int
    {
        $since = $this->option('since');
        $limit = (int) $this->option('limit');
        $userId = (int) $this->option('user-id');

        if ($since !== null && $since !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $since)) {
            $this->error('--since must be YYYY-MM-DD.');

            return self::FAILURE;
        }

        $q = DB::table('ahg_mention_park')
            ->select('mention_id', 'parked_at', 'reason')
            ->orderBy('parked_at');
        if ($since !== null && $since !== '') {
            $q->where('parked_at', '>=', $since.' 00:00:00');
        }
        if ($limit > 0) {
            $q->limit($limit);
        }
        $rows = $q->get();

        if ($rows->isEmpty()) {
            $sinceLabel = $since !== null && $since !== '' ? "since {$since}" : '(all)';
            $this->info("No parked mentions found {$sinceLabel}.");

            return self::SUCCESS;
        }

        $sinceLabel = $since !== null && $since !== '' ? "parked >= {$since}" : 'all parked';
        $this->info(sprintf('Re-reviewing %d parked mention(s) (%s)...', $rows->count(), $sinceLabel));

        $okCount = 0;
        $errCount = 0;
        $newCandidateFlagged = 0;

        foreach ($rows as $r) {
            $mentionId = (int) $r->mention_id;
            try {
                $result = $parkService->unparkAndRereview($mentionId, $userId);
                $candidateCount = count($result['candidate_ids'] ?? []);
                if ($candidateCount > 0) {
                    $newCandidateFlagged++;
                }
                $okCount++;
                $this->line(sprintf(
                    '  mention %d: unparked, %d candidates, %d scored.',
                    $mentionId,
                    $candidateCount,
                    (int) ($result['scored_count'] ?? 0)
                ));
            } catch (\Throwable $e) {
                $errCount++;
                $this->error(sprintf('  mention %d: %s', $mentionId, $e->getMessage()));
            }
        }

        $this->info(sprintf(
            'Done. %d re-reviewed, %d failed, %d now have at least one candidate (new_candidate_available).',
            $okCount,
            $errCount,
            $newCandidateFlagged
        ));

        return self::SUCCESS;
    }
}
