<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class OaiHarvestCommand extends Command
{
    protected $signature = 'ahg:oai-harvest
        {--url= : OAI-PMH endpoint URL}
        {--set= : OAI set identifier}
        {--from= : Harvest records from date (YYYY-MM-DD)}
        {--until= : Harvest records until date (YYYY-MM-DD)}';

    protected $description = 'Harvest OAI-PMH records';

    public function handle(): int
    {
        $this->info('Harvesting OAI-PMH records...');
        // TODO: Implement OAI-PMH harvesting
        $this->info('OAI-PMH harvest complete.');
        return 0;
    }
}
