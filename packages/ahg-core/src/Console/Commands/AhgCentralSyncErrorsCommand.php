<?php

/**
 * AhgCentralSyncErrorsCommand - Heratio
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
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgCore\Console\Commands;

use AhgCore\Services\AhgCentralService;
use Illuminate\Console\Command;

/**
 * Push redacted ahg_error_log rows to AHG Central for fleet-wide error
 * visibility (heratio#127). Incremental + watermarked - each run sends only
 * rows past ahg_central_last_error_id and drains any backlog in bounded
 * passes. No-ops silently unless BOTH ahg_central_enabled and
 * ahg_central_error_sync are on.
 */
class AhgCentralSyncErrorsCommand extends Command
{
    protected $signature = 'ahg:central-sync-errors {--batch=200 : Rows per POST}';
    protected $description = 'Sync ahg_error_log rows to AHG Central (redacted, watermarked, incremental).';

    /** Bounded passes per run - caps a fresh-enable backlog drain. */
    private const MAX_PASSES = 25;

    public function handle(): int
    {
        $svc = app(AhgCentralService::class);

        if (!$svc->isEnabled()) {
            $this->line('[ahg-central-sync-errors] AHG Central disabled - skipping.');

            return self::SUCCESS;
        }
        if (!$svc->errorSyncEnabled()) {
            $this->line('[ahg-central-sync-errors] error-sync toggle off - skipping.');

            return self::SUCCESS;
        }

        $batch = max(1, min((int) $this->option('batch'), 500));
        $total = 0;

        for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
            $res = $svc->syncErrors($batch);
            if (empty($res['ok'])) {
                $this->warn('[ahg-central-sync-errors] stopped: ' . ($res['error'] ?? 'unknown error'));
                // Non-fatal - the schedule retries on its next tick.
                return self::SUCCESS;
            }
            $sent = (int) ($res['sent'] ?? 0);
            $total += $sent;
            if ($sent < $batch) {
                break; // caught up with the watermark
            }
        }

        $this->info('[ahg-central-sync-errors] synced ' . $total . ' error row(s).');

        return self::SUCCESS;
    }
}
