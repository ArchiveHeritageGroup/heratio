<?php

namespace AhgSharePoint\Console\Commands;

use Illuminate\Console\Command;

/**
 * Phase 2 — fails until subscription lifecycle ships.
 */
class SharePointSubscribeCommand extends Command
{
    protected $signature = 'sharepoint:subscribe {--drive= : sharepoint_drive.id}';
    protected $description = 'Create Graph webhook subscription for a drive (Phase 2)';

    public function handle(): int
    {
        $this->error('Phase 2 — sharepoint:subscribe not yet shipped. See plan §6.1.');
        return self::FAILURE;
    }
}
