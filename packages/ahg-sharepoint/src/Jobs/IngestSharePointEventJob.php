<?php

namespace AhgSharePoint\Jobs;

use AhgSharePoint\Services\SharePointIngestAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Laravel queue job equivalent of `php artisan sharepoint:ingest-event --event-id=X`.
 *
 * Dispatched by SharePointWebhookController and by SharePointIngestEventCommand.
 * Mirror of AtomFramework QueueService::dispatch('sharepoint:ingest-event', ...).
 *
 * @phase 2.A
 */
class IngestSharePointEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 60;

    public function __construct(public int $eventId)
    {
    }

    public function handle(SharePointIngestAdapter $adapter): void
    {
        $adapter->ingest($this->eventId);
    }
}
