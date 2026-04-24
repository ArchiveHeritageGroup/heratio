<?php

/**
 * IngestCommitJob — Heratio ingest (queued commit)
 *
 * Queue-backed variant of IngestCommitRunner::run(). The controller
 * dispatches one of these when an ingest_session has more than
 * heratio.ingest.queue_threshold (default 500) rows, so large batches
 * don't block the web request thread.
 *
 * Progress tracking is unchanged — the existing commit.blade.php polling
 * already reads ingest_job rows every 3 seconds, so nothing on the UI
 * side needs to change.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgIngest\Jobs;

use AhgIngest\Services\IngestCommitRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IngestCommitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $sessionId;
    public int $tries = 1;          // don't auto-retry; failures go on the job row
    public int $timeout = 3600;     // 1 hour max — large batches + packaging can take a while

    public function __construct(int $sessionId)
    {
        $this->sessionId = $sessionId;
    }

    public function handle(IngestCommitRunner $runner): void
    {
        try {
            $runner->run($this->sessionId);
        } catch (\Throwable $e) {
            Log::error('[ahg-ingest] queued commit failed for session ' . $this->sessionId . ': ' . $e->getMessage());
            // Stamp the most recent ingest_job row as failed so the UI
            // stops polling "running" forever.
            $jobId = DB::table('ingest_job')
                ->where('session_id', $this->sessionId)
                ->orderByDesc('id')
                ->value('id');
            if ($jobId) {
                DB::table('ingest_job')->where('id', $jobId)->update([
                    'status' => 'failed',
                    'error_log' => json_encode([['stage' => 'queue', 'error' => $e->getMessage()]]),
                    'completed_at' => now(),
                ]);
            }
            throw $e;
        }
    }
}
