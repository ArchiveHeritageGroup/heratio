<?php

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class AiSummarizeCommand extends Command
{
    protected $signature = 'ahg:ai-summarize {--all-empty} {--field=scope_and_content} {--object=} {--repository=} {--limit=} {--dry-run}';
    protected $description = 'OCR transcript to scope summary';

    public function handle(): int
    {
        $this->info('Starting AI summarization...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
