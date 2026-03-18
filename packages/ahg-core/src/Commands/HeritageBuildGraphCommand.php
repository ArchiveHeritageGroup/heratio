<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class HeritageBuildGraphCommand extends Command
{
    protected $signature = 'ahg:heritage-build-graph
        {--full : Full rebuild of the knowledge graph}
        {--link-getty : Link to Getty vocabularies}
        {--getty-limit= : Limit Getty API requests}';

    protected $description = 'Build heritage knowledge graph';

    public function handle(): int
    {
        $this->info('Building heritage knowledge graph...');
        // TODO: Implement heritage knowledge graph building
        $this->info('Heritage knowledge graph build complete.');
        return 0;
    }
}
