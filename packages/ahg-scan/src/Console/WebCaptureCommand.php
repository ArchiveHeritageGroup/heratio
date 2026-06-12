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

use AhgScan\Services\WebArchiveCaptureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Capture a single URL to a WARC 1.1 file from the command line.
 *
 * Single-page capture only: no crawl, no replay. The remote host being down is
 * not an error here; the run records a 'failed' row and exits cleanly.
 */
class WebCaptureCommand extends Command
{
    protected $signature = 'ahg:web-capture {url : The http/https URL to capture to WARC}';

    protected $description = 'Capture a single web page to a WARC 1.1 file (no crawl, no replay)';

    public function handle(WebArchiveCaptureService $service): int
    {
        if (! Schema::hasTable('web_archive_capture')) {
            $this->error('web_archive_capture table is not installed yet. Boot the app once, or run a web request, to auto-install it.');

            return self::FAILURE;
        }

        $url = (string) $this->argument('url');
        $this->line('Capturing: '.$url);

        $id = $service->capture($url, null);

        if ($id === null) {
            $this->error('Capture could not be recorded (insert failed).');

            return self::FAILURE;
        }

        $row = DB::table('web_archive_capture')->find($id);
        if ($row === null) {
            $this->warn('Recorded id '.$id.' but row could not be re-read.');

            return self::SUCCESS;
        }

        if ($row->status === 'captured') {
            $this->info('Captured #'.$id.' ('.($row->http_status ?? '?').' '.($row->content_type ?? '?').', '.number_format((int) $row->byte_size).' bytes)');
            $this->line('WARC: '.$row->warc_path);

            return self::SUCCESS;
        }

        $this->error('Capture failed #'.$id.': '.($row->error ?? 'unknown error'));

        return self::FAILURE;
    }
}
