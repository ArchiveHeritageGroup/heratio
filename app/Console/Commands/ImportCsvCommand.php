<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportCsvCommand extends Command
{
    protected $signature = 'heratio:import:csv
                            {filename : Path to the CSV file to import}
                            {--source-name= : Source name for the import}
                            {--default-legacy-parent-id= : Default parent ID for legacy records}
                            {--skip-nested-set-build : Skip rebuilding the nested set after import}';

    protected $description = 'Import CSV data via the Heratio CLI';

    public function handle(): int
    {
        $filename = $this->argument('filename');

        if (! file_exists($filename)) {
            $this->error("File not found: {$filename}");

            return self::FAILURE;
        }

        $this->info("Importing CSV: {$filename}");

        $cmd = 'php /usr/share/nginx/archive/bin/atom import:csv ' . escapeshellarg($filename);

        if ($sourceName = $this->option('source-name')) {
            $cmd .= ' --source-name=' . escapeshellarg($sourceName);
        }

        if ($parentId = $this->option('default-legacy-parent-id')) {
            $cmd .= ' --default-legacy-parent-id=' . escapeshellarg($parentId);
        }

        if ($this->option('skip-nested-set-build')) {
            $cmd .= ' --skip-nested-set-build';
        }

        passthru($cmd, $exitCode);

        if ($exitCode === 0) {
            $this->info('CSV import completed successfully.');
        } else {
            $this->error('CSV import failed with exit code: ' . $exitCode);
        }

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
