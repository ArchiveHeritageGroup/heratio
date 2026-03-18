<?php

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class AiSpellcheckCommand extends Command
{
    protected $signature = 'ahg:ai-spellcheck {--object=} {--repository=} {--all} {--limit=100} {--dry-run}';
    protected $description = 'Spellcheck records';

    public function handle(): int
    {
        $this->info('Starting AI spellcheck...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
