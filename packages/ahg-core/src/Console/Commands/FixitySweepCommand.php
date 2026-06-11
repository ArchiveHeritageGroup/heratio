<?php

/**
 * FixitySweepCommand - Console command for Heratio
 *
 * heratio#1244 (fixity slice). Runs a BOUNDED fixity sweep: re-computes the
 * checksum of a capped batch of digital objects, compares each to its stored
 * baseline, and logs the result to core_fixity_check_log. Prints a coverage
 * summary + the per-result counts (or JSON with --json).
 *
 * Bounded (--limit, default 100, hard-capped by FixityService::MAX_LIMIT),
 * resilient (a missing/unreadable file becomes a missing_file/error row, never an
 * exception), and size-aware (oversized files are skipped + logged). Read-only
 * over digital_object; the only writes are fixity-log rows. No AI calls.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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
 */

namespace AhgCore\Console\Commands;

use AhgCore\Services\FixityService;
use Illuminate\Console\Command;

class FixitySweepCommand extends Command
{
    protected $signature = 'ahg:fixity-sweep '
        .'{--limit=100 : Max digital objects to verify this run (hard-capped by FixityService::MAX_LIMIT).} '
        .'{--json : Emit the result summary as JSON instead of a table.}';

    protected $description = 'Verify a bounded batch of digital objects against their stored checksum baseline (fixity sweep)';

    public function handle(FixityService $service): int
    {
        $limit = (int) $this->option('limit');
        $json  = (bool) $this->option('json');

        $summary = $service->verifyBatch($limit);

        if ($json) {
            // Drop the verbose per-object array from the JSON header line; keep the
            // counts. Callers wanting per-row detail can read core_fixity_check_log.
            $compact = $summary;
            unset($compact['results']);
            $this->line(json_encode($compact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if (! $summary['available']) {
            $this->warn('digital_object table not present - nothing to verify.');

            return self::SUCCESS;
        }

        if (! $summary['logged']) {
            $this->warn('Note: '.FixityService::LOG_TABLE.' not present - results were computed but not persisted. '
                .'It auto-installs on the next web/console boot.');
        }

        $this->info(sprintf(
            'Fixity sweep complete (generated %s). Batch limit: %d.',
            $summary['generated_at'], $summary['limit']
        ));

        $this->table(
            ['result', 'count'],
            [
                ['checked',          $summary['checked']],
                ['match',            $summary['match']],
                ['mismatch',         $summary['mismatch']],
                ['missing_file',     $summary['missing_file']],
                ['skipped_oversize', $summary['skipped_oversize']],
                ['error',            $summary['error']],
            ]
        );

        if ($summary['mismatch'] > 0) {
            $this->error($summary['mismatch'].' object(s) FAILED fixity (checksum mismatch). Review /admin/fixity.');
        }
        if ($summary['missing_file'] > 0) {
            $this->warn($summary['missing_file'].' object(s) had no file on disk (missing_file).');
        }

        // Non-zero exit on a real integrity failure so a cron/monitor can alert.
        return $summary['mismatch'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
