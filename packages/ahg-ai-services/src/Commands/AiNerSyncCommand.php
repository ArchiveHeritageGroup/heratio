<?php

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class AiNerSyncCommand extends Command
{
    protected $signature = 'ahg:ai-ner-sync {--export} {--retrain}';
    protected $description = 'Sync NER training data';

    public function handle(): int
    {
        $this->info('Starting NER training data sync...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
