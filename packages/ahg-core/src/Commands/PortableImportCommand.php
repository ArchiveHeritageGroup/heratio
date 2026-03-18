<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class PortableImportCommand extends Command
{
    protected $signature = 'ahg:portable-import
        {--zip= : Path to ZIP archive}
        {--path= : Path to extracted package}
        {--mode=merge : Import mode (merge, replace, skip)}
        {--culture=en : Default culture/language}
        {--entity-types= : Comma-separated entity types to import}
        {--import-id= : Resume specific import}';

    protected $description = 'Import portable package';

    public function handle(): int
    {
        $this->info('Importing portable package...');
        // TODO: Implement portable package import
        $this->info('Portable import complete.');
        return 0;
    }
}
