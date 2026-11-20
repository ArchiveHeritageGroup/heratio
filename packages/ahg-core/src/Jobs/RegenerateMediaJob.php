<?php

/**
 * RegenerateMediaJob - Heratio ahg-core
 *
 * Queued wrapper around MediaRegenerateService::regenerate() for one master:
 * ahg:media-regenerate --queue, the media settings button, and
 * DigitalObjectService::refreshDerivatives() for a replaced audio/video master.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Jobs;

use AhgCore\Services\MediaRegenerateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RegenerateMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** One attempt: a run that failed is logged, and running it again is a plain re-run of the command. */
    public int $tries = 1;

    /** As GenerateMediaDerivativesJob: under the database queue's retry_after (1900 s), so a long encode is not started twice. */
    public int $timeout = 1800;

    public function __construct(public int $masterId) {}

    public function handle(): void
    {
        MediaRegenerateService::regenerate($this->masterId);
    }
}
