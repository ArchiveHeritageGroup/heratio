<?php

/**
 * PingCommand - artisan command for Heratio
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

namespace AhgArchivematica\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Health-check the configured Archivematica endpoints. Read-only: it only
 * probes reachability of the Storage Service and Dashboard URLs and prints
 * OK / FAIL per endpoint. It never starts a transfer or mutates anything.
 *
 * Usage:
 *   php artisan am:ping
 */
class PingCommand extends Command
{
    protected $signature = 'am:ping {--timeout=10 : Per-endpoint timeout in seconds}';

    protected $description = 'Check reachability of the configured Archivematica Storage Service and Dashboard URLs (read-only).';

    public function handle(): int
    {
        $timeout = (int) $this->option('timeout');
        if ($timeout <= 0) {
            $timeout = 10;
        }

        $endpoints = [
            'Storage Service' => (string) config('archivematica.am_ss_url', ''),
            'Dashboard'       => (string) config('archivematica.am_dashboard_url', ''),
        ];

        $anyFail = false;

        foreach ($endpoints as $label => $url) {
            if ($url === '') {
                $this->warn(sprintf('%-16s SKIP  (no URL configured)', $label));
                continue;
            }

            $ok = $this->probe($url, $timeout, $detail);

            if ($ok) {
                $this->info(sprintf('%-16s OK    %s  (%s)', $label, $url, $detail));
            } else {
                $anyFail = true;
                $this->error(sprintf('%-16s FAIL  %s  (%s)', $label, $url, $detail));
            }
        }

        return $anyFail ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Probe a single URL. Tries HEAD first, falls back to GET (some servers
     * reject HEAD). Any HTTP response (even 4xx) means the host is reachable;
     * only a connection/transport error counts as a failure.
     */
    private function probe(string $url, int $timeout, ?string &$detail): bool
    {
        foreach (['head', 'get'] as $method) {
            try {
                $response = Http::timeout($timeout)
                    ->withOptions(['allow_redirects' => true])
                    ->{$method}($url);

                $detail = 'HTTP ' . $response->status();

                return true;
            } catch (\Throwable $e) {
                $detail = $e->getMessage();
                // fall through to try GET, then report the last error
            }
        }

        return false;
    }
}
