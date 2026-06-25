<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgRdm\Jobs;

use AhgRdm\Services\PopiaScanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Run a POPIA scan off the request thread (#1339). The deterministic detectors
 * are instant, but the NER augmentation calls the AI gateway and can take tens
 * of seconds per file - far past php-fpm/nginx request limits. So the web
 * "Run POPIA scan" action queues this job; the dataset shows 'scanning' until
 * the worker finishes and PopiaScanService writes the findings + verdict.
 */
class ScanDatasetJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Generous - NER on several files can be slow when the gateway is busy. */
    public int $timeout = 900;

    public function __construct(public int $datasetId)
    {
    }

    public function handle(PopiaScanService $service): void
    {
        $service->scanDataset($this->datasetId);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('[ScanDatasetJob] scan failed for dataset '.$this->datasetId.': '.$e->getMessage());
        // Don't leave the dataset stuck on 'scanning'.
        DB::table('rdm_dataset')->where('id', $this->datasetId)->update(['status' => 'review', 'updated_at' => now()]);
    }
}
