<?php

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class AiHtrCommand extends Command
{
    protected $signature = 'ahg:ai-htr {--object=} {--repository=} {--all} {--limit=100} {--mode=all} {--no-zones} {--overwrite} {--dry-run}';
    protected $description = 'Handwritten text recognition';

    public function handle(): int
    {
        $this->info('Starting AI handwritten text recognition...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
