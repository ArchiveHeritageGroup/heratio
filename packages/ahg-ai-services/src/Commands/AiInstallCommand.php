<?php

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class AiInstallCommand extends Command
{
    protected $signature = 'ahg:ai-install';
    protected $description = 'Create/update AI plugin database tables';

    public function handle(): int
    {
        $this->info('Starting AI plugin installation...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
