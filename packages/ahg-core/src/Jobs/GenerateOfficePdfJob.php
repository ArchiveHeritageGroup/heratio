<?php

/**
 * GenerateOfficePdfJob - Heratio ahg-core
 *
 * heratio#1627: converts an office master (Word, Excel, PowerPoint,
 * OpenDocument, RTF) to its PDF display copy with headless LibreOffice, off
 * the web request. Queued by a replaced master and the ahg:office-pdfs sweep.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Jobs;

use AhgCore\Services\OfficeDisplayCopyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateOfficePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** LibreOffice is capped at 90 s per file in previewSource(). */
    public int $timeout = 300;

    public function __construct(public int $masterId, public bool $refreshPictures = false) {}

    public function handle(): void
    {
        $id = OfficeDisplayCopyService::make($this->masterId, $this->refreshPictures);
        Log::info("[GenerateOfficePdfJob] DO {$this->masterId}: ".($id ? "PDF copy {$id}" : 'no PDF copy'));
    }
}
