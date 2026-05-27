<?php

/**
 * KbartRefreshFeedsCommand - scheduler entry point for remote KBART feed imports
 *
 * Copyright (C) 2026 Johan Pieterse
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgLibrary\Console\Commands;

use AhgLibrary\Services\KbartRemoteService;
use Illuminate\Console\Command;

class KbartRefreshFeedsCommand extends Command
{
    protected $signature = 'ahg:library-kbart-refresh {--once= : Override feed ID and fetch only that feed}';
    protected $description = 'Fetch all active KBART remote feeds and commit to the library catalogue.';

    public function handle(KbartRemoteService $remote): int
    {
        $feedId = $this->option('once');

        if ($feedId !== null) {
            $feedId = (int) $feedId;
            $row = \Illuminate\Support\Facades\DB::table('library_kbart_feed')->where('id', $feedId)->first(['name', 'url']);
            if (! $row) {
                $this->error("Feed #{$feedId} not found.");
                return self::FAILURE;
            }
            $result = $remote->fetchSingleFeed($feedId, $row->name, $row->url);
            $this->reportResult($result);
            return self::SUCCESS;
        }

        $results = $remote->fetchAllActiveFeeds();

        if (empty($results)) {
            $this->info('No active KBART feeds or auto-import is disabled.');
            return self::SUCCESS;
        }

        $totalRows = 0;
        $failCount = 0;

        foreach ($results as $r) {
            $this->reportResult($r);
            if ($r['status'] === 'fail') {
                $failCount++;
            } else {
                $totalRows += $r['row_count'];
            }
        }

        $this->newLine();
        $this->line("Import complete. {$totalRows} row(s) written. {$failCount} feed(s) failed.");

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function reportResult(array $r): void
    {
        $emoji = match ($r['status']) {
            'success' => '<fg=green>OK</>',
            'fail'    => '<fg=red>FAIL</>',
            'skipped' => '<fg=yellow>SKIP</>',
            default   => $r['status'],
        };

        $this->line("  [{$emoji}] {$r['name']} — {$r['row_count']} row(s) imported.");
        if ($r['error']) {
            $this->warn("  Error: {$r['error']}");
        }
    }
}
