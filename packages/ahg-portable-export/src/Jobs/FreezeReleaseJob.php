<?php

namespace AhgPortableExport\Jobs;

use AhgPortableExport\Services\PortableReleaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Copies and hashes a whole package, which outlasts any web request. */
class FreezeReleaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Below the database queue's retry_after (1900 s), so a live job is never handed out twice. */
    public int $timeout = 1800;

    /** A repeat is the archivist's decision, from the series page. */
    public int $tries = 1;

    public function __construct(public int $requestId) {}

    public function handle(PortableReleaseService $service): void
    {
        $service->runFreezeRequest($this->requestId); // records its own outcome
    }

    /** Timed out or the worker died: finally blocks did not run. */
    public function failed(?\Throwable $e = null): void
    {
        app(PortableReleaseService::class)->failFreezeRequest($this->requestId, 'The freeze ran out of time or its worker stopped. Try again.');
    }
}
