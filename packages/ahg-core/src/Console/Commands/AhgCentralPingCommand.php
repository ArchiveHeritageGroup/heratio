<?php

/**
 * AhgCentralPingCommand - Heratio
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
 * Manual reachability check against the configured AHG Central endpoint.
 * Closes #67's diagnostic surface - operators run this after configuring
 * the four ahg_central_* settings to verify the cloud aggregator answers.
 */
class AhgCentralPingCommand extends Command
{
    protected $signature = 'ahg:central-ping';
    protected $description = 'Ping the configured AHG Central endpoint and report HTTP status.';

    public function handle(): int
    {
        $svc = app(AhgCentralService::class);
        $result = $svc->ping();

        if ($result['ok']) {
            $this->info('[ahg-central-ping] OK (HTTP ' . $result['http'] . ') against ' . $svc->apiUrl());
            return self::SUCCESS;
        }

        $msg = $result['error'] ?? ('HTTP ' . $result['http']);
        $this->error('[ahg-central-ping] FAILED: ' . $msg . ' (url=' . ($svc->apiUrl() ?: '(unset)') . ')');
        return self::FAILURE;
    }
}
