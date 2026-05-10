<?php

namespace AhgSharePoint\Console\Commands;

use AhgSharePoint\Services\SharePointSubscriptionService;
use Illuminate\Console\Command;

/**
 * Cron-driven (hourly) subscription renewal.
 *
 * Mirror of sharepointRenewSubscriptionsTask.class.php in the AtoM plugin.
 *
 * @phase 2.A
 */
class SharePointRenewSubscriptionsCommand extends Command
{
    protected $signature = 'sharepoint:renew-subscriptions';
    protected $description = 'Renew Graph webhook subscriptions expiring within 12h';

    public function handle(SharePointSubscriptionService $svc): int
    {
        $result = $svc->renewExpiring();
        $this->info("renewed={$result['renewed']} errors={$result['errors']}");
        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
