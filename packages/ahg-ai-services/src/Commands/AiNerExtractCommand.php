<?php

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class AiNerExtractCommand extends Command
{
    protected $signature = 'ahg:ai-ner {--limit=} {--unprocessed} {--batch=20}';
    protected $description = 'Extract named entities from descriptions using AI';

    public function handle(): int
    {
        $this->info('Starting AI NER extraction...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
