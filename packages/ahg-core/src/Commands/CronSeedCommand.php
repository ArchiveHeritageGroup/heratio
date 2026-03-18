<?php

namespace AhgCore\Commands;

use AhgCore\Services\CronSchedulerService;
use Illuminate\Console\Command;

class CronSeedCommand extends Command
{
    protected $signature = 'ahg:cron-seed
        {--reset : Overwrite all schedules to defaults}';

    protected $description = 'Seed the cron_schedule table with default entries';

    public function handle(CronSchedulerService $service): int
    {
        $reset = $this->option('reset');

        if ($reset) {
            $this->warn('Resetting all schedules to defaults...');
        }

        $count = $service->seedDefaults($reset);

        $this->info("Seeded {$count} schedule entries.");
        return self::SUCCESS;
    }
}
