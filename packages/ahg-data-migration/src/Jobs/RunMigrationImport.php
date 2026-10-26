<?php

/**
 * RunMigrationImport - runs a queued data-migration import job
 * (atom_migration_job) on the queue worker (heratio#1555). Before this the
 * wizard queued rows that nothing ever picked up.
 *
 * One try only: an import is not idempotent in create mode, so a retry would
 * duplicate the rows that had already been written. The timeout stays under
 * the database queue's retry_after (1900 s) so a long import is never handed
 * to a second worker while the first is still running.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgDataMigration\Jobs;

use AhgDataMigration\Services\DataMigrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunMigrationImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $jobId)
    {
    }

    public function handle(DataMigrationService $service): void
    {
        $service->executeImport($this->jobId);
    }

    /** A crash or timeout leaves the job row saying so, not "processing" forever. */
    public function failed(\Throwable $e): void
    {
        app(DataMigrationService::class)->updateJobProgress($this->jobId, [
            'status' => 'failed',
            'completed_at' => now(),
            'progress_message' => 'Import stopped: '.mb_substr($e->getMessage(), 0, 500),
        ]);
    }
}
