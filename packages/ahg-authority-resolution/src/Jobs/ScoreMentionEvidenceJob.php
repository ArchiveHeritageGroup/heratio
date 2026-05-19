<?php

/**
 * ScoreMentionEvidenceJob - Queue job for Heratio
 *
 * Task-4 async path. Dispatched by ScoreEvidenceCommand --async (or any
 * caller that wants to score evidence off the request thread). Wraps
 * EvidenceScorer::scoreAllForMention.
 *
 * Requires `php artisan queue:work` (or supervisor + queue worker) to be
 * running for jobs to flush. The default queue connection is whatever the
 * Heratio app is configured with (database / redis / sync) - this job is
 * connection-agnostic.
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

namespace AhgAuthorityResolution\Jobs;

use AhgAuthorityResolution\Services\EvidenceScorer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScoreMentionEvidenceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(public int $mentionId) {}

    public function handle(EvidenceScorer $scorer): void
    {
        try {
            $result = $scorer->scoreAllForMention($this->mentionId);
            Log::info('auth-res.score-evidence.job', [
                'mention_id' => $this->mentionId,
                'scored_count' => $result['scored_count'],
            ]);
        } catch (\Throwable $e) {
            Log::error('auth-res.score-evidence.job-failed', [
                'mention_id' => $this->mentionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
