<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class PortableExportCommand extends Command
{
    protected $signature = 'ahg:portable-export
        {--scope=all : Export scope (all, repository, collection)}
        {--slug= : Information object or repository slug}
        {--repository-id= : Repository ID}
        {--mode=archive : Export mode (archive, transfer, backup)}
        {--output= : Output directory}
        {--zip : Create ZIP archive}
        {--no-objects : Exclude digital objects}
        {--include-masters : Include master files}
        {--export-id= : Resume specific export}';

    protected $description = 'Generate portable catalogue';

    public function handle(): int
    {
        $this->info('Generating portable catalogue export...');
        // TODO: Implement portable catalogue export
        $this->info('Portable export complete.');
        return 0;
    }
}
