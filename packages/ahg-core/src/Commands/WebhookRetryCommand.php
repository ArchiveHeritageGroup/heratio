<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class WebhookRetryCommand extends Command
{
    protected $signature = 'ahg:webhook-retry
        {--limit= : Limit number of webhooks to retry}
        {--max-attempts=5 : Maximum retry attempts per webhook}';

    protected $description = 'Retry failed webhook deliveries';

    public function handle(): int
    {
        $this->info('Retrying failed webhook deliveries...');
        // TODO: Implement failed webhook retry
        $this->info('Webhook retry complete.');
        return 0;
    }
}
