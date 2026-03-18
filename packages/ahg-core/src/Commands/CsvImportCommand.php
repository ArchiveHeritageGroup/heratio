<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class CsvImportCommand extends Command
{
    protected $signature = 'ahg:csv-import
        {file : Path to CSV file}
        {--source-name= : Source name identifier}
        {--default-legacy-parent-id= : Default parent ID for orphan records}
        {--skip-matched : Skip records that already exist}
        {--update= : Update strategy (match, overwrite, skip)}
        {--limit= : Maximum records to import}
        {--index : Reindex after import}';

    protected $description = 'CSV import';

    public function handle(): int
    {
        $this->info('Importing CSV file...');
        // TODO: Implement CSV import
        $this->info('CSV import complete.');
        return 0;
    }
}
