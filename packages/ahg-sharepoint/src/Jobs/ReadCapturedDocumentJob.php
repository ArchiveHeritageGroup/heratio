<?php

namespace AhgSharePoint\Jobs;

use AhgSharePoint\Services\SharePointWriteBackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * After a SharePoint capture: read the document ("Read the document") and write
 * its fields and PO match back to the library's columns, with no click.
 * Queued separately so a slow AI read never holds up the next capture.
 * Switch off with ahg_settings sharepoint_auto_read = 0.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */
class ReadCapturedDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 60;

    public int $timeout = 300;

    public function __construct(public int $informationObjectId) {}

    public function handle(SharePointWriteBackService $writeBack): void
    {
        try {
            $out = $writeBack->readAndWriteBack($this->informationObjectId);
        } catch (\Throwable $e) {
            // An automatic read that fails leaves the record as captured; the
            // "Read the document" button still works. Never fail the capture.
            Log::warning('SharePoint auto-read of '.$this->informationObjectId.' failed: '.substr($e->getMessage(), 0, 300));

            return;
        }
        $r = $out['result'];
        Log::info('SharePoint auto-read of '.$this->informationObjectId.': '
            .($r === null ? 'not a SharePoint capture' : ($r['error'] ?? (($r['saved'] ?? 0).' field(s), columns: '.implode(', ', array_keys($out['written']))))));
    }
}
