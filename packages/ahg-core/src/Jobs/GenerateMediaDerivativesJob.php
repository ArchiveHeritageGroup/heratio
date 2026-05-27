<?php

/**
 * GenerateMediaDerivativesJob — Heratio ahg-core
 *
 * Queued wrapper around MediaDerivativeService::generateForMaster().
 *
 * Audio / video / 3D / pyramid-TIFF derivative generation is
 * computationally expensive (ffmpeg / ImageMagick / GLTF processing) and
 * must not block the synchronous scan pipeline or the web request that
 * uploaded the master. This job is dispatched in-place of the direct
 * service call from:
 *
 *   - ProcessScanFile::stageDeriving()   (scan pipeline)
 *   - ProcessScanFile::resumeFromDeriving() (rights-release resume)
 *
 * The RegenDerivativesCommand intentionally calls the service directly
 * (synchronously) so operators get immediate feedback from the CLI.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMediaDerivativesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** ID of the digital_object master record. */
    public int $masterId;

    /**
     * Hard-fail after this many attempts. One attempt is sufficient for most
     * transient errors (ffmpeg busy, temp-disk full); a second retry catches
     * the occasional resource spike. The 300 s timeout handles long encodes.
     */
    public int $tries = 3;

    /** Do not retry once this many exceptions have been recorded. */
    public int $maxExceptions = 1;

    /** Seconds before the worker kills this job. 5 min covers a 1080p encode. */
    public int $timeout = 300;

    public function __construct(int $masterId)
    {
        $this->masterId = $masterId;
    }

    /**
     * Run derivative generation asynchronously.
     *
     * Emits one PREMIS derivation(creation) event per derivative created,
     * mirroring the behaviour of the synchronous stageDeriving() caller.
     * The IO id is inferred from the digital_object row so no extra
     * parameter is needed.
     */
    public function handle(): void
    {
        $created = \AhgCore\Services\MediaDerivativeService::generateForMaster($this->masterId);

        Log::info("[GenerateMediaDerivativesJob] DO {$this->masterId}: {$created} derivative(s) created");

        if ($created === 0) {
            return;
        }

        // Emit PREMIS derivation events for each new derivative, matching
        // the pattern used in ProcessScanFile::stageDeriving().
        if (class_exists(\AhgScan\Services\PremisEventService::class)) {
            $do = DB::table('digital_object')->where('id', $this->masterId)->first();
            $derivatives = DB::table('digital_object')
                ->where('parent_id', $this->masterId)
                ->get(['id', 'name', 'usage_id']);

            foreach ($derivatives as $d) {
                try {
                    \AhgScan\Services\PremisEventService::emit(
                        (int) ($do->object_id ?? 0),
                        (int) $d->id,
                        \AhgScan\Services\PremisEventService::TYPE_DERIVATION,
                        \AhgScan\Services\PremisEventService::OUTCOME_SUCCESS,
                        'Derivative generated (queued): '.$d->name,
                        ['usage_id' => $d->usage_id, 'parent_do_id' => $this->masterId]
                    );
                } catch (\Throwable $e) {
                    Log::warning('[GenerateMediaDerivativesJob] PREMIS emit failed for DO '.$d->id.': '.$e->getMessage());
                }
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[GenerateMediaDerivativesJob] DO {$this->masterId} failed after {$this->tries} attempts: ".$e->getMessage());

        // Mark the digital_object record so operators can identify and retry
        // the stuck item without hunting through queue logs.
        try {
            DB::table('digital_object')->where('id', $this->masterId)->update([
                'status' => 'derivative_failed',
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // DB may be unavailable in the failed() context — skip.
        }
    }

    /**
     * Release back onto the queue with exponential back-off.
     * onFailure() is called automatically by Laravel between tries.
     */
    public function backoff(): array
    {
        return [30, 90, 240];
    }
}
