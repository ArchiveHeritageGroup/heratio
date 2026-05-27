<?php

/**
 * ahg:frbr-backfill-work-keys - bulk compute work_key for every library_item.
 *
 * Idempotent. Re-run after large imports or when normalisation rules change.
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgBiblioFrbr\Console\Commands;

use AhgBiblioFrbr\Services\WorkKeyService;
use Illuminate\Console\Command;

class FrbrBackfillWorkKeysCommand extends Command
{
    protected $signature = 'ahg:frbr-backfill-work-keys
        {--batch=500 : Rows per chunk}';

    protected $description = 'Compute FRBR work-key (cluster hash) for every library_item row.';

    public function handle(WorkKeyService $svc): int
    {
        $batch = max(50, (int) $this->option('batch'));
        $this->info("Backfilling work-keys for all library_item rows (batch={$batch})...");

        $start = microtime(true);
        $result = $svc->backfillAll($batch);
        $elapsed = round(microtime(true) - $start, 2);

        $this->info("Processed: {$result['processed']}");
        $this->info("Updated:   {$result['updated']}");
        $this->info("Elapsed:   {$elapsed}s");

        return self::SUCCESS;
    }
}
