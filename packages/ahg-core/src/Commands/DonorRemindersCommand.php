<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class DonorRemindersCommand extends Command
{
    protected $signature = 'ahg:donor-reminders
        {--dry-run : Show reminders without sending}';

    protected $description = 'Process donor agreement reminders';

    public function handle(): int
    {
        $this->info('Processing donor agreement reminders...');
        // TODO: Implement donor agreement reminder processing
        $this->info('Donor agreement reminders complete.');
        return 0;
    }
}
