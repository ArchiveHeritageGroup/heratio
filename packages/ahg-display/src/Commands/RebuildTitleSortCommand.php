<?php

/**
 * RebuildTitleSortCommand — rebuild the information_object_title_sort sidecar.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 *
 * Pattern C from docs/adr/0001-atom-base-schema-readonly-sidecar-pattern.md.
 *
 * Reads from AtoM base tables (information_object, information_object_i18n)
 * and writes into the AHG sidecar (information_object_title_sort). No AtoM
 * base table is altered.
 *
 * Run after a bulk import, or let the hourly schedule keep it current.
 */

namespace AhgDisplay\Commands;

use AhgDisplay\Services\TitleSortService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RebuildTitleSortCommand extends Command
{
    protected $signature = 'ahg:display-rebuild-title-sort';

    protected $description = 'Rebuild the browse alphabetical-sort sidecar (information_object_title_sort)';

    public function handle(): int
    {
        if (! Schema::hasTable(TitleSortService::TABLE)) {
            $this->error(TitleSortService::TABLE.' does not exist. Boot ahg-display once to create it.');

            return self::FAILURE;
        }

        $started = microtime(true);
        $rows = (new TitleSortService())->rebuildAll();
        $ms = (microtime(true) - $started) * 1000;

        $this->info(sprintf(
            'Rebuilt %s in %s rows, %.0f ms.',
            TitleSortService::TABLE,
            number_format($rows),
            $ms
        ));

        if ($rows === 0) {
            $this->warn('Zero rows written — browse will fall back to the unindexed ORDER BY.');
        }

        return self::SUCCESS;
    }
}
