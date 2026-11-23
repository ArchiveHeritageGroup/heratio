<?php

namespace AhgLibrary\Console\Commands;

use AhgLibrary\Services\ClassificationScheduleService;
use Illuminate\Console\Command;

/**
 * Backfills library_item_shelf_key (#1475), for records that predate the table
 * or were written by a path that does not go through LibraryService.
 */
class RebuildShelfKeysCommand extends Command
{
    protected $signature = 'ahg:library-shelf-keys';

    protected $description = 'Rebuild the derived shelf-order keys for library items';

    public function handle(ClassificationScheduleService $service): int
    {
        $this->info($service->rebuildShelfKeys().' shelf keys written.');

        return self::SUCCESS;
    }
}
