<?php

/**
 * NormalizeDigitalObjectJob - Heratio ahg-preservation (#1385 Phase 1)
 *
 * Queued wrapper around NormalizationService::normalizeDigitalObject().
 * Normalization (ImageMagick / Ghostscript / FFmpeg / LibreOffice) is slow,
 * so the ingest commit dispatches this per digital object rather than running
 * it inline. Fail-soft: a normalization failure never affects the ingest.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0
 */

declare(strict_types=1);

namespace AhgPreservation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use AhgPreservation\Services\NormalizationService;

class NormalizeDigitalObjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $digitalObjectId;
    public string $purpose;

    /** One retry catches a transient tool/resource spike. */
    public int $tries = 2;
    public int $maxExceptions = 1;

    /** Long enough for a large TIFF/PDF-A/video encode. */
    public int $timeout = 600;

    public function __construct(int $digitalObjectId, string $purpose = 'preservation')
    {
        $this->digitalObjectId = $digitalObjectId;
        $this->purpose = $purpose;
    }

    public function handle(NormalizationService $service): void
    {
        $result = $service->normalizeDigitalObject($this->digitalObjectId, $this->purpose);
        if ($result) {
            Log::info("[NormalizeDigitalObjectJob] DO {$this->digitalObjectId} -> {$result['target_format']} (derivative DO " . ($result['derivative_do_id'] ?? 'none') . ')');
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::warning("[NormalizeDigitalObjectJob] DO {$this->digitalObjectId} failed: " . $e->getMessage());
    }
}
