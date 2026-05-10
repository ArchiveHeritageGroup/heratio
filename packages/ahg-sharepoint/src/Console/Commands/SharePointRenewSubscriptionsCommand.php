<?php

namespace AhgSharePoint\Console\Commands;

use Illuminate\Console\Command;

/**
 * Phase 2 — cron-driven (hourly) subscription renewal.
 */
class SharePointRenewSubscriptionsCommand extends Command
{
    protected $signature = 'sharepoint:renew-subscriptions';
    protected $description = 'Renew Graph webhook subscriptions expiring within 12h (Phase 2)';

    public function handle(): int
    {
        $this->error('Phase 2 — sharepoint:renew-subscriptions not yet shipped. See plan §6.1.');
        return self::FAILURE;
    }
}
