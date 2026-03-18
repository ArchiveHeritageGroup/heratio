<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class HeritageInstallCommand extends Command
{
    protected $signature = 'ahg:heritage-install
        {--region= : Install specific regions (comma-separated)}
        {--all-regions : Install all available regions}';

    protected $description = 'Install heritage schema';

    public function handle(): int
    {
        $this->info('Installing heritage schema...');
        // TODO: Implement heritage schema installation
        $this->info('Heritage schema installation complete.');
        return 0;
    }
}
