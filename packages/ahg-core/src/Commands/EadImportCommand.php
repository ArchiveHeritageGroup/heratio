<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class EadImportCommand extends Command
{
    protected $signature = 'ahg:ead-import
        {--source= : Source file or directory path}
        {--schema=ead : Schema type (ead, ead3)}
        {--output= : Output report path}';

    protected $description = 'Bulk EAD/XML import';

    public function handle(): int
    {
        $this->info('Importing EAD/XML files...');
        // TODO: Implement EAD/XML import
        $this->info('EAD/XML import complete.');
        return 0;
    }
}
