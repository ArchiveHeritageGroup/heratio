<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class FormsImportCommand extends Command
{
    protected $signature = 'ahg:forms-import
        {--file= : Input file path}
        {--repository= : Import forms for a specific repository}';

    protected $description = 'Import form configurations';

    public function handle(): int
    {
        $this->info('Importing form configurations...');
        // TODO: Implement form configuration import
        $this->info('Form configuration import complete.');
        return 0;
    }
}
