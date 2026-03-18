<?php

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class AiSuggestDescriptionCommand extends Command
{
    protected $signature = 'ahg:ai-suggest-description {--object=} {--repository=} {--level=} {--empty-only} {--with-ocr} {--limit=50} {--template=} {--llm-config=} {--dry-run} {--delay=2}';
    protected $description = 'AI description suggestions';

    public function handle(): int
    {
        $this->info('Starting AI description suggestions...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
