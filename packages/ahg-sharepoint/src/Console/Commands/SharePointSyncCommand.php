<?php

namespace AhgSharePoint\Console\Commands;

use Illuminate\Console\Command;

class SharePointSyncCommand extends Command
{
    protected $signature = 'sharepoint:sync {--drive= : sharepoint_drive.id (omit to sync all ingest-enabled)} {--full : Discard delta cursor} {--limit=0}';
    protected $description = 'Delta-poll one or all ingest-enabled SharePoint drives';

    public function handle(): int
    {
        // TODO (Phase 1):
        //   1. Resolve drive list (one or all ingest_enabled).
        //   2. For each drive: read sharepoint_sync_state.delta_link, fetch delta page,
        //      iterate items, hand to SharePointIngestAdapter, persist returned deltaLink.
        //   3. Update last_run_at, last_status, items_processed.

        $this->error('sharepoint:sync not implemented yet');
        return self::FAILURE;
    }
}
