<?php

/**
 * ScanRetryFailedCommand — Heratio ahg-scan (P6)
 *
 * Re-dispatches failed scan files whose backoff window has elapsed.
 * Registered in cron_schedule to run every 5 minutes; exits quickly when
 * there's nothing to do.
 *
 * Backoff ladder (minutes per attempt, from config/heratio.php):
 *   15, 60, 240, 1440, 4320  (≈ 15min, 1h, 4h, 24h, 72h)
 *
 * After max_attempts retries (default 5), the file is left in `failed`
 * state with `last_attempt_at` up to date but no further retries scheduled.
 * Admin operators can still hit the manual "Retry" button to try again.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Console;

use AhgScan\Jobs\ProcessScanFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScanRetryFailedCommand extends Command
{
    protected $signature = 'ahg:scan-retry-failed
        {--limit=20 : Max files to retry per run}
        {--dry-run : Print plan without dispatching retries}';

    protected $description = 'Re-dispatch failed scan files whose backoff window has elapsed';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dry = (bool) $this->option('dry-run');

        $maxAttempts = (int) config('heratio.scan.max_attempts', 5);
        $backoff = $this->parseBackoff((string) config('heratio.scan.retry_backoff_minutes', '15,60,240,1440,4320'));
        if (empty($backoff)) {
            $this->warn('No retry backoff configured — command is a no-op.');
            return self::SUCCESS;
        }

        $candidates = DB::table('ingest_file as f')
            ->join('ingest_session as s', 'f.session_id', '=', 's.id')
            ->where('s.session_kind', '!=', 'wizard')
            ->where('f.status', 'failed')
            ->whereNotNull('f.last_attempt_at')
            ->where('f.attempts', '<', $maxAttempts)
            ->orderBy('f.last_attempt_at')
            ->limit($limit * 4)  // over-fetch so the in-memory filter has room
            ->get(['f.id', 'f.attempts', 'f.last_attempt_at']);

        $retried = 0;
        $now = time();
        foreach ($candidates as $row) {
            if ($retried >= $limit) { break; }
            $attempt = max(1, (int) $row->attempts);
            $backoffMin = $backoff[min($attempt - 1, count($backoff) - 1)];
            $nextAllowed = strtotime($row->last_attempt_at) + ($backoffMin * 60);
            if ($nextAllowed > $now) {
                continue;  // still in back-off window
            }

            if ($dry) {
                $this->line("  would retry ingest_file #{$row->id} (attempt " . ($attempt + 1) . "/{$maxAttempts})");
            } else {
                ProcessScanFile::dispatch((int) $row->id, null);
                $retried++;
                $this->info("retry ingest_file #{$row->id} (attempt " . ($attempt + 1) . "/{$maxAttempts})");
            }
        }

        if ($retried === 0 && !$dry) {
            $this->line('Nothing to retry.');
        }
        return self::SUCCESS;
    }

    protected function parseBackoff(string $raw): array
    {
        $parts = array_filter(array_map('trim', explode(',', $raw)), 'strlen');
        return array_values(array_map(fn($v) => max(1, (int) $v), $parts));
    }
}
