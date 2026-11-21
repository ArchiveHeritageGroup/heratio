<?php

/**
 * RunLiveIngestJob - runs the live ingest worker for one event (heratio#1743).
 *
 * Queued by "Start" on the event page, on the 'live' connection (config/queue.php),
 * whose retry_after outlasts the longest event so the database queue never
 * hands the still-running job to a second worker. It needs its own worker:
 *   php artisan queue:work live --queue=live --timeout=0 --tries=1
 * The job blocks for the whole event; $timeout is the wait for the encoder plus
 * the longest event plus a margin, so a hung run is still killed in the end.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgMediaStreaming\Jobs;

use AhgMediaStreaming\Services\LiveStreamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RunLiveIngestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public int $eventId, public ?int $userId = null)
    {
        $svc = app(LiveStreamService::class);
        $this->timeout = $svc->waitSeconds() + $svc->maxSeconds() + 900;
    }

    public function handle(LiveStreamService $service): void
    {
        if ($this->userId && ! Auth::check()) {
            try {
                Auth::onceUsingId($this->userId); // so the audit rows and the digital object name who started it
            } catch (\Throwable) {
            }
        }
        try {
            $service->run($this->eventId, null, fn (string $m) => Log::info('[live] '.$m));
        } catch (\RuntimeException $e) {
            // Refused (port in use, already running, no ffmpeg): say so on the event page.
            Log::warning('[live] event '.$this->eventId.': '.$e->getMessage());
            DB::table(LiveStreamService::TABLE)->where('id', $this->eventId)
                ->update(['error' => $e->getMessage(), 'start_requested_at' => null, 'updated_at' => now()]);
        }
    }
}
