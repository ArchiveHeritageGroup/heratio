<?php

/**
 * WebCaptureCommand - Heratio ahg-scan
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
 *
 * @copyright Plain Sailing Information Systems
 */

namespace AhgScan\Console;

use AhgCore\Services\WarcCaptureService;
use Illuminate\Console\Command;

/**
 * Capture a single URL to a WARC 1.1 file from the command line (url mode).
 *
 * This is the CLI face of the single web-archive surface: it calls the reusable ahg-core
 * engine (WarcCaptureService::captureUrl) and writes to the single warc_capture table.
 * Single-page capture only (url mode does not crawl subresources). The remote host being
 * down is not an error here; the run records a 'failed' row and exits cleanly. SSRF is not
 * loosened: the engine enforces http/https-only + a public-host guard.
 */
class WebCaptureCommand extends Command
{
    protected $signature = 'ahg:web-capture {url : The http/https URL to capture to WARC}';

    protected $description = 'Capture a single web page to a WARC 1.1 file (url mode; no crawl)';

    public function handle(WarcCaptureService $service): int
    {
        if (! $service->isAvailable()) {
            $this->error('warc_capture table is not installed yet. Boot the app once, or run a web request, to auto-install it.');

            return self::FAILURE;
        }

        $url = (string) $this->argument('url');
        $this->line('Capturing: '.$url);

        $result = $service->captureUrl($url, null);

        if (! empty($result['ok'])) {
            $this->info('Captured #'.($result['id'] ?? '?').' ('.number_format((int) ($result['byte_size'] ?? 0)).' bytes)');
            if (! empty($result['sha256'])) {
                $this->line('sha256: '.$result['sha256']);
            }

            return self::SUCCESS;
        }

        $this->error('Capture failed: '.($result['message'] ?? 'unknown error'));

        return self::FAILURE;
    }
}
