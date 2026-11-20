<?php

/**
 * BuildPublishPackageJob - makes one platform publish package off the web
 * request (heratio#1761). Queued when the clip is longer than
 * publish_queue_seconds; shorter clips are made in the request.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgMediaStreaming\Jobs;

use AhgMediaStreaming\Services\PublishPackageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class BuildPublishPackageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** A long clip re-encoded at 1080p on the CPU takes minutes. */
    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $packageId, public ?int $userId = null)
    {
    }

    public function handle(PublishPackageService $service): void
    {
        if ($this->userId && ! Auth::check()) {
            try {
                Auth::onceUsingId($this->userId); // so the audit row names who asked
            } catch (\Throwable) {
                // the audit row is written without a user name
            }
        }
        $service->build($this->packageId);
    }
}
