<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class FindingAidGenerateCommand extends Command
{
    protected $signature = 'ahg:finding-aid-generate
        {--slug= : Information object slug}
        {--all : Generate for all top-level descriptions}
        {--format=pdf : Output format (pdf, html, rtf)}';

    protected $description = 'Generate finding aids';

    public function handle(): int
    {
        $this->info('Generating finding aids...');
        // TODO: Implement finding aid generation
        $this->info('Finding aid generation complete.');
        return 0;
    }
}
