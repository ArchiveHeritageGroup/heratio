<?php

namespace AhgSharePoint\Console\Commands;

use AhgSharePoint\Services\SharePointIngestAdapter;
use Illuminate\Console\Command;

/**
 * Manual / debug invocation of the per-event ingest handler.
 *
 * Production traffic flows through IngestSharePointEventJob via the queue.
 * This command exists for re-running an event by id from a shell, for tests,
 * and as a queue-handler entry point for systems that prefer artisan commands
 * over Laravel jobs.
 *
 * Mirror of sharepointIngestEventTask.class.php in the AtoM plugin.
 *
 * @phase 2.A
 */
class SharePointIngestEventCommand extends Command
{
    protected $signature = 'sharepoint:ingest-event {--event-id= : sharepoint_event.id}';
    protected $description = 'Process one inbound SharePoint webhook event';

    public function handle(SharePointIngestAdapter $adapter): int
    {
        $eventId = (int) $this->option('event-id');
        if ($eventId <= 0) {
            $this->error('--event-id=<id> required');
            return self::INVALID;
        }

        try {
            $status = $adapter->ingest($eventId);
            $this->info("event {$eventId} -> {$status}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("event {$eventId} failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
