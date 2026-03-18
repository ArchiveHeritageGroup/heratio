<?php

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class QdrantImageIndexCommand extends Command
{
    protected $signature = 'ahg:qdrant-image-index {--db-name=archive} {--db-user=root} {--db-password=} {--collection=} {--atom-root=} {--reset} {--offset=0} {--limit=0}';
    protected $description = 'CLIP image embeddings index';

    public function handle(): int
    {
        $this->info('Starting CLIP image embeddings indexing...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
