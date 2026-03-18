<?php

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class LlmHealthCheckCommand extends Command
{
    protected $signature = 'ahg:llm-health';
    protected $description = 'Check LLM provider health';

    public function handle(): int
    {
        $this->info('Starting LLM provider health check...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
