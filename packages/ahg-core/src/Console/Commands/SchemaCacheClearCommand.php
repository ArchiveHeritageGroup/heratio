<?php

/**
 * SchemaCacheClearCommand - Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgCore\Console\Commands;

use Illuminate\Console\Command;

/**
 * Deletes the table/column existence cache (see SchemaExistenceCache). Needed
 * only after a table or column is dropped outside Schema::drop / Schema::table
 * - raw SQL or a manual restore that leaves something out. A deploy already
 * starts a fresh cache, because the file is keyed by release version.
 */
class SchemaCacheClearCommand extends Command
{
    protected $signature = 'ahg:schema-cache-clear';

    protected $description = 'Forget which tables and columns are known to exist, so providers re-check live';

    public function handle(): int
    {
        $n = 0;
        foreach (glob(storage_path('framework/cache/ahg-schema-*.php')) ?: [] as $f) {
            if (@unlink($f)) {
                $n++;
            }
        }
        \AhgCore\Database\SchemaExistenceCache::disable();
        $this->info("Removed {$n} schema cache file(s).");

        return self::SUCCESS;
    }
}
