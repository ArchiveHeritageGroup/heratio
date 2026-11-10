<?php

/**
 * ClassifyPagesJob - runs PageClassifier for one record off the web request
 * (heratio#1584). Queued from the record's Pages screen and, when
 * ai_page_classify_on_ingest is on, after an ingest commit.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgAiServices\Jobs;

use AhgAiServices\Services\PageClassifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClassifyPagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** One LLM call per page through the gateway: a long file takes minutes. */
    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $ioId, public ?int $userId = null)
    {
    }

    public function handle(PageClassifier $classifier): void
    {
        // At night the privacy screen runs and the document type waits for a
        // daytime rerun (GPUs are off 18:00-06:00 SAST).
        $classifier->run($this->ioId, $this->userId);
    }
}
