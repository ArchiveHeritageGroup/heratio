<?php

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class AiConditionStatusCommand extends Command
{
    protected $signature = 'ahg:ai-condition-status';
    protected $description = 'Check AI condition service health';

    public function handle(): int
    {
        $this->info('Starting AI condition service health check...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
