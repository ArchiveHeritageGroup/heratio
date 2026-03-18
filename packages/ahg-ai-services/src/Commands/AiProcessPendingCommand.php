<?php

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class AiProcessPendingCommand extends Command
{
    protected $signature = 'ahg:ai-process-pending {--limit=50} {--task-type=ner} {--dry-run}';
    protected $description = 'Process pending AI queue';

    public function handle(): int
    {
        $this->info('Starting AI pending queue processing...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
