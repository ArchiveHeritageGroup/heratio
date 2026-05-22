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
 * Push redacted open ahg_error_log rows to AHG Central for fleet-wide error
 * visibility (heratio#127). Each run sends the site's current open-error set
 * (resolved_at IS NULL) as a full replace, so the fleet view stays to open
 * errors only - an error resolved at source drops out on the next run.
 * No-ops silently unless BOTH ahg_central_enabled and ahg_central_error_sync
 * are on.
 */
class AhgCentralSyncErrorsCommand extends Command
{
    protected $signature = 'ahg:central-sync-errors {--batch=500 : Max open error rows to send}';
    protected $description = 'Sync the open ahg_error_log rows to AHG Central (redacted, full replace).';

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

        // One POST - a full replace of the site's open-error set on Central.
        $res = $svc->syncErrors((int) $this->option('batch'));
        if (empty($res['ok'])) {
            $this->warn('[ahg-central-sync-errors] ' . ($res['error'] ?? 'failed'));
            // Non-fatal - the schedule retries on its next tick.

            return self::SUCCESS;
        }

        $this->info('[ahg-central-sync-errors] synced ' . (int) ($res['sent'] ?? 0) . ' open error row(s).');

        return self::SUCCESS;
    }
}
