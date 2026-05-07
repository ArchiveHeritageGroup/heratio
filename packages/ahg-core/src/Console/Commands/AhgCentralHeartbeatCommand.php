<?php

/**
 * AhgCentralHeartbeatCommand - Heratio
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
 * Daily heartbeat to the configured AHG Central endpoint. POSTs a
 * site_id + version + timestamp body so the cloud aggregator knows the
 * Heratio instance is alive. No-ops silently when ahg_central_enabled=0.
 *
 * Closes #67 (the schedule consumer for the four ahg_central_* settings).
 */
class AhgCentralHeartbeatCommand extends Command
{
    protected $signature = 'ahg:central-heartbeat';
    protected $description = 'Send a daily heartbeat to the configured AHG Central endpoint.';

    public function handle(): int
    {
        $svc = app(AhgCentralService::class);
        if (!$svc->isEnabled()) {
            $this->line('[ahg-central-heartbeat] disabled - skipping.');
            return self::SUCCESS;
        }

        $result = $svc->heartbeat();
        if ($result['ok']) {
            $this->info('[ahg-central-heartbeat] OK (HTTP ' . $result['http'] . ')');
            return self::SUCCESS;
        }

        $msg = $result['error'] ?? ('HTTP ' . $result['http']);
        $this->warn('[ahg-central-heartbeat] non-2xx: ' . $msg);
        // Heartbeat failures are non-fatal - the schedule keeps trying.
        return self::SUCCESS;
    }
}
