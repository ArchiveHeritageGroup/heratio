<?php

/**
 * CleanupJobsCommand - Prune old completed/failed jobs
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgCore\Commands;

use AhgCore\Services\AhgSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanupJobsCommand extends Command
{
    protected $signature = 'ahg:cleanup-jobs {--days= : Override cleanup days}';
    protected $description = 'Delete completed/failed jobs older than the configured retention period (jobs_cleanup_days setting)';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: AhgSettingsService::get('jobs_cleanup_days', '30'));

        if ($days < 1) {
            $this->warn('Cleanup days must be at least 1.');
            return 1;
        }

        $cutoff = now()->subDays($days);
        $deleted = 0;

        // Clean Laravel's job table
        if (Schema::hasTable('job')) {
            $deleted += DB::table('job')
                ->whereIn('status_id', [184, 185]) // JOB_STATUS_COMPLETED, JOB_STATUS_ERROR (TermId constants)
                ->where('completed_at', '<', $cutoff)
                ->delete();
        }

        // Clean failed_jobs table
        if (Schema::hasTable('failed_jobs')) {
            $deleted += DB::table('failed_jobs')
                ->where('failed_at', '<', $cutoff)
                ->delete();
        }

        // Clean ahg_ai_job completed entries
        if (Schema::hasTable('ahg_ai_job')) {
            $deleted += DB::table('ahg_ai_job')
                ->whereIn('status', ['completed', 'failed'])
                ->where('updated_at', '<', $cutoff)
                ->delete();
        }

        $this->info("Cleaned up {$deleted} job(s) older than {$days} days.");

        return 0;
    }
}
