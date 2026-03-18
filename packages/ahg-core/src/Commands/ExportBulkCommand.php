<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class ExportBulkCommand extends Command
{
    protected $signature = 'ahg:export-bulk
        {--criteria= : Export criteria (JSON or query string)}
        {--format=ead : Export format (ead, ead3, dc, csv)}
        {--path= : Output directory path}';

    protected $description = 'Bulk export descriptions';

    public function handle(): int
    {
        $this->info('Bulk exporting descriptions...');
        // TODO: Implement bulk export
        $this->info('Bulk export complete.');
        return 0;
    }
}
