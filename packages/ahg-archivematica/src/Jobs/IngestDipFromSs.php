<?php

/**
 * IngestDipFromSs - queued ingest of a single DIP pulled from the Storage Service.
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

namespace AhgArchivematica\Jobs;

use AhgArchivematica\Services\DipIngestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Wraps DipIngestService::ingestFromSs() for one DIP UUID so a batch of DIPs
 * can be processed off the web/cron thread (download + unpack + ingest can be
 * slow for large access derivatives). Idempotency lives in the service, so a
 * re-dispatched job for an already-linked DIP is a cheap no-op.
 */
class IngestDipFromSs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Don't auto-retry; a failure is logged and left for the next poll. */
    public int $tries = 1;

    /** Download + unpack + ingest of a large DIP can take a while. */
    public int $timeout = 3600;

    public function __construct(public string $dipUuid)
    {
    }

    public function handle(DipIngestService $service): void
    {
        try {
            $summary = $service->ingestFromSs($this->dipUuid);
            Log::info('[ahg-archivematica] DIP ingest complete', $summary);
        } catch (\Throwable $e) {
            Log::error('[ahg-archivematica] DIP ingest failed for ' . $this->dipUuid . ': ' . $e->getMessage());
            throw $e;
        }
    }
}
