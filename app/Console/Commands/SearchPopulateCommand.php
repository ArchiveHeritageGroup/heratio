<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SearchPopulateCommand extends Command
{
    protected $signature = 'heratio:search:populate {--exclude-types= : Comma-separated list of types to exclude}';

    protected $description = 'Populate Elasticsearch indices from the database';

    public function handle(): int
    {
        $this->info('Populating search indices...');

        $cmd = 'php /usr/share/nginx/archive/symfony search:populate';

        if ($exclude = $this->option('exclude-types')) {
            $cmd .= ' --exclude-types=' . escapeshellarg($exclude);
        }

        passthru($cmd, $exitCode);

        if ($exitCode === 0) {
            $this->info('Search indices populated successfully.');
        } else {
            $this->error('Search population failed with exit code: ' . $exitCode);
        }

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
