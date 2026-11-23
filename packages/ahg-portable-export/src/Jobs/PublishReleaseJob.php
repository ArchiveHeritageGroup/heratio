<?php

namespace AhgPortableExport\Jobs;

use AhgPortableExport\Services\PortableReleaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Runs an approved publish outside the web request: a package upload can take hours. */
class PublishReleaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 7200;

    /** A retry is the archivist's decision, from the release page. */
    public int $tries = 1;

    public function __construct(public int $releaseId) {}

    public function handle(PortableReleaseService $service): void
    {
        $service->publish($this->releaseId); // records its own failure on the release row
    }
}
