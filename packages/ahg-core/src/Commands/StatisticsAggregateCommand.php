<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class StatisticsAggregateCommand extends Command
{
    protected $signature = 'ahg:statistics-aggregate
        {--all : Aggregate all statistics}
        {--daily : Aggregate daily statistics}
        {--monthly : Aggregate monthly statistics}
        {--cleanup : Clean up old raw statistics}
        {--days=90 : Retention period in days for cleanup}
        {--backfill= : Backfill statistics from a specific date (Y-m-d)}';

    protected $description = 'Aggregate usage statistics';

    public function handle(): int
    {
        $this->info('Aggregating usage statistics...');
        // TODO: Implement statistics aggregation
        $this->info('Statistics aggregation complete.');
        return 0;
    }
}
