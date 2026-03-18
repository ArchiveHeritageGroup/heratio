<?php

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class QdrantIndexCommand extends Command
{
    protected $signature = 'ahg:qdrant-index {--db-name=archive} {--db-user=root} {--db-password=} {--collection=archive_records} {--reset} {--offset=0} {--limit=0}';
    protected $description = 'Rebuild Qdrant vector index';

    public function handle(): int
    {
        $this->info('Starting Qdrant vector index rebuild...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
