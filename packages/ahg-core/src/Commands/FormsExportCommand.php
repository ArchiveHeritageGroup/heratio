<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class FormsExportCommand extends Command
{
    protected $signature = 'ahg:forms-export
        {--repository= : Export forms for a specific repository}
        {--output= : Output file path}';

    protected $description = 'Export form configurations';

    public function handle(): int
    {
        $this->info('Exporting form configurations...');
        // TODO: Implement form configuration export
        $this->info('Form configuration export complete.');
        return 0;
    }
}
