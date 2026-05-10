<?php

namespace AhgSharePoint\Console\Commands;

use Illuminate\Console\Command;

/**
 * Phase 2 — queue handler for sharepoint_event rows.
 */
class SharePointIngestEventCommand extends Command
{
    protected $signature = 'sharepoint:ingest-event {--event-id= : sharepoint_event.id}';
    protected $description = 'Process one inbound SharePoint webhook event (Phase 2)';

    public function handle(): int
    {
        $this->error('Phase 2 — sharepoint:ingest-event not yet shipped. See plan §6.3.');
        return self::FAILURE;
    }
}
