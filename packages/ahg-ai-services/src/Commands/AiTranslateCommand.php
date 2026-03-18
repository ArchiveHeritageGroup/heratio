<?php

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class AiTranslateCommand extends Command
{
    protected $signature = 'ahg:ai-translate {--from=} {--to=} {--limit=}';
    protected $description = 'Auto-translate record fields';

    public function handle(): int
    {
        $this->info('Starting AI translation...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
