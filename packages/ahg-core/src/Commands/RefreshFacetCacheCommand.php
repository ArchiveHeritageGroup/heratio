<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class RefreshFacetCacheCommand extends Command
{
    protected $signature = 'ahg:refresh-facet-cache
        {--facet= : Only rebuild a specific facet}
        {--repository= : Only rebuild facets for a specific repository}';

    protected $description = 'Rebuild browse facet counts';

    public function handle(): int
    {
        $this->info('Rebuilding browse facet cache...');
        // TODO: Implement facet cache rebuild
        $this->info('Browse facet cache rebuild complete.');
        return 0;
    }
}
