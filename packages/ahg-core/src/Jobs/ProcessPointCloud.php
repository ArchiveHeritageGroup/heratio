<?php

/**
 * ProcessPointCloud - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Jobs;

use AhgCore\Services\PointCloudConverterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * heratio#1183 - convert an uploaded point cloud into a Potree octree off the web request.
 * The uploaded source is removed once converted (the octree is the kept artifact).
 */
class ProcessPointCloud implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $cloudId, public string $sourcePath) {}

    public function handle(PointCloudConverterService $svc): void
    {
        $svc->process($this->cloudId, $this->sourcePath);
        @unlink($this->sourcePath);
    }

    public function failed(\Throwable $e): void
    {
        DB::table('ahg_point_cloud')->where('id', $this->cloudId)->update([
            'status' => 'failed',
            'error' => mb_substr('Conversion job failed: '.$e->getMessage(), 0, 1000),
            'updated_at' => now(),
        ]);
        @unlink($this->sourcePath);
    }
}
