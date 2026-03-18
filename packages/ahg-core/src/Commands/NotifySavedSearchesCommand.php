<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class NotifySavedSearchesCommand extends Command
{
    protected $signature = 'ahg:notify-saved-searches
        {--frequency=daily : Notification frequency (daily, weekly, monthly)}';

    protected $description = 'Email saved search notifications';

    public function handle(): int
    {
        $this->info('Processing saved search notifications...');
        // TODO: Implement saved search notification emails
        $this->info('Saved search notifications complete.');
        return 0;
    }
}
