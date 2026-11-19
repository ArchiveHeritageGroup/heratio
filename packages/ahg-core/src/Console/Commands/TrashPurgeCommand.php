<?php

/**
 * TrashPurgeCommand - heratio#1734: remove trashed digital-object files
 * whose retention has passed.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Console\Commands;

use AhgCore\Services\DigitalObjectTrash;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrashPurgeCommand extends Command
{
    protected $signature = 'ahg:trash-purge {--dry-run : List what would be purged, remove nothing}';

    protected $description = 'Purge trashed digital objects past their retention';

    public function handle(): int
    {
        if (! Schema::hasTable(DigitalObjectTrash::TABLE)) {
            $this->warn('No trash table; run the migrations.');

            return self::SUCCESS;
        }
        if ($this->option('dry-run')) {
            $due = DB::table(DigitalObjectTrash::TABLE)->where('status', 'trashed')->where('purge_after', '<=', now())->get(['id', 'digital_object_id', 'name', 'deleted_at']);
            foreach ($due as $t) {
                $this->line("trash#{$t->id} object {$t->digital_object_id} {$t->name} (deleted {$t->deleted_at})");
            }
            $this->info($due->count().' due for purge.');

            return self::SUCCESS;
        }
        $this->info(DigitalObjectTrash::purgeExpired().' purged.');

        return self::SUCCESS;
    }
}
