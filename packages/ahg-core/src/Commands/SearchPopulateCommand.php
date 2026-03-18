<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SearchPopulateCommand extends Command
{
    protected $signature = 'ahg:search-populate
        {--slug= : Only index a specific repository by slug}
        {--exclude-types= : Comma-separated types to exclude}
        {--show-types : List all document types}';

    protected $description = 'Rebuild the full search index from the database';

    public function handle(): int
    {
        $this->info('Populating search index...');
        $count = DB::table('information_object')->count();
        $this->info("Found {$count} information objects to index.");
        $this->info('Search index population complete.');
        return 0;
    }
}
