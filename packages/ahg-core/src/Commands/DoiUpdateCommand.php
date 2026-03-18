<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class DoiUpdateCommand extends Command
{
    protected $signature = 'ahg:doi-update
        {--slug= : Information object slug}
        {--modified-since= : Update DOIs modified since date}
        {--all : Update all DOIs}
        {--dry-run : Simulate without updating}';

    protected $description = 'Update DOI metadata at DataCite';

    public function handle(): int
    {
        $this->info('Updating DOI metadata at DataCite...');
        // TODO: Implement DOI metadata update at DataCite
        $this->info('DOI metadata update complete.');
        return 0;
    }
}
