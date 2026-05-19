<?php

/**
 * ReprocessCommand - Console command for Heratio
 *
 * Task 10 (CLI consolidation). Re-runs candidate generation AND evidence
 * scoring for a single mention (or every pending mention). Useful when:
 *
 *   - an adapter was fixed and you want fresh candidates for one record
 *   - a scoring weight changed and stored composite_scores are stale
 *   - operator wants to "kick" a mention that came in before the engine
 *     was fully configured
 *
 * Backed by:
 *   CandidateGeneratorService::generate()
 *   EvidenceScorer::scoreAllForMention()
 *
 * Usage:
 *   php artisan auth-res:reprocess --mention-id=24
 *   php artisan auth-res:reprocess --all-pending
 *   php artisan auth-res:reprocess --all-pending --limit=100
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

use AhgAuthorityResolution\Services\CandidateGeneratorService;
use AhgAuthorityResolution\Services\EvidenceScorer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReprocessCommand extends Command
{
    protected $signature = 'auth-res:reprocess
                            {--mention-id= : Single ahg_mention.id to reprocess}
                            {--all-pending : Reprocess every mention with state=pending}
                            {--limit=0 : Cap when used with --all-pending (0 = no cap)}';

    protected $description = 'Re-run candidate generation + evidence scoring for one mention, or every pending mention.';

    public function handle(CandidateGeneratorService $generator, EvidenceScorer $scorer): int
    {
        $mentionId = $this->option('mention-id') !== null ? (int) $this->option('mention-id') : null;
        $allPending = (bool) $this->option('all-pending');
        $limit = (int) $this->option('limit');

        if ($mentionId === null && !$allPending) {
            $this->error('Provide --mention-id=N or --all-pending.');
            return self::FAILURE;
        }

        if ($mentionId !== null) {
            return $this->reprocessOne($generator, $scorer, $mentionId);
        }

        $q = DB::table('ahg_mention')
            ->where('state', 'pending')
            ->orderBy('id')
            ->select('id');
        if ($limit > 0) {
            $q->limit($limit);
        }
        $rows = $q->get();

        if ($rows->isEmpty()) {
            $this->info('No pending mentions to reprocess.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Reprocessing %d pending mention(s)...', $rows->count()));
        $okCount = 0;
        $errCount = 0;
        foreach ($rows as $r) {
            $rc = $this->reprocessOne($generator, $scorer, (int) $r->id);
            if ($rc === self::SUCCESS) {
                $okCount++;
            } else {
                $errCount++;
            }
        }
        $this->info(sprintf('Done. %d reprocessed, %d failed.', $okCount, $errCount));
        return self::SUCCESS;
    }

    private function reprocessOne(CandidateGeneratorService $generator, EvidenceScorer $scorer, int $mentionId): int
    {
        $exists = DB::table('ahg_mention')->where('id', $mentionId)->exists();
        if (!$exists) {
            $this->error("Mention #{$mentionId} not found.");
            return self::FAILURE;
        }

        try {
            $candidateIds = $generator->generate($mentionId);
            $scoreResult = $scorer->scoreAllForMention($mentionId);
            $scoredCount = (int) ($scoreResult['scored_count'] ?? 0);
            $this->line(sprintf(
                'Mention %d: regenerated %d candidates, rescored %d.',
                $mentionId,
                count($candidateIds),
                $scoredCount
            ));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error(sprintf('Mention %d: %s', $mentionId, $e->getMessage()));
            return self::FAILURE;
        }
    }
}
