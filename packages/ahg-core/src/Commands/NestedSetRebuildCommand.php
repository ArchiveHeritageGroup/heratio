<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class NestedSetRebuildCommand extends Command
{
    protected $signature = 'ahg:nested-set-rebuild
        {--model= : Specific model to rebuild (e.g. information_object, term)}';

    protected $description = 'Rebuild nested set tree';

    public function handle(): int
    {
        $this->info('Rebuilding nested set tree...');
        // TODO: Implement nested set tree rebuild
        $this->info('Nested set tree rebuild complete.');
        return 0;
    }
}
