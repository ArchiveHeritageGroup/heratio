<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class LoadDigitalObjectsCommand extends Command
{
    protected $signature = 'ahg:load-digital-objects
        {--path= : Source directory path}
        {--attach-to= : Parent information object slug}
        {--limit= : Maximum objects to load}';

    protected $description = 'Batch load digital objects';

    public function handle(): int
    {
        $this->info('Loading digital objects...');
        // TODO: Implement batch digital object loading
        $this->info('Digital object loading complete.');
        return 0;
    }
}
