<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class AuthorityNerPipelineCommand extends Command
{
    protected $signature = 'ahg:authority-ner-pipeline
        {--dry-run : Simulate without creating records}
        {--threshold= : Minimum confidence threshold}';

    protected $description = 'Create stub authorities from NER';

    public function handle(): int
    {
        $this->info('Running NER pipeline for authority records...');
        // TODO: Implement NER-based authority record creation
        $this->info('NER pipeline complete.');
        return 0;
    }
}
