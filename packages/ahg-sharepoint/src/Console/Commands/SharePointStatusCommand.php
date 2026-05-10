<?php

namespace AhgSharePoint\Console\Commands;

use Illuminate\Console\Command;

class SharePointStatusCommand extends Command
{
    protected $signature = 'sharepoint:status';
    protected $description = 'Print SharePoint integration health (tenants, drives, subs, queue depth)';

    public function handle(): int
    {
        // TODO (Phase 1): tenant table, drive table, sync_state table.
        // (Phase 2): subscription expiry countdown, event status counts, queue depth.

        $this->error('sharepoint:status not implemented yet');
        return self::FAILURE;
    }
}
